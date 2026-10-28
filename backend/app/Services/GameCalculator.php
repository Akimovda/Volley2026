<?php

namespace App\Services;

/**
 * Роли и размер команды по схеме. Источник — config/volleyball.php (одна копия):
 *  - classic.*: players_per_team + positions (роли на команду);
 *  - beach.*: players_per_team; роль одна — 'player' (ключ пляжной 4x4 — '4x4_beach', чтобы не пересекаться с классической);
 *  - beach.2x2.positions_by_roles: режим «По амплуа» (blocker + defender по одному на пару).
 */
class GameCalculator
{
    public static function config(): array
    {
        $cfg = (array) config('volleyball', []);
        $out = [];

        foreach ((array) ($cfg['classic'] ?? []) as $key => $scheme) {
            $out[$key] = [
                'team_size' => (int) $scheme['players_per_team'],
                'roles'     => (array) $scheme['positions'],
            ];
        }

        foreach ((array) ($cfg['beach'] ?? []) as $key => $scheme) {
            $size = (int) $scheme['players_per_team'];
            $out[$key === '4x4' ? '4x4_beach' : $key] = [
                'team_size' => $size,
                'roles'     => !empty($scheme['positions']) ? (array) $scheme['positions'] : ['player' => $size],
            ];
        }

        return $out;
    }

    /** Роли пляжной схемы в режиме «По амплуа» (null — режима для схемы нет). */
    public static function beachRoles(string $subtype): ?array
    {
        $roles = config("volleyball.beach.{$subtype}.positions_by_roles");

        return is_array($roles) && $roles ? $roles : null;
    }

    public static function calculate(string $subtype, ?string $liberoMode, int $teams = 2, bool $beachRoles = false): array
    {
        $config = self::config();

        if ($subtype === '5x1' && $liberoMode === 'with_libero') {
            $subtype = '5x1_libero';
        }

        if (!isset($config[$subtype])) {
            return [
                'team_size' => 0,
                'max_players' => 0,
                'roles' => [],
            ];
        }

        $teamSize = $config[$subtype]['team_size'];
        $roles = $config[$subtype]['roles'];

        if ($beachRoles && ($byRoles = self::beachRoles($subtype))) {
            $roles = $byRoles;
        }

        return [
            'team_size' => $teamSize,
            'max_players' => $teamSize * $teams,
            'roles' => $roles,
        ];
    }
}
