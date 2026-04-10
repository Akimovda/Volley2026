<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->timestamp('confirmed_at')->nullable()->after('subscription_usage_id');
        });
    }
    public function down(): void {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropColumn('confirmed_at');
        });
    }
};
