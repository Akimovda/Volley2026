<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Блок «Скоро»: пользователь скрывает отдельные мероприятия (крестик на карточке) и весь блок,
 * плюс счётчики для организаторов (показы, переходы, скрытия по дням).
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('upcoming_dismissals')) {
            Schema::create('upcoming_dismissals', function (Blueprint $t) {
                $t->id();
                $t->foreignId('user_id')->constrained()->cascadeOnDelete();
                $t->foreignId('event_id')->constrained()->cascadeOnDelete();
                $t->timestamp('created_at')->useCurrent();
                $t->unique(['user_id', 'event_id']);
                $t->index('event_id');
            });
        }
        if (!Schema::hasTable('upcoming_block_stats')) {
            Schema::create('upcoming_block_stats', function (Blueprint $t) {
                $t->id();
                $t->foreignId('event_id')->constrained()->cascadeOnDelete();
                $t->date('day');
                $t->unsignedInteger('impressions')->default(0);
                $t->unsignedInteger('clicks')->default(0);
                $t->unsignedInteger('dismissals')->default(0);
                $t->unique(['event_id', 'day']);
            });
        }
        if (!Schema::hasColumn('users', 'upcoming_hidden_until')) {
            Schema::table('users', function (Blueprint $t) {
                $t->timestamp('upcoming_hidden_until')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('upcoming_block_stats');
        Schema::dropIfExists('upcoming_dismissals');
        if (Schema::hasColumn('users', 'upcoming_hidden_until')) {
            Schema::table('users', fn (Blueprint $t) => $t->dropColumn('upcoming_hidden_until'));
        }
    }
};
