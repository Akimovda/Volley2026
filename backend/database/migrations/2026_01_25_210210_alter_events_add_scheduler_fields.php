<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('events')) {
            return;
        }

        Schema::table('events', function (Blueprint $table) {

            if (!Schema::hasColumn('events', 'organizer_id')) {
                $table->foreignId('organizer_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('events', 'location_id')) {
                $table->foreignId('location_id')
                    ->nullable()
                    ->after('organizer_id')
                    ->constrained('locations')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('events', 'timezone')) {
                $table->string('timezone', 64)
                    ->default('Europe/Berlin')
                    ->after('location_id');
            }

            // ВАЖНО: если колонка уже существует (у тебя она есть), мы её не трогаем.
            // Тип уже приводился отдельной миграцией fix_events_timezones.
            if (!Schema::hasColumn('events', 'starts_at')) {
                $table->timestampTz('starts_at')
                    ->nullable()
                    ->after('timezone');
            }

            if (!Schema::hasColumn('events', 'ends_at')) {
                $table->timestampTz('ends_at')
                    ->nullable()
                    ->after('starts_at');
            }

            if (!Schema::hasColumn('events', 'is_private')) {
                $table->boolean('is_private')
                    ->default(false)
                    ->after('ends_at');
            }

            if (!Schema::hasColumn('events', 'direction')) {
                // classic|beach
                $table->string('direction', 16)
                    ->default('classic')
                    ->after('is_private');
            }

            if (!Schema::hasColumn('events', 'format')) {
                // free_play|game|training|training_game|coach_student|tournament|camp
                $table->string('format', 32)
                    ->default('game')
                    ->after('direction');
            }

            if (!Schema::hasColumn('events', 'allow_registration')) {
                $table->boolean('allow_registration')
                    ->default(true)
                    ->after('format');
            }

            if (!Schema::hasColumn('events', 'is_recurring')) {
                $table->boolean('is_recurring')
                    ->default(false)
                    ->after('allow_registration');
            }

            if (!Schema::hasColumn('events', 'recurrence_rule')) {
                $table->text('recurrence_rule')
                    ->nullable()
                    ->after('is_recurring');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('events')) {
            return;
        }

        Schema::table('events', function (Blueprint $table) {

            // 1) Сначала внешние ключи
            if (Schema::hasColumn('events', 'location_id')) {
                try { $table->dropForeign(['location_id']); } catch (\Throwable $e) {}
            }

            // organizer_id НЕ трогаем (как ты и хотел) — он мог быть раньше и используется политиками.

            // 2) Потом колонки
            $cols = [
                'recurrence_rule',
                'is_recurring',
                'allow_registration',
                'format',
                'direction',
                'is_private',
                'ends_at',
                'starts_at',
                'timezone',
                'location_id',
            ];

            foreach ($cols as $c) {
                if (Schema::hasColumn('events', $c)) {
                    try { $table->dropColumn($c); } catch (\Throwable $e) {}
                }
            }
        });
    }
};
