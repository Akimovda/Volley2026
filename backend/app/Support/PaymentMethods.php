<?php

namespace App\Support;

/** Способы оплаты, которые можно предлагать игроку на выбор. */
class PaymentMethods
{
    public const LABELS = [
        'cash'       => '💵 Наличными',
        'tbank_link' => '🏦 Т-Банк (перевод по ссылке)',
        'sber_link'  => '💚 Сбер (перевод по ссылке)',
        'yoomoney'   => '🟡 Онлайн: карта, СБП (ЮKassa)',
    ];

    /** Пересечение отмеченных способов с реально настроенными; меньше двух — выбора нет (пустой массив). */
    public static function offered(bool $enabled, ?array $ticked, array $available): array
    {
        if (!$enabled) {
            return [];
        }
        $out = array_values(array_filter(
            array_keys(self::LABELS),
            fn ($k) => in_array($k, $ticked ?? [], true) && in_array($k, $available, true)
        ));

        return count($out) >= 2 ? $out : [];
    }

    /** [['key'=>..., 'label'=>...], ...] для JS-попапа. */
    public static function forJs(array $keys): array
    {
        return array_map(fn ($k) => ['key' => $k, 'label' => self::LABELS[$k]], $keys);
    }
}
