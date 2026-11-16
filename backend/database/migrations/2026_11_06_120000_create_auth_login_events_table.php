<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Исходы входа через провайдеров (success/success_new/error) — для метрик в Kuma (auth:report-metrics). */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('auth_login_events')) {
            Schema::create('auth_login_events', function (Blueprint $t) {
                $t->id();
                $t->string('provider', 20);
                $t->string('outcome', 20);
                $t->string('reason', 160)->nullable();
                $t->timestamp('created_at')->useCurrent();
                $t->index(['created_at', 'provider']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_login_events');
    }
};
