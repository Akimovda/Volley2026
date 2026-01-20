{{-- resources/views/auth/vk-widget.blade.php --}}
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Вход через VK</title>
</head>
<body style="font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial; padding: 24px;">
    <h1 style="margin: 0 0 12px;">Вход через VK</h1>
    <p style="margin: 0 0 18px; color:#555;">
        Нажмите кнопку VK ID ниже.
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

    <div id="vkid-container" style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;"></div>

    <div id="vkid-error" style="margin-top: 12px; font-size: 13px; color:#8a1f1f;"></div>

    <p style="margin-top:24px;">
        <a href="{{ route('login') }}">← Назад на страницу входа</a>
    </p>

    {{-- VK ID OneTap --}}
    <script src="https://unpkg.com/@vkid/sdk@<3.0.0/dist-sdk/umd/index.js"></script>
    <script type="text/javascript">
        (function () {
            const errEl = document.getElementById('vkid-error');

            function showError(msg) {
                errEl.textContent = msg || 'VKID error';
            }

            if (!('VKIDSDK' in window)) {
                showError('VKIDSDK не загрузился (проверьте доступ к unpkg и CSP).');
                return;
            }

            const VKID = window.VKIDSDK;

            // Важно: app ID и redirectUrl берём из config/services.php, прокинуты из контроллера
            VKID.Config.init({
                app: {{ (int) $vkAppId }},
                redirectUrl: @json($vkRedirectUrl),
                responseMode: VKID.ConfigResponseMode.Callback,
                source: VKID.ConfigSource.LOWCODE,
                scope: @json($vkScope ?? ''), // обычно '' или 'email'
            });

            const oneTap = new VKID.OneTap();

            oneTap.render({
                container: document.getElementById('vkid-container'),
                showAlternativeLogin: true
            })
            .on(VKID.WidgetEvents.ERROR, function (e) {
                showError('Ошибка VK виджета: ' + (e?.message || JSON.stringify(e)));
            })
            .on(VKID.OneTapInternalEvents.LOGIN_SUCCESS, function (payload) {
                try {
                    const code = payload.code;
                    const deviceId = payload.device_id;

                    // Режим Callback: дальше надо отдать code+device_id нашему backend callback,
                    // чтобы он обменял на access_token и получил профиль.
                    const url = new URL(@json($vkRedirectUrl));
                    url.searchParams.set('code', code);
                    url.searchParams.set('device_id', deviceId);

                    window.location.href = url.toString();
                } catch (e) {
                    showError('Не удалось обработать ответ VK: ' + (e?.message || e));
                }
            });
        })();
    </script>
</body>
</html>
