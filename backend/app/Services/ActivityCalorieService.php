<?php

namespace App\Services;

class ActivityCalorieService
{
    /**
     * Keytel et al. (2005) — energy expenditure in kJ/min → kcal/min.
     * gender 'f' = female formula, otherwise male.
     */
    public function keytelKcalPerMin(int $hr, float $weightKg, int $age, string $gender): float
    {
        if ($gender === 'f') {
            $ee = -20.4022 + 0.4472 * $hr - 0.1263 * $weightKg + 0.074 * $age;
        } else {
            $ee = -55.0969 + 0.6309 * $hr + 0.1988 * $weightKg + 0.2017 * $age;
        }

        return max(0.0, $ee / 4.184);
    }
}
