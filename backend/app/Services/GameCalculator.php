<?php

namespace App\Services;

class GameCalculator
{
    public static function config(): array
    {
        return [

            // CLASSIC
            '4x4' => [
                'team_size' => 4,
                'roles' => [
                    'setter' => 1,
                    'outside' => 2,
                    'opposite' => 1,
                ]
            ],

            '4x2' => [
                'team_size' => 6,
                'roles' => [
                    'setter' => 2,
                    'outside' => 4,
                ]
            ],

            '5x1' => [
                'team_size' => 6,
                'roles' => [
                    'setter' => 1,
                    'outside' => 2,
                    'opposite' => 1,
                    'middle' => 2,
                ]
            ],

            '5x1_libero' => [
                'team_size' => 7,
                'roles' => [
                    'setter' => 1,
                    'outside' => 2,
                    'opposite' => 1,
                    'middle' => 2,
                    'libero' => 1,
                ]
            ],

            // BEACH
            '2x2' => [
                'team_size' => 2,
                'roles' => [
                    'player' => 2,
                ]
            ],

            '3x3' => [
                'team_size' => 3,
                'roles' => [
                    'player' => 3,
                ]
            ],

            '4x4_beach' => [
                'team_size' => 4,
                'roles' => [
                    'player' => 4,
                ]
            ],
        ];
    }

    public static function calculate(string $subtype, ?string $liberoMode, int $teams = 2): array
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

        return [
            'team_size' => $teamSize,
            'max_players' => $teamSize * $teams,
            'roles' => $config[$subtype]['roles'],
        ];
    }
}
