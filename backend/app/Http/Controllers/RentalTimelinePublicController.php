<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\RentalWidget;
use App\Services\RentalTimelineService;
use App\Services\WidgetStyleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Публичные адреса виджета «Таймлайн бронирования»: iframe, JSON-данные, JS-скрипт. Бронирование происходит на сайте владельца. */
class RentalTimelinePublicController extends Controller
{
    private const KIND = 'timeline';

    public function __construct(private RentalTimelineService $service)
    {
    }

    /** Подписи интерфейса виджета в выбранном языке (тексты из lang/club.php, ключи tl_*). */
    public static function labels(): array
    {
        $out = [];
        foreach (['today', 'day', 'week', 'prev', 'next', 'loading', 'closed', 'no_courts', 'location', 'indoor', 'outdoor', 'busy_n',
                  'closed_short', 'error', 'free_aria', 'empty', 'occupancy', 'now', 'total', 'total_all', 'reset', 'book', 'h', 'min', 'legend_free',
                  'legend_busy', 'legend_past', 'legend_prices', 'all_courts', 'per_hour', 'select_hint'] as $k) {
            $out[$k] = __('club.tl_' . $k);
        }
        foreach (['f_title', 'f_name', 'f_phone', 'f_email', 'f_comment', 'f_consent', 'f_policy', 'f_submit_request', 'f_submit_pay', 'f_back', 'f_sending', 'f_err', 'f_ok_request', 'f_ok_link', 'f_pay_info', 'f_request_info'] as $k) {
            $out['rb_' . $k] = __('club.rb_' . $k);
        }
        $out['classic'] = __('events.card_dir_classic');
        $out['beach'] = __('events.card_dir_beach');

        return $out;
    }

    private function widgetByKey(string $key): ?RentalWidget
    {
        return RentalWidget::where('api_key', $key)->where('kind', self::KIND)->where('is_active', true)->first();
    }

    private function ownerAllowed(RentalWidget $widget): bool
    {
        $u = $widget->user;

        return (bool) ($u && $u->isOrganizerPro() && $u->is_club_manager);
    }

    private function styleFor(RentalWidget $widget, Request $request): array
    {
        $style = WidgetStyleService::resolve(
            (array) ($widget->settings['style'] ?? []),
            (string) $widget->getSetting('color', '#f59e0b'),
            self::KIND
        );
        $style = WidgetStyleService::forRenderTimeline($style, $request->only(['theme', 'lang', 'mode', 'accent', 'header']));
        if (in_array($style['lang'], ['ru', 'en'], true)) {
            app()->setLocale($style['lang']);
        }

        return $style;
    }

    /** JSON: настройки оформления, подписи и данные таймлайна (день или неделя). */
    public function data(Request $request): JsonResponse
    {
        $widget = $this->widgetByKey((string) $request->query('key', ''));
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
            return response()->json(['ok' => false, 'code' => 'subscription_inactive', 'error' => __('profile.widget_subscription_inactive')], 403)
                ->header('Access-Control-Allow-Origin', '*');
        }

        $style = $this->styleFor($widget, $request);
        $locations = $this->service->locationsFor($widget, $widget->user_id);

        $params = $request->only(['location_id', 'direction', 'date', 'mode']);
        $params['mode'] = $params['mode'] ?? $style['default_mode'];

        return response()->json([
            'ok'     => true,
            'style'  => $style,
            'labels' => self::labels(),
            'locale' => app()->getLocale(),
            'data'   => $this->service->payload($locations, $params, (bool) $style['show_titles']) + [
                'booking_mode' => app(\App\Services\CourtBookingService::class)->canPrepayOnline((int) $widget->user_id) ? 'prepaid' : 'request',
            ],
        ])->header('Access-Control-Allow-Origin', '*');
    }

    /** iFrame-страница: подгружает то же приложение, что и JS-вариант. */
    public function iframe(Request $request, int $userId): Response
    {
        $widget = $this->widgetByKey((string) $request->query('key', ''));
        if (!$widget || (int) $widget->user_id !== $userId) {
            return response('Виджет недоступен.', 403);
        }
        if (!$this->ownerAllowed($widget)) {
            return response()->view('widget.unavailable', ['message' => __('profile.widget_subscription_inactive')])
                ->header('X-Frame-Options', 'ALLOWALL')->header('Content-Security-Policy', 'frame-ancestors *');
        }

        return response()->view('widget.timeline_iframe', [
            'app'     => $this->appJs(),
            'options' => [
                'api'  => route('rental_timeline.data'),
                'key'  => $widget->api_key,
                'mode' => (string) $request->query('mode', ''),
                'theme' => (string) $request->query('theme', ''),
                'lang' => (string) $request->query('lang', ''),
                'accent' => (string) $request->query('accent', ''),
                'header' => (string) $request->query('header', ''),
                'location' => (int) $request->query('location', 0),
                'direction' => (string) $request->query('direction', ''),
            ],
        ])->header('X-Frame-Options', 'ALLOWALL')->header('Content-Security-Policy', 'frame-ancestors *');
    }

    /** JS-вариант: контейнер #volley-rental-timeline, рисуем в shadow-root; data-атрибуты переопределяют настройки. */
    public function script(Request $request): Response
    {
        $key = json_encode((string) $request->query('key', ''), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
        $api = json_encode(route('rental_timeline.data'), JSON_UNESCAPED_SLASHES);

        $js = $this->appJs() . <<<JS

(function () {
    var c = document.getElementById('volley-rental-timeline');
    if (!c) { console.warn('volley-rental-timeline: #volley-rental-timeline not found'); return; }
    var ds = c.dataset || {};
    window.VolleyRentalTimeline.mount(c, {
        api: {$api}, key: {$key},
        mode: ds.mode || '', theme: ds.theme || '', lang: ds.lang || '', accent: ds.accent || '', header: ds.header || '',
        location: parseInt(ds.location || '0', 10) || 0, direction: ds.direction || ''
    });
})();
JS;

        return response($js, 200, [
            'Content-Type'                => 'application/javascript; charset=utf-8',
            'Cache-Control'               => 'public, max-age=60',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    private function appJs(): string
    {
        return (string) file_get_contents(public_path('assets/rental-timeline.js'));
    }
}
