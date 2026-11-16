<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\RentalWidget;
use App\Services\RentalTimelineService;
use App\Services\RentalWidgetService;
use App\Services\WidgetStyleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Виджеты аренды в ЛК: Организатор Pro + управляющий клубом (владелец локации).
 * Два вида: cards — карточки площадок; timeline — таймлайн бронирования (клик по слоту ведёт на сайт владельца).
 */
class RentalWidgetController extends Controller
{
    /** Доступ к разделу: управляющий клубом, у которого есть хотя бы одна своя локация. */
    private function authorizeManager(Request $request): void
    {
        $user = $request->user();
        abort_unless($user && $user->is_club_manager && $user->ownedLocations()->exists(), 403);
    }

    private function kind(Request $request): string
    {
        return $request->input('kind', $request->query('kind')) === RentalWidget::KIND_TIMELINE
            ? RentalWidget::KIND_TIMELINE : RentalWidget::KIND_CARDS;
    }

    private function widgetOf(Request $request, string $kind): ?RentalWidget
    {
        return RentalWidget::where('user_id', $request->user()->id)->where('kind', $kind)->first();
    }

    private function back(string $kind): RedirectResponse
    {
        return redirect()->route('profile.rental_widget', ['kind' => $kind]);
    }

    public function index(Request $request): View
    {
        $this->authorizeManager($request);
        $user   = $request->user();
        $kind   = $this->kind($request);
        $widget = $this->widgetOf($request, $kind);

        $accent = (string) ($widget?->getSetting('color', '#f59e0b') ?? '#f59e0b');
        $style  = WidgetStyleService::resolve((array) ($widget?->settings['style'] ?? []), $accent, $kind);

        return view('profile.rental_widget', [
            'kind'          => $kind,
            'widget'        => $widget,
            'isPro'         => $user->isOrganizerPro(),
            'style'         => $style,
            'styleGroups'   => $kind === 'timeline' ? WidgetStyleService::timelineGroups() : WidgetStyleService::rentalFormGroups(),
            'presets'       => WidgetStyleService::presets($kind),
            'styleDefaults' => WidgetStyleService::defaults('#f59e0b', $kind),
            'locations'     => $user->ownedLocations()->orderBy('name')->get(['id', 'name']),
            'selectedIds'   => array_map('intval', (array) ($widget?->getSetting('location_ids', []) ?? [])),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManager($request);
        $user = $request->user();
        $kind = $this->kind($request);

        if (!$user->isOrganizerPro()) {
            return $this->back($kind)->with('error', __('Требуется подписка Организатор Pro.'));
        }

        $data = $request->validate([
            'allowed_domains'         => ['nullable', 'string'],
            'settings.color'          => ['nullable', 'string', 'max:7'],
            'settings.location_ids'   => ['nullable', 'array'],
            'settings.location_ids.*' => ['integer'],
            'style'                   => ['nullable', 'array'],
        ]);

        $domains = [];
        if (!empty($data['allowed_domains'])) {
            $domains = array_filter(
                array_map('trim', explode("\n", str_replace(',', "\n", $data['allowed_domains']))),
                fn ($d) => $d !== ''
            );
        }

        $color = preg_match('/^#[0-9a-f]{6}$/i', (string) ($data['settings']['color'] ?? ''))
            ? strtolower($data['settings']['color']) : '#f59e0b';

        // только свои локации
        $own = $user->ownedLocations()->pluck('id')->all();
        $locationIds = array_values(array_intersect($own, array_map('intval', (array) ($data['settings']['location_ids'] ?? []))));

        $style = WidgetStyleService::sanitize((array) ($data['style'] ?? []), WidgetStyleService::defaults($color, $kind), true, $kind);

        RentalWidget::updateOrCreate(
            ['user_id' => $user->id, 'kind' => $kind],
            [
                'api_key'         => $this->widgetOf($request, $kind)?->api_key ?? RentalWidget::generateKey(),
                'allowed_domains' => array_values($domains),
                'settings'        => ['color' => $color, 'location_ids' => $locationIds, 'style' => $style],
                'is_active'       => true,
            ]
        );

        return $this->back($kind)->with('status', __('profile.rw_saved'));
    }

    public function regenerateKey(Request $request): RedirectResponse
    {
        $this->authorizeManager($request);
        $kind = $this->kind($request);
        $widget = RentalWidget::where('user_id', $request->user()->id)->where('kind', $kind)->firstOrFail();
        $widget->update(['api_key' => RentalWidget::generateKey()]);

        return $this->back($kind)->with('status', __('profile.rw_key_regenerated'));
    }

    public function toggle(Request $request): RedirectResponse
    {
        $this->authorizeManager($request);
        $kind = $this->kind($request);
        $widget = RentalWidget::where('user_id', $request->user()->id)->where('kind', $kind)->firstOrFail();
        $widget->update(['is_active' => !$widget->is_active]);

        return $this->back($kind)->with('status', $widget->is_active ? __('profile.rw_enabled') : __('profile.rw_disabled'));
    }

    /** Предпросмотр по ещё не сохранённым значениям формы (без записи в БД). */
    public function preview(Request $request, RentalWidgetService $service, RentalTimelineService $timeline)
    {
        $this->authorizeManager($request);
        $user = $request->user();
        abort_unless($user->isOrganizerPro(), 403);
        $kind = $this->kind($request);

        $color = preg_match('/^#[0-9a-f]{6}$/i', (string) $request->input('settings.color'))
            ? strtolower((string) $request->input('settings.color')) : '#f59e0b';
        $style = WidgetStyleService::sanitize((array) $request->input('style', []), WidgetStyleService::defaults($color, $kind), true, $kind);

        $own = $user->ownedLocations()->pluck('id')->all();
        $ids = array_values(array_intersect($own, array_map('intval', (array) $request->input('settings.location_ids', []))));

        if ($kind === RentalWidget::KIND_TIMELINE) {
            $style = WidgetStyleService::forRenderTimeline($style);
            if (in_array($style['lang'], ['ru', 'en'], true)) {
                app()->setLocale($style['lang']);
            }

            return response()->view('widget.timeline_iframe', [
                'app'     => (string) file_get_contents(public_path('assets/rental-timeline.js')),
                'options' => [
                    'api'           => route('profile.rental_widget.timeline_data', ['ids' => implode(',', $ids)]),
                    'key'           => 'preview',
                    'credentials'   => 'same-origin',
                    'styleOverride' => $style,
                    'labels'        => RentalTimelinePublicController::labels(),
                    'locale'        => app()->getLocale(),
                ],
            ])->header('X-Frame-Options', 'SAMEORIGIN');
        }

        $style = WidgetStyleService::forRender($style);
        if (in_array($style['lang'], ['ru', 'en'], true)) {
            app()->setLocale($style['lang']);
        }

        $widget = $this->widgetOf($request, RentalWidget::KIND_CARDS) ?? new RentalWidget(['settings' => []]);
        $widget->settings = array_merge((array) $widget->settings, ['location_ids' => $ids]);

        $list = $service->getLocations($widget, $user->id) ?: $service->sampleLocations();

        return response()->view('widget.rental_iframe', ['locations' => $list, 'style' => $style])
            ->header('X-Frame-Options', 'SAMEORIGIN');
    }

    /** Данные таймлайна для предпросмотра в ЛК (сессия владельца, без ключа): только его локации. */
    public function timelineData(Request $request, RentalTimelineService $timeline)
    {
        $this->authorizeManager($request);
        $user = $request->user();
        abort_unless($user->isOrganizerPro(), 403);

        $own = $user->ownedLocations()->pluck('id')->all();
        $ids = array_values(array_intersect($own, array_map('intval', array_filter(explode(',', (string) $request->query('ids', ''))))));

        $widget = new RentalWidget(['settings' => ['location_ids' => $ids]]);
        $locations = $timeline->locationsFor($widget, $user->id);

        $params = $request->only(['location_id', 'direction', 'date', 'mode']);
        $params['mode'] = $params['mode'] ?? 'day';

        return response()->json([
            'ok'   => true,
            'data' => $timeline->payload($locations, $params, false),
        ]);
    }
}
