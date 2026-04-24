<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leagues', function (Blueprint $table) {
            $table->string('vk')->nullable()->after('description');
            $table->string('telegram')->nullable()->after('vk');
            $table->string('max_messenger')->nullable()->after('telegram');
            $table->string('website')->nullable()->after('max_messenger');
            $table->string('phone')->nullable()->after('website');
        });
    }

    public function down(): void
    {
        Schema::table('leagues', function (Blueprint $table) {
            $table->dropColumn(['vk', 'telegram', 'max_messenger', 'website', 'phone']);
        });
    }
};
