<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_clients', function (Blueprint $t) {
            $t->id();
            $t->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $t->string('name', 150);
            $t->string('phone', 20);          // только цифры, 7XXXXXXXXXX
            $t->string('email', 190)->nullable();
            $t->timestamp('consent_at')->nullable();   // согласие на обработку ПДн (152-ФЗ)
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->unique(['owner_id', 'phone']);
        });

        Schema::table('court_bookings', function (Blueprint $t) {
            $t->foreignId('rental_client_id')->nullable()->constrained('rental_clients')->nullOnDelete();
            $t->string('guest_email', 190)->nullable();
            $t->string('order_token', 48)->nullable()->index(); // общий токен заказа из виджета (несколько кортов)
            $t->text('guest_comment')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('court_bookings', function (Blueprint $t) {
            $t->dropConstrainedForeignId('rental_client_id');
            $t->dropColumn(['guest_email', 'order_token', 'guest_comment']);
        });
        Schema::dropIfExists('rental_clients');
    }
};
