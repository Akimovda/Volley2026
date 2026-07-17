<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Дедуп: рассылка «новое мероприятие в городе» отправляется не более
            // одного раза за событие (защита от повторного диспатча/ретрая job).
            $table->timestamp('city_notified_at')->nullable()->after('is_private');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('city_notified_at');
        });
    }
};
