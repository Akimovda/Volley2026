<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('volleyball_schools', function (Blueprint $table) {
            $table->string('vk_url')->nullable()->after('website');
            $table->string('tg_url')->nullable()->after('vk_url');
            $table->string('max_url')->nullable()->after('tg_url');
            $table->unsignedBigInteger('city_id')->nullable()->after('city');
            $table->unsignedBigInteger('logo_media_id')->nullable()->after('city_id');
            $table->unsignedBigInteger('cover_media_id')->nullable()->after('logo_media_id');
        });
    }

    public function down(): void
    {
        Schema::table('volleyball_schools', function (Blueprint $table) {
            $table->dropColumn(['vk_url', 'tg_url', 'max_url', 'city_id', 'logo_media_id', 'cover_media_id']);
        });
    }
};
