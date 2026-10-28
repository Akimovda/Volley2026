<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Пляжка 2×2: регистрация «По амплуа» (Блокирующий + Защитник) — явный флаг, как registration_without_positions у классики.
        Schema::table('event_game_settings', function (Blueprint $table) {
            $table->boolean('registration_by_roles')->default(false)->after('registration_without_positions');
        });

        // Бэкфилл: если режим уже включали через набор позиций.
        DB::table('event_game_settings')
            ->whereRaw('positions::jsonb @> ?::jsonb', [json_encode(['blocker', 'defender'])])
            ->update(['registration_by_roles' => true]);
    }

    public function down(): void
    {
        Schema::table('event_game_settings', function (Blueprint $table) {
            $table->dropColumn('registration_by_roles');
        });
    }
};
