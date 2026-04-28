<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_registration_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained('event_registrations')->cascadeOnDelete();
            $table->foreignId('event_id')->index();
            $table->foreignId('occurrence_id')->nullable()->index();
            $table->foreignId('user_id')->index();
            $table->foreignId('actor_id')->nullable(); // кто совершил действие
            $table->string('action'); // registered | cancelled | restored
            $table->timestamp('created_at');
        });

        // Сид: «registered» из существующих регистраций
        DB::statement("
            INSERT INTO event_registration_logs
                (registration_id, event_id, occurrence_id, user_id, actor_id, action, created_at)
            SELECT
                er.id,
                er.event_id,
                er.occurrence_id,
                er.user_id,
                er.user_id,
                'registered',
                COALESCE(er.created_at, now())
            FROM event_registrations er
        ");

        // Сид: «cancelled» из записей с cancelled_at
        DB::statement("
            INSERT INTO event_registration_logs
                (registration_id, event_id, occurrence_id, user_id, actor_id, action, created_at)
            SELECT
                er.id,
                er.event_id,
                er.occurrence_id,
                er.user_id,
                er.user_id,
                'cancelled',
                er.cancelled_at
            FROM event_registrations er
            WHERE er.cancelled_at IS NOT NULL
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('event_registration_logs');
    }
};
