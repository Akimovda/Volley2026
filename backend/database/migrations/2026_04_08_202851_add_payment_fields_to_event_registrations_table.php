<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->string('payment_status', 20)->nullable()->after('status');
            // pending|paid|refunded|free|link_pending|link_confirmed
            $table->unsignedBigInteger('payment_id')->nullable()->after('payment_status');
            $table->timestamp('payment_expires_at')->nullable()->after('payment_id');
        });
    }
    public function down(): void {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropColumn(['payment_status','payment_id','payment_expires_at']);
        });
    }
};
