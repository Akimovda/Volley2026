<?php

namespace App\Support;

/**
 * Комиссия эквайринга и налог: «включены в цену» (плательщик платит базовую цену, издержки несёт получатель)
 * либо «сверху» (к базовой цене добавляется надбавка, оба процента считаются от базовой цены).
 * Комиссия ЮKassa зависит от способа оплаты, поэтому при режиме «сверху» способ выбирается на нашей стороне
 * (needsChoice), и платёж создаётся с payment_method_data выбранного способа.
 * Параметры лежат в строке настроек с префиксом: '' (payment_settings организатора), 'sub_', 'ad_' (platform_payment_settings).
 */
class PaymentPricing
{
    /** Способы ЮKassa (payment_method_data.type) → подпись. */
    public const METHODS = [
        'bank_card'    => 'Банковская карта',
        'sbp'          => 'СБП',
        'sberbank'     => 'SberPay',
        'tinkoff_bank' => 'T-Pay',
        'yoo_money'    => 'ЮMoney',
    ];

    /** Системы налогообложения → подпись и типовая ставка (подставляется в поле «Налог, %», остаётся редактируемой). */
    public const TAX_SYSTEMS = [
        'usn_income'          => ['УСН «Доходы»', 6],
        'usn_income_expense'  => ['УСН «Доходы минус расходы»', 15],
        'ausn_income'         => ['АУСН «Доходы»', 8],
        'ausn_income_expense' => ['АУСН «Доходы минус расходы» (не менее 3% от доходов)', 20],
        'npd'                 => ['НПД (самозанятый)', 6],
        'osn'                 => ['ОСН', 0],
    ];

    /** Тариф ЮKassa (оператор + агрегатор): карты/кошельки 2,8+1 %, СБП 0,7+1,5 %. Минимумы/максимумы не учитываются. */
    public const DEFAULT_FEE = ['bank_card' => 3.8, 'sbp' => 2.2, 'sberbank' => 3.8, 'tinkoff_bank' => 3.8, 'yoo_money' => 3.8];

    /** @return array<string,float> способ => % (только предлагаемые, > 0) */
    public static function methodPercents(?object $s, string $prefix = ''): array
    {
        if (!$s) {
            return [];
        }
        $raw = $s->{$prefix . 'fee_methods'} ?? null;
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        if (!is_array($raw)) {
            // Старые настройки без таблицы способов: единый процент для всех.
            $flat = (float) ($s->{$prefix . 'fee_percent'} ?? 0);
            return $flat > 0 ? array_fill_keys(array_keys(self::METHODS), $flat) : [];
        }
        $out = [];
        foreach (self::METHODS as $k => $_) {
            if (isset($raw[$k]) && (float) $raw[$k] > 0) {
                $out[$k] = (float) $raw[$k];
            }
        }
        return $out;
    }

    /** Комиссия «сверху» и есть способы с процентом → плательщик должен выбрать способ до создания платежа. */
    public static function needsChoice(?object $s, string $prefix = ''): bool
    {
        return $s && ($s->{$prefix . 'fee_mode'} ?? 'included') === 'on_top' && self::methodPercents($s, $prefix) !== [];
    }

    /** @return array{total:int, fee:int, tax:int} минорные единицы; fee/tax — только надбавки «сверху» */
    public static function apply(int $baseMinor, ?object $s, string $prefix = '', ?string $method = null): array
    {
        $fee = 0;
        if (self::needsChoice($s, $prefix) && $method !== null) {
            $pct = self::methodPercents($s, $prefix)[$method] ?? 0;
            $fee = (int) round($baseMinor * $pct / 100);
        }

        $tax = 0;
        if ($s && ($s->{$prefix . 'tax_mode'} ?? 'included') === 'on_top') {
            $tax = (int) round($baseMinor * (float) ($s->{$prefix . 'tax_percent'} ?? 0) / 100);
        }

        return ['total' => $baseMinor + $fee + $tax, 'fee' => $fee, 'tax' => $tax];
    }

    public static function rules(string $prefix = ''): array
    {
        $r = [
            $prefix . 'fee_mode'    => ['nullable', 'in:included,on_top'],
            $prefix . 'tax_percent' => ['nullable', 'numeric', 'min:0', 'max:50'],
            $prefix . 'tax_mode'    => ['nullable', 'in:included,on_top'],
            $prefix . 'tax_system'  => ['nullable', 'in:' . implode(',', array_keys(self::TAX_SYSTEMS))],
        ];
        foreach (self::METHODS as $k => $_) {
            $r[$prefix . 'fee_m_' . $k] = ['nullable', 'numeric', 'min:0', 'max:50'];
        }
        return $r;
    }

    public static function fromRequest(array $data, string $prefix = ''): array
    {
        $methods = [];
        foreach (self::METHODS as $k => $_) {
            $v = $data[$prefix . 'fee_m_' . $k] ?? null;
            if ($v !== null && $v !== '' && (float) $v > 0) {
                $methods[$k] = round((float) $v, 2);
            }
        }

        return [
            $prefix . 'fee_methods' => $methods === [] ? null : $methods,
            $prefix . 'fee_percent' => $methods === [] ? 0 : max($methods),
            $prefix . 'fee_mode'    => ($data[$prefix . 'fee_mode'] ?? 'included') === 'on_top' ? 'on_top' : 'included',
            $prefix . 'tax_percent' => round((float) ($data[$prefix . 'tax_percent'] ?? 0), 2),
            $prefix . 'tax_mode'    => ($data[$prefix . 'tax_mode'] ?? 'included') === 'on_top' ? 'on_top' : 'included',
            $prefix . 'tax_system'  => isset(self::TAX_SYSTEMS[$data[$prefix . 'tax_system'] ?? '']) ? $data[$prefix . 'tax_system'] : null,
        ];
    }
}
