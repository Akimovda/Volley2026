<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE event_registrations DROP CONSTRAINT IF EXISTS event_registrations_event_id_user_id_unique');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE event_registrations ADD CONSTRAINT event_registrations_event_id_user_id_unique UNIQUE (event_id, user_id)');
    }
};
