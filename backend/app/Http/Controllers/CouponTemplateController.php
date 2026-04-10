<?php
namespace App\Http\Controllers;

use App\Models\CouponTemplate;
use App\Models\Event;
use App\Models\User;
use App\Services\CouponService;
use Illuminate\Http\Request;

class CouponTemplateController extends Controller
{
    public function __construct(private CouponService $service) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $templates = CouponTemplate::with('organizer')
            ->when(!$user->isAdmin(), fn($q) => $q->where('organizer_id', $user->id))
            ->orderByDesc('id')
            ->paginate(20);

        return view('coupons.templates.index', compact('templates'));
    }

    public function create()
    {
        $user = auth()->user();
        $events = Event::where('organizer_id', $user->id)
            ->orderByDesc('id')->limit(100)->get();

        return view('coupons.templates.create', compact('events'));
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name'                => ['required', 'string', 'max:150'],
            'description'         => ['nullable', 'string', 'max:1000'],
            'event_ids'           => ['nullable', 'array'],
            'event_ids.*'         => ['integer'],
            'valid_from'          => ['nullable', 'date'],
            'valid_until'         => ['nullable', 'date'],
            'discount_pct'        => ['required', 'integer', 'min:1', 'max:100'],
            'uses_per_coupon'     => ['required', 'integer', 'min:1'],
            'cancel_hours_before' => ['required', 'integer', 'min:0'],
            'transfer_enabled'    => ['sometimes', 'boolean'],
            'issue_limit'         => ['nullable', 'integer', 'min:1'],
        ]);

        $organizerId = $user->isAdmin()
            ? ($request->input('organizer_id') ?? $user->id)
            : $user->id;

        CouponTemplate::create(array_merge($data, [
            'organizer_id'     => $organizerId,
            'transfer_enabled' => (bool)($data['transfer_enabled'] ?? false),
            'is_active'        => true,
        ]));

        return redirect()->route('coupon_templates.index')
            ->with('status', '✅ Шаблон купона создан!');
    }

    public function edit(CouponTemplate $couponTemplate)
    {
        $this->authorize($couponTemplate);
        $events = Event::where('organizer_id', $couponTemplate->organizer_id)
            ->orderByDesc('id')->limit(100)->get();

        return view('coupons.templates.edit', ['template' => $couponTemplate, 'events' => $events]);
    }

    public function update(Request $request, CouponTemplate $couponTemplate)
    {
        $this->authorize($couponTemplate);
        $data = $request->validate([
            'name'                => ['required', 'string', 'max:150'],
            'description'         => ['nullable', 'string', 'max:1000'],
            'event_ids'           => ['nullable', 'array'],
            'valid_from'          => ['nullable', 'date'],
            'valid_until'         => ['nullable', 'date'],
            'discount_pct'        => ['required', 'integer', 'min:1', 'max:100'],
            'uses_per_coupon'     => ['required', 'integer', 'min:1'],
            'cancel_hours_before' => ['required', 'integer', 'min:0'],
            'transfer_enabled'    => ['sometimes', 'boolean'],
            'issue_limit'         => ['nullable', 'integer', 'min:1'],
            'is_active'           => ['sometimes', 'boolean'],
        ]);

        $couponTemplate->update(array_merge($data, [
            'transfer_enabled' => (bool)($data['transfer_enabled'] ?? false),
            'is_active'        => (bool)($data['is_active'] ?? true),
        ]));

        return redirect()->route('coupon_templates.index')
            ->with('status', '✅ Шаблон купона обновлён!');
    }

    // Выдать купон пользователю
    public function issue(Request $request, CouponTemplate $couponTemplate)
    {
        $this->authorize($couponTemplate);
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'channel' => ['nullable', 'string', 'in:telegram,vk,max,inapp,manual'],
        ]);

        $coupon = $this->service->issue(
            $couponTemplate,
            $data['user_id'],
            $data['channel'] ?? 'manual',
            auth()->id()
        );

        return back()->with('status', "✅ Купон {$coupon->code} выдан пользователю #{$data['user_id']}");
    }

    private function authorize(CouponTemplate $template): void
    {
        $user = auth()->user();
        if (!$user->isAdmin() && $template->organizer_id !== $user->id) abort(403);
    }

    // Массовая выдача купонов
    public function bulkIssue(Request $request, CouponTemplate $couponTemplate)
    {
        $this->authorize($couponTemplate);

        $data = $request->validate([
            'user_ids'   => ['required', 'string'], // CSV: 1,2,3,4
            'channel'    => ['nullable', 'string', 'in:telegram,vk,max,inapp,manual'],
        ]);

        $userIds = array_filter(
            array_map('intval', explode(',', $data['user_ids'])),
            fn($id) => $id > 0
        );

        if (empty($userIds)) {
            return back()->with('error', 'Не указаны пользователи');
        }

        $issued  = 0;
        $skipped = 0;
        $errors  = [];

        foreach (array_unique($userIds) as $userId) {
            try {
                if (!$couponTemplate->canIssueMore()) {
                    $errors[] = "Лимит выдачи исчерпан на пользователе #{$userId}";
                    break;
                }

                // Проверяем существует ли пользователь
                if (!\App\Models\User::where('id', $userId)->exists()) {
                    $skipped++;
                    continue;
                }

                // Проверяем нет ли уже активного купона
                $exists = \App\Models\Coupon::where('user_id', $userId)
                    ->where('template_id', $couponTemplate->id)
                    ->where('status', 'active')
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                $this->service->issue(
                    $couponTemplate,
                    $userId,
                    $data['channel'] ?? 'manual',
                    auth()->id()
                );
                $issued++;

            } catch (\Throwable $e) {
                $errors[] = "Ошибка для #{$userId}: " . $e->getMessage();
            }
        }

        $msg = "✅ Выдано: {$issued}";
        if ($skipped > 0) $msg .= ", пропущено: {$skipped}";
        if (!empty($errors)) $msg .= ". Ошибки: " . implode('; ', array_slice($errors, 0, 3));

        return back()->with('status', $msg);
    }

    // Создать купон-ссылку для рассылки (без привязки к пользователю)
    public function issueLink(Request $request, CouponTemplate $couponTemplate)
    {
        $this->authorize($couponTemplate);

        $data = $request->validate([
            'count'   => ['required', 'integer', 'min:1', 'max:1000'],
            'channel' => ['required', 'string', 'in:telegram,vk,max,inapp,manual'],
        ]);

        $links = [];
        for ($i = 0; $i < $data['count']; $i++) {
            if (!$couponTemplate->canIssueMore()) break;

            // Создаём купон без user_id
            $coupon = \App\Models\Coupon::create([
                'user_id'        => null,
                'template_id'    => $couponTemplate->id,
                'organizer_id'   => $couponTemplate->organizer_id,
                'code'           => \App\Models\Coupon::generateCode(),
                'starts_at'      => $couponTemplate->valid_from,
                'expires_at'     => $couponTemplate->valid_until,
                'uses_total'     => $couponTemplate->uses_per_coupon,
                'uses_used'      => 0,
                'uses_remaining' => $couponTemplate->uses_per_coupon,
                'status'         => 'active',
                'issued_by'      => auth()->id(),
                'issue_channel'  => $data['channel'],
            ]);
            $couponTemplate->increment('issued_count');

            \App\Models\SubscriptionCouponLog::write('coupon', $coupon->id, 'issued', [
                'channel' => $data['channel'],
                'code'    => $coupon->code,
                'link'    => true,
            ], auth()->id());

            $links[] = [
                'code' => $coupon->code,
                'url'  => url('/coupon/' . $coupon->code),
            ];
        }

        return back()->with('status', "✅ Создано " . count($links) . " ссылок")
                     ->with('coupon_links', $links);
    }
}
