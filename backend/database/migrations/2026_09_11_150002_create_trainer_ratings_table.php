<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::create('trainer_ratings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('occurrence_id');
            $table->unsignedBigInteger('trainer_user_id');
            $table->unsignedBigInteger('rater_user_id');
            $table->unsignedTinyInteger('score');
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['occurrence_id', 'trainer_user_id', 'rater_user_id'], 'uniq_occ_trainer_rater');
            $table->foreign('occurrence_id')->references('id')->on('event_occurrences')->cascadeOnDelete();
            $table->foreign('trainer_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('rater_user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        DB::statement('ALTER TABLE trainer_ratings ADD CONSTRAINT trainer_ratings_score_check CHECK (score >= 1 AND score <= 10)');
    }
    public function down(): void {
        Schema::dropIfExists('trainer_ratings');
    }
};
