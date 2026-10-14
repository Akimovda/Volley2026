<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventTeam;
use App\Models\EventTeamMember;
use App\Models\EventTournamentSetting;
use App\Models\Payment;
use App\Models\PaymentSetting;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TournamentPaymentService
{
    /**
     * Создать платёж при подаче заявки команды (режим team).
     * Капитан оплачивает за всю команду.
     */
    public function createTeamPayment(EventTeam $team, ?string $chosenMethod = null): ?Payment
    {
        $event = $team->event;
        $settings = $this->getSettings($event);

        if (!$settings || !$settings->isTeamPayment()) {
            return null;
        }

        $amountMinor = (int) ($event->price_minor ?? 0);
        if ($amountMinor <= 0) {
            return null;
        }

        $method    = $this->resolveMethod($event, $chosenMethod);
        $orgSettings = PaymentSetting::where('organizer_id', $event->organizer_id)->first();
        $holdMin   = $orgSettings?->payment_hold_minutes ?? 15;
        $priced    = $this->price($amountMinor, $method, $orgSettings);

        $payment = Payment::create([
            'user_id'       => $team->captain_user_id,
            'organizer_id'  => $event->organizer_id,
            'event_id'      => $event->id,
            'team_id'       => $team->id,
            'method'        => $method,
            'status'        => 'pending',
            'amount_minor'  => $priced['total'],
            'fee_minor'     => $priced['fee'],
            'tax_minor'     => $priced['tax'],
            'yoomoney_meta' => ['base_minor' => $amountMinor],
            'currency'      => $event->price_currency ?? 'RUB',
            'expires_at'    => in_array($method, ['yoomoney'])
                ? now()->addMinutes($holdMin)
                : null,
        ]);

        $this->startOnline($payment, $event, $orgSettings, $team);

        $team->update([
            'payment_status' => $method === 'cash' ? 'pending' : 'pending',
            'payment_id'     => $payment->id,
        ]);

        Log::info("TournamentPayment: team #{$team->id} payment created, method={$method}, amount={$amountMinor}");

        return $payment;
    }

    /**
     * Создать индивидуальный платёж для участника (режим per_player).
     */
    public function createMemberPayment(EventTeamMember $member, ?string $chosenMethod = null): ?Payment
    {
        $team  = $member->team ?? EventTeam::find($member->event_team_id);
        $event = $team->event;
        $settings = $this->getSettings($event);

        if (!$settings || !$settings->isPerPlayerPayment()) {
            return null;
        }

        $amountMinor = (int) ($event->price_minor ?? 0);
        if ($amountMinor <= 0) {
            return null;
        }

        // Проверка: уже есть оплата?
        if ($member->payment_id) {
            return Payment::find($member->payment_id);
        }

        $method    = $this->resolveMethod($event, $chosenMethod);
        $orgSettings = PaymentSetting::where('organizer_id', $event->organizer_id)->first();
        $holdMin   = $orgSettings?->payment_hold_minutes ?? 15;
        $priced    = $this->price($amountMinor, $method, $orgSettings);

        $payment = Payment::create([
            'user_id'        => $member->user_id,
            'organizer_id'   => $event->organizer_id,
            'event_id'       => $event->id,
            'team_id'        => $team->id,
            'team_member_id' => $member->id,
            'method'         => $method,
            'status'         => 'pending',
            'amount_minor'   => $priced['total'],
            'fee_minor'      => $priced['fee'],
            'tax_minor'      => $priced['tax'],
            'yoomoney_meta'  => ['base_minor' => $amountMinor],
            'currency'       => $event->price_currency ?? 'RUB',
            'expires_at'     => in_array($method, ['yoomoney'])
                ? now()->addMinutes($holdMin)
                : null,
        ]);

        $this->startOnline($payment, $event, $orgSettings, $team);

        $member->update([
            'payment_status' => 'pending',
            'payment_id'     => $payment->id,
        ]);

        Log::info("TournamentPayment: member #{$member->id} (user #{$member->user_id}) payment created");

        return $payment;
    }

    /**
     * Создать платежи при подаче заявки: «за команду» — один платёж капитана; «каждый сам» — по платежу
     * на каждого подтверждённого участника (капитану — выбранный им способ, остальным — способ мероприятия;
     * свой способ участник может сменить кнопкой «Оплатить»). Не бросает исключений — заявка важнее платежа.
     */
    public function ensurePayments(EventTeam $team, ?string $chosenMethod = null): void
    {
        try {
            $settings = $this->getSettings($team->event);
            if (!$settings || !$settings->isPaymentRequired()) {
                return;
            }

            if ($settings->isTeamPayment()) {
                $existing = $team->payment_id ? Payment::find($team->payment_id) : null;
                if (!$existing || $existing->status === 'cancelled') {
                    $this->createTeamPayment($team, $chosenMethod);
                }
                return;
            }

            $members = EventTeamMember::where('event_team_id', $team->id)
                ->where('confirmation_status', 'confirmed')
                ->get();
            foreach ($members as $member) {
                $this->createMemberPayment(
                    $member,
                    (int) $member->user_id === (int) $team->captain_user_id ? $chosenMethod : null
                );
            }
        } catch (\Throwable $e) {
            Log::error('TournamentPayment: ensurePayments failed', ['team_id' => $team->id, 'error' => $e->getMessage()]);
        }
    }

    /** Платёж, который должен оплатить пользователь за эту команду (капитан — командный; игрок — свой). */
    public function paymentFor(EventTeam $team, User $user): ?Payment
    {
        $settings = $this->getSettings($team->event);
        if (!$settings || !$settings->isPaymentRequired()) {
            return null;
        }
        if ($settings->isTeamPayment()) {
            return (int) $team->captain_user_id === (int) $user->id && $team->payment_id ? Payment::find($team->payment_id) : null;
        }
        $member = EventTeamMember::where('event_team_id', $team->id)->where('user_id', $user->id)->first();
        return $member?->payment_id ? Payment::find($member->payment_id) : null;
    }

    /**
     * Сменить способ неоплаченного платежа (игрок выбрал в попапе) и, для онлайн-способа, создать платёж ЮKassa.
     * Нельзя после «Я оплатил» и после оплаты.
     */
    public function applyMethod(Payment $payment, string $method): Payment
    {
        if ($payment->status !== 'pending' || $payment->user_confirmed) {
            throw new DomainException('Платёж уже нельзя изменить.');
        }
        $event       = Event::findOrFail($payment->event_id);
        $orgSettings = PaymentSetting::where('organizer_id', $event->organizer_id)->first();
        $offered     = $orgSettings?->offeredMethods() ?? [];
        if ($method !== $payment->method && !in_array($method, $offered, true)) {
            throw new DomainException('Этот способ оплаты недоступен.');
        }

        $base   = (int) ($payment->yoomoney_meta['base_minor'] ?? $payment->amount_minor - $payment->fee_minor - $payment->tax_minor);
        $priced = $this->price($base, $method, $orgSettings);

        $payment->update([
            'method'                    => $method,
            'amount_minor'              => $priced['total'],
            'fee_minor'                 => $priced['fee'],
            'tax_minor'                 => $priced['tax'],
            'yoomoney_payment_id'       => null,
            'yoomoney_confirmation_url' => null,
        ]);

        $this->startOnline($payment->fresh(), $event, $orgSettings, $payment->team_id ? EventTeam::find($payment->team_id) : null);

        return $payment->fresh();
    }

    /** Способ платежа: выбранный (если организатор предлагает выбор и значение допустимо) либо способ мероприятия. */
    private function resolveMethod(Event $event, ?string $chosen): string
    {
        $offered = PaymentSetting::offeredFor((int) $event->organizer_id);
        if ($chosen && in_array($chosen, $offered, true)) {
            return $chosen;
        }
        return $event->payment_method ?? 'cash';
    }

    /** Налог/комиссия «сверху» — только для онлайн-оплаты (комиссия откладывается, если игрок выбирает способ ЮKassa позже). */
    private function price(int $baseMinor, string $method, ?PaymentSetting $orgSettings): array
    {
        if ($method !== 'yoomoney') {
            return ['total' => $baseMinor, 'fee' => 0, 'tax' => 0];
        }
        return \App\Support\PaymentPricing::apply($baseMinor, $orgSettings);
    }

    /** Онлайн-способ: создать платёж ЮKassa (если комиссия «сверху» не требует отдельного выбора способа ЮKassa). Сбой логируется. */
    private function startOnline(Payment $payment, Event $event, ?PaymentSetting $orgSettings, ?EventTeam $team): void
    {
        if ($payment->method !== 'yoomoney' || !$orgSettings?->isYoomoneyReady() || \App\Support\PaymentPricing::needsChoice($orgSettings)) {
            return;
        }
        try {
            app(YookassaService::class)->createEventPayment(
                $payment,
                $event,
                $orgSettings,
                $team ? route('tournamentTeams.show', [$event, $team]) : route('events.show', $event->id),
            );
        } catch (\Throwable $e) {
            Log::error('TournamentPayment: createEventPayment failed', ['payment_id' => $payment->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Подтвердить оплату команды (капитан нажал «Я оплатил»).
     */
    public function userConfirmTeamPayment(Payment $payment): void
    {
        $payment->update([
            'user_confirmed'    => true,
            'user_confirmed_at' => now(),
        ]);

        if ($payment->team_id) {
            EventTeam::where('id', $payment->team_id)->update([
                'payment_status' => 'link_pending',
            ]);
        }
    }

    /**
     * Организатор подтверждает оплату команды.
     */
    public function orgConfirmTeamPayment(Payment $payment): void
    {
        $payment->update([
            'status'           => 'paid',
            'org_confirmed'    => true,
            'org_confirmed_at' => now(),
        ]);

        if ($payment->team_id) {
            EventTeam::where('id', $payment->team_id)->update([
                'payment_status' => 'paid',
            ]);
        }

        // Если per_player — проверяем всех ли подтвердили
        if ($payment->team_member_id) {
            $this->checkTeamFullyPaid($payment->team_id);
        }
    }

    /**
     * Организатор отклоняет оплату.
     */
    public function orgRejectPayment(Payment $payment): void
    {
        $payment->update(['status' => 'cancelled']);

        if ($payment->team_id && !$payment->team_member_id) {
            // Командная оплата — отменяем всю команду
            EventTeam::where('id', $payment->team_id)->update([
                'payment_status' => 'cancelled',
            ]);
        }

        if ($payment->team_member_id) {
            EventTeamMember::where('id', $payment->team_member_id)->update([
                'payment_status' => 'cancelled',
            ]);
        }
    }

    /**
     * Обработка оплаты через ЮMoney (автоматическая).
     */
    public function handleYoomoneyPaid(Payment $payment): void
    {
        $payment->update(['status' => 'paid']);

        if ($payment->team_id && !$payment->team_member_id) {
            EventTeam::where('id', $payment->team_id)->update([
                'payment_status' => 'paid',
            ]);
        }

        if ($payment->team_member_id) {
            EventTeamMember::where('id', $payment->team_member_id)->update([
                'payment_status' => 'paid',
            ]);
            $this->checkTeamFullyPaid($payment->team_id);
        }
    }

    /**
     * Обработка по абонементу — списание визита.
     */
    public function payWithSubscription(EventTeam $team, User $user): bool
    {
        $event = $team->event;
        $settings = $this->getSettings($event);

        if (!$settings || !$settings->isPaymentRequired()) {
            return false;
        }

        // Ищем подходящий абонемент
        $subscription = \App\Models\Subscription::where('user_id', $user->id)
            ->where('organizer_id', $event->organizer_id)
            ->where('status', 'active')
            ->where('visits_remaining', '>', 0)
            ->first();

        if (!$subscription) {
            return false;
        }

        // Списываем визит
        $subscription->decrement('visits_remaining');
        $subscription->increment('visits_used');

        app(SubscriptionService::class)->notifyIfLowVisits($subscription);

        if ($settings->isTeamPayment()) {
            $team->update(['payment_status' => 'subscription']);
        } else {
            $member = EventTeamMember::where('event_team_id', $team->id)
                ->where('user_id', $user->id)
                ->first();

            if ($member) {
                $member->update(['payment_status' => 'subscription']);
                $this->checkTeamFullyPaid($team->id);
            }
        }

        return true;
    }

    /**
     * Проверить, все ли участники команды оплатили (per_player).
     * Если да — ставим команде payment_status = paid.
     */
    public function checkTeamFullyPaid(?int $teamId): void
    {
        if (!$teamId) return;

        $team = EventTeam::find($teamId);
        if (!$team) return;

        $event = $team->event;
        $settings = $this->getSettings($event);

        if (!$settings || !$settings->isPerPlayerPayment()) {
            return;
        }

        $members = EventTeamMember::where('event_team_id', $teamId)
            ->where('confirmation_status', 'confirmed')
            ->get();

        $allPaid = $members->every(fn($m) =>
            in_array($m->payment_status, ['paid', 'subscription', null])
        );

        $team->update([
            'payment_status' => $allPaid ? 'paid' : 'pending',
        ]);
    }

    /**
     * Проверить, допускается ли команда к турниру (оплата пройдена).
     */
    public function isTeamEligible(EventTeam $team): bool
    {
        $event = $team->event;
        $settings = $this->getSettings($event);

        if (!$settings || !$settings->isPaymentRequired()) {
            return true; // бесплатный турнир
        }

        return in_array($team->payment_status, ['paid', 'subscription']);
    }

    /**
     * Получить информацию об оплате для UI.
     */
    public function getPaymentInfo(EventTeam $team): array
    {
        $event = $team->event;
        $settings = $this->getSettings($event);

        if (!$settings || !$settings->isPaymentRequired()) {
            return ['required' => false, 'mode' => 'free'];
        }

        $info = [
            'required'     => true,
            'mode'         => $settings->paymentMode(),
            'amount'       => (int) ($event->price_minor ?? 0),
            'currency'     => $event->price_currency ?? 'RUB',
            'method'       => $event->payment_method ?? 'cash',
            'team_status'  => $team->payment_status,
            'team_paid'    => $this->isTeamEligible($team),
        ];

        if ($settings->isPerPlayerPayment()) {
            $members = EventTeamMember::where('event_team_id', $team->id)
                ->where('confirmation_status', 'confirmed')
                ->with('user:id,first_name,last_name')
                ->get();

            $info['members'] = $members->map(fn($m) => [
                'id'             => $m->id,
                'user_id'        => $m->user_id,
                'name'           => trim(($m->user->first_name ?? '') . ' ' . ($m->user->last_name ?? '')),
                'payment_status' => $m->payment_status,
                'paid'           => in_array($m->payment_status, ['paid', 'subscription']),
            ])->toArray();
        }

        return $info;
    }

    protected function getSettings(Event $event): ?EventTournamentSetting
    {
        return EventTournamentSetting::where('event_id', $event->id)->first();
    }
}
