<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('events')) {
            // лучше явно упасть, чем молча пропустить
            throw new \RuntimeException('Table events does not exist');
        }

        Schema::table('events', function (Blueprint $table) {

            // Локация
            if (!Schema::hasColumn('events', 'location_id')) {
                $table->foreignId('location_id')
                    ->nullable()
                    ->index()
                    ->after('id');
            }

            // Таймзона события (IANA)
            if (!Schema::hasColumn('events', 'timezone')) {
                $table->string('timezone', 64)
                    ->default('Europe/Berlin')
                    ->after('location_id');
            }

            // Начало/конец (timestamptz)
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

            // sport_category: classic|beach
            if (!Schema::hasColumn('events', 'sport_category')) {
                $table->string('sport_category', 20)
                    ->default('classic')
                    ->index()
                    ->after('ends_at');
            }

            // event_format: из списка
            if (!Schema::hasColumn('events', 'event_format')) {
                $table->string('event_format', 40)
                    ->default('game')
                    ->index()
                    ->after('sport_category');
            }

            // visibility: public|unlisted
            if (!Schema::hasColumn('events', 'visibility')) {
                $table->string('visibility', 20)
                    ->default('public')
                    ->index()
                    ->after('event_format');
            }

            // public_token: uuid
            if (!Schema::hasColumn('events', 'public_token')) {
                $table->uuid('public_token')
                    ->nullable()
                    ->unique()
                    ->after('visibility');
            }

            // recurring + rrule
            if (!Schema::hasColumn('events', 'is_recurring')) {
                $table->boolean('is_recurring')
                    ->default(false)
                    ->after('public_token');
            }

            if (!Schema::hasColumn('events', 'rrule')) {
                $table->string('rrule')
                    ->nullable()
                    ->after('is_recurring');
            }

            // registrable
            if (!Schema::hasColumn('events', 'is_registrable')) {
                $table->boolean('is_registrable')
                    ->default(true)
                    ->index()
                    ->after('rrule');
            }

            // paid + price_text
            if (!Schema::hasColumn('events', 'is_paid')) {
                $table->boolean('is_paid')
                    ->default(false)
                    ->index()
                    ->after('is_registrable');
            }

            if (!Schema::hasColumn('events', 'price_text')) {
                $table->string('price_text')
                    ->nullable()
                    ->after('is_paid');
            }
        });

        // FK location_id -> locations.id (только если locations существует)
        if (Schema::hasTable('locations') && Schema::hasColumn('events', 'location_id')) {
            Schema::table('events', function (Blueprint $table) {
                // если FK уже есть — просто не упадём
                try {
                    $table->foreign('location_id')
                        ->references('id')
                        ->on('locations')
                        ->nullOnDelete();
                } catch (\Throwable $e) {
                    // ignore
                }
            });
        }
    }

    public function down(): void
    {
        /**
         * ВАЖНО:
         * Тут intentionally NO-OP, потому что в твоей базе эти поля уже были ДО этой миграции.
         * Откат “по-честному” может снести рабочие колонки/данные.
         */
    }
};
