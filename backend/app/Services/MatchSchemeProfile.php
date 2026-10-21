<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventTournamentSetting;
use App\Models\TournamentMatch;
use App\Models\TournamentStage;

/**
 * «Профиль схемы матча»: размер основы, есть ли либеро, ведётся ли учёт состава.
 * Единая точка правды вместо private resolveScheme/requiresLibero в TournamentTeamService.
 */
class MatchSchemeProfile
{
    /** Позиции, на которых игрок стоит на площадке (libero — отдельно, без зоны). */
    public const COURT_POSITIONS = ['setter', 'outside', 'opposite', 'middle'];

    /**
     * Схема классики: из настроек турнира, а для обычной командной игры
     * (без event_tournament_settings) — из event_game_settings (5x1 + with_libero → 5x1_libero).
     */
    public static function resolveScheme(Event $event, ?EventTournamentSetting $settings): string
    {
        if ($settings) {
            return $settings->getGameScheme();
        }

        $gs = $event->gameSettings;
        $subtype = (string) ($gs?->subtype ?? '5x1');

        if ($subtype === '5x1' && (string) ($gs?->libero_mode ?? '') === 'with_libero') {
            return '5x1_libero';
        }

        return $subtype;
    }

    public static function requiresLibero(?EventTournamentSetting $settings, string $scheme): bool
    {
        return (bool) ($settings?->require_libero) || $scheme === '5x1_libero';
    }

    /**
     * @return array{scheme:string,is_beach:bool,friendly:bool,toggle:bool,enabled:bool,on_court_size:int,has_libero:bool,positions:array<int,string>}
     *   enabled — учёт состава реально включён (переключатель И классика); иначе поведение «как раньше».
     */
    public static function forMatch(TournamentMatch $match): array
    {
        $stage = $match->relationLoaded('stage') ? $match->stage : $match->stage()->first();

        return self::forEvent($stage->event, $stage->type === TournamentStage::TYPE_FRIENDLY);
    }

    public static function forEvent(Event $event, bool $friendly = false): array
    {
        $settings = $event->tournamentSetting;
        $scheme = self::resolveScheme($event, $settings);

        $isBeach = $event->direction === 'beach';
        $dir = $isBeach ? 'beach' : 'classic';
        $cfg = config("volleyball.$dir.$scheme") ?? config("volleyball.classic.$scheme");

        $hasLibero = !$isBeach && self::requiresLibero($settings, $scheme);
        $perTeam = (int) ($cfg['players_per_team'] ?? 0);
        // 5x1_libero в конфиге = 6 основы + 1 либеро; для require_libero с другой схемой либеро сверх основы
        $onCourt = $scheme === '5x1_libero' ? max(0, $perTeam - 1) : $perTeam;

        $toggle = $friendly
            ? (bool) ($event->gameSettings?->track_lineup)
            : (bool) ($settings?->track_lineup);

        $positions = array_values(array_intersect(
            array_keys($cfg['positions'] ?? []),
            self::COURT_POSITIONS
        )) ?: self::COURT_POSITIONS;

        return [
            'scheme'        => $scheme,
            'is_beach'      => $isBeach,
            'friendly'      => $friendly,
            'toggle'        => $toggle,
            'enabled'       => $toggle && !$isBeach && $onCourt > 0,
            'on_court_size' => $onCourt,
            'has_libero'    => $hasLibero,
            'positions'     => $positions,
        ];
    }
}
