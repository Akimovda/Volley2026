<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tournament_matches', function (Blueprint $t) {
            // 'auto' — площадку поставила система, 'manual' — организатор; null — площадки нет
            $t->string('court_assigned_by', 10)->nullable()->after('court_id');
        });

        // Всё, что уже было назначено до появления автоматики, считаем ручным: автоматика это не тронет
        DB::table('tournament_matches')->whereNotNull('court_id')->update(['court_assigned_by' => 'manual']);
    }

    public function down(): void
    {
        Schema::table('tournament_matches', function (Blueprint $t) {
            $t->dropColumn('court_assigned_by');
        });
    }
};
