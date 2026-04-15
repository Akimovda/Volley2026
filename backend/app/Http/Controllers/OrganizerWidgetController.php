<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\OrganizerWidget;
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

        return view('profile.widget', compact('widget', 'isPro'));
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
        ]);

        $domains = [];
        if (!empty($data['allowed_domains'])) {
            $domains = array_filter(
                array_map('trim', explode("\n", str_replace(',', "\n", $data['allowed_domains']))),
                fn($d) => $d !== ''
            );
        }

        $settings = [
            'limit'         => (int) ($data['settings']['limit'] ?? 10),
            'color'         => $data['settings']['color'] ?? '#f59e0b',
            'show_slots'    => (bool) ($data['settings']['show_slots'] ?? true),
            'show_location' => (bool) ($data['settings']['show_location'] ?? true),
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
}
