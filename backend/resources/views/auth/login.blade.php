{{-- resources/views/auth/login.blade.php --}}
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Вход</title>
</head>
<body style="font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial; padding: 24px;">
    <h1 style="margin: 0 0 16px;">Вход</h1>

    <p style="margin: 0 0 24px; color:#555;">
        Войдите через Telegram, VK ID или Яндекс.
    </p>

    <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
        {{-- VK ID --}}
        <a href="{{ route('auth.vk.redirect') }}"
           style="display:inline-block; padding:12px 16px; border:1px solid #ddd; border-radius:10px; text-decoration:none;">
            Войти через VK ID
        </a>

        {{-- Telegram (через экран с Telegram Login Widget) --}}
        <a href="{{ route('auth.telegram.redirect') }}"
           style="display:inline-block; padding:12px 16px; border:1px solid #ddd; border-radius:10px; text-decoration:none;">
            Войти через Telegram
        </a>

        {{-- Yandex --}}
        <a href="{{ route('auth.yandex.redirect') }}"
           style="display:inline-block; padding:12px 16px; border:1px solid #ddd; border-radius:10px; text-decoration:none;">
            Войти через Яндекс
        </a>
    </div>

    <p style="margin-top:24px;">
        <a href="{{ url('/events') }}">Перейти к мероприятиям</a>
    </p>
</body>
</html>
