<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $existing = DB::table('notification_templates')
            ->whereIn('code', ['waitlist_auto_booked', 'organizer_player_auto_booked'])
            ->pluck('code')
            ->toArray();

        $toInsert = [];

        if (!in_array('waitlist_auto_booked', $existing, true)) {
            $toInsert[] = [
                'code'               => 'waitlist_auto_booked',
                'channel'            => null,
                'name'               => 'Игроку: авто-запись из листа ожидания',
                'title_template'     => null,
                'body_template'      => null,
                'is_active'          => false,
                'created_at'         => $now,
                'updated_at'         => $now,
            ];
        }

        if (!in_array('organizer_player_auto_booked', $existing, true)) {
            $toInsert[] = [
                'code'               => 'organizer_player_auto_booked',
                'channel'            => null,
                'name'               => 'Организатору: игрок авто-записан из листа ожидания',
                'title_template'     => null,
                'body_template'      => null,
                'is_active'          => false,
                'created_at'         => $now,
                'updated_at'         => $now,
            ];
        }

        if ($toInsert) {
            DB::table('notification_templates')->insert($toInsert);
        }
    }

    public function down(): void
    {
        DB::table('notification_templates')
            ->whereIn('code', ['waitlist_auto_booked', 'organizer_player_auto_booked'])
            ->delete();
    }
};
