<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('occurrence_waitlist', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('positions');
        });

        // Инициализируем sort_order по порядку created_at внутри каждого occurrence
        DB::statement("
            UPDATE occurrence_waitlist ow
            SET sort_order = sub.rn
            FROM (
                SELECT id,
                       ROW_NUMBER() OVER (PARTITION BY occurrence_id ORDER BY created_at, id) AS rn
                FROM occurrence_waitlist
            ) sub
            WHERE ow.id = sub.id
        ");
    }

    public function down(): void
    {
        Schema::table('occurrence_waitlist', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
