<?php

namespace App\Services\Broadcast;

use Carbon\Carbon;

/** Короткая строка «идёт матч / дальше» по состоянию площадки — для пульта и экрана камеры. */
class CourtStatusLine
{
    public static function of(array $state, string $tz): array
    {
        $m = $state['match'] ?? null;
        if (($state['mode'] ?? '') !== 'splash' && $m && ($m['status'] ?? '') === 'live') {
            return ['kind' => 'live', 'text' => __('stream.court_live', ['a' => $m['home'], 'b' => $m['away']])];
        }
        $n = $state['next_match'] ?? null;
        if ($n) {
            $t = !empty($n['scheduled_at']) ? Carbon::parse($n['scheduled_at'])->setTimezone($tz)->format('H:i') : null;
            return ['kind' => 'next', 'text' => $t
                ? __('stream.court_next', ['a' => $n['home'], 'b' => $n['away'], 't' => $t])
                : __('stream.court_next_nt', ['a' => $n['home'], 'b' => $n['away']])];
        }
        return ['kind' => 'none', 'text' => __('stream.court_none')];
    }
}
