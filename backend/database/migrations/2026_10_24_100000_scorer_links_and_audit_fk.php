<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ведущий матча по токен-ссылке площадки: приглашённый пользователь, «кто последний вводил»,
 * FK аудита recorded_by_link_id → broadcast_links (nullOnDelete).
 */
return new class extends Migration {
    private const AUDIT_TABLES = ['match_rally_events', 'match_lineup_events', 'match_set_starts', 'match_set_lineups', 'match_action_log'];

    public function up(): void
    {
        Schema::table('broadcast_links', function (Blueprint $table) {
            $table->foreignId('assigned_user_id')->nullable()->after('court_id')->constrained('users')->nullOnDelete();
            $table->string('last_actor_name', 80)->nullable();
            $table->foreignId('last_actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('last_actor_at')->nullable();
        });

        foreach (self::AUDIT_TABLES as $t) {
            // Осиротевшие значения (если были) — в NULL, иначе FK не создастся
            \DB::statement("UPDATE {$t} SET recorded_by_link_id = NULL WHERE recorded_by_link_id IS NOT NULL
                AND recorded_by_link_id NOT IN (SELECT id FROM broadcast_links)");
            Schema::table($t, function (Blueprint $table) {
                $table->foreign('recorded_by_link_id')->references('id')->on('broadcast_links')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (self::AUDIT_TABLES as $t) {
            Schema::table($t, fn (Blueprint $table) => $table->dropForeign([ 'recorded_by_link_id' ]));
        }
        Schema::table('broadcast_links', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_user_id');
            $table->dropConstrainedForeignId('last_actor_user_id');
            $table->dropColumn(['last_actor_name', 'last_actor_at']);
        });
    }
};
