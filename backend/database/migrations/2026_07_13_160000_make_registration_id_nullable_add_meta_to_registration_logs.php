<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_registration_logs', function (Blueprint $table) {
            // Waitlist-события (join/leave/auto-booked/removed_by_organizer) происходят
            // до создания event_registrations — регистрации ещё не существует.
            $table->unsignedBigInteger('registration_id')->nullable()->change();

            // Деталь события (напр. позиция waitlist) — существующие action-типы её не
            // хранили, но без неё текст истории теряет "(Доигровщик)" из примера в задаче.
            $table->jsonb('meta')->nullable()->after('action');
        });
    }

    public function down(): void
    {
        Schema::table('event_registration_logs', function (Blueprint $table) {
            $table->dropColumn('meta');
            $table->unsignedBigInteger('registration_id')->nullable(false)->change();
        });
    }
};
