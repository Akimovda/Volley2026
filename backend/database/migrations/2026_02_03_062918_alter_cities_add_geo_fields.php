<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            if (!Schema::hasColumn('cities', 'country_code')) {
                $table->string('country_code', 2)->nullable()->index();
            }
            if (!Schema::hasColumn('cities', 'timezone')) {
                $table->string('timezone', 64)->nullable()->index();
            }
            if (!Schema::hasColumn('cities', 'lat')) {
                $table->decimal('lat', 10, 7)->nullable();
            }
            if (!Schema::hasColumn('cities', 'lon')) {
                $table->decimal('lon', 10, 7)->nullable();
            }
            if (!Schema::hasColumn('cities', 'population')) {
                $table->integer('population')->nullable()->index();
            }
            if (!Schema::hasColumn('cities', 'geoname_id')) {
                $table->bigInteger('geoname_id')->nullable()->unique();
            }

            // антидубли (не unique, а просто индекс)
            $table->index(['country_code', 'name', 'region']);
        });
    }

    public function down(): void
    {
        // по желанию
    }
};
