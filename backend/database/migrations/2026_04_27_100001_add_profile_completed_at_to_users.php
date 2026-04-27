<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('profile_completed_at')->nullable()->after('created_at');
        });

        // Бэкфилл: существующие пользователи с заполненным профилем
        DB::table('users')
            ->whereNull('profile_completed_at')
            ->where(function ($q) {
                $q->where(fn ($q2) => $q2->whereNotNull('name')->where('name', '!=', ''))
                  ->orWhere(fn ($q2) => $q2->whereNotNull('first_name')->where('first_name', '!=', ''));
            })
            ->update(['profile_completed_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('profile_completed_at');
        });
    }
};
