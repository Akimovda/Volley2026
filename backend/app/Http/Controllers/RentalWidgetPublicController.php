<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\RentalWidget;
use App\Services\RentalWidgetService;
use App\Services\WidgetStyleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Публичные адреса виджета аренды: iframe, JSON, JS-скрипт. */
class RentalWidgetPublicController extends Controller
{
    private const KIND = 'rental';

    public function __construct(private RentalWidgetService $service)
    {
    }

    public function iframe(Request $request, int $userId): Response
    {
        $widget = RentalWidget::where('user_id', $userId)
            ->where('api_key', (string) $request->query('key', ''))
            ->where('is_active', true)
            ->first();

        if (!$widget) {
            return response('Виджет недоступен.', 403);
        }

        if (!$this->ownerAllowed($widget)) {
            return response()
                ->view('widget.unavailable', ['message' => __('profile.widget_subscription_inactive')])
                ->header('X-Frame-Options', 'ALLOWALL')
                ->header('Content-Security-Policy', 'frame-ancestors *');
        }

        $style     = $this->styleFor($widget, $request);
        $locations = $this->service->getLocations($widget, $userId);

        return response()
            ->view('widget.rental_iframe', compact('locations', 'style'))
            ->header('X-Frame-Options', 'ALLOWALL')
            ->header('Content-Security-Policy', 'frame-ancestors *');
    }

    public function json(Request $request): JsonResponse
    {
        $widget = RentalWidget::where('api_key', (string) $request->query('key', ''))->where('is_active', true)->first();

        if (!$widget) {
            return response()->json(['ok' => false, 'error' => 'Invalid key'], 403);
        }

        $referer = $request->header('Referer', '');
        if ($referer && !empty($widget->allowed_domains)) {
            $domain = parse_url($referer, PHP_URL_HOST) ?? '';
            if (!$widget->allowsDomain($domain)) {
                return response()->json(['ok' => false, 'error' => 'Domain not allowed'], 403);
            }
        }

        if (!$this->ownerAllowed($widget)) {
            return response()->json([
                'ok' => false, 'code' => 'subscription_inactive', 'error' => __('profile.widget_subscription_inactive'),
            ], 403)->header('Access-Control-Allow-Origin', '*');
        }

        $style     = $this->styleFor($widget, $request);
        $locations = $this->service->getLocations($widget, $widget->user_id);

        return response()->json([
            'ok'        => true,
            'locations' => $locations,
            'html'      => view('widget.rental._content', compact('locations', 'style'))->render(),
        ])->header('Access-Control-Allow-Origin', '*');
    }

    /** JS-скрипт: разметку и CSS отдаёт сервер, рисуем в shadow-root (стили чужого сайта не мешают). */
    public function script(Request $request): Response
    {
        $keyJs = json_encode((string) $request->query('key', ''), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
        $urlJs = json_encode(route('rental_widget.json'), JSON_UNESCAPED_SLASHES);

        $js = <<<JS
(function() {
    var key = {$keyJs};
    var container = document.getElementById('volley-rental-widget');
    if (!container) { console.warn('volley-rental-widget: #volley-rental-widget not found'); return; }

    var root = container.attachShadow ? (container.shadowRoot || container.attachShadow({mode: 'open'})) : container;
    function note(text, color) {
        var d = document.createElement('div');
        d.style.cssText = 'font-family:sans-serif;padding:12px;font-size:14px;color:' + color;
        d.textContent = text;
        root.innerHTML = '';
        root.appendChild(d);
    }
    note('Загрузка...', '#666');

    var q = 'key=' + encodeURIComponent(key);
    ['layout', 'columns', 'theme', 'accent', 'lang', 'header'].forEach(function(n) {
        if (container.dataset[n]) q += '&' + n + '=' + encodeURIComponent(container.dataset[n]);
    });

    fetch({$urlJs} + '?' + q)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.ok) { note(data.error || 'unavailable', data.code === 'subscription_inactive' ? '#888' : 'red'); return; }
            root.innerHTML = data.html;
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

    private function styleFor(RentalWidget $widget, Request $request): array
    {
        $style = WidgetStyleService::resolve(
            (array) ($widget->settings['style'] ?? []),
            (string) $widget->getSetting('color', '#f59e0b'),
            self::KIND
        );
        $style = WidgetStyleService::forRender($style, $request->only(['layout', 'columns', 'theme', 'accent', 'lang', 'header']));

        if (in_array($style['lang'], ['ru', 'en'], true)) {
            app()->setLocale($style['lang']);
        }

        return $style;
    }

    /** Виджет работает, пока у владельца активна подписка Организатор Pro и он по-прежнему управляет клубом. */
    private function ownerAllowed(RentalWidget $widget): bool
    {
        $u = $widget->user;

        return (bool) ($u && $u->isOrganizerPro() && $u->is_club_manager);
    }
}
