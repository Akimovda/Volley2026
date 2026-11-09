<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Запомненный фильтр списка мероприятий (/events) для залогиненного пользователя:
 * {"direction":"beach","format":"game","level":"3","location":"12","city":"all"}.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'events_filters')) {
            Schema::table('users', function (Blueprint $t) {
                $t->jsonb('events_filters')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'events_filters')) {
            Schema::table('users', function (Blueprint $t) {
                $t->dropColumn('events_filters');
            });
        }
    }
};
