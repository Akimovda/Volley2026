<?php
namespace App\Http\Controllers;

use App\Models\SubscriptionTemplate;
use App\Models\Event;
use App\Models\User;
use App\Services\EventAccessService;
use App\Services\StaffLogService;
use Illuminate\Http\Request;

class SubscriptionTemplateController extends Controller
{
    private function access(): EventAccessService
    {
        return app(EventAccessService::class);
    }

    /** Организаторы на выбор в форме создания (если у пользователя их больше одного). */
    private function organizerChoices(User $user)
    {
        $ids = $this->access()->subsOrganizerIds($user);
        if ($user->isAdmin() || count($ids) < 2) {
            return collect();
        }
        return User::whereIn('id', $ids)->get(['id', 'first_name', 'last_name'])
            ->sortBy(fn ($u) => (int) $u->id === (int) $user->id ? 0 : 1)->values();
    }

    /** Запись в журнал помощника, если действие выполнено от имени другого организатора. */
    private function logForOther(User $user, int $organizerId, string $action, int $entityId, string $text): void
    {
        if (!$user->isAdmin() && $organizerId !== (int) $user->id) {
            app(StaffLogService::class)->log($user, $organizerId, $action, 'subscription_template', $entityId, $text);
        }
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $this->access()->ensureCanUseSubs($user);
        $templates = SubscriptionTemplate::with('organizer')
            ->when(!$user->isAdmin(), fn($q) => $q->whereIn('organizer_id', $this->access()->subsOrganizerIds($user)))
            ->orderByDesc('id')
            ->paginate(20);

        return view('subscriptions.templates.index', compact('templates'));
    }

    public function create(Request $request)
    {
        $user = $request->user();
        $this->access()->ensureCanUseSubs($user);
        $events = Event::whereIn('organizer_id', $this->access()->subsOrganizerIds($user))
            ->orderByDesc('id')->limit(100)->get();
        $organizerChoices = $this->organizerChoices($user);

        return view('subscriptions.templates.create', compact('events', 'organizerChoices'));
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $this->access()->ensureCanUseSubs($user);
        $data = $request->validate([
            'name'                  => ['required', 'string', 'max:150'],
            'description'           => ['nullable', 'string', 'max:1000'],
            'event_ids'             => ['nullable', 'array'],
            'event_ids.*'           => ['integer'],
            'valid_from'            => ['nullable', 'date'],
            'valid_until'           => ['nullable', 'date', 'after_or_equal:valid_from'],
            'duration_months'       => ['nullable', 'integer', 'min:0', 'max:36'],
            'duration_days'         => ['nullable', 'integer', 'min:0', 'max:365'],
            'visits_total'          => ['required', 'integer', 'min:1', 'max:1000'],
            'cancel_hours_before'   => ['required', 'integer', 'min:0'],
            'freeze_enabled'        => ['sometimes', 'boolean'],
            'freeze_max_weeks'      => ['required_if:freeze_enabled,true', 'integer', 'min:0'],
            'freeze_max_months'     => ['required_if:freeze_enabled,true', 'integer', 'min:0'],
            'transfer_enabled'      => ['sometimes', 'boolean'],
            'auto_booking_enabled'  => ['sometimes', 'boolean'],
            'price_rub'             => ['required', 'numeric', 'min:0'],
            'currency'              => ['required', 'string', 'size:3'],
            'sale_limit'            => ['nullable', 'integer', 'min:1'],
            'sale_enabled'          => ['sometimes', 'boolean'],
        ]);

        $organizerId = $user->isAdmin()
            ? ($request->input('organizer_id') ?? $user->id)
            : $this->access()->resolveSubsOrganizerId($user, $request->integer('organizer_id') ?: null);

        // Мероприятия шаблона — только выбранного организатора
        if (!empty($data['event_ids'])) {
            $data['event_ids'] = Event::where('organizer_id', $organizerId)
                ->whereIn('id', $data['event_ids'])->pluck('id')->map(fn ($v) => (int) $v)->all();
        }

        $data['price_minor'] = (int) round(($data['price_rub'] ?? 0) * 100);
        unset($data['price_rub']);

        $created = SubscriptionTemplate::create(array_merge($data, [
            'organizer_id'     => $organizerId,
            'freeze_enabled'   => (bool)($data['freeze_enabled'] ?? false),
            'transfer_enabled' => (bool)($data['transfer_enabled'] ?? false),
            'auto_booking_enabled' => (bool)($data['auto_booking_enabled'] ?? false),
            'sale_enabled'     => (bool)($data['sale_enabled'] ?? false),
            'is_active'        => true,
        ]));

        $this->logForOther($user, (int) $organizerId, 'create_subscription_template', $created->id, "Создал шаблон абонемента: {$created->name}");

        return redirect()->route('subscription_templates.index')
            ->with('status', '✅ Шаблон абонемента создан!');
    }

