<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!DB::table('notification_templates')->where('code', 'broadcast_scorer_invited')->exists()) {
            DB::table('notification_templates')->insert([
                'code'           => 'broadcast_scorer_invited',
                'channel'        => null,
                'name'           => 'Приглашение ведущим матчей площадки (трансляция)',
                'title_template' => null,
                'body_template'  => null,
                'is_active'      => false,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('notification_templates')->where('code', 'broadcast_scorer_invited')->delete();
    }
};
