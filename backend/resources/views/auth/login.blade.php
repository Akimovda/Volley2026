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
        Войдите через Telegram или VK ID.
    </p>

    <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
        {{-- VK ID --}}
        <a href="{{ route('auth.vk.redirect') }}"
           style="display:inline-block; padding:12px 16px; border:1px solid #ddd; border-radius:10px; text-decoration:none;">
            Войти через VK ID
        </a>

        {{-- Telegram Widget --}}
        <div>
            <script async src="https://telegram.org/js/telegram-widget.js?22"
                    data-telegram-login="{{ config('services.telegram.bot_name') }}"
                    data-size="large"
                    data-auth-url="{{ route('telegram.callback') }}"
                    data-request-access="write">
            </script>
        </div>
    </div>

    <p style="margin-top:24px;">
        <a href="/events">Перейти к мероприятиям</a>
    </p>
</body>
</html>
