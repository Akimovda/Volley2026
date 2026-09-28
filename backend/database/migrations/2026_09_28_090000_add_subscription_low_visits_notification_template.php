<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('notification_templates')->where('code', 'subscription_low_visits')->exists();
        if (!$exists) {
            DB::table('notification_templates')->insert([
                'code'           => 'subscription_low_visits',
                'channel'        => null,
                'name'           => 'Игроку: у абонемента остался последний визит',
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
        DB::table('notification_templates')->where('code', 'subscription_low_visits')->delete();
    }
};
