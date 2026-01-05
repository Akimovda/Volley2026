<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizer_staff', function (Blueprint $table) {
            $table->foreignId('organizer_id')
                ->after('id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('staff_user_id')
                ->after('organizer_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->unique(['organizer_id', 'staff_user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('organizer_staff', function (Blueprint $table) {
            $table->dropUnique(['organizer_id', 'staff_user_id']);
            $table->dropConstrainedForeignId('staff_user_id');
            $table->dropConstrainedForeignId('organizer_id');
        });
    }
};
