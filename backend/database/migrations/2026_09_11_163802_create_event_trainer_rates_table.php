<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// §1.4: ставка тренера на конкретное СОБЫТИЕ (серию) — override, приоритет выше
// school_trainer_rates (общей ставки по школе). Append-only с историчностью через
// effective_from, тот же паттерн, что school_trainer_rates.
return new class extends Migration {
    public function up(): void {
        Schema::create('event_trainer_rates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('user_id');
            $table->string('rate_type', 20);
            $table->decimal('rate', 10, 2);
            $table->timestampTz('effective_from');
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'user_id', 'effective_from'], 'idx_event_trainer_rate_effective');
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        DB::statement("ALTER TABLE event_trainer_rates ADD CONSTRAINT event_trainer_rates_type_check CHECK (rate_type IN ('hourly', 'per_session', 'fixed_monthly'))");
    }
    public function down(): void {
        Schema::dropIfExists('event_trainer_rates');
    }
};
