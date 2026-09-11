<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('event_occurrences', function (Blueprint $table) {
            $table->timestampTz('trainer_rating_notified_at')->nullable();
        });
    }
    public function down(): void {
        Schema::table('event_occurrences', function (Blueprint $table) {
            $table->dropColumn('trainer_rating_notified_at');
        });
    }
};
