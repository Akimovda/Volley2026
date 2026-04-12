<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_payment_settings', function (Blueprint $table) {
            $table->id();
            $table->enum('method', ['tbank_link', 'sber_link', 'yoomoney'])->default('tbank_link');
            $table->string('tbank_link')->nullable();
            $table->string('sber_link')->nullable();
            $table->string('yoomoney_shop_id')->nullable();
            $table->text('yoomoney_secret_key')->nullable();
            $table->boolean('yoomoney_verified')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_payment_settings');
    }
};
