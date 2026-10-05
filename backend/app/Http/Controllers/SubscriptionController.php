<?php
namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\SubscriptionTemplate;
use App\Models\User;
use App\Services\SubscriptionService;
use App\Services\StaffLogService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class SubscriptionController extends Controller
{
    public function __construct(private SubscriptionService $service) {}

    // Список абонементов (для организатора/админа)
    public function index(Request $request)
    {
        $user = $request->user();
        $access = app(\App\Services\EventAccessService::class);
        $access->ensureCanUseSubs($user);
        $subs = Subscription::with(['user', 'template'])
            ->when(!$user->isAdmin(), fn($q) => $q->whereIn('organizer_id', $access->subsOrganizerIds($user)))
            ->orderByDesc('id')
            ->paginate(30);

        return view('subscriptions.index', compact('subs'));
    }

    // Мои абонементы (для игрока)
    public function my(Request $request)
    {
        $subs = Subscription::with(['template', 'organizer', 'usages', 'autoBookings.event'])
            ->where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->get();

        // Автозапись по абонементу: доступные для настройки мероприятия (только активные абонементы).
        $autoEvents = [];
        foreach ($subs as $sub) {
            if ($sub->status === 'active' && $sub->template?->auto_booking_enabled) {
                $autoEvents[$sub->id] = $this->service->autoBookingEventsFor($sub, $request->user())->all();
            }
        }

        return view('subscriptions.my', compact('subs', 'autoEvents'));
    }

    // Выдать абонемент вручную
    public function issue(Request $request)
    {
        $data = $request->validate([
            'template_id' => ['required', 'integer', 'exists:subscription_templates,id'],
            'user_id'     => ['required', 'integer', 'exists:users,id'],
            'reason'      => ['nullable', 'string', 'max:200'],
        ]);

        $template = SubscriptionTemplate::findOrFail($data['template_id']);
        $user = auth()->user();

        if (!app(\App\Services\EventAccessService::class)->canManageSubsOf($user, (int) $template->organizer_id)) {
            abort(403);
        }

        $sub = $this->service->issue(
            $template,
            $data['user_id'],
            $user->id,
            $data['reason'] ?? 'manual'
        );

        // Лог помощника (выдача от имени другого организатора)
        if (!$user->isAdmin() && (int) $template->organizer_id !== (int) $user->id) {
            app(\App\Services\StaffLogService::class)->log($user, (int) $template->organizer_id, 'issue_subscription', 'subscription', $sub->id, "Выдал абонемент #{$sub->id} пользователю #{$data['user_id']}");
        }

        return back()->with('status', "✅ Абонемент #{$sub->id} выдан пользователю #{$data['user_id']}");
    }

    // Покупка абонемента пользователем (с публичной страницы школы)
    public function buy(Request $request, \App\Models\SubscriptionTemplate $template)
    {
        $user = $request->user();

        // Проверяем что шаблон активен и доступен для продажи
        if (!$template->is_active || !$template->sale_enabled) {
            return back()->with('error', 'Абонемент недоступен для покупки.');
        }

        // Проверяем лимит продаж
        if ($template->sale_limit && $template->sold_count >= $template->sale_limit) {
            return back()->with('error', 'Абонемент распродан.');
        }

        // Проверяем срок действия
        if ($template->valid_until && $template->valid_until->isPast()) {
            return back()->with('error', 'Срок действия абонемента истёк.');
        }

        // Выдаём абонемент пользователю
        $sub = $this->service->issue(
            $template,
            $user->id,
            $template->organizer_id,
            'self_purchase'
        );

        // Увеличиваем счётчик продаж
        $template->increment('sold_count');

        return back()->with('status', '✅ Абонемент успешно получен! Он доступен в разделе «Мои абонементы».');
    }

    // Продлить срок
    public function extend(Request $request, Subscription $subscription)
    {
        $this->authorizeSubscription($subscription);
        $data = $request->validate([
            'days' => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        $this->service->extend($subscription, $data['days']);

        // Лог помощника
        $authUser = $request->user();
        if (!$authUser->isAdmin() && (int) $subscription->organizer_id !== (int) $authUser->id) {
            app(StaffLogService::class)->log($authUser, (int) $subscription->organizer_id, 'extend_subscription', 'subscription', $subscription->id, "Продлил абонемент #{$subscription->id} на {$data['days']} дней");
        }

        return back()->with('status', "✅ Срок продлён на {$data['days']} дней");
    }

    // Заморозить (пользователь)
    public function freeze(Request $request, Subscription $subscription)
    {
        if ($subscription->user_id !== $request->user()->id) abort(403);

        $data = $request->validate([
            'until' => ['required', 'date', 'after' => 'today'],
        ]);

        $this->service->freeze($subscription, Carbon::parse($data['until']));
        return back()->with('status', "❄️ Абонемент заморожен до {$data['until']}");
    }

    // Разморозить
    public function unfreeze(Request $request, Subscription $subscription)
    {
        $user = $request->user();
        if ($subscription->user_id !== $user->id
            && !app(\App\Services\EventAccessService::class)->canManageSubsOf($user, (int) $subscription->organizer_id)) {
            abort(403);
        }

        $this->service->unfreeze($subscription);
        return back()->with('status', '✅ Абонемент разморожен');
    }

    // Передача (пользователь)
    public function transfer(Request $request, Subscription $subscription)
    {
        if ($subscription->user_id !== $request->user()->id) abort(403);

        $data = $request->validate([
            'to_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $this->service->transfer($subscription, $data['to_user_id']);
        return back()->with('status', '✅ Абонемент передан');
    }

    // История использований
    public function usages(Subscription $subscription)
    {
        $user = auth()->user();
        if ($subscription->user_id !== $user->id
            && !app(\App\Services\EventAccessService::class)->canManageSubsOf($user, (int) $subscription->organizer_id)) {
            abort(403);
        }

        $usages = $subscription->usages()->with('event')->orderByDesc('used_at')->get();
        return view('subscriptions.usages', compact('subscription', 'usages'));
    }

    private function authorizeSubscription(Subscription $sub): void
    {
        $user = auth()->user();
        if (!app(\App\Services\EventAccessService::class)->canManageSubsOf($user, (int) $sub->organizer_id)) abort(403);
    }
}
