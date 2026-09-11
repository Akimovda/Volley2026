<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('notification_templates')->where('code', 'trainer_assigned')->exists();
        if (!$exists) {
            DB::table('notification_templates')->insert([
                'code'           => 'trainer_assigned',
                'channel'        => null,
                'name'           => 'Тренера пригласили в школу',
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
        DB::table('notification_templates')->where('code', 'trainer_assigned')->delete();
    }
};
