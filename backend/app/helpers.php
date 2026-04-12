<?php

declare(strict_types=1);

// =========================
// Levels
// =========================

if (! function_exists('level_name')) {
    function level_name($level): string
    {
        $levels = [
            1 => 'Начальный',
            2 => 'Начальный +',
            3 => 'Средний −',
            4 => 'Средний',
            5 => 'Средний +',
            6 => 'Полупрофи (К.М.С)',
            7 => 'Профи (М.С.)',
        ];

        $level = (int) $level;

        return $levels[$level] ?? '—';
    }
}

// =========================
// Arrays / Old helpers
// =========================

if (! function_exists('arr_int_unique')) {
    /** @return int[] */
    function arr_int_unique($value): array
    {
        if (is_string($value)) $value = [$value];
        if (! is_array($value)) $value = [];

        $out = [];
        foreach ($value as $v) {
            $i = (int) $v;
            if ($i > 0) $out[$i] = true;
        }

        return array_values(array_map('intval', array_keys($out)));
    }
}

if (! function_exists('old_or')) {
    /**
     * old_or('field', $prefill, 'default')
     * Берёт old() если есть (даже пустую строку), иначе $prefill['field'], иначе default.
     */
    function old_or(string $key, array $prefill = [], $default = null)
    {
        $all = request()->old(); // массив всех old значений
        if (is_array($all) && array_key_exists($key, $all)) {
            return old($key);
        }

        return $prefill[$key] ?? $default;
    }
}

// =========================
// Strings
// =========================

if (! function_exists('str_clip')) {
    function str_clip(?string $s, int $limit = 80): string
    {
        $s = trim((string) $s);
        if ($s === '') return '';
        if (mb_strlen($s) <= $limit) return $s;
        return mb_substr($s, 0, max(0, $limit - 1)) . '…';
    }
}

// =========================
// Volleyball Positions
// =========================
if (! function_exists('position_name')) {
    function position_name(?string $role): string
    {
        $map = [

            'outside'  => 'Доигровщик',
            'opposite' => 'Диагональный',
            'middle'   => 'ЦБ',
            'setter'   => 'Связующий',
            'libero'   => 'Либеро',
            'player'   => 'Игрок',

        ];

        if (!$role) {
            return '';
        }

        $role = strtolower(trim($role));

        return $map[$role] ?? ucfirst($role);
    }
}
// =========================
// Валюта
// =========================
if (!function_exists('money_human')) {
    function money_human(?int $minor, ?string $currency = 'RUB'): string
    {
        if ($minor === null) {
            return '—';
        }

        $currency = strtoupper((string)($currency ?: 'RUB'));

        $symbols = [
            'RUB' => '₽',
            'USD' => '$',
            'EUR' => '€',
            'KZT' => '₸',
            'KGS' => 'сом',
            'BYN' => 'Br',
            'UZS' => 'сум',
            'AMD' => '֏',
            'AZN' => '₼',
            'TJS' => 'сомони',
            'TMT' => 'манат',
            'GEL' => '₾',
            'MDL' => 'лей',
        ];

        $amount = $minor / 100;
        $formatted = number_format($amount, 2, ',', ' ');
        $symbol = $symbols[$currency] ?? $currency;

        return $formatted . ' ' . $symbol;
    }
}

// =========================
// Dates
// =========================

if (! function_exists('dt_local_value')) {
    /**
     * Для input[type=datetime-local] -> "YYYY-MM-DDTHH:MM"
     */
    function dt_local_value($dt): string
    {
        if (empty($dt)) return '';

        try {
            if ($dt instanceof \DateTimeInterface) {
                return $dt->format('Y-m-d\TH:i');
            }

            return (new \DateTime((string) $dt))->format('Y-m-d\TH:i');
        } catch (\Throwable $e) {
            return '';
        }
    }
}

// =========================
// Subscription statuses
// =========================
if (!function_exists('subscription_status_name')) {
    function subscription_status_name(?string $status): string
    {
        $map = [
            'active'    => 'Активен',
            'frozen'    => 'Заморожен',
            'expired'   => 'Истёк',
            'exhausted' => 'Исчерпан',
            'cancelled' => 'Отменён',
        ];
        return $map[(string)($status ?? '')] ?? (string)($status ?? '—');
    }
}

// =========================
// Coupon statuses
// =========================
if (!function_exists('coupon_status_name')) {
    function coupon_status_name(?string $status): string
    {
        $map = [
            'active'      => 'Активен',
            'used'        => 'Использован',
            'expired'     => 'Истёк',
            'transferred' => 'Передан',
            'cancelled'   => 'Отменён',
        ];
        return $map[(string)($status ?? '')] ?? (string)($status ?? '—');
    }
}

// =========================
// Payment statuses
// =========================
if (!function_exists('payment_status_name')) {
    function payment_status_name(?string $status): string
    {
        $map = [
            'free'         => 'Бесплатно',
            'pending'      => 'Ожидает оплаты',
            'link_pending' => 'Ожидает оплаты по ссылке',
            'paid'         => 'Оплачено',
            'refunded'     => 'Возвращено',
            'expired'      => 'Истёкло',
            'cancelled'    => 'Отменено',
            'subscription' => 'По абонементу',
        ];
        return $map[(string)($status ?? '')] ?? (string)($status ?? '—');
    }
}

// =========================
// Event direction
// =========================
if (!function_exists('direction_name')) {
    function direction_name(?string $direction): string
    {
        $map = [
            'classic' => 'Классика',
            'beach'   => 'Пляжный',
        ];
        return $map[(string)($direction ?? '')] ?? (string)($direction ?? '—');
    }
}

// =========================
// Event format
// =========================
if (!function_exists('format_name')) {
    function format_name(?string $format): string
    {
        $map = [
            'game'           => 'Игра',
            'training'       => 'Тренировка',
            'training_game'  => 'Тренировка-игра',
            'tournament'     => 'Турнир',
            'open'           => 'Открытая игра',
        ];
        return $map[(string)($format ?? '')] ?? (string)($format ?? '—');
    }
}

// =========================
// Registration mode
// =========================
if (!function_exists('reg_mode_name')) {
    function reg_mode_name(?string $mode): string
    {
        $map = [
            'single'       => 'Одиночная',
            'mixed_group'  => 'Смешанная группа',
            'team_beach'   => 'Пляжная пара',
            'team_classic' => 'Команда (классика)',
            'team'         => 'Команда',
        ];
        return $map[(string)($mode ?? '')] ?? (string)($mode ?? '—');
    }
}

if (! function_exists('level_color')) {
    function level_color(int $level): string
    {
        return match($level) {
            1 => '#9e9e9e', // ⚪️ серый
            2 => '#f5c842', // 🟡 жёлтый
            3 => '#ff9800', // 🟠 оранжевый
            4 => '#2196f3', // 🔵 синий
            5 => '#9c27b0', // 🟣 фиолетовый
            6 => '#f44336', // 🔴 красный
            7 => '#212121', // ⚫️ чёрный
            default => '#888',
        };
    }
}
