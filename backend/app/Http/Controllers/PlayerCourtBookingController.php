<?php

namespace App\Http\Controllers;

use App\Models\CourtBooking;
use App\Models\LocationCourt;
use App\Services\CourtBookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class PlayerCourtBookingController extends Controller
{
    /**
     * Фаза 5 — прямая бронь корта игроком со страницы локации.
     * Слот выбирается через GET /locations/{id}/booking-windows (тот же endpoint,
     * что и в форме создания события) — сервер здесь только перепроверяет
     * пересечение/рабочие часы/лимиты, как и полагается для доверенного бэкенд-пути.
     */
    public function store(Request $request, CourtBookingService $service)
    {
        $user = $request->user();

        $data = $request->validate([
            'court_id'  => ['required', 'integer', 'exists:location_courts,id'],
            'date'      => ['required', 'date_format:Y-m-d'],
            'time_from' => ['required', 'date_format:H:i'],
            'duration'  => ['required', 'integer', Rule::in([30, 60, 90, 120, 180])],
        ]);

        $court = LocationCourt::with('direction.location')->findOrFail($data['court_id']);
        $tz = $court->direction->location->effectiveTimezone();
        $startsAt = Carbon::parse($data['date'] . ' ' . $data['time_from'], $tz)->setTimezone('UTC');
        $endsAt = $startsAt->copy()->addMinutes((int) $data['duration']);

        try {
            $booking = $service->createByPlayer($user, $court, $startsAt, $endsAt);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($booking->payment_mode === CourtBooking::PAYMENT_MODE_PREPAID) {
            return back()->with('success', __('club.booking_pay_button', ['amount' => number_format((float) $booking->price_total, 0, ',', ' ')]))
                ->with('pay_booking_url', route('player.my-court-bookings'));
        }

        return back()->with('success', __('club.booking_pending_info'));
    }

    public function myBookings(Request $request)
    {
        $user = $request->user();

        $base = CourtBooking::where('user_id', $user->id)
            ->with(['court.direction.location', 'event']);

        $active = (clone $base)
            ->whereIn('status', [CourtBooking::STATUS_PENDING, CourtBooking::STATUS_CONFIRMED, CourtBooking::STATUS_PAID])
            ->where('ends_at', '>=', now())
            ->orderBy('starts_at')
            ->get();

        $history = (clone $base)
            ->where(function ($q) {
                $q->whereIn('status', [CourtBooking::STATUS_CANCELLED, CourtBooking::STATUS_EXPIRED])
                    ->orWhere('ends_at', '<', now());
            })
            ->orderByDesc('starts_at')
            ->limit(100)
            ->get();

        return view('player.bookings', compact('active', 'history'));
    }

    public function cancel(Request $request, CourtBooking $booking, CourtBookingService $service)
    {
        $user = $request->user();

        try {
            $refunded = $service->cancelByUser($booking, $user);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($refunded) {
            app(\App\Services\UserNotificationService::class)
                ->createCourtBookingRefundedNotification($user->id, $booking);

            return back()->with('success', __('club.booking_cancelled_refunded'));
        }

        return back()->with('success', __('club.booking_cancelled'));
    }

    /**
     * Фаза 4 — оплатить prepaid-бронь (после создания или пока не истёк TTL).
     * POST /my/bookings/{booking}/pay — редиректит на страницу оплаты ЮKassa.
     */
    public function pay(Request $request, CourtBooking $booking, \App\Services\PaymentService $paymentService)
    {
        $user = $request->user();

        if ((int) $booking->user_id !== (int) $user->id) {
            abort(403);
        }

        if ($booking->status !== CourtBooking::STATUS_PENDING || $booking->payment_mode !== CourtBooking::PAYMENT_MODE_PREPAID) {
            return back()->with('error', __('club.booking_pay_not_available'));
        }

        if ($booking->expires_at && now()->gte($booking->expires_at)) {
            return back()->with('error', __('club.booking_pay_expired'));
        }

        $booking->loadMissing('court.direction.location');
        $ownerId = $booking->court?->direction?->location?->owner_id;
        $settings = $ownerId ? \App\Models\PaymentSetting::where('organizer_id', $ownerId)->first() : null;

        if (!$settings || !$settings->isYoomoneyReady()) {
            return back()->with('error', __('club.booking_pay_not_available'));
        }

        try {
            $payment = $paymentService->createForBooking($booking, $settings, route('player.my-court-bookings'));
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', __('club.booking_pay_failed'));
        }

        return redirect()->away($payment->yoomoney_confirmation_url);
    }
}
