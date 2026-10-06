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

        $events = $this->events->getEvents($widget, $userId);
        $style  = $this->styleFor($widget);
        $css    = WidgetStyleService::css($style);

        return response()
            ->view('widget.iframe', compact('widget', 'events', 'userId', 'style', 'css'))
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

        $events = $this->events->getEvents($widget, $widget->user_id);
        $style  = $this->styleFor($widget);
        $css    = WidgetStyleService::css($style);

        return response()->json([
            'ok'       => true,
            'settings' => $widget->settings,
            'events'   => $events,
            'html'     => view('widget._content', compact('events', 'style', 'css'))->render(),
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

    fetch({$urlJs} + '?key=' + encodeURIComponent(key))
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

    private function styleFor(OrganizerWidget $widget): array
    {
        return WidgetStyleService::resolve(
            (array) ($widget->settings['style'] ?? []) + [
                'show_slots'    => (bool) $widget->getSetting('show_slots', true),
                'show_location' => (bool) $widget->getSetting('show_location', true),
            ],
            (string) $widget->getSetting('color', '#f59e0b')
        );
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
