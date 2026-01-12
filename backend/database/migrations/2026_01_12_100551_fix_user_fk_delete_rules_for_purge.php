<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * events.organizer_id -> users.id
         * Сейчас NO ACTION, из-за этого нельзя удалить user-организатора.
         * Логичный вариант для purge: SET NULL (событие остаётся, организатор исчез).
         */
        if (Schema::hasTable('events') && Schema::hasColumn('events', 'organizer_id')) {
            Schema::table('events', function (Blueprint $table) {
                // На всякий случай: организатор должен быть nullable, иначе SET NULL не сработает
                $table->unsignedBigInteger('organizer_id')->nullable()->change();
            });

            Schema::table('events', function (Blueprint $table) {
                // имя FK может отличаться, поэтому dropForeign по колонке
                try { $table->dropForeign(['organizer_id']); } catch (\Throwable $e) {}
                $table->foreign('organizer_id')
                    ->references('id')->on('users')
                    ->nullOnDelete();
            });
        }

        /**
         * organizer_requests.reviewed_by -> users.id
         * Сейчас NO ACTION, мешает purge модератора/админа.
         * Для purge: SET NULL (заявка остаётся, reviewed_by очищается).
         */
        if (Schema::hasTable('organizer_requests') && Schema::hasColumn('organizer_requests', 'reviewed_by')) {
            Schema::table('organizer_requests', function (Blueprint $table) {
                $table->unsignedBigInteger('reviewed_by')->nullable()->change();
            });

            Schema::table('organizer_requests', function (Blueprint $table) {
                try { $table->dropForeign(['reviewed_by']); } catch (\Throwable $e) {}
                $table->foreign('reviewed_by')
                    ->references('id')->on('users')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        /**
         * В down возвращать NO ACTION обычно не нужно (и часто невозможно безопасно,
         * т.к. имена constraint разные). Оставим пустым.
         */
    }
};
