<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «Разослать и игрокам без уровня»: при заданном диапазоне уровней рассылка о новом мероприятии
 * по умолчанию идёт только игрокам с заполненным уровнем в диапазоне. Флаг добавляет тех, у кого уровень не указан.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('events', 'notify_unrated_players')) {
            Schema::table('events', function (Blueprint $t) {
                $t->boolean('notify_unrated_players')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('events', 'notify_unrated_players')) {
            Schema::table('events', function (Blueprint $t) {
                $t->dropColumn('notify_unrated_players');
            });
        }
    }
};
