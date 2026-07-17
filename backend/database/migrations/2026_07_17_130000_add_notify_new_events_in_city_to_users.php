<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Опт-аут (по умолчанию ВКЛ): уведомлять о новом мероприятии в своём городе.
            $table->boolean('notify_new_events_in_city')->default(true)->after('city_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notify_new_events_in_city');
        });
    }
};
