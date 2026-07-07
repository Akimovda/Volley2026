<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE activity_jump_events DROP CONSTRAINT activity_jump_events_session_id_t_offset_sec_unique');
        DB::statement('ALTER TABLE activity_jump_events RENAME COLUMN t_offset_sec TO t_offset_ms');
        DB::statement('ALTER TABLE activity_jump_events ADD CONSTRAINT activity_jump_events_session_id_t_offset_ms_unique UNIQUE (session_id, t_offset_ms)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE activity_jump_events DROP CONSTRAINT activity_jump_events_session_id_t_offset_ms_unique');
        DB::statement('ALTER TABLE activity_jump_events RENAME COLUMN t_offset_ms TO t_offset_sec');
        DB::statement('ALTER TABLE activity_jump_events ADD CONSTRAINT activity_jump_events_session_id_t_offset_sec_unique UNIQUE (session_id, t_offset_sec)');
    }
};
