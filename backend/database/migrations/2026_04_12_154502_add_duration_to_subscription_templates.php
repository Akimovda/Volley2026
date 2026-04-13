<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_templates', function (Blueprint $table) {
            $table->unsignedSmallInteger('duration_months')->default(0)->after('valid_until');
            $table->unsignedSmallInteger('duration_days')->default(0)->after('duration_months');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_templates', function (Blueprint $table) {
            $table->dropColumn(['duration_months', 'duration_days']);
        });
    }
};
