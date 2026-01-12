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

    {{-- Flash messages --}}
    @if (session('status'))
        <div style="margin: 0 0 16px; padding: 12px 14px; border: 1px solid #cfe9d8; background: #f2fbf5; border-radius: 10px; color:#1f6b3a;">
            {{ session('status') }}
        </div>
    @endif

    @if (session('error'))
        <div style="margin: 0 0 16px; padding: 12px 14px; border: 1px solid #f3c7c7; background: #fff3f3; border-radius: 10px; color:#8a1f1f;">
            {{ session('error') }}
        </div>
    @endif

    <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:center; margin-bottom:16px;">
        {{-- VK ID --}}
        <a href="{{ route('auth.vk.redirect') }}"
           style="display:inline-block; padding:12px 16px; border:1px solid #ddd; border-radius:10px; text-decoration:none;">
            Войти через VK ID
        </a>

        {{-- Yandex --}}
        <a href="{{ route('auth.yandex.redirect') }}"
           style="display:inline-block; padding:12px 16px; border:1px solid #ddd; border-radius:10px; text-decoration:none;">
            Войти через Яндекс
        </a>
    </div>

    @php
        // Telegram Login Widget требует username бота (без @)
        $tgBotUsername = config('services.telegram.bot_username') ?: env('TELEGRAM_BOT_USERNAME');
        // Telegram дергает auth_url с клиента — лучше абсолютный URL
        $tgAuthUrl = route('auth.telegram.callback', absolute: true);
    @endphp

    {{-- Telegram Login Widget --}}
    <div style="margin: 8px 0 0;">
        <div style="margin:0 0 8px; color:#555;">Войти через Telegram:</div>

        @if(empty($tgBotUsername))
            <div style="margin: 0 0 16px; padding: 12px 14px; border: 1px solid #f3c7c7; background: #fff3f3; border-radius: 10px; color:#8a1f1f;">
                Не задан TELEGRAM_BOT_USERNAME (или <code>services.telegram.bot_username</code>).<br>
                Telegram Widget без него покажет “Bot username required”.
            </div>
        @else
            <script
                async
                src="https://telegram.org/js/telegram-widget.js?22"
                data-telegram-login="{{ $tgBotUsername }}"
                data-size="large"
                data-radius="10"
                data-userpic="true"
                data-auth-url="{{ $tgAuthUrl }}"
                data-request-access="write"></script>

            <div style="margin-top:8px; font-size: 13px; color:#777;">
                Если виджет не отображается — проверьте, что сайт открывается по HTTPS, и домен добавлен у бота в BotFather.
            </div>
        @endif
    </div>

    <p style="margin-top:24px;">
        <a href="{{ url('/events') }}">Перейти к мероприятиям</a>
    </p>
</body>
</html>
