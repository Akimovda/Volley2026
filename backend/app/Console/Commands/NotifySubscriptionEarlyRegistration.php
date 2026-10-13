<?php

namespace App\Console\Commands;

use App\Models\EventOccurrence;
use App\Models\Subscription;
use App\Services\UserNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Раннее окно записи для владельцев абонементов: когда оно открылось (раньше общей регистрации) —
 * уведомляем игроков с действующим абонементом на это мероприятие. Автозапись по абонементу
 * на такие туры отключена (см. RunAutoBooking/AutoBookingSubscriptionJob) — игрок записывается сам.
 */
class NotifySubscriptionEarlyRegistration extends Command
{
    protected $signature = 'subscriptions:notify-early-registration {--dry-run}';
    protected $description = 'Уведомить владельцев абонементов об открытии раннего окна записи';

    public function handle(UserNotificationService $notifications): int
    {
        $now = now();

        $occurrences = EventOccurrence::with('event')
            ->where('starts_at', '>', $now)
            ->whereNull('cancelled_at')
            ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
            ->whereNull('subscription_reg_notified_at')
            ->where(function ($q) use ($now) {
                $q->where(function ($w) use ($now) {
                    $w->whereNotNull('subscription_registration_starts_at')
                      ->where('subscription_registration_starts_at', '<=', $now)
                      ->where('subscription_registration_starts_at', '>', $now->copy()->subHours(6));
                })->orWhere(function ($w) use ($now) {
                    // раннее окно унаследовано от серии
                    $w->whereNull('subscription_registration_starts_at')
                      ->whereHas('event', fn ($e) => $e
                          ->whereNotNull('subscription_registration_starts_at')
                          ->where('subscription_registration_starts_at', '<=', $now)
                          ->where('subscription_registration_starts_at', '>', $now->copy()->subHours(6)));
                });
            })
            ->get();

        $sent = 0;
        foreach ($occurrences as $occ) {
            $event = $occ->event;
            $early = $occ->effectiveSubscriptionRegistrationStartsAt();
            if (!$event || !$early || $early->gt($now) || !$occ->effectiveAllowRegistration()) {
                continue;
            }
            // Общая регистрация уже открыта — «раннее» окно потеряло смысл
            $general = $occ->effectiveRegistrationStartsAt();
            if ($general && $general->lte($now)) {
                if (!$this->option('dry-run')) {
                    $occ->forceFill(['subscription_reg_notified_at' => $now])->save();
                }
                continue;
            }

            if ($this->option('dry-run')) {
                $this->line("occurrence #{$occ->id}: early window open since {$early}");
                continue;
            }

            // Атомарно «занимаем» отправку — параллельный запуск не продублирует
            $claimed = DB::table('event_occurrences')
                ->where('id', $occ->id)
                ->whereNull('subscription_reg_notified_at')
                ->update(['subscription_reg_notified_at' => $now]);
            if (!$claimed) {
                continue;
            }

            $registered = DB::table('event_registrations')
                ->where('occurrence_id', $occ->id)
                ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
                ->whereNull('cancelled_at')
                ->where('status', '!=', 'cancelled')
                ->pluck('user_id')->flip();

            $userIds = Subscription::with(['template', 'user'])
                ->where('organizer_id', $event->organizer_id)
                ->where('status', 'active')
                ->get()
                ->filter(fn ($s) => $s->user && !$s->user->is_bot
                    && !isset($registered[$s->user_id])
                    && $s->isUsableForEvent((int) $event->id))
                ->pluck('user_id')->unique()->values();

            $url = route('events.show', $event) . '?occurrence=' . $occ->id;
            $title = $event->title;
            $when = $general
                ? $general->copy()->setTimezone($event->timezone ?: 'UTC')->locale('ru')->translatedFormat('d F в H:i')
                : null;

            foreach ($userIds as $uid) {
                try {
                    $notifications->create(
                        userId: (int) $uid,
                        type: 'subscription_early_registration',
                        title: '🎫 Открыта запись по абонементу',
                        body: "Началась регистрация на «{$title}», на которое у вас действует абонемент."
                            . ($when ? " Для остальных игроков запись откроется {$when}." : '')
                            . ' Автозапись на это мероприятие не работает — запишитесь сами.',
                        payload: [
                            'event_id'      => $event->id,
                            'occurrence_id' => $occ->id,
                            'button_text'   => 'Записаться',
                            'button_url'    => $url,
                        ],
                        channels: ['in_app', 'telegram', 'vk', 'max', 'push'],
                    );
                    $sent++;
                } catch (\Throwable $e) {
                    Log::warning('subscriptions:notify-early-registration failed', [
                        'occurrence_id' => $occ->id, 'user_id' => $uid, 'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $this->info("Notified {$sent} subscription holders");
        return self::SUCCESS;
    }
}
