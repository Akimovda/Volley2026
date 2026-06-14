<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $codes = [
        'promotion' => [
            'name'           => 'Промоушен/вылет в лиге (перемещение команды)',
            'title_template' => null,
            'body_template'  => null,
            'is_active'      => false,
        ],
        'reserve_spot_offered' => [
            'name'           => 'Игроку: освободилось место в лиге (из резерва)',
            'title_template' => null,
            'body_template'  => null,
            'is_active'      => false,
        ],
        'reserve_spot_offered_organizer' => [
            'name'           => 'Организатору: команда из резерва лиги приглашена',
            'title_template' => null,
            'body_template'  => null,
            'is_active'      => false,
        ],
    ];

    public function up(): void
    {
        $now = now();
        $existing = DB::table('notification_templates')
            ->whereIn('code', array_keys($this->codes))
            ->pluck('code')
            ->toArray();

        $toInsert = [];
        foreach ($this->codes as $code => $data) {
            if (!in_array($code, $existing, true)) {
                $toInsert[] = array_merge(['code' => $code, 'channel' => null], $data, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if ($toInsert) {
            DB::table('notification_templates')->insert($toInsert);
        }
    }

    public function down(): void
    {
        DB::table('notification_templates')
            ->whereIn('code', array_keys($this->codes))
            ->delete();
    }
};
