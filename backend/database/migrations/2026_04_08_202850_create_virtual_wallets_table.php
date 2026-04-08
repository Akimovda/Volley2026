<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('virtual_wallets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->unsignedBigInteger('organizer_id'); // у какого организатора счёт
            $table->unsignedInteger('balance_minor')->default(0); // копейки
            $table->string('currency', 3)->default('RUB');
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('organizer_id')->references('id')->on('users')->cascadeOnDelete();
            // Один кошелёк на пару user+organizer
            $table->unique(['user_id', 'organizer_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('virtual_wallets'); }
};
