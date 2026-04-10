<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('coupon_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organizer_id');
            $table->string('name', 150);
            $table->text('description')->nullable();

            $table->json('event_ids')->nullable(); // null = все мероприятия организатора

            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();

            // Скидка
            $table->unsignedTinyInteger('discount_pct'); // 1-100

            // Лимит использований на один выданный купон
            $table->unsignedSmallInteger('uses_per_coupon')->default(1);

            // Сгорание при отмене
            $table->unsignedSmallInteger('cancel_hours_before')->default(0);

            // Передача
            $table->boolean('transfer_enabled')->default(false);

            // Лимит выдачи
            $table->unsignedInteger('issue_limit')->nullable();
            $table->unsignedInteger('issued_count')->default(0);

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('organizer_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['organizer_id', 'is_active']);
        });
    }
    public function down(): void { Schema::dropIfExists('coupon_templates'); }
};
