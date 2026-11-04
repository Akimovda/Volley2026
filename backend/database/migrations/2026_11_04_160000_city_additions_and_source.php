<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «+ Добавить город»: города, созданные пользователем (Админ/Организатор/Staff) через GeoNames.
 *  - cities.source ('geonames_user' для добавленных через виджет; null — импорт/ручные миграции), cities.created_by;
 *  - city_additions — журнал: кто, что искал, что создал (или нашёл уже существующим).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('cities', function (Blueprint $t) {
            if (!Schema::hasColumn('cities', 'source')) {
                $t->string('source', 30)->nullable();
            }
            if (!Schema::hasColumn('cities', 'created_by')) {
                $t->unsignedBigInteger('created_by')->nullable()->index();
            }
        });

        if (!Schema::hasTable('city_additions')) {
            Schema::create('city_additions', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('user_id')->nullable()->index();
                $t->unsignedBigInteger('city_id')->nullable()->index();
                $t->unsignedBigInteger('geoname_id')->nullable();
                $t->string('country_code', 2)->nullable();
                $t->string('query', 120)->nullable();
                $t->string('status', 20)->default('created'); // created | exists | failed
                $t->json('meta')->nullable();
                $t->timestamps();
                $t->index(['user_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('city_additions');
        Schema::table('cities', function (Blueprint $t) {
            if (Schema::hasColumn('cities', 'created_by')) {
                $t->dropColumn('created_by');
            }
            if (Schema::hasColumn('cities', 'source')) {
                $t->dropColumn('source');
            }
        });
    }
};
