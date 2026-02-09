<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $indexName = 'er_event_cancelled_at_idx';

    public function up(): void
    {
        // Проверяем, нет ли уже такого индекса (Postgres)
        $exists = DB::selectOne("
            select 1
            from pg_indexes
            where tablename = 'event_registrations'
              and indexname = ?
            limit 1
        ", [$this->indexName]);

        if ($exists) return;

        Schema::table('event_registrations', function (Blueprint $table) {
            $table->index(['event_id', 'cancelled_at'], $this->indexName);
        });
    }

    public function down(): void
    {
        // dropIndex по имени — ок
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropIndex($this->indexName);
        });
    }
};
