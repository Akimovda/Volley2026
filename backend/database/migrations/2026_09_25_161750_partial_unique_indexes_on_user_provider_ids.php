<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Полные уникальные индексы на provider id считали уникальность по ВСЕЙ
 * таблице, включая soft-deleted строки — при повторном входе мягко
 * удалённого пользователя через тот же OAuth-провайдер новый User::save()
 * падал на UniqueConstraintViolationException (500), т.к. значение всё ещё
 * занято старой (удалённой) строкой. См. report/kernel_cleanup_recon.md.
 *
 * Частичный индекс WHERE deleted_at IS NULL проверяет уникальность только
 * среди живых пользователей — после этого код в AccountDeleteRequestController
 * и UserMergeService (обнуляющий provider id при удалении/слиянии) достаточен
 * сам по себе, но индекс — последняя линия защиты на случай будущих багов
 * в этой логике.
 */
return new class extends Migration
{
    private const COLUMNS = ['telegram_id', 'vk_id', 'yandex_id', 'apple_id', 'google_id'];

    public function up(): void
    {
        Schema::table('users', function ($table) {
            foreach (self::COLUMNS as $column) {
                $table->dropUnique("users_{$column}_unique");
            }
        });

        foreach (self::COLUMNS as $column) {
            DB::statement("CREATE UNIQUE INDEX users_{$column}_unique ON users ({$column}) WHERE deleted_at IS NULL");
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as $column) {
            DB::statement("DROP INDEX IF EXISTS users_{$column}_unique");
        }

        Schema::table('users', function ($table) {
            foreach (self::COLUMNS as $column) {
                $table->unique($column);
            }
        });
    }
};
