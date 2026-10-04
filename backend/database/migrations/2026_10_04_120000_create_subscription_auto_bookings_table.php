<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Автозапись по абонементу: «абонемент → мероприятие (серия) → позиция».
        // position NULL допустим только для событий без выбора амплуа (пляжка и т.п.);
        // для классики с амплуа позиция обязательна (проверяется в контроллере и в джобе).
        Schema::create('subscription_auto_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('position', 32)->nullable();
            $table->timestamps();

            $table->unique(['subscription_id', 'event_id']);
            $table->index('event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_auto_bookings');
    }
};
