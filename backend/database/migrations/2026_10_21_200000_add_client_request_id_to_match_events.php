<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Идемпотентность поочкового ввода: клиент шлёт uuid одного нажатия (client_request_id),
 * повтор того же запроса возвращает уже записанное событие вместо нового очка/замены.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['match_rally_events' => 'mre_match_crid_unique', 'match_lineup_events' => 'mle_match_crid_unique'] as $table => $index) {
            Schema::table($table, function (Blueprint $t) {
                $t->uuid('client_request_id')->nullable();
            });
            DB::statement("CREATE UNIQUE INDEX {$index} ON {$table} (match_id, client_request_id) WHERE client_request_id IS NOT NULL");
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS mre_match_crid_unique');
        DB::statement('DROP INDEX IF EXISTS mle_match_crid_unique');
        foreach (['match_rally_events', 'match_lineup_events'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('client_request_id');
            });
        }
    }
};
