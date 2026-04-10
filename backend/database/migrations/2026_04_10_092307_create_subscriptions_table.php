<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('template_id');
            $table->unsignedBigInteger('organizer_id');

            // Срок действия экземпляра
            $table->date('starts_at');
            $table->date('expires_at')->nullable();

            // Посещения
            $table->unsignedSmallInteger('visits_total');
            $table->unsignedSmallInteger('visits_used')->default(0);
            $table->unsignedSmallInteger('visits_remaining'); // computed but stored

            // Статус
            $table->string('status', 20)->default('active');
            // active|frozen|expired|exhausted|cancelled

            // Заморозка
            $table->date('frozen_at')->nullable();
            $table->date('frozen_until')->nullable();

            // Автозапись
            $table->boolean('auto_booking')->default(false);
            $table->json('auto_booking_event_ids')->nullable();

            // Оплата
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->string('payment_status', 20)->nullable(); // free|paid|pending

            // Кто выдал
            $table->unsignedBigInteger('issued_by')->nullable(); // null = сам купил
            $table->string('issue_reason')->nullable();

            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('template_id')->references('id')->on('subscription_templates');
            $table->foreign('organizer_id')->references('id')->on('users');
            $table->index(['user_id', 'status']);
            $table->index(['organizer_id', 'status']);
        });
    }
    public function down(): void { Schema::dropIfExists('subscriptions'); }
};
