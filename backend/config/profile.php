<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Profile Section → Required Fields Map
    |--------------------------------------------------------------------------
    |
    | Используется в ProfileCompletionController для генерации required полей
    | по параметру ?section=
    |
    */

    'sections' => [

        'personal' => [
            'full_name',
            'patronymic',
            'phone',
            'city',
            'birth_date',
        ],

        'classic' => [
            'classic_level',
        ],

        'beach' => [
            'beach_level',
        ],

    ],

];
