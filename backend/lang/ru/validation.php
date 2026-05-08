<?php

return [
    'required' => 'Поле :attribute обязательно.',
    'string' => 'Поле :attribute должно быть строкой.',
    'max' => [
        'string' => 'Поле :attribute не должно превышать :max символов.',
        'numeric' => 'Поле :attribute не должно быть больше :max.',
    ],
    'integer' => 'Поле :attribute должно быть числом.',
    'boolean' => 'Поле :attribute должно быть логическим значением.',
    'in' => 'Выбранное значение для :attribute некорректно.',
    'date_format' => 'Поле :attribute имеет неверный формат.',
    'exists' => 'Выбранное значение для :attribute некорректно.',
    'array' => 'Поле :attribute должно быть массивом.',

    'attributes' => [
        'title' => 'Название',
        'direction' => 'Направление',
        'format' => 'Тип мероприятия',
        'allow_registration' => 'Регистрация через сервис',
        'timezone' => 'Часовой пояс',
        'starts_at_local' => 'Начало',
        'location_id' => 'Локация',
        'game_subtype' => 'Подтип игры',
        'game_max_players' => 'Макс. участников',
        'game_girls_max' => 'Макс. девушек',
        'template_name' => 'Название шаблона',
    ],
];
