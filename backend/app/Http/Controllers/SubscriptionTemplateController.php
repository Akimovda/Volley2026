<?php
namespace App\Http\Controllers;

use App\Models\SubscriptionTemplate;
use App\Models\Event;
use Illuminate\Http\Request;

class SubscriptionTemplateController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $templates = SubscriptionTemplate::with('organizer')
            ->when(!$user->isAdmin(), fn($q) => $q->where('organizer_id', $user->id))
            ->orderByDesc('id')
            ->paginate(20);

        return view('subscriptions.templates.index', compact('templates'));
    }

    public function create(Request $request)
    {
        $user = $request->user();
        $events = Event::where('organizer_id', $user->id)
            ->orderByDesc('id')->limit(100)->get();

        return view('subscriptions.templates.create', compact('events'));
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name'                  => ['required', 'string', 'max:150'],
            'description'           => ['nullable', 'string', 'max:1000'],
            'event_ids'             => ['nullable', 'array'],
            'event_ids.*'           => ['integer'],
            'valid_from'            => ['nullable', 'date'],
            'valid_until'           => ['nullable', 'date', 'after_or_equal:valid_from'],
            'visits_total'          => ['required', 'integer', 'min:1', 'max:1000'],
            'cancel_hours_before'   => ['required', 'integer', 'min:0'],
            'freeze_enabled'        => ['sometimes', 'boolean'],
            'freeze_max_weeks'      => ['required_if:freeze_enabled,true', 'integer', 'min:0'],
            'freeze_max_months'     => ['required_if:freeze_enabled,true', 'integer', 'min:0'],
            'transfer_enabled'      => ['sometimes', 'boolean'],
            'auto_booking_enabled'  => ['sometimes', 'boolean'],
            'price_minor'           => ['required', 'integer', 'min:0'],
            'currency'              => ['required', 'string', 'size:3'],
            'sale_limit'            => ['nullable', 'integer', 'min:1'],
            'sale_enabled'          => ['sometimes', 'boolean'],
        ]);

        $organizerId = $user->isAdmin()
            ? ($request->input('organizer_id') ?? $user->id)
            : $user->id;

        SubscriptionTemplate::create(array_merge($data, [
            'organizer_id'     => $organizerId,
            'freeze_enabled'   => (bool)($data['freeze_enabled'] ?? false),
            'transfer_enabled' => (bool)($data['transfer_enabled'] ?? false),
            'auto_booking_enabled' => (bool)($data['auto_booking_enabled'] ?? false),
            'sale_enabled'     => (bool)($data['sale_enabled'] ?? false),
            'is_active'        => true,
        ]));

        return redirect()->route('subscription_templates.index')
            ->with('status', '✅ Шаблон абонемента создан!');
    }

    public function edit(SubscriptionTemplate $subscriptionTemplate)
    {
        $this->authorizeTemplate($subscriptionTemplate);
        $events = Event::where('organizer_id', $subscriptionTemplate->organizer_id)
            ->orderByDesc('id')->limit(100)->get();

        return view('subscriptions.templates.edit', [
            'template' => $subscriptionTemplate,
            'events'   => $events,
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
            'visits_total'          => ['required', 'integer', 'min:1'],
            'cancel_hours_before'   => ['required', 'integer', 'min:0'],
            'freeze_enabled'        => ['sometimes', 'boolean'],
            'freeze_max_weeks'      => ['integer', 'min:0'],
            'freeze_max_months'     => ['integer', 'min:0'],
            'transfer_enabled'      => ['sometimes', 'boolean'],
            'auto_booking_enabled'  => ['sometimes', 'boolean'],
            'price_minor'           => ['required', 'integer', 'min:0'],
            'currency'              => ['required', 'string', 'size:3'],
            'sale_limit'            => ['nullable', 'integer', 'min:1'],
            'sale_enabled'          => ['sometimes', 'boolean'],
            'is_active'             => ['sometimes', 'boolean'],
        ]);

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
        $subscriptionTemplate->update(['is_active' => false]);
        return back()->with('status', 'Шаблон деактивирован');
    }

    private function authorizeTemplate(SubscriptionTemplate $template): void
    {
        $user = auth()->user();
        if (!$user->isAdmin() && $template->organizer_id !== $user->id) {
            abort(403);
        }
    }
}
