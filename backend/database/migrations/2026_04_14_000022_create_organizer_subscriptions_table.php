<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizer_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('plan');                    // trial | month | quarter | year
            $table->string('status')->default('active'); // active | expired | cancelled
            $table->timestamp('starts_at');
            $table->timestamp('expires_at');
            $table->string('payment_method')->nullable(); // tbank | yoomoney | manual
            $table->string('payment_id')->nullable();     // внешний ID платежа
            $table->decimal('amount_rub', 10, 2)->nullable();
            $table->boolean('auto_renew')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizer_subscriptions');
    }
};
