<?php
namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Services\CouponService;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function __construct(private CouponService $service) {}

    // Мои купоны (игрок)
    public function my(Request $request)
    {
        $coupons = Coupon::with(['template', 'organizer'])
            ->where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->get();

        return view('coupons.my', compact('coupons'));
    }

    // Активировать купон по коду (из ссылки)
    public function activate(Request $request, string $code)
    {
        $coupon = $this->service->findByCode($code);

        if (!$coupon) {
            return redirect()->route('home')->with('error', 'Купон не найден ❌');
        }

        if ($coupon->status !== 'active') {
            return redirect()->route('coupons.my')->with('error', 'Купон уже использован или истёк ❌');
        }

        // Купон уже у этого пользователя
        if ($coupon->user_id === $request->user()->id) {
            return redirect()->route('coupons.my')->with('status', 'Этот купон уже у вас ✅');
        }

        // Купон выдан другому пользователю — передаём если разрешено
        if ($coupon->user_id !== null && $coupon->template->transfer_enabled) {
            $this->service->transfer($coupon, $request->user()->id);
            return redirect()->route('coupons.my')
                ->with('status', "🎟 Купон {$code} получен! Скидка {$coupon->getDiscountPct()}%");
        }

        // Купон без владельца (выдан по ссылке) — привязываем
        if ($coupon->user_id === null) {
            $coupon->update(['user_id' => $request->user()->id]);
            \App\Models\SubscriptionCouponLog::write('coupon', $coupon->id, 'activated', [
                'channel' => $coupon->issue_channel,
                'code'    => $code,
            ], $request->user()->id);

            return redirect()->route('coupons.my')
                ->with('status', "🎟 Купон {$code} активирован! Скидка {$coupon->getDiscountPct()}%");
        }

        return redirect()->route('coupons.my')
            ->with('error', 'Купон принадлежит другому пользователю ❌');
    }

    // Передача купона
    public function transfer(Request $request, Coupon $coupon)
    {
        if ($coupon->user_id !== $request->user()->id) abort(403);

        $data = $request->validate([
            'to_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $this->service->transfer($coupon, $data['to_user_id']);
        return back()->with('status', '✅ Купон передан');
    }

    // Список купонов организатора
    public function orgIndex(Request $request)
    {
        $user = $request->user();
        $coupons = Coupon::with(['user', 'template'])
            ->when(!$user->isAdmin(), fn($q) => $q->where('organizer_id', $user->id))
            ->orderByDesc('id')
            ->paginate(30);

        return view('coupons.org_index', compact('coupons'));
    }
}
