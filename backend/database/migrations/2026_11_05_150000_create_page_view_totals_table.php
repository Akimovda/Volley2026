<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Итоговые счётчики просмотров страниц (entity_type + entity_id): сюда «сворачиваются» старые строки page_views
 * при плановой чистке (maintenance:prune-old-records), чтобы счётчик «просмотры профиля за всё время» не уменьшался.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('page_view_totals')) {
            Schema::create('page_view_totals', function (Blueprint $table) {
                $table->string('entity_type', 64);
                $table->unsignedBigInteger('entity_id');
                $table->unsignedBigInteger('views')->default(0);
                $table->timestampTz('updated_at')->nullable();
                $table->primary(['entity_type', 'entity_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('page_view_totals');
    }
};
