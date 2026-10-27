<?php

namespace App\Services;

use App\Models\Event;
use App\Models\TournamentStage;

/**
 * Смена сторон и технический тайм-аут в пляжном волейболе (B5). Классика — без изменений (правила не возвращаются).
 * Функции чистые: сигнал считается по текущему счёту партии, показывает его клиент (пульт/оверлей), дедуплицируя по `key`.
 */
class SideSwitchService
{
    public const DEFAULT_EVERY = 7;          // партии 1–2 (и одна партия bo1)
    public const DEFAULT_EVERY_DECIDING = 5; // решающая
    public const TECH_TIMEOUT_AT = 21;       // сумма очков в партиях 1–2

    /** Правила из формата матча (config стадии); null — не пляжка. */
    public static function rules(?TournamentStage $stage, ?Event $event = null): ?array
    {
        $event ??= $stage?->event;
        if (($event?->direction ?? null) !== 'beach') {
            return null;
        }

        return [
            'every'          => max(2, (int) ($stage?->configValue('side_switch_every', self::DEFAULT_EVERY) ?: self::DEFAULT_EVERY)),
            'every_deciding' => max(2, (int) ($stage?->configValue('side_switch_deciding', self::DEFAULT_EVERY_DECIDING) ?: self::DEFAULT_EVERY_DECIDING)),
            'tech_timeout'   => (bool) ($stage?->configValue('tech_timeout', false)),
            'tech_at'        => self::TECH_TIMEOUT_AT,
        ];
    }

    /**
     * @return array{key:string,switch:bool,timeout:bool,total:int,set:int}|null  null — на этом счёте ничего не происходит
     */
    public static function signal(?array $rules, int $matchId, int $setNumber, int $home, int $away, bool $deciding): ?array
    {
        $total = $home + $away;
        if (!$rules || $total <= 0) {
            return null;
        }

        $every   = $deciding ? $rules['every_deciding'] : $rules['every'];
        $switch  = $total % $every === 0;
        $timeout = $rules['tech_timeout'] && !$deciding && $setNumber <= 2 && $total === $rules['tech_at'];
        if (!$switch && !$timeout) {
            return null;
        }

        return ['key' => "m{$matchId}:s{$setNumber}:{$total}", 'switch' => $switch, 'timeout' => $timeout, 'total' => $total, 'set' => $setNumber];
    }
}
