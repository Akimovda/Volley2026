<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::create('school_trainer_rates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('user_id');
            $table->string('rate_type', 20);
            $table->decimal('rate', 10, 2);
            $table->timestampTz('effective_from');
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'user_id', 'effective_from'], 'idx_school_trainer_rate_effective');
            $table->foreign('school_id')->references('id')->on('volleyball_schools')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        DB::statement("ALTER TABLE school_trainer_rates ADD CONSTRAINT school_trainer_rates_type_check CHECK (rate_type IN ('hourly', 'per_session', 'fixed_monthly'))");
    }
    public function down(): void {
        Schema::dropIfExists('school_trainer_rates');
    }
};
