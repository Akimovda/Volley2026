<?php

namespace App\Console\Commands;

use App\Models\Location;
use App\Models\User;
use App\Services\ClubAnalyticsService;
use App\Services\UserNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Ежемесячный отчёт по аналитике клуба: краткая сводка за прошлый месяц + ссылка на PDF.
 * Получают управляющие клубом с активным Организатор Pro, включившие «Присылать отчёт» на странице аналитики.
 * Доставка — через обычные уведомления (сайт + Telegram/VK/MAX по настройкам пользователя).
 */
class SendClubMonthlyReport extends Command
{
    protected $signature = 'club:send-monthly-report {--user= : только этот пользователь} {--dry : не отправлять, только показать}';
    protected $description = 'Отправить владельцам клубов (Организатор Pro) ежемесячный отчёт по аналитике';

    public function handle(ClubAnalyticsService $analytics, UserNotificationService $notifier): int
    {
        $users = User::query()
            ->where('is_club_manager', true)
            ->where('club_report_monthly', true)
            ->when($this->option('user'), fn ($q, $id) => $q->whereKey((int) $id))
            ->get();

        $sent = 0;
        foreach ($users as $user) {
            if (!$user->isOrganizerPro()) {
                continue;
            }

            foreach (Location::where('owner_id', $user->id)->orderBy('name')->get() as $location) {
                $tz = $location->effectiveTimezone();
                $anchor = Carbon::now($tz)->subMonthNoOverflow()->startOfMonth();
                [$start, $end] = $analytics->resolvePeriod(ClubAnalyticsService::PERIOD_MONTH, $anchor);

                $key = "club_monthly_report_{$user->id}_{$location->id}_" . $start->format('Ym');
                if (!$this->option('dry') && Cache::has($key)) {
                    continue; // уже отправляли за этот месяц
                }

                $stats = $analytics->forLocationCached($location, $start, $end, true);
                $center = $stats['center'];
                $b = $stats['bookings'];
                $monthName = \Illuminate\Support\Str::ucfirst($start->copy()->locale('ru')->isoFormat('MMMM YYYY'));

                $body = "{$location->name}\n"
                    . "Загрузка: {$center['occupancy_pct']}%\n"
                    . 'Выручка: ' . number_format((float) $center['revenue'], 0, ',', ' ') . " ₽\n"
                    . "Броней: {$b['active']}, отмен: {$b['cancelled']} ({$b['cancel_rate']}%)";

                if ($this->option('dry')) {
                    $this->line("[{$user->id}] {$monthName}\n{$body}\n");
                    continue;
                }

                $notifier->create(
                    userId:   $user->id,
                    type:     'club_monthly_report',
                    title:    "Отчёт по клубу за {$monthName}",
                    body:     $body,
                    payload:  [
                        'location_id' => $location->id,
                        'button_text' => 'Скачать отчёт в PDF',
                        'button_url'  => route('club.analytics.export_pdf', [
                            'location_id' => $location->id,
                            'period' => ClubAnalyticsService::PERIOD_MONTH,
                            'anchor' => $start->toDateString(),
                        ]),
                    ],
                    channels: ['in_app', 'telegram', 'vk', 'max'],
                );

                Cache::put($key, 1, now()->addDays(40));
                $sent++;
            }
        }

        $this->info("Отправлено отчётов: {$sent}");

        return self::SUCCESS;
    }
}
