<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_likes', function (Blueprint $table) {
            $table->foreignId('occurrence_id')->nullable()->after('event_id')
                ->constrained('event_occurrences')->cascadeOnDelete();
        });

        // Бэкфилл: лайк был поставлен на карточке конкретного тура (occurrence),
        // но до этой миграции хранился только по event_id (серия) — из-за этого
        // лайк на одной дате повторяющегося мероприятия показывался на ВСЕХ его
        // датах. Для уже существующих записей (фича всего ~недельной давности,
        // строк мало) привязываем к первому по дате туру серии — лучшее, что можно
        // восстановить без исходного контекста клика.
        DB::table('event_likes')->whereNull('occurrence_id')->orderBy('id')->get()->each(function ($like) {
            $occurrenceId = DB::table('event_occurrences')
                ->where('event_id', $like->event_id)
                ->orderBy('starts_at')
                ->value('id');

            if ($occurrenceId) {
                DB::table('event_likes')->where('id', $like->id)->update(['occurrence_id' => $occurrenceId]);
            }
        });

        Schema::table('event_likes', function (Blueprint $table) {
            $table->dropUnique('event_likes_event_id_user_id_unique');
            $table->unique(['occurrence_id', 'user_id'], 'event_likes_occurrence_user_unique');
        });
    }

    public function down(): void
    {
        Schema::table('event_likes', function (Blueprint $table) {
            $table->dropUnique('event_likes_occurrence_user_unique');
            $table->unique(['event_id', 'user_id'], 'event_likes_event_id_user_id_unique');
            $table->dropConstrainedForeignId('occurrence_id');
        });
    }
};
