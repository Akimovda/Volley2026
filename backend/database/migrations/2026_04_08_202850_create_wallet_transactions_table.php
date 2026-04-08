<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('wallet_id');
            $table->string('type', 20); // credit|debit
            $table->unsignedInteger('amount_minor'); // копейки
            $table->string('currency', 3)->default('RUB');
            $table->string('reason', 50)->nullable(); // refund_quorum|refund_organizer|payment|manual
            $table->unsignedBigInteger('event_id')->nullable();
            $table->unsignedBigInteger('occurrence_id')->nullable();
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
            $table->foreign('wallet_id')->references('id')->on('virtual_wallets')->cascadeOnDelete();
        });
    }
    public function down(): void { Schema::dropIfExists('wallet_transactions'); }
};
