<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (!Schema::hasColumn('events', 'price_minor')) {
                $table->bigInteger('price_minor')->nullable()->after('is_paid');
            }

            if (!Schema::hasColumn('events', 'price_currency')) {
                $table->char('price_currency', 3)->nullable()->after('price_minor');
            }
        });

        // legacy fallback
        DB::table('events')
            ->whereNotNull('price_text')
            ->whereNull('price_currency')
            ->update([
                'price_currency' => 'RUB',
            ]);
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events', 'price_currency')) {
                $table->dropColumn('price_currency');
            }

            if (Schema::hasColumn('events', 'price_minor')) {
                $table->dropColumn('price_minor');
            }
        });
    }
};
