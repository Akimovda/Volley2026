<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('payment_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organizer_id')->unique();
            
            // Метод оплаты по умолчанию
            $table->string('default_method', 20)->default('cash'); // cash|link|yoomoney
            
            // Ссылки для ручного перевода
            $table->string('tbank_link')->nullable();
            $table->string('sber_link')->nullable();
            
            // ЮМани
            $table->string('yoomoney_shop_id')->nullable();
            $table->string('yoomoney_secret_key')->nullable(); // encrypted
            $table->boolean('yoomoney_enabled')->default(false);
            $table->boolean('yoomoney_verified')->default(false);
            
            // Политика возврата (по умолчанию)
            $table->unsignedSmallInteger('refund_hours_full')->default(48);  // за сколько часов 100%
            $table->unsignedSmallInteger('refund_hours_partial')->default(24); // за сколько часов частичный
            $table->unsignedTinyInteger('refund_partial_pct')->default(50);    // % частичного возврата
            $table->boolean('refund_no_quorum_full')->default(true); // 100% при отмене по кворуму
            
            // Время удержания резерва (минуты)
            $table->unsignedSmallInteger('payment_hold_minutes')->default(15);
            
            $table->timestamps();
            $table->foreign('organizer_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
    public function down(): void { Schema::dropIfExists('payment_settings'); }
};
