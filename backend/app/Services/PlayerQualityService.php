<?php

namespace App\Services;

use App\Models\EventOccurrence;
use Illuminate\Support\Facades\DB;

class PlayerQualityService
{
    /**
     * @param array $players  массив ['level' => int(1..7), 'is_female' => bool]
     * @return float|null     коэффициент, округлённый до 2 знаков; null если игроков нет
     */
    public function compute(array $players): ?float
    {
        if (count($players) === 0) return null;

        $men   = array_filter($players, fn($p) => !$p['is_female']);
        $women = array_filter($players, fn($p) =>  $p['is_female']);

        $menSum   = array_sum(array_column($men,   'level'));
        $womenSum = array_sum(array_column($women, 'level'));

        $menCount   = count($men);
        $womenCount = count($women);

        // Понижение применяем, когда женщин <= мужчин (равенство ТОЖЕ понижаем).
        // Без понижения только если женщин строго больше.
        $womenContribution = ($womenCount <= $menCount)
            ? $womenSum - $womenCount
            : $womenSum;

        $total = count($players);
        $value = ($menSum + $womenContribution) / $total;

        return round($value, 2);
    }

    public function forOccurrence(EventOccurrence $occurrence): ?float
    {
        $direction  = $occurrence->event?->direction ?? 'beach';
        $levelField = $direction === 'classic' ? 'classic_level' : 'beach_level';

        $rows = DB::table('event_registrations as er')
            ->join('users as u', 'u.id', '=', 'er.user_id')
            ->where('er.occurrence_id', $occurrence->id)
            ->whereRaw('(er.is_cancelled IS NULL OR er.is_cancelled = false)')
            ->whereNull('er.cancelled_at')
            ->whereRaw("(er.status IS NULL OR er.status = 'confirmed')")
            ->where('er.position', '!=', 'reserve')
            ->select("u.{$levelField} as level", 'u.gender')
            ->get();

        if ($rows->isEmpty()) return null;

        $players = $rows->map(fn($r) => [
            'level'     => (int) $r->level,
            'is_female' => $r->gender === 'f',
        ])->all();

        return $this->compute($players);
    }
}
