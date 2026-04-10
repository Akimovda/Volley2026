<?php
namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\SubscriptionTemplate;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class SubscriptionController extends Controller
{
    public function __construct(private SubscriptionService $service) {}

    // Список абонементов (для организатора/админа)
    public function index(Request $request)
    {
        $user = $request->user();
        $subs = Subscription::with(['user', 'template'])
            ->when(!$user->isAdmin(), fn($q) => $q->where('organizer_id', $user->id))
            ->orderByDesc('id')
            ->paginate(30);

        return view('subscriptions.index', compact('subs'));
    }

    // Мои абонементы (для игрока)
    public function my(Request $request)
    {
        $subs = Subscription::with(['template', 'organizer', 'usages'])
            ->where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->get();

        return view('subscriptions.my', compact('subs'));
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

        if (!$user->isAdmin() && $template->organizer_id !== $user->id) {
            abort(403);
        }

        $sub = $this->service->issue(
            $template,
            $data['user_id'],
            $user->id,
            $data['reason'] ?? 'manual'
        );

        return back()->with('status', "✅ Абонемент #{$sub->id} выдан пользователю #{$data['user_id']}");
    }

    // Продлить срок
    public function extend(Request $request, Subscription $subscription)
    {
        $this->authorizeSubscription($subscription);
        $data = $request->validate([
            'days' => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        $this->service->extend($subscription, $data['days']);
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
        if (!$user->isAdmin() && $subscription->organizer_id !== $user->id
            && $subscription->user_id !== $user->id) {
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
        if (!$user->isAdmin() && $subscription->user_id !== $user->id
            && $subscription->organizer_id !== $user->id) {
            abort(403);
        }

        $usages = $subscription->usages()->with('event')->orderByDesc('used_at')->get();
        return view('subscriptions.usages', compact('subscription', 'usages'));
    }

    private function authorizeSubscription(Subscription $sub): void
    {
        $user = auth()->user();
        if (!$user->isAdmin() && $sub->organizer_id !== $user->id) abort(403);
    }
}
