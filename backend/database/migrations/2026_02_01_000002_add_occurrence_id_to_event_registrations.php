<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->foreignId('occurrence_id')
                ->nullable()
                ->after('event_id')
                ->constrained('event_occurrences')
                ->cascadeOnDelete();

            // Запретим двойную запись одного юзера на одно занятие
            $table->unique(['occurrence_id', 'user_id'], 'uniq_occ_user');
        });
    }

    public function down(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropUnique('uniq_occ_user');
            $table->dropConstrainedForeignId('occurrence_id');
        });
    }
};
