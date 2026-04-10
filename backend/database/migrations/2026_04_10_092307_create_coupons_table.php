<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('template_id');
            $table->unsignedBigInteger('organizer_id');

            $table->string('code', 32)->unique(); // уникальный код купона

            $table->date('starts_at')->nullable();
            $table->date('expires_at')->nullable();

            $table->unsignedSmallInteger('uses_total');
            $table->unsignedSmallInteger('uses_used')->default(0);
            $table->unsignedSmallInteger('uses_remaining');

            $table->string('status', 20)->default('active');
            // active|used|expired|transferred|cancelled

            $table->unsignedBigInteger('issued_by')->nullable();
            $table->string('issue_channel', 20)->nullable(); // telegram|vk|max|inapp|manual

            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('template_id')->references('id')->on('coupon_templates');
            $table->foreign('organizer_id')->references('id')->on('users');
            $table->index(['user_id', 'status']);
            $table->index('code');
        });
    }
    public function down(): void { Schema::dropIfExists('coupons'); }
};
