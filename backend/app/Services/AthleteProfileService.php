<?php

namespace App\Services;

use App\Models\User;

class AthleteProfileService
{
    public function suggestMaxHr(int $age): int
    {
        return (int) round(208 - 0.7 * $age);
    }

    public function effectiveMaxHr(User $user): int
    {
        $profile = $user->athleteProfile;
        if ($profile && $profile->max_hr) {
            return $profile->max_hr;
        }
        if ($user->birth_date) {
            $age = (int) \Carbon\Carbon::parse($user->birth_date)->age;
            return $this->suggestMaxHr($age);
        }
        return 190;
    }

    public function effectiveRestingHr(User $user): int
    {
        $profile = $user->athleteProfile;
        return ($profile && $profile->resting_hr) ? $profile->resting_hr : 60;
    }

    /**
     * Зоны Карвонена: target = rest + pct * (max - rest)
     * Возвращает [z1 => [low, high], ..., z5 => [low, high]]
     */
    public function zoneThresholds(User $user): array
    {
        $max     = $this->effectiveMaxHr($user);
        $resting = $this->effectiveRestingHr($user);
        $hrr     = $max - $resting;

        $pcts = [
            'z1' => [0.50, 0.60],
            'z2' => [0.60, 0.70],
            'z3' => [0.70, 0.80],
            'z4' => [0.80, 0.90],
            'z5' => [0.90, 1.00],
        ];

        $zones = [];
        foreach ($pcts as $key => [$lo, $hi]) {
            $zones[$key] = [
                'low'  => (int) round($resting + $lo * $hrr),
                'high' => (int) round($resting + $hi * $hrr),
            ];
        }
        // z5.high = max (не бесконечность)
        $zones['z5']['high'] = $max;

        return $zones;
    }

    /**
     * 0 = ниже Z1, 1..5 = зона
     */
    public function zoneForBpm(User $user, int $bpm): int
    {
        $zones = $this->zoneThresholds($user);
        for ($z = 5; $z >= 1; $z--) {
            if ($bpm >= $zones["z$z"]['low']) {
                return $z;
            }
        }
        return 0;
    }
}
