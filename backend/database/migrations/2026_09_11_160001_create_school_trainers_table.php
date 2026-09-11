<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::create('school_trainers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('invited_by_user_id')->nullable();
            $table->string('status', 20)->default('pending');
            $table->boolean('can_manage_schedule')->default(false);
            $table->boolean('can_manage_registrations')->default(false);
            $table->boolean('can_view_analytics')->default(false);
            $table->timestampTz('invited_at')->nullable();
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('removed_at')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'user_id'], 'uniq_school_trainer');
            $table->foreign('school_id')->references('id')->on('volleyball_schools')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('invited_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        DB::statement("ALTER TABLE school_trainers ADD CONSTRAINT school_trainers_status_check CHECK (status IN ('pending', 'confirmed', 'declined', 'removed'))");
    }
    public function down(): void {
        Schema::dropIfExists('school_trainers');
    }
};
