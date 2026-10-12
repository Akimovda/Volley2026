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

        $filters = $this->filters($request);
        $subs = $this->filteredQuery($user, $filters)
            ->with(['user', 'template'])
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        $templates = SubscriptionTemplate::query()
            ->when(!$user->isAdmin(), fn($q) => $q->whereIn('organizer_id', $access->subsOrganizerIds($user)))
            ->orderBy('name')->get(['id', 'name']);

        return view('subscriptions.index', compact('subs', 'filters', 'templates'));
    }

    private const STATUSES = ['active', 'ending', 'frozen', 'expired', 'exhausted', 'cancelled'];

    private function filters(Request $request): array
    {
        $status = (string) $request->query('status', '');
        return [
            'q'        => trim((string) $request->query('q', '')),
            'status'   => in_array($status, self::STATUSES, true) ? $status : '',
            'template' => (int) $request->query('template', 0),
        ];
    }

    private function filteredQuery($user, array $f)
    {
        $access = app(\App\Services\EventAccessService::class);

        return Subscription::query()
            ->when(!$user->isAdmin(), fn($q) => $q->whereIn('organizer_id', $access->subsOrganizerIds($user)))
            ->when($f['status'] === 'ending', fn($q) => $q->where('status', 'active')->whereBetween('visits_remaining', [1, Subscription::ENDING_VISITS]))
            ->when($f['status'] === 'active', fn($q) => $q->where('status', 'active')->where('visits_remaining', '>', Subscription::ENDING_VISITS))
            ->when(!in_array($f['status'], ['', 'ending', 'active'], true), fn($q) => $q->where('status', $f['status']))
            ->when($f['template'] > 0, fn($q) => $q->where('template_id', $f['template']))
            ->when($f['q'] !== '', function ($q) use ($f) {
                $term   = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $f['q']);
                $like   = '%' . $term . '%';
                $digits = preg_replace('/\D+/', '', $f['q']);
                $q->where(function ($w) use ($like, $digits, $f) {
                    $w->whereHas('user', function ($u) use ($like, $digits) {
                        $u->where(function ($x) use ($like, $digits) {
                            $x->where('first_name', 'ilike', $like)
                              ->orWhere('last_name', 'ilike', $like)
                              ->orWhere('patronymic', 'ilike', $like)
                              ->orWhere('email', 'ilike', $like)
                              ->orWhereRaw("(coalesce(last_name,'') || ' ' || coalesce(first_name,'')) ilike ?", [$like])
                              ->orWhereRaw("(coalesce(first_name,'') || ' ' || coalesce(last_name,'')) ilike ?", [$like]);
                            if (strlen($digits) >= 3) {
                                $x->orWhereRaw("regexp_replace(coalesce(phone,''), '\\D', '', 'g') like ?", ['%' . $digits . '%']);
                            }
                        });
                    })->orWhereHas('template', fn($t) => $t->where('name', 'ilike', $like));
                    if (ctype_digit(ltrim($f['q'], '#'))) {
                        $w->orWhere('id', (int) ltrim($f['q'], '#'));
                    }
                });
            });
    }

    /** Строки отчёта: ФИО, телефон, продления, повторные покупки. */
    private function reportRows($user, array $f): array
    {
        $subs = $this->filteredQuery($user, $f)->with(['user', 'template'])->orderBy('id')->get();
        if ($subs->isEmpty()) return [];

        $extended = \App\Models\SubscriptionCouponLog::query()
            ->where('entity_type', 'subscription')->where('action', 'extended')
            ->whereIn('entity_id', $subs->pluck('id'))
            ->selectRaw('entity_id, count(*) as c')->groupBy('entity_id')
            ->pluck('c', 'entity_id');

        // Покупки игрока по тому же шаблону (по всей базе организатора, а не только по фильтру)
        $userIds = $subs->pluck('user_id')->unique();
        $all = Subscription::whereIn('user_id', $userIds)
            ->whereIn('template_id', $subs->pluck('template_id')->unique())
            ->get(['id', 'user_id', 'template_id', 'organizer_id'])
            ->groupBy(fn($s) => $s->organizer_id . ':' . $s->user_id . ':' . $s->template_id);

        return $subs->map(function ($s) use ($extended, $all) {
            $u = $s->user;
            $group = $all->get($s->organizer_id . ':' . $s->user_id . ':' . $s->template_id, collect())->pluck('id')->sort()->values();
            $n = $group->search($s->id);
            $total = max(1, $group->count());
            return [
                'id'         => $s->id,
                'name'       => $u ? (trim(($u->last_name ?? '') . ' ' . ($u->first_name ?? '') . ' ' . ($u->patronymic ?? '')) ?: ($u->email ?? '#' . $s->user_id)) : '#' . $s->user_id,
                'phone'      => $u?->formatted_phone ?: ($u->phone ?? ''),
                'template'   => $s->template->name ?? '',
                'visits'     => $s->visits_remaining . ' / ' . $s->visits_total,
                'status'     => __('subscriptions.st_' . $s->displayStatus()),
                'starts'     => $s->starts_at?->format('d.m.Y') ?? '',
                'expires'    => $s->expires_at?->format('d.m.Y') ?? '∞',
                'extended'   => (int) ($extended[$s->id] ?? 0),
                'purchase_n' => $n === false ? 1 : $n + 1,
                'repeat'     => $total - 1,
            ];
        })->all();
    }

    private function reportHeaders(): array
    {
        return ['ID', __('subscriptions.col_player'), __('subscriptions.rep_phone'), __('subscriptions.col_template'),
            __('subscriptions.col_visits'), __('subscriptions.col_status'), __('subscriptions.rep_starts'),
            __('subscriptions.col_expires'), __('subscriptions.rep_extended'), __('subscriptions.rep_purchase_n'),
            __('subscriptions.rep_repeat')];
    }

    // GET /subscriptions/export/pdf
    public function exportPdf(Request $request)
    {
        $user = $request->user();
        app(\App\Services\EventAccessService::class)->ensureCanUseSubs($user);
        $f = $this->filters($request);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('subscriptions.report_pdf', [
            'rows'    => $this->reportRows($user, $f),
            'headers' => $this->reportHeaders(),
            'filters' => $this->filterSummary($f),
            'brand'   => $user->brand,
        ])->setPaper('a4', 'landscape');

        return $pdf->download('subscriptions-' . now()->format('Ymd_His') . '.pdf');
    }

    // GET /subscriptions/export/xlsx
    public function exportXlsx(Request $request)
    {
        $user = $request->user();
        app(\App\Services\EventAccessService::class)->ensureCanUseSubs($user);
        $rows = $this->reportRows($user, $this->filters($request));

        $data = [$this->reportHeaders()];
        foreach ($rows as $r) {
            $data[] = array_values($r);
        }
        $path = \App\Support\SimpleXlsx::write($data, 'Абонементы');

        return response()->download($path, 'subscriptions-' . now()->format('Ymd_His') . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function filterSummary(array $f): string
    {
        $parts = [];
        if ($f['q'] !== '') $parts[] = __('subscriptions.flt_q') . ': ' . $f['q'];
        if ($f['status'] !== '') $parts[] = __('subscriptions.col_status') . ': ' . __('subscriptions.st_' . $f['status']);
        if ($f['template'] > 0) $parts[] = __('subscriptions.col_template') . ': ' . (SubscriptionTemplate::find($f['template'])->name ?? $f['template']);
        return $parts ? implode('; ', $parts) : __('subscriptions.rep_no_filters');
    }

    // Отменить абонемент (организатор/мастер-помощник/админ)
    public function cancel(Request $request, Subscription $subscription)
    {
        $this->authorizeSubscription($subscription);

        if (!in_array($subscription->status, ['active', 'frozen'], true)) {
            return back()->with('error', __('subscriptions.sub_cancel_not_allowed'));
        }

        $this->service->cancel($subscription);

        $authUser = $request->user();
        if (!$authUser->isAdmin() && (int) $subscription->organizer_id !== (int) $authUser->id) {
            app(StaffLogService::class)->log($authUser, (int) $subscription->organizer_id, 'cancel_subscription', 'subscription', $subscription->id, "Отменил абонемент #{$subscription->id}");
        }

        return back()->with('status', __('subscriptions.sub_cancel_done', ['id' => $subscription->id]));
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
