<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('court_bookings', function (Blueprint $table) {
            $table->string('guest_name', 150)->nullable()->after('user_id');
            $table->string('guest_phone', 30)->nullable()->after('guest_name');
        });

        // doctrine/dbal не установлен — ->change() недоступен, делаем raw ALTER.
        DB::statement('ALTER TABLE court_bookings ALTER COLUMN user_id DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE court_bookings ALTER COLUMN user_id SET NOT NULL');

        Schema::table('court_bookings', function (Blueprint $table) {
            $table->dropColumn(['guest_name', 'guest_phone']);
        });
    }
};
