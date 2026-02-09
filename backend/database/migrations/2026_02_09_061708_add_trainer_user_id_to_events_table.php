<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (!Schema::hasColumn('events', 'trainer_user_id')) {
                $table->unsignedBigInteger('trainer_user_id')->nullable()->after('organizer_id');
                $table->foreign('trainer_user_id')
                    ->references('id')->on('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events', 'trainer_user_id')) {
                // имя FK может отличаться, но обычно Laravel делает events_trainer_user_id_foreign
                $table->dropForeign(['trainer_user_id']);
                $table->dropColumn('trainer_user_id');
            }
        });
    }
};
