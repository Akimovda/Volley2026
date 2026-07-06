<?php

namespace App\Http\Controllers;

use App\Models\CourtBooking;
use App\Models\Location;
use App\Models\LocationCourt;
use App\Models\User;
use App\Services\CourtBookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class ClubBookingController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user && ($user->is_club_manager || $user->isAdmin()), 403);

        $locationIds = $user->isAdmin()
            ? Location::whereNotNull('owner_id')->pluck('id')
            : Location::where('owner_id', $user->id)->pluck('id');

        $courtIds = LocationCourt::whereHas('direction', function ($q) use ($locationIds) {
            $q->whereIn('location_id', $locationIds);
        })->pluck('id');

        $base = CourtBooking::whereIn('court_id', $courtIds)
            ->with(['court.direction.location', 'user', 'event']);

        $pending = (clone $base)->where('status', CourtBooking::STATUS_PENDING)
            ->orderBy('starts_at')->get();

        $active = (clone $base)->whereIn('status', [CourtBooking::STATUS_CONFIRMED, CourtBooking::STATUS_PAID])
            ->where('ends_at', '>=', now())
            ->orderBy('starts_at')->get();

        $history = (clone $base)->where(function ($q) {
            $q->whereIn('status', [CourtBooking::STATUS_CANCELLED, CourtBooking::STATUS_EXPIRED])
                ->orWhere(function ($q2) {
                    $q2->whereIn('status', [CourtBooking::STATUS_CONFIRMED, CourtBooking::STATUS_PAID])
                        ->where('ends_at', '<', now());
                });
        })->orderByDesc('starts_at')->limit(100)->get();

        $locations = Location::whereIn('id', $locationIds)
            ->with(['directions' => fn ($q) => $q->where('is_active', true)
                ->with(['courts' => fn ($q2) => $q2->where('is_active', true)->orderBy('sort_order')])])
            ->get();

        return view('club.bookings', compact('pending', 'active', 'history', 'locations'));
    }

    public function storeManual(Request $request, CourtBookingService $service)
    {
        $user = $request->user();
        abort_unless($user && ($user->is_club_manager || $user->isAdmin()), 403);

        $data = $request->validate([
            'court_ids'      => ['required', 'array', 'min:1'],
            'court_ids.*'    => ['integer', 'exists:location_courts,id'],
            'date'          => ['required', 'date_format:Y-m-d'],
            'time_from'     => ['required', 'date_format:H:i'],
            'time_to'       => ['required', 'date_format:H:i'],
            'title'         => ['nullable', 'string', 'max:150'],
            'color'         => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'organizer_id'  => ['nullable', 'required_without:guest_name', 'integer', 'exists:users,id'],
            'guest_name'    => ['nullable', 'required_without:organizer_id', 'string', 'max:150'],
            'guest_phone'   => ['nullable', 'string', 'max:30'],
            'status'        => ['required', Rule::in([CourtBooking::STATUS_CONFIRMED, CourtBooking::STATUS_PAID])],
            'repeat'        => ['nullable', Rule::in(array_merge([CourtBooking::REPEAT_NONE], CourtBooking::REPEAT_OPTIONS))],
            'repeat_until'  => ['required_if:repeat,' . implode(',', CourtBooking::REPEAT_OPTIONS), 'nullable', 'date_format:Y-m-d', 'after_or_equal:date'],
        ]);

        $courts = LocationCourt::with('direction.location')->whereIn('id', $data['court_ids'])->get();
        if ($courts->count() !== count($data['court_ids'])) {
            return back()->with('error', __('club.court_not_found'));
        }

        foreach ($courts as $court) {
            try {
                $service->assertCanManageCourt($court, $user);
            } catch (\InvalidArgumentException $e) {
                abort(403, $e->getMessage());
            }
        }

        $tz = $courts->first()->direction->location->effectiveTimezone();
        $startsAt = Carbon::parse($data['date'] . ' ' . $data['time_from'], $tz)->setTimezone('UTC');
        $endsAt = Carbon::parse($data['date'] . ' ' . $data['time_to'], $tz)->setTimezone('UTC');

        $repeat = $data['repeat'] ?? CourtBooking::REPEAT_NONE;
        $repeatUntilUtc = null;
        if ($repeat !== CourtBooking::REPEAT_NONE) {
            $repeatUntilDate = Carbon::parse($data['repeat_until'], $tz);
            if ($repeatUntilDate->gt(Carbon::parse($data['date'], $tz)->addMonths(3))) {
                return back()->with('error', __('club.repeat_until_too_far'));
            }
            $repeatUntilUtc = Carbon::parse($data['repeat_until'] . ' 23:59:59', $tz)->setTimezone('UTC');
        }

        $bookingUser = !empty($data['organizer_id']) ? User::find($data['organizer_id']) : null;
        $extra = ['title' => $data['title'] ?? null, 'color' => $data['color'] ?? null];

        try {
            $result = $service->createManualMultiCourt(
                $courts->all(),
                $startsAt,
                $endsAt,
                $bookingUser,
                $data['guest_name'] ?? null,
                $data['guest_phone'] ?? null,
                $data['status'],
                $extra,
                $repeat,
                $repeatUntilUtc
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($repeat !== CourtBooking::REPEAT_NONE || !empty($result['skipped'])) {
            $message = __('club.booking_series_created', ['count' => count($result['created'])]);
            if (!empty($result['skipped'])) {
                $message .= ' ' . __('club.skipped_dates', ['dates' => implode(', ', $result['skipped'])]);
            }
            return back()->with('success', $message);
        }

        return back()->with('success', __('club.booking_saved'));
    }

    public function update(Request $request, CourtBooking $booking, CourtBookingService $service)
    {
        $user = $request->user();
        abort_unless($user, 403);

        $data = $request->validate([
            'court_id'      => ['required', 'integer', 'exists:location_courts,id'],
            'date'          => ['required', 'date_format:Y-m-d'],
            'time_from'     => ['required', 'date_format:H:i'],
            'time_to'       => ['required', 'date_format:H:i'],
            'title'         => ['nullable', 'string', 'max:150'],
            'color'         => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'organizer_id'  => ['nullable', 'required_without:guest_name', 'integer', 'exists:users,id'],
            'guest_name'    => ['nullable', 'required_without:organizer_id', 'string', 'max:150'],
            'guest_phone'   => ['nullable', 'string', 'max:30'],
            'status'        => ['required', Rule::in([CourtBooking::STATUS_CONFIRMED, CourtBooking::STATUS_PAID])],
        ]);

        $court = LocationCourt::with('direction.location')->findOrFail($data['court_id']);
        $tz = $court->direction->location->effectiveTimezone();
        $startsAt = Carbon::parse($data['date'] . ' ' . $data['time_from'], $tz)->setTimezone('UTC');
        $endsAt = Carbon::parse($data['date'] . ' ' . $data['time_to'], $tz)->setTimezone('UTC');
        $bookingUser = !empty($data['organizer_id']) ? User::find($data['organizer_id']) : null;

        try {
            $result = $service->update($booking, $user, [
                'court_id'    => (int) $data['court_id'],
                'starts_at'   => $startsAt,
                'ends_at'     => $endsAt,
                'title'       => $data['title'] ?? null,
                'color'       => $data['color'] ?? null,
                'status'      => $data['status'],
                'user'        => $bookingUser,
                'guest_name'  => $data['guest_name'] ?? null,
                'guest_phone' => $data['guest_phone'] ?? null,
            ]);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $updated = $result['booking'];
        if ($result['schedule_changed'] && $updated->user_id) {
            app(\App\Services\UserNotificationService::class)
                ->createCourtBookingChangedNotification($updated->user_id, $updated->load('court.direction.location'));
        }

        return back()->with('success', __('club.booking_updated'));
    }

    public function cancel(Request $request, CourtBooking $booking, CourtBookingService $service)
    {
        $user = $request->user();
        abort_unless($user, 403);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
            'scope'  => ['nullable', Rule::in(['only_this', 'this_and_following'])],
        ]);

        $scope = ($data['scope'] ?? 'only_this') === 'this_and_following' ? 'this_and_following' : 'this';

        try {
            $result = $service->cancel($booking, $user, $data['reason'] ?? null, $scope);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $notificationService = app(\App\Services\UserNotificationService::class);
        $refundedIds = $result['refunded_ids'];
        foreach ($result['cancelled'] as $b) {
            if (!$b->user_id) {
                continue;
            }
            $notificationService->createCourtBookingCancelledNotification($b->user_id, $b->load('court.direction.location'), $data['reason'] ?? null);
            if (in_array($b->id, $refundedIds, true)) {
                $notificationService->createCourtBookingRefundedNotification($b->user_id, $b);
            }
        }

        return back()->with('success', __('club.booking_cancelled'));
    }

    public function confirm(Request $request, CourtBooking $booking, CourtBookingService $service)
    {
        $user = $request->user();
        abort_unless($user, 403);

        try {
            $service->confirm($booking, $user);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($booking->user_id) {
            app(\App\Services\UserNotificationService::class)
                ->createCourtBookingConfirmedNotification($booking->user_id, $booking->load('court.direction.location'));
        }

        return back()->with('success', 'Бронь подтверждена.');
    }

    public function reject(Request $request, CourtBooking $booking, CourtBookingService $service)
    {
        $user = $request->user();
        abort_unless($user, 403);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $notifyUserId = $booking->user_id;
        $bookingForNotification = $booking->load('court.direction.location');

        try {
            $service->reject($booking, $user, $data['reason'] ?? '');
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($notifyUserId) {
            app(\App\Services\UserNotificationService::class)
                ->createCourtBookingRejectedNotification($notifyUserId, $bookingForNotification, $data['reason'] ?? null);
        }

        return back()->with('success', 'Бронь отклонена.');
    }
}
