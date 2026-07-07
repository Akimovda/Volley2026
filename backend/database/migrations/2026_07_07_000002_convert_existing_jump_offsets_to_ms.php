<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Предыдущая миграция переименовала t_offset_sec -> t_offset_ms без конвертации значений.
 * Все строки, существовавшие на момент переименования, были записаны в секундах —
 * домножаем их на 1000, пока не появились новые строки, реально записанные в мс.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('UPDATE activity_jump_events SET t_offset_ms = t_offset_ms * 1000');
    }

    public function down(): void
    {
        DB::statement('UPDATE activity_jump_events SET t_offset_ms = t_offset_ms / 1000');
    }
};
