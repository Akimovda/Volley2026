<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('subscription_usages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subscription_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('occurrence_id');
            $table->unsignedBigInteger('registration_id')->nullable();

            $table->string('action', 20); // used|returned|burned
            // used = использовано при записи
            // returned = возвращено при отмене (вовремя)
            // burned = сгорело при отмене (слишком поздно)

            $table->timestamp('used_at');
            $table->timestamp('returned_at')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            // FK добавляется после создания subscriptions
            // $table->foreign('subscription_id')->references('id')->on('subscriptions')->cascadeOnDelete();
            $table->index(['subscription_id', 'action']);
            $table->index('occurrence_id');
        });
    }
    public function down(): void { Schema::dropIfExists('subscription_usages'); }
};
