<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Режим оплаты в настройках турнира
        Schema::table('event_tournament_settings', function (Blueprint $table) {
            $table->string('payment_mode')->default('free')->after('total_players_max');
            // free | team | per_player
        });

        // 2. Оплата команды (капитан платит за всех)
        Schema::table('event_teams', function (Blueprint $table) {
            $table->string('payment_status')->nullable()->after('confirmed_at');
            // null (не требуется) | pending | paid | expired | subscription
            $table->foreignId('payment_id')->nullable()->after('payment_status')
                  ->constrained('payments')->nullOnDelete();
        });

        // 3. Оплата участника (каждый сам)
        Schema::table('event_team_members', function (Blueprint $table) {
            $table->string('payment_status')->nullable()->after('confirmed_at');
            $table->foreignId('payment_id')->nullable()->after('payment_status')
                  ->constrained('payments')->nullOnDelete();
        });

        // 4. Привязка платежа к команде
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('registration_id')
                  ->constrained('event_teams')->nullOnDelete();
            $table->foreignId('team_member_id')->nullable()->after('team_id')
                  ->constrained('event_team_members')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_member_id');
            $table->dropConstrainedForeignId('team_id');
        });

        Schema::table('event_team_members', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_id');
            $table->dropColumn('payment_status');
        });

        Schema::table('event_teams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_id');
            $table->dropColumn('payment_status');
        });

        Schema::table('event_tournament_settings', function (Blueprint $table) {
            $table->dropColumn('payment_mode');
        });
    }
};
