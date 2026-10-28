<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Типы атаки: spike→power (силовой), shot→cut (кат/накат), dump→tip (обман/скидка). Новые значения poke (краб) и roll (обводка)
 * старых аналогов не имеют. Только данные: колонка match_rally_events.attack_type (varchar(10)) не меняется.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('match_rally_events')->where('attack_type', 'spike')->update(['attack_type' => 'power']);
        DB::table('match_rally_events')->where('attack_type', 'shot')->update(['attack_type' => 'cut']);
        DB::table('match_rally_events')->where('attack_type', 'dump')->update(['attack_type' => 'tip']);
    }

    /** Обратно: power→spike, cut→shot, tip→dump; poke/roll в старой схеме нет — становятся «без уточнения» (NULL). */
    public function down(): void
    {
        DB::table('match_rally_events')->where('attack_type', 'power')->update(['attack_type' => 'spike']);
        DB::table('match_rally_events')->where('attack_type', 'cut')->update(['attack_type' => 'shot']);
        DB::table('match_rally_events')->where('attack_type', 'tip')->update(['attack_type' => 'dump']);
        DB::table('match_rally_events')->whereIn('attack_type', ['poke', 'roll'])->update(['attack_type' => null]);
    }
};
