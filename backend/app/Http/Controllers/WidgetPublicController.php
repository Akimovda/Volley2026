<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\OrganizerWidget;
use App\Services\WidgetEventsService;
use App\Services\WidgetStyleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WidgetPublicController extends Controller
{
    public function __construct(private WidgetEventsService $events)
    {
    }

    /** iFrame страница */
    public function iframe(Request $request, int $userId): Response
    {
        $widget = $this->resolveWidget($request, $userId);

        if (!$widget) {
            return response('Виджет недоступен.', 403);
        }

        // Виджет — функция Организатор Pro: без активной подписки владельца показываем сообщение
        if (!$this->ownerHasPro($widget)) {
            return response()
                ->view('widget.unavailable', ['message' => __('profile.widget_subscription_inactive')])
                ->header('X-Frame-Options', 'ALLOWALL')
                ->header('Content-Security-Policy', "frame-ancestors *");
        }

        $style  = $this->styleFor($widget, $request);
        $events = $this->events->getEvents($widget, $userId);

        return response()
            ->view('widget.iframe', compact('widget', 'events', 'userId', 'style'))
            ->header('X-Frame-Options', 'ALLOWALL')
            ->header('Content-Security-Policy', "frame-ancestors *");
    }

    /** JSON API для JS-виджета */
    public function json(Request $request): JsonResponse
    {
        $key    = $request->query('key', '');
        $widget = OrganizerWidget::where('api_key', $key)->where('is_active', true)->first();

        if (!$widget) {
            return response()->json(['ok' => false, 'error' => 'Invalid key'], 403);
        }

        // Проверка домена
        $referer = $request->header('Referer', '');
        if ($referer && !empty($widget->allowed_domains)) {
            $domain = parse_url($referer, PHP_URL_HOST) ?? '';
            if (!$widget->allowsDomain($domain)) {
                return response()->json(['ok' => false, 'error' => 'Domain not allowed'], 403);
            }
        }

        if (!$this->ownerHasPro($widget)) {
            return response()->json([
                'ok'      => false,
                'code'    => 'subscription_inactive',
                'error'   => __('profile.widget_subscription_inactive'),
            ], 403)->header('Access-Control-Allow-Origin', '*');
        }

        $style  = $this->styleFor($widget, $request);
        $events = $this->events->getEvents($widget, $widget->user_id);

        return response()->json([
            'ok'       => true,
            'settings' => $widget->settings,
            'events'   => $events,
            'html'     => view('widget._content', compact('events', 'style'))->render(),
        ])->header('Access-Control-Allow-Origin', '*');
    }

    /** JS-скрипт виджета: разметку и CSS отдаёт сервер, рисуем в shadow-root (стили чужого сайта не мешают) */
    public function script(Request $request): Response
    {
        $keyJs     = json_encode((string) $request->query('key', ''), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
        $urlJs     = json_encode(route('widget.json'), JSON_UNESCAPED_SLASHES);

        $js = <<<JS
(function() {
    var key = {$keyJs};
    var container = document.getElementById('volley-widget');
    if (!container) { console.warn('volley-widget: #volley-widget not found'); return; }

    var root = container.attachShadow ? (container.shadowRoot || container.attachShadow({mode: 'open'})) : container;
    function show(html) { root.innerHTML = html; }
    function note(text, color) {
        var d = document.createElement('div');
        d.style.cssText = 'font-family:sans-serif;padding:12px;font-size:14px;color:' + color;
        d.textContent = text;
        root.innerHTML = '';
        root.appendChild(d);
    }

    note('Загрузка...', '#666');

    // data-атрибуты контейнера переопределяют серверные настройки
    var q = 'key=' + encodeURIComponent(key);
    ['layout', 'columns', 'theme', 'accent', 'limit'].forEach(function(n) {
        if (container.dataset[n]) q += '&' + n + '=' + encodeURIComponent(container.dataset[n]);
    });

    fetch({$urlJs} + '?' + q)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.ok) {
                note(data.code === 'subscription_inactive' ? data.error : 'Ошибка: ' + (data.error || 'unavailable'),
                     data.code === 'subscription_inactive' ? '#888' : 'red');
                return;
            }
            show(data.html);
        })
        .catch(function() { note('Ошибка загрузки.', 'red'); });
})();
JS;

        return response($js, 200, [
            'Content-Type'                => 'application/javascript',
            'Cache-Control'               => 'public, max-age=60',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    /**
     * Итоговое оформление. Переопределения из data-атрибутов контейнера (layout, columns, theme, accent, limit)
     * приходят query-параметрами; limit применяется только в памяти (виджет не сохраняется).
     */
    private function styleFor(OrganizerWidget $widget, Request $request): array
    {
        $limit = (int) $request->query('limit', 0);
        if ($limit > 0) {
            $widget->settings = array_merge((array) $widget->settings, ['limit' => min(50, $limit)]);
        }

        $style = WidgetStyleService::resolve(
            (array) ($widget->settings['style'] ?? []) + [
                'show_slots'    => (bool) $widget->getSetting('show_slots', true),
                'show_location' => (bool) $widget->getSetting('show_location', true),
            ],
            (string) $widget->getSetting('color', '#f59e0b')
        );

        return WidgetStyleService::forRender($style, $request->only(['layout', 'columns', 'theme', 'accent']));
    }

    /** Активен ли Организатор Pro у владельца виджета (ключ и настройки при этом не трогаем) */
    private function ownerHasPro(OrganizerWidget $widget): bool
    {
        return (bool) $widget->user?->isOrganizerPro();
    }

    private function resolveWidget(Request $request, int $userId): ?OrganizerWidget
    {
        $key    = $request->query('key', '');
        $widget = OrganizerWidget::where('user_id', $userId)
            ->where('api_key', $key)
            ->where('is_active', true)
            ->first();

        return $widget;
    }
}
