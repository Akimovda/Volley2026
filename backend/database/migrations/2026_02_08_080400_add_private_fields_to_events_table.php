<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (!Schema::hasColumn('events', 'is_private')) {
                $table->boolean('is_private')->default(false)->index();
            }
            if (!Schema::hasColumn('events', 'public_token')) {
                $table->string('public_token', 64)->nullable()->unique();
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events', 'public_token')) {
                $table->dropUnique(['public_token']);
                $table->dropColumn('public_token');
            }
            if (Schema::hasColumn('events', 'is_private')) {
                $table->dropIndex(['is_private']);
                $table->dropColumn('is_private');
            }
        });
    }
};
