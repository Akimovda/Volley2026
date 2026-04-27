<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Бэкфилл для пользователей у которых заполнены ФИО + телефон,
        // но profile_completed_at не был проставлен из-за бага в ProfileExtraController
        DB::table('users')
            ->whereNull('profile_completed_at')
            ->whereNotNull('first_name')->where('first_name', '!=', '')
            ->whereNotNull('last_name')->where('last_name', '!=', '')
            ->whereNotNull('patronymic')->where('patronymic', '!=', '')
            ->whereNotNull('phone')->where('phone', '!=', '')
            ->update(['profile_completed_at' => DB::raw('NOW()')]);
    }

    public function down(): void {}
};
