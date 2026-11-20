<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Участники, созданные «Распределить случайно/по уровню» (tournament_individual) до 20.11.2026,
 * получили team_role = NULL: на странице команды пустой чип роли, не работали основа/запас и смена амплуа.
 * Заполняем по role_code — так же, как EventTeamMember::getEffectiveTeamRoleAttribute().
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('event_team_members')
            ->whereNull('team_role')
            ->update([
                'team_role' => DB::raw("CASE role_code WHEN 'captain' THEN 'captain' WHEN 'reserve' THEN 'reserve' ELSE 'player' END"),
            ]);
    }

    public function down(): void
    {
        // данные не откатываем: NULL был ошибкой
    }
};
