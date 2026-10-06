<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\OrganizerWidget;
use App\Services\WidgetEventsService;
use App\Services\WidgetStyleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrganizerWidgetController extends Controller
{
    /** Страница управления виджетом в ЛК */
    public function index(Request $request): View
    {
        $user   = $request->user();
        $widget = OrganizerWidget::where('user_id', $user->id)->first();
        $isPro  = $user->isOrganizerPro();

        $accent  = (string) ($widget?->getSetting('color', '#f59e0b') ?? '#f59e0b');
        $style   = WidgetStyleService::resolve(
            (array) ($widget?->settings['style'] ?? []) + [
                'show_slots'    => (bool) ($widget?->getSetting('show_slots', true) ?? true),
                'show_location' => (bool) ($widget?->getSetting('show_location', true) ?? true),
            ],
            $accent
        );
        $styleGroups = WidgetStyleService::groups();
        $presets     = WidgetStyleService::presets();
        $styleDefaults = WidgetStyleService::defaults('#f59e0b');

        return view('profile.widget', compact('widget', 'isPro', 'style', 'styleGroups', 'presets', 'styleDefaults'));
    }

    /** Создать или пересоздать виджет */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (!$user->isOrganizerPro()) {
            return redirect()->route('profile.widget')
                ->with('error', 'Требуется подписка Организатор Pro.');
        }

        $data = $request->validate([
            'allowed_domains' => ['nullable', 'string'],
            'settings.limit'  => ['nullable', 'integer', 'min:1', 'max:50'],
            'settings.color'  => ['nullable', 'string', 'max:7'],
            'settings.show_slots' => ['nullable', 'boolean'],
            'settings.show_location' => ['nullable', 'boolean'],
            'style'           => ['nullable', 'array'],
        ]);

        $domains = [];
        if (!empty($data['allowed_domains'])) {
            $domains = array_filter(
                array_map('trim', explode("\n", str_replace(',', "\n", $data['allowed_domains']))),
                fn($d) => $d !== ''
            );
        }

        $color = preg_match('/^#[0-9a-f]{6}$/i', (string) ($data['settings']['color'] ?? ''))
            ? strtolower($data['settings']['color'])
            : '#f59e0b';

        $style = WidgetStyleService::sanitize((array) ($data['style'] ?? []), WidgetStyleService::defaults($color));

        $settings = [
            'limit'         => (int) ($data['settings']['limit'] ?? 10),
            'color'         => $color,
            // show_* дублируются на верхнем уровне: их читает WidgetEventsService (ключ кеша, адрес, места)
            'show_slots'    => $style['show_slots'],
            'show_location' => $style['show_location'],
            'style'         => $style,
        ];

        OrganizerWidget::updateOrCreate(
            ['user_id' => $user->id],
            [
                'api_key'        => OrganizerWidget::where('user_id', $user->id)->value('api_key')
                                    ?? OrganizerWidget::generateKey(),
                'allowed_domains' => array_values($domains),
                'settings'        => $settings,
                'is_active'       => true,
            ]
        );

        return redirect()
            ->route('profile.widget')
            ->with('status', '✅ Виджет сохранён.');
    }

    /** Пересгенерировать API ключ */
    public function regenerateKey(Request $request): RedirectResponse
    {
        $widget = OrganizerWidget::where('user_id', $request->user()->id)->firstOrFail();
        $widget->update(['api_key' => OrganizerWidget::generateKey()]);

        return redirect()
            ->route('profile.widget')
            ->with('status', '🔑 API-ключ пересоздан. Обновите код на вашем сайте.');
    }

    /** Включить / выключить виджет */
    public function toggle(Request $request): RedirectResponse
    {
        $widget = OrganizerWidget::where('user_id', $request->user()->id)->firstOrFail();
        $widget->update(['is_active' => !$widget->is_active]);

        $status = $widget->is_active ? '✅ Виджет включён.' : '⏸ Виджет отключён.';

        return redirect()->route('profile.widget')->with('status', $status);
    }

    /** Предпросмотр для формы: рендерит виджет по ещё не сохранённым значениям (без записи в БД) */
    public function preview(Request $request, WidgetEventsService $events)
    {
        $user = $request->user();
        abort_unless($user->isOrganizerPro(), 403);

        $color  = preg_match('/^#[0-9a-f]{6}$/i', (string) $request->input('settings.color'))
            ? strtolower((string) $request->input('settings.color')) : '#f59e0b';
        $style  = WidgetStyleService::sanitize((array) $request->input('style', []), WidgetStyleService::defaults($color));
        $style  = WidgetStyleService::forRender($style);

        $widget = OrganizerWidget::where('user_id', $user->id)->first() ?? new OrganizerWidget(['settings' => []]);
        $widget->settings = array_merge((array) $widget->settings, [
            'limit'         => max(1, min(50, (int) $request->input('settings.limit', 10))),
            'show_slots'    => $style['show_slots'],
            'show_location' => $style['show_location'],
        ]);

        $list = $events->getEvents($widget, $user->id);
        if (!$list) {
            $list = $events->sampleEvents();
        }

        return response()->view('widget.iframe', ['events' => $list, 'style' => $style])
            ->header('X-Frame-Options', 'SAMEORIGIN');
    }
}
