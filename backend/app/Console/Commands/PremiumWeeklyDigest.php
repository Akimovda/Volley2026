<?php

namespace App\Console\Commands;

use App\Models\PremiumSubscription;
use App\Models\User;
use App\Services\UserNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PremiumWeeklyDigest extends Command
{
    protected $signature   = 'premium:weekly-digest';
    protected $description = 'Отправить Premium-пользователям недельную сводку игр';

    public function handle(UserNotificationService $notificationService): void
    {
        $from = now()->startOfWeek();
        $to   = now()->endOfWeek();

        // Берём всех активных Premium с включённой сводкой
        $subscriptions = PremiumSubscription::query()
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->where('weekly_digest', true)
            ->with('user')
            ->get();

        $this->info("Найдено подписок: {$subscriptions->count()}");

        foreach ($subscriptions as $sub) {
            $user = $sub->user;
            if (!$user) continue;

            // Определяем город пользователя
            $cityId = $sub->notify_city_id ?? $user->city_id ?? null;

            if (!$cityId) {
                $this->line("  Skip user #{$user->id} — нет города");
                continue;
            }

            // Запрос игр на неделю с учётом фильтра уровня
            $query = DB::table('event_occurrences as eo')
                ->join('events as e', 'e.id', '=', 'eo.event_id')
                ->join('locations as l', 'l.id', '=', 'e.location_id')
                ->where('l.city_id', $cityId)
                ->where('eo.starts_at', '>=', $from)
                ->where('eo.starts_at', '<=', $to)
                ->where('e.visibility', 'public')
                ->select('e.id', 'e.title', 'eo.starts_at', 'l.name as location_name');

            // Фильтр по уровню
            if ($sub->notify_level_min !== null) {
                $query->where('e.classic_level_min', '>=', $sub->notify_level_min);
            }
            if ($sub->notify_level_max !== null) {
                $query->where('e.classic_level_max', '<=', $sub->notify_level_max);
            }

            $events = $query->orderBy('eo.starts_at')->limit(10)->get();

            if ($events->isEmpty()) {
                $this->line("  Skip user #{$user->id} — нет игр на неделю");
                continue;
            }

            $count    = $events->count();
            $cityName = DB::table('cities')->where('id', $cityId)->value('name') ?? 'вашем городе';

            // Формируем текст сводки
            $lines = $events->map(function ($ev) {
                $date = \Carbon\Carbon::parse($ev->starts_at)->format('d.m H:i');
                return "• {$date} — {$ev->title} ({$ev->location_name})";
            })->join("\n");

            $body = "На этой неделе в {$cityName} {$count} " . $this->pluralGames($count) . ":\n\n{$lines}";

            try {
                $notificationService->create(
                    userId: $user->id,
                    type: 'weekly_digest',
                    title: "🏐 На этой неделе {$count} " . $this->pluralGames($count) . " рядом с тобой",
                    body: $body,
                    payload: [
                        'event_ids' => $events->pluck('id')->toArray(),
                        'city_id'   => $cityId,
                        'week_from' => $from->toDateString(),
                        'week_to'   => $to->toDateString(),
                    ],
                    channels: ['in_app', 'telegram', 'vk', 'max']
                );
                $this->line("  ✅ Отправлено user #{$user->id}");
            } catch (\Throwable $e) {
                $this->error("  ❌ user #{$user->id}: " . $e->getMessage());
            }
        }

        $this->info('Готово!');
    }

    private function pluralGames(int $n): string
    {
        if ($n % 100 >= 11 && $n % 100 <= 19) return 'игр';
        return match($n % 10) {
            1       => 'игра',
            2, 3, 4 => 'игры',
            default => 'игр',
        };
    }
}
