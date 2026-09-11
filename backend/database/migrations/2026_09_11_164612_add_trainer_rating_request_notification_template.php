<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('notification_templates')->where('code', 'trainer_rating_request')->exists();
        if (!$exists) {
            DB::table('notification_templates')->insert([
                'code'           => 'trainer_rating_request',
                'channel'        => null,
                'name'           => 'Игроку: просьба оценить тренера после тура',
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
        DB::table('notification_templates')->where('code', 'trainer_rating_request')->delete();
    }
};
