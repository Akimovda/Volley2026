<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!DB::table('notification_templates')->where('code', 'organizer_broadcast')->exists()) {
            DB::table('notification_templates')->insert([
                'code'             => 'organizer_broadcast',
                'channel'          => null,
                'name'             => 'Рассылка организатора участникам',
                'title_template'   => '',
                'body_template'    => '',
                'is_active'        => false,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('notification_templates')->where('code', 'organizer_broadcast')->delete();
    }
};