    public function edit(SubscriptionTemplate $subscriptionTemplate)
    {
        $this->authorizeTemplate($subscriptionTemplate);
        $events = Event::where('organizer_id', $subscriptionTemplate->organizer_id)
            ->orderByDesc('id')->limit(100)->get();

        return view('subscriptions.templates.edit', [
            'subscriptionTemplate' => $subscriptionTemplate,
            'template'             => $subscriptionTemplate,
            'events'               => $events,
        ]);
    }

    public function update(Request $request, SubscriptionTemplate $subscriptionTemplate)
    {
        $this->authorizeTemplate($subscriptionTemplate);
        $data = $request->validate([
            'name'                  => ['required', 'string', 'max:150'],
            'description'           => ['nullable', 'string', 'max:1000'],
            'event_ids'             => ['nullable', 'array'],
            'event_ids.*'           => ['integer'],
            'valid_from'            => ['nullable', 'date'],
            'valid_until'           => ['nullable', 'date'],
            'duration_months'       => ['nullable', 'integer', 'min:0', 'max:36'],
            'duration_days'         => ['nullable', 'integer', 'min:0', 'max:365'],
            'visits_total'          => ['required', 'integer', 'min:1'],
            'cancel_hours_before'   => ['required', 'integer', 'min:0'],
            'freeze_enabled'        => ['sometimes', 'boolean'],
            'freeze_max_weeks'      => ['integer', 'min:0'],
            'freeze_max_months'     => ['integer', 'min:0'],
            'transfer_enabled'      => ['sometimes', 'boolean'],
            'auto_booking_enabled'  => ['sometimes', 'boolean'],
            'price_rub'             => ['required', 'numeric', 'min:0'],
            'currency'              => ['required', 'string', 'size:3'],
            'sale_limit'            => ['nullable', 'integer', 'min:1'],
            'sale_enabled'          => ['sometimes', 'boolean'],
            'is_active'             => ['sometimes', 'boolean'],
        ]);

        $data['price_minor'] = (int) round(($data['price_rub'] ?? 0) * 100);
        unset($data['price_rub']);

        $this->logForOther(auth()->user(), (int) $subscriptionTemplate->organizer_id, 'update_subscription_template', $subscriptionTemplate->id, "Изменил шаблон абонемента: {$subscriptionTemplate->name}");

        $subscriptionTemplate->update(array_merge($data, [
            'freeze_enabled'       => (bool)($data['freeze_enabled'] ?? false),
            'transfer_enabled'     => (bool)($data['transfer_enabled'] ?? false),
            'auto_booking_enabled' => (bool)($data['auto_booking_enabled'] ?? false),
            'sale_enabled'         => (bool)($data['sale_enabled'] ?? false),
            'is_active'            => (bool)($data['is_active'] ?? true),
        ]));

        return redirect()->route('subscription_templates.index')
            ->with('status', '✅ Шаблон обновлён!');
    }

    public function destroy(SubscriptionTemplate $subscriptionTemplate)
    {
        $this->authorizeTemplate($subscriptionTemplate);
        $this->logForOther(auth()->user(), (int) $subscriptionTemplate->organizer_id, 'delete_subscription_template', $subscriptionTemplate->id, "Деактивировал шаблон абонемента: {$subscriptionTemplate->name}");
        $subscriptionTemplate->update(['is_active' => false]);
        return back()->with('status', 'Шаблон деактивирован');
    }

    public function forceDelete(\Illuminate\Http\Request $request, SubscriptionTemplate $subscriptionTemplate)
    {
        $this->authorizeTemplate($subscriptionTemplate);

        $forceCode = $request->input('force_code');
        if ($forceCode !== '973124') {
            return back()->with('error', '❌ Неверный код подтверждения.');
        }

        // Удаляем связанные абонементы
        \Illuminate\Support\Facades\DB::table('subscriptions')
            ->where('template_id', $subscriptionTemplate->id)
            ->delete();

        $subscriptionTemplate->delete();
        return back()->with('status', '✅ Шаблон и связанные абонементы удалены');
    }

    private function authorizeTemplate(SubscriptionTemplate $template): void
    {
        $user = auth()->user();
        if (!$this->access()->canManageSubsOf($user, (int) $template->organizer_id)) {
            abort(403);
        }
    }
}
