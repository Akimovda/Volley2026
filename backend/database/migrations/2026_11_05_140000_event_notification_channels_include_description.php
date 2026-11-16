<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Чекбокс «Описание» в настройках каналов анонсов (кемпы, мастер-классы, турниры): добавлять ли в анонс блок описания.
 * NULL — значение по умолчанию для формата (у кемпа описание в анонсе было всегда → включено; у остальных → нет).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('event_notification_channels') && !Schema::hasColumn('event_notification_channels', 'include_description')) {
            Schema::table('event_notification_channels', function (Blueprint $table) {
                $table->boolean('include_description')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('event_notification_channels', 'include_description')) {
            Schema::table('event_notification_channels', function (Blueprint $table) {
                $table->dropColumn('include_description');
            });
        }
    }
};
