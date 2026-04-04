<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class EventRulesService
{
    /**
     * Нормализация age_policy
     */
    public function normalizeAgePolicy(array $data): string
    {
        $agePolicy = (string)($data['age_policy'] ?? 'any');

        return in_array($agePolicy, ['adult', 'child', 'any'], true)
            ? $agePolicy
            : 'any';
    }

    /**
     * Проверка правил формата события
     */
    public function assertFormatRules(array $data): void
    {
        $format = $data['format'] ?? null;
        $direction = $data['direction'] ?? null;

        if ($format === 'coach_student' && $direction !== 'beach') {
            throw ValidationException::withMessages([
                'format' => [
                    'Формат "Тренер+ученик" доступен только для пляжного волейбола.'
                ]
            ]);
        }
    }

    /**
     * Проверка уровней пляжного волейбола
     */
    public function assertBeachLevelsIfNeeded(array $data, string $direction): void
    {
        if ($direction !== 'beach') {
            return;
        }

        $withMinors = (bool)($data['with_minors'] ?? false);

        $allowedLevels = $withMinors
            ? [1, 2, 4]
            : [1, 2, 3, 4, 5, 6, 7];

        foreach (['beach_level_min', 'beach_level_max'] as $field) {

            if (!array_key_exists($field, $data)) {
                continue;
            }

            $value = $data[$field];

            if ($value === null || $value === '') {
                continue;
            }

            if (!in_array((int)$value, $allowedLevels, true)) {

                throw ValidationException::withMessages([
                    $field => [
                        $withMinors
                            ? 'Для пляжа "с несовершеннолетними" допустимы уровни только 1, 2 или 4.'
                            : 'Для пляжа без несовершеннолетних допустимы уровни 1–7.'
                    ]
                ]);
            }
        }
    }
    public function normalizeLevelsByDirection(array $data): array
        {
            $direction = $data['direction'] ?? null;
        
            if ($direction === 'classic') {
                $data['beach_level_min'] = null;
                $data['beach_level_max'] = null;
            }
        
            if ($direction === 'beach') {
                $data['classic_level_min'] = null;
                $data['classic_level_max'] = null;
            }
        
            return $data;
        }
    /**
     * Проверка правил оплаты
     */
     
    public function normalizePrice(array $data): array
    {
        $isPaid = (bool)($data['is_paid'] ?? false);
    
        if (!$isPaid) {
            $data['price_amount'] = null;
            $data['price_minor'] = null;
            $data['price_currency'] = null;
            return $data;
        }
    
        $amount = $data['price_amount'] ?? null;
        $currency = strtoupper(trim((string)($data['price_currency'] ?? 'RUB')));
    
        if ($amount === null || $amount === '') {
            $data['price_minor'] = null;
            $data['price_currency'] = $currency;
            return $data;
        }
    
        $normalized = str_replace([' ', ','], ['', '.'], trim((string)$amount));
    
        if (!is_numeric($normalized)) {
            $data['price_minor'] = null;
            $data['price_currency'] = $currency;
            return $data;
        }
    
        $floatAmount = (float)$normalized;
    
        if ($floatAmount < 10 || $floatAmount > 500000) {
            $data['price_minor'] = null;
            $data['price_currency'] = $currency;
            return $data;
        }
    
        $data['price_minor'] = (int) round($floatAmount * 100);
        $data['price_currency'] = $currency;
    
        return $data;
    }
    
    public function assertPaidRules(array $data): array
{

    $errors = [];

    $isPaid = (bool)($data['is_paid'] ?? false);

    if (!$isPaid) {
        return $errors;
    }

    $priceMinor = $data['price_minor'] ?? null;
    $priceCurrency = trim((string)($data['price_currency'] ?? ''));

    if (is_null($priceMinor) || (int)$priceMinor < 1000) {
        $errors['price_amount'][] = 'Укажи стоимость от 10 до 500 000.';
    }

    if ($priceCurrency === '') {
        $errors['price_currency'][] = 'Укажи валюту.';
    }

    return $errors;
}
   
}