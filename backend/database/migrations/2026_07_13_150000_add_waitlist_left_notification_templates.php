<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $existing = DB::table('notification_templates')
            ->whereIn('code', ['organizer_player_waitlist_left', 'waitlist_removed_by_organizer'])
            ->pluck('code')
            ->toArray();

        $toInsert = [];

        if (!in_array('organizer_player_waitlist_left', $existing, true)) {
            $toInsert[] = [
                'code'           => 'organizer_player_waitlist_left',
                'channel'        => null,
                'name'           => 'Организатору: игрок покинул лист ожидания',
                'title_template' => null,
                'body_template'  => null,
                'is_active'      => false,
                'created_at'     => $now,
                'updated_at'     => $now,
            ];
        }

        if (!in_array('waitlist_removed_by_organizer', $existing, true)) {
            $toInsert[] = [
                'code'           => 'waitlist_removed_by_organizer',
                'channel'        => null,
                'name'           => 'Игроку: больше не в листе ожидания (удалил организатор)',
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
            ->whereIn('code', ['organizer_player_waitlist_left', 'waitlist_removed_by_organizer'])
            ->delete();
    }
};
