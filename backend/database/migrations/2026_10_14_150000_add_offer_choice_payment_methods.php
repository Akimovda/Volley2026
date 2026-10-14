<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // «Предлагать игроку выбор способа оплаты» + какие способы предлагать
        Schema::table('payment_settings', function (Blueprint $t) {
            $t->boolean('offer_choice')->default(false);
            $t->json('offer_methods')->nullable();
        });
        Schema::table('platform_payment_settings', function (Blueprint $t) {
            $t->boolean('offer_choice')->default(false);
            $t->json('offer_methods')->nullable();
        });
        // Способ, выбранный организатором для оплаты рекламы (когда предлагается выбор)
        Schema::table('events', fn (Blueprint $t) => $t->string('ad_payment_method', 20)->nullable());
    }

    public function down(): void
    {
        Schema::table('payment_settings', fn (Blueprint $t) => $t->dropColumn(['offer_choice', 'offer_methods']));
        Schema::table('platform_payment_settings', fn (Blueprint $t) => $t->dropColumn(['offer_choice', 'offer_methods']));
        Schema::table('events', fn (Blueprint $t) => $t->dropColumn('ad_payment_method'));
    }
};
