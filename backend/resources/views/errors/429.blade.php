@include('errors._standalone', [
    'icon' => '⏳', 'refresh' => 0,
    'ru' => ['title' => 'Слишком много запросов', 'h1' => 'Притормозите на минуту',
        'text' => 'Вы слишком часто обращались к сайту, поэтому мы ненадолго приостановили запросы. Подождите около минуты и попробуйте снова.',
        'reload' => '🔄 Попробовать снова', 'back' => 'Назад', 'home' => 'На главную', 'code' => 'Ошибка 429'],
    'en' => ['title' => 'Too many requests', 'h1' => 'Slow down for a minute',
        'text' => 'You made too many requests, so we paused them for a short while. Wait about a minute and try again.',
        'reload' => '🔄 Try again', 'back' => 'Back', 'home' => 'Home', 'code' => 'Error 429'],
])
