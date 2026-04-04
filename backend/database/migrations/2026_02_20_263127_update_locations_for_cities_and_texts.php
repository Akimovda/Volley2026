<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            // если у тебя уже есть city (string) — оставим временно, потом можно удалить
            if (!Schema::hasColumn('locations', 'city_id')) {
                $table->unsignedBigInteger('city_id')->nullable()->index()->after('organizer_id');
            }

            if (!Schema::hasColumn('locations', 'short_text')) {
                $table->string('short_text', 255)->nullable();
            }
            if (!Schema::hasColumn('locations', 'long_text')) {
                $table->text('long_text')->nullable();
            }
            if (!Schema::hasColumn('locations', 'long_text_full')) {
                $table->text('long_text_full')->nullable();
            }
            if (!Schema::hasColumn('locations', 'note')) {
                $table->text('note')->nullable();
            }

            if (Schema::hasColumn('locations', 'timezone')) {
                $table->dropColumn('timezone');
            }
        });

        // FK (если таблица cities есть)
        Schema::table('locations', function (Blueprint $table) {
            if (Schema::hasTable('cities')) {
                // если fk уже есть - пропусти
                $table->foreign('city_id')->references('id')->on('cities')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            if (Schema::hasColumn('locations', 'note')) $table->dropColumn('note');
            if (Schema::hasColumn('locations', 'long_text_full')) $table->dropColumn('long_text_full');
            if (Schema::hasColumn('locations', 'long_text')) $table->dropColumn('long_text');
            if (Schema::hasColumn('locations', 'short_text')) $table->dropColumn('short_text');

            if (Schema::hasColumn('locations', 'city_id')) {
                // fk
                try { $table->dropForeign(['city_id']); } catch (\Throwable $e) {}
                $table->dropColumn('city_id');
            }

            // timezone обратно не восстанавливаю (если надо — добавишь)
        });
    }
};
