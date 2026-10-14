<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function cols(Blueprint $t, string $p): void
    {
        foreach (['fee', 'tax'] as $k) {
            $t->decimal($p . $k . '_percent', 5, 2)->default(0);
            $t->string($p . $k . '_mode', 10)->default('included'); // included | on_top
        }
    }

    public function up(): void
    {
        Schema::table('payment_settings', fn (Blueprint $t) => $this->cols($t, ''));
        Schema::table('platform_payment_settings', function (Blueprint $t) {
            $this->cols($t, 'sub_');
            $this->cols($t, 'ad_');
        });
        Schema::table('payments', function (Blueprint $t) {
            $t->bigInteger('fee_minor')->default(0);
            $t->bigInteger('tax_minor')->default(0);
        });
    }

    public function down(): void
    {
        $names = fn (string $p) => [$p . 'fee_percent', $p . 'fee_mode', $p . 'tax_percent', $p . 'tax_mode'];
        Schema::table('payment_settings', fn (Blueprint $t) => $t->dropColumn($names('')));
        Schema::table('platform_payment_settings', fn (Blueprint $t) => $t->dropColumn(array_merge($names('sub_'), $names('ad_'))));
        Schema::table('payments', fn (Blueprint $t) => $t->dropColumn(['fee_minor', 'tax_minor']));
    }
};
