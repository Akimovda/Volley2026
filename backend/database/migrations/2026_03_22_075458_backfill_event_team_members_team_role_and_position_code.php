<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('event_team_members')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $roleCode = (string) ($row->role_code ?? 'player');

                    $teamRole = 'player';
                    $positionCode = null;

                    if ($roleCode === 'captain') {
                        $teamRole = 'captain';
                    } elseif ($roleCode === 'reserve') {
                        $teamRole = 'reserve';
                    } elseif (in_array($roleCode, ['setter', 'outside', 'opposite', 'middle', 'libero'], true)) {
                        $teamRole = 'player';
                        $positionCode = $roleCode;
                    } else {
                        $teamRole = 'player';
                    }

                    DB::table('event_team_members')
                        ->where('id', $row->id)
                        ->update([
                            'team_role' => $teamRole,
                            'position_code' => $positionCode,
                        ]);
                }
            });
    }

    public function down(): void
    {
        DB::table('event_team_members')->update([
            'team_role' => null,
            'position_code' => null,
        ]);
    }
};
