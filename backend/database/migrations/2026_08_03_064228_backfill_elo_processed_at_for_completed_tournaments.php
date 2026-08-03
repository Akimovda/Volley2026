<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Турниры, у которых ВСЕ стадии уже completed на момент миграции — их каскад
        // завершения уже сработал (возможно, несколько раз, до фикса 5618e1da).
        // Бэкфилл фиксирует текущее состояние как "уже обработано", чтобы будущее
        // повторное срабатывание каскада (например, дозаполнение плей-офф матчем
        // после того, как стадия уже была completed — новый штатный сценарий из
        // этого же коммита) не пересчитало Elo/уведомление повторно для СТАРЫХ
        // матчей. НЕ трогает турниры, которые ещё не завершены целиком (напр. event
        // 376 — лига) — их матчи должны получить elo_processed_at только когда
        // каскад реально обработает их в первый раз.
        $fullyCompletedEventIds = DB::table('tournament_stages')
            ->select('event_id')
            ->groupBy('event_id')
            ->havingRaw("COUNT(*) = COUNT(*) FILTER (WHERE status = 'completed')")
            ->pluck('event_id');

        if ($fullyCompletedEventIds->isEmpty()) {
            return;
        }

        $stageIds = DB::table('tournament_stages')
            ->whereIn('event_id', $fullyCompletedEventIds)
            ->pluck('id');

        DB::table('tournament_matches')
            ->whereIn('stage_id', $stageIds)
            ->where('status', 'completed')
            ->whereNotNull('winner_team_id')
            ->whereNull('elo_processed_at')
            ->update(['elo_processed_at' => DB::raw('COALESCE(scored_at, updated_at, now())')]);

        DB::table('events')
            ->whereIn('id', $fullyCompletedEventIds)
            ->whereNull('tournament_completed_notified_at')
            ->update(['tournament_completed_notified_at' => now()]);
    }

    public function down(): void
    {
        // Не откатываем: обнуление задним числом снова открыло бы риск повторной
        // обработки при следующем цикле down/up.
    }
};
