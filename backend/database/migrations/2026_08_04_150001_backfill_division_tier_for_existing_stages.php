<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill для стадий, которые СЕГОДНЯ распознаются как дивизионные по старому
     * паттерну (имя "Группа Hard/Medium[-N]/Lite" для командных турниров, или просто
     * "Hard/Medium[-N]/Lite" без префикса для king_beach — см. report_division_tier_migration_plan_2026-08-04.md).
     * Присваивает division_tier = позиция в порядке Hard -> Medium-1 -> Medium-2 -> ... -> Lite
     * внутри каждой группы (event_id, occurrence_id) — та же логика, что formDivisions()
     * использует при создании новых дивизионных стадий.
     */
    public function up(): void
    {
        $stages = DB::table('tournament_stages')
            ->select('id', 'event_id', 'occurrence_id', 'name')
            ->whereNull('division_tier')
            ->get();

        $candidates = [];
        foreach ($stages as $stage) {
            $key = str_starts_with($stage->name, 'Группа ')
                ? substr($stage->name, strlen('Группа '))
                : $stage->name;

            if (!preg_match('/^(Hard|Lite|Medium(?:-(\d+))?)$/', $key, $m)) {
                continue;
            }

            $sortWeight = match (true) {
                $m[1] === 'Hard' => 0,
                $m[1] === 'Lite' => 1000,
                isset($m[2]) => 1 + (int) $m[2],
                default => 500, // голое "Medium" без суффикса, между Hard и Medium-N
            };

            $candidates[$stage->event_id . ':' . ($stage->occurrence_id ?? 'null')][] = [
                'id' => $stage->id,
                'weight' => $sortWeight,
            ];
        }

        foreach ($candidates as $group) {
            usort($group, fn($a, $b) => $a['weight'] <=> $b['weight']);
            foreach (array_values($group) as $i => $row) {
                DB::table('tournament_stages')
                    ->where('id', $row['id'])
                    ->update(['division_tier' => $i + 1]);
            }
        }
    }

    public function down(): void
    {
        // Откат: division_tier проставленный этим backfill'ом неотличим от тега,
        // который могли поставить последующие вызовы formDivisions() уже с новым
        // кодом — поэтому откат явно не нужен и не выполняется (nullable-поле,
        // безопасно оставить как есть при откате схемы предыдущей миграцией).
    }
};
