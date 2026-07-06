<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            // 'full' — полный возврат при отмене до дедлайна; 'none' — без возвратов
            $table->string('refund_policy', 20)->default('full')->after('booking_cancel_hours');
            $table->unsignedSmallInteger('refund_deadline_hours')->default(24)->after('refund_policy');
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn(['refund_policy', 'refund_deadline_hours']);
        });
    }
};
