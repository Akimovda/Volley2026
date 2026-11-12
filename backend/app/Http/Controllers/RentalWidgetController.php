<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\RentalWidget;
use App\Services\RentalWidgetService;
use App\Services\WidgetStyleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Виджет аренды кортов и площадок в ЛК: Организатор Pro + управляющий клубом (владелец локации).
 */
class RentalWidgetController extends Controller
{
    private const KIND = 'rental';

    /** Доступ к разделу: управляющий клубом, у которого есть хотя бы одна своя локация. */
    private function authorizeManager(Request $request): void
    {
        $user = $request->user();
        abort_unless($user && $user->is_club_manager && $user->ownedLocations()->exists(), 403);
    }

    public function index(Request $request): View
    {
        $this->authorizeManager($request);
        $user   = $request->user();
        $widget = RentalWidget::where('user_id', $user->id)->first();
        $isPro  = $user->isOrganizerPro();

        $accent = (string) ($widget?->getSetting('color', '#f59e0b') ?? '#f59e0b');
        $style  = WidgetStyleService::resolve((array) ($widget?->settings['style'] ?? []), $accent, self::KIND);

        return view('profile.rental_widget', [
            'widget'        => $widget,
            'isPro'         => $isPro,
            'style'         => $style,
            'styleGroups'   => WidgetStyleService::rentalFormGroups(),
            'presets'       => WidgetStyleService::presets(),
            'styleDefaults' => WidgetStyleService::defaults('#f59e0b', self::KIND),
            'locations'     => $user->ownedLocations()->orderBy('name')->get(['id', 'name']),
            'selectedIds'   => array_map('intval', (array) ($widget?->getSetting('location_ids', []) ?? [])),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManager($request);
        $user = $request->user();

        if (!$user->isOrganizerPro()) {
            return redirect()->route('profile.rental_widget')->with('error', __('Требуется подписка Организатор Pro.'));
        }

        $data = $request->validate([
            'allowed_domains'   => ['nullable', 'string'],
            'settings.color'    => ['nullable', 'string', 'max:7'],
            'settings.location_ids'   => ['nullable', 'array'],
            'settings.location_ids.*' => ['integer'],
            'style'             => ['nullable', 'array'],
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

        $style = WidgetStyleService::sanitize((array) ($data['style'] ?? []), WidgetStyleService::defaults($color, self::KIND), true, self::KIND);

        RentalWidget::updateOrCreate(
            ['user_id' => $user->id],
            [
                'api_key'         => RentalWidget::where('user_id', $user->id)->value('api_key') ?? RentalWidget::generateKey(),
                'allowed_domains' => array_values($domains),
                'settings'        => ['color' => $color, 'location_ids' => $locationIds, 'style' => $style],
                'is_active'       => true,
            ]
        );

        return redirect()->route('profile.rental_widget')->with('status', __('✅ Виджет сохранён.'));
    }

    public function regenerateKey(Request $request): RedirectResponse
    {
        $this->authorizeManager($request);
        $widget = RentalWidget::where('user_id', $request->user()->id)->firstOrFail();
        $widget->update(['api_key' => RentalWidget::generateKey()]);

        return redirect()->route('profile.rental_widget')->with('status', __('🔑 API-ключ пересоздан. Обновите код на вашем сайте.'));
    }

    public function toggle(Request $request): RedirectResponse
    {
        $this->authorizeManager($request);
        $widget = RentalWidget::where('user_id', $request->user()->id)->firstOrFail();
        $widget->update(['is_active' => !$widget->is_active]);

        return redirect()->route('profile.rental_widget')
            ->with('status', $widget->is_active ? __('profile.rw_enabled') : __('profile.rw_disabled'));
    }

    /** Предпросмотр по ещё не сохранённым значениям формы (без записи в БД). */
    public function preview(Request $request, RentalWidgetService $service)
    {
        $this->authorizeManager($request);
        $user = $request->user();
        abort_unless($user->isOrganizerPro(), 403);

        $color = preg_match('/^#[0-9a-f]{6}$/i', (string) $request->input('settings.color'))
            ? strtolower((string) $request->input('settings.color')) : '#f59e0b';
        $style = WidgetStyleService::sanitize((array) $request->input('style', []), WidgetStyleService::defaults($color, self::KIND), true, self::KIND);
        $style = WidgetStyleService::forRender($style);
        if (in_array($style['lang'], ['ru', 'en'], true)) {
            app()->setLocale($style['lang']);
        }

        $own = $user->ownedLocations()->pluck('id')->all();
        $ids = array_values(array_intersect($own, array_map('intval', (array) $request->input('settings.location_ids', []))));

        $widget = RentalWidget::where('user_id', $user->id)->first() ?? new RentalWidget(['settings' => []]);
        $widget->settings = array_merge((array) $widget->settings, ['location_ids' => $ids]);

        $list = $service->getLocations($widget, $user->id) ?: $service->sampleLocations();

        return response()->view('widget.rental_iframe', ['locations' => $list, 'style' => $style])
            ->header('X-Frame-Options', 'SAMEORIGIN');
    }
}
