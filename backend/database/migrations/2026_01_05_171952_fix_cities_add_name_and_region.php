<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            if (!Schema::hasColumn('cities', 'name')) {
                $table->string('name')->nullable();
            }

            if (!Schema::hasColumn('cities', 'region')) {
                $table->string('region')->nullable();
            }
        });

        // Уникальность можно добавить позже, когда будут чистые данные.
        // Schema::table('cities', fn (Blueprint $table) => $table->unique(['name', 'region']));
    }

    public function down(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            if (Schema::hasColumn('cities', 'region')) {
                $table->dropColumn('region');
            }

            if (Schema::hasColumn('cities', 'name')) {
                $table->dropColumn('name');
            }
        });
    }
};
