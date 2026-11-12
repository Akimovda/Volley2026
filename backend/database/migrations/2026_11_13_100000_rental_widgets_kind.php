<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Два вида виджета аренды у одного владельца: cards (карточки площадок) и timeline (таймлайн бронирования)
        Schema::table('rental_widgets', function (Blueprint $table) {
            $table->string('kind', 16)->default('cards')->after('user_id');
            $table->dropUnique(['user_id']);
            $table->unique(['user_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::table('rental_widgets', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'kind']);
            $table->unique('user_id');
            $table->dropColumn('kind');
        });
    }
};
