<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('organizer_id');
            $table->unsignedBigInteger('event_id')->nullable();
            $table->unsignedBigInteger('occurrence_id')->nullable();
            $table->unsignedBigInteger('registration_id')->nullable();
            
            $table->string('method', 20); // cash|tbank_link|sber_link|yoomoney|wallet
            $table->string('status', 20)->default('pending'); // pending|paid|refunded|cancelled|expired
            
            $table->unsignedInteger('amount_minor'); // копейки
            $table->string('currency', 3)->default('RUB');
            
            // ЮМани
            $table->string('yoomoney_payment_id')->nullable()->unique();
            $table->string('yoomoney_confirmation_url', 500)->nullable();
            $table->json('yoomoney_meta')->nullable();
            
            // Резерв
            $table->timestamp('expires_at')->nullable(); // когда истекает резерв
            
            // Подтверждение по ссылке
            $table->boolean('user_confirmed')->default(false);   // "Я оплатил"
            $table->boolean('org_confirmed')->default(false);    // организатор подтвердил
            $table->timestamp('user_confirmed_at')->nullable();
            $table->timestamp('org_confirmed_at')->nullable();
            
            // Возврат
            $table->unsignedInteger('refund_amount_minor')->nullable();
            $table->string('refund_reason')->nullable();
            $table->timestamp('refunded_at')->nullable();
            
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('organizer_id')->references('id')->on('users');
            $table->index(['status', 'expires_at']);
            $table->index(['user_id', 'status']);
        });
    }
    public function down(): void { Schema::dropIfExists('payments'); }
};
