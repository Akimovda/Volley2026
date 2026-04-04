<?php
// config/volleyball.php
return [

    'classic' => [

        '4x4' => [
            'players_per_team' => 4,
            'positions' => [
                'setter' => 1,
                'outside' => 2,
                'opposite' => 1,
            ]
        ],

        '4x2' => [
            'players_per_team' => 6,
            'positions' => [
                'setter' => 2,
                'outside' => 4,
            ]
        ],

        '5x1' => [
            'players_per_team' => 6,
            'positions' => [
                'setter' => 1,
                'outside' => 2,
                'opposite' => 1,
                'middle' => 2,
            ]
        ],

        '5x1_libero' => [
            'players_per_team' => 7,
            'positions' => [
                'setter' => 1,
                'outside' => 2,
                'opposite' => 1,
                'middle' => 2,
                'libero' => 1,
            ]
        ],

    ],

    'beach' => [

        '2x2' => [
            'players_per_team' => 2,
            'positions' => []
        ],

        '3x3' => [
            'players_per_team' => 3,
            'positions' => []
        ],

        '4x4' => [
            'players_per_team' => 4,
            'positions' => []
        ],

    ]

];
