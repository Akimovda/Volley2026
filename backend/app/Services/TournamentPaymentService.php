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
    public function createTeamPayment(EventTeam $team): ?Payment
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

        $method    = $event->payment_method ?? 'cash';
        $orgSettings = PaymentSetting::where('organizer_id', $event->organizer_id)->first();
        $holdMin   = $orgSettings?->payment_hold_minutes ?? 15;

        $payment = Payment::create([
            'user_id'       => $team->captain_user_id,
            'organizer_id'  => $event->organizer_id,
            'event_id'      => $event->id,
            'team_id'       => $team->id,
            'method'        => $method,
            'status'        => $method === 'cash' ? 'pending' : 'pending',
            'amount_minor'  => $amountMinor,
            'currency'      => $event->price_currency ?? 'RUB',
            'expires_at'    => in_array($method, ['yoomoney'])
                ? now()->addMinutes($holdMin)
                : null,
        ]);

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
    public function createMemberPayment(EventTeamMember $member): ?Payment
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

        $method    = $event->payment_method ?? 'cash';
        $orgSettings = PaymentSetting::where('organizer_id', $event->organizer_id)->first();
        $holdMin   = $orgSettings?->payment_hold_minutes ?? 15;

        $payment = Payment::create([
            'user_id'        => $member->user_id,
            'organizer_id'   => $event->organizer_id,
            'event_id'       => $event->id,
            'team_id'        => $team->id,
            'team_member_id' => $member->id,
            'method'         => $method,
            'status'         => 'pending',
            'amount_minor'   => $amountMinor,
            'currency'       => $event->price_currency ?? 'RUB',
            'expires_at'     => in_array($method, ['yoomoney'])
                ? now()->addMinutes($holdMin)
                : null,
        ]);

        $member->update([
            'payment_status' => 'pending',
            'payment_id'     => $payment->id,
        ]);

        Log::info("TournamentPayment: member #{$member->id} (user #{$member->user_id}) payment created");

        return $payment;
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
