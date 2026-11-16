<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Несколько черновиков создания мероприятия на пользователя (раньше — один, уникальный user_id).
 * Новый черновик создаётся при первом автосохранении «пустой» формы; дальше форма обновляет свой draft_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_drafts', function (Blueprint $table) {
            $table->dropUnique('event_drafts_user_id_unique');
            $table->index(['user_id', 'updated_at'], 'event_drafts_user_updated_idx');
        });
    }

    public function down(): void
    {
        Schema::table('event_drafts', function (Blueprint $table) {
            $table->dropIndex('event_drafts_user_updated_idx');
        });
        // вернуть уникальность можно только оставив по одному (самому свежему) черновику на пользователя
        \Illuminate\Support\Facades\DB::statement(
            'DELETE FROM event_drafts d USING event_drafts n WHERE d.user_id = n.user_id AND (d.updated_at, d.id) < (n.updated_at, n.id)'
        );
        Schema::table('event_drafts', function (Blueprint $table) {
            $table->unique('user_id');
        });
    }
};
