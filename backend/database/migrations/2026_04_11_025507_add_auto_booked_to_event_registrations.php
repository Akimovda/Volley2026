<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->boolean('auto_booked')->default(false)->after('confirmed_at');
        });
    }
    public function down(): void {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropColumn('auto_booked');
        });
    }
};
