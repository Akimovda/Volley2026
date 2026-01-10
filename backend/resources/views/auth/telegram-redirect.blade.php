<x-guest-layout>
    <div class="min-h-screen flex items-center justify-center">
        <div class="p-6 bg-white shadow rounded w-full max-w-md text-center">
            <h1 class="text-xl font-semibold mb-2">Авторизация Telegram</h1>
            <p class="text-gray-600 mb-6">Нажмите кнопку и подтвердите вход в Telegram.</p>

            <script async src="https://telegram.org/js/telegram-widget.js?22"
                    data-telegram-login="{{ $botName }}"
                    data-size="large"
                    data-auth-url="{{ $authUrl }}"
                    data-request-access="write"></script>

            <div class="mt-6 text-sm text-gray-500">
                После авторизации вы вернётесь обратно на сайт.
            </div>
        </div>
    </div>
</x-guest-layout>
