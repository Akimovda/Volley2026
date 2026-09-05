<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_restrictions', function (Blueprint $table) {
            $table->foreignId('organizer_id')->nullable()->after('user_id')
                ->constrained('users')->nullOnDelete();
            $table->index(['scope', 'organizer_id']);
        });
    }

    public function down(): void
    {
        Schema::table('user_restrictions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organizer_id');
        });
    }
};
