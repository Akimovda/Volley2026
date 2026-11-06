<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Шаблон команды: статус «запасной» отдельно от роли. Капитан (role_code=captain) тоже может быть запасным;
 * у остальных is_reserve повторяет role_code='reserve'.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('user_team_members', 'is_reserve')) {
            Schema::table('user_team_members', function (Blueprint $t) {
                $t->boolean('is_reserve')->default(false)->after('role_code');
            });
        }
        DB::table('user_team_members')->where('role_code', 'reserve')->update(['is_reserve' => true]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('user_team_members', 'is_reserve')) {
            Schema::table('user_team_members', fn (Blueprint $t) => $t->dropColumn('is_reserve'));
        }
    }
};
