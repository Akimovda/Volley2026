<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) DROP INDEX (безопасно, если уже удалены руками/в другой ветке)
        DB::statement('DROP INDEX IF EXISTS events_event_format_index');
        DB::statement('DROP INDEX IF EXISTS events_sport_category_index');
        DB::statement('DROP INDEX IF EXISTS events_is_registrable_index');

        // 2) DROP COLUMNS (если колонок нет — не трогаем)
        Schema::table('events', function (Blueprint $table) {
            $cols = [];

            if (Schema::hasColumn('events', 'sport_category')) $cols[] = 'sport_category';
            if (Schema::hasColumn('events', 'event_format')) $cols[] = 'event_format';
            if (Schema::hasColumn('events', 'rrule')) $cols[] = 'rrule';
            if (Schema::hasColumn('events', 'is_registrable')) $cols[] = 'is_registrable';

            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }

    public function down(): void
    {
        // ВНИМАНИЕ: down восстанавливает legacy-колонки как было в схеме (примерно).
        // Если у тебя раньше были другие defaults/nullability — подгони под свой pgsql-schema.sql.

        Schema::table('events', function (Blueprint $table) {
            if (!Schema::hasColumn('events', 'sport_category')) {
                $table->string('sport_category', 20)->default('classic');
            }
            if (!Schema::hasColumn('events', 'event_format')) {
                $table->string('event_format', 40)->default('game');
            }
            if (!Schema::hasColumn('events', 'rrule')) {
                $table->string('rrule', 255)->nullable();
            }
            if (!Schema::hasColumn('events', 'is_registrable')) {
                $table->boolean('is_registrable')->default(true);
            }
        });

        DB::statement('CREATE INDEX IF NOT EXISTS events_event_format_index ON public.events USING btree (event_format)');
        DB::statement('CREATE INDEX IF NOT EXISTS events_sport_category_index ON public.events USING btree (sport_category)');
        DB::statement('CREATE INDEX IF NOT EXISTS events_is_registrable_index ON public.events USING btree (is_registrable)');
    }
};
