<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizer_staff', function (Blueprint $table) {
            // «Мастер»: помощник, которому организатор доверил абонементы и купоны
            $table->boolean('can_manage_subs')->default(false)->after('staff_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('organizer_staff', function (Blueprint $table) {
            $table->dropColumn('can_manage_subs');
        });
    }
};
