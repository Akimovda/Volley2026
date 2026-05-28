<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $existing = DB::table('notification_templates')
            ->whereIn('code', ['followed_player_registered', 'organizer_player_waitlisted'])
            ->pluck('code')
            ->toArray();

        $toInsert = [];

        if (!in_array('followed_player_registered', $existing, true)) {
            $toInsert[] = [
                'code'           => 'followed_player_registered',
                'channel'        => null,
                'name'           => 'Подписка: отслеживаемый игрок записался на мероприятие',
                'title_template' => null,
                'body_template'  => null,
                'is_active'      => false,
                'created_at'     => $now,
                'updated_at'     => $now,
            ];
        }

        if (!in_array('organizer_player_waitlisted', $existing, true)) {
            $toInsert[] = [
                'code'           => 'organizer_player_waitlisted',
                'channel'        => null,
                'name'           => 'Организатору: игрок записался в лист ожидания',
                'title_template' => null,
                'body_template'  => null,
                'is_active'      => false,
                'created_at'     => $now,
                'updated_at'     => $now,
            ];
        }

        if ($toInsert) {
            DB::table('notification_templates')->insert($toInsert);
        }
    }

    public function down(): void
    {
        DB::table('notification_templates')
            ->whereIn('code', ['followed_player_registered', 'organizer_player_waitlisted'])
            ->delete();
    }
};
