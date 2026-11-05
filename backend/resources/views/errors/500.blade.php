@include('errors._standalone', [
    'icon' => '🏐', 'refresh' => 0,
    'ru' => ['title' => 'Что-то пошло не так', 'h1' => 'Ой, что-то пошло не так',
        'text' => 'Мы уже получили уведомление об ошибке и разберёмся. Попробуйте обновить страницу или вернуться назад — чаще всего этого достаточно. Если ошибка повторяется, попробуйте чуть позже.',
        'reload' => '🔄 Обновить', 'back' => 'Назад', 'home' => 'На главную', 'code' => 'Ошибка 500'],
    'en' => ['title' => 'Something went wrong', 'h1' => 'Oops, something went wrong',
        'text' => 'We have been notified about the error and will look into it. Try refreshing the page or going back — that is usually enough. If the error keeps happening, please try again a bit later.',
        'reload' => '🔄 Refresh', 'back' => 'Back', 'home' => 'Home', 'code' => 'Error 500'],
])
