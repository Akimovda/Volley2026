@include('errors._standalone', [
    'icon' => '🛠️', 'refresh' => 30,
    'ru' => ['title' => 'Технические работы', 'h1' => 'Скоро вернёмся',
        'text' => 'Сейчас на сайте идут технические работы или он временно перегружен. Это ненадолго — страница обновится сама через полминуты, либо нажмите «Обновить».',
        'reload' => '🔄 Обновить', 'back' => 'Назад', 'home' => 'На главную', 'code' => 'Ошибка 503'],
    'en' => ['title' => 'Maintenance', 'h1' => 'We will be right back',
        'text' => 'The site is under maintenance or temporarily overloaded. It will not take long — the page refreshes itself in half a minute, or press "Refresh".',
        'reload' => '🔄 Refresh', 'back' => 'Back', 'home' => 'Home', 'code' => 'Error 503'],
])
