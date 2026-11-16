<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('court_bookings', function (Blueprint $t) {
            $t->string('guest_locale', 5)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('court_bookings', fn (Blueprint $t) => $t->dropColumn('guest_locale'));
    }
};
