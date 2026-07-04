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
            'court_id'      => ['required', 'integer', 'exists:location_courts,id'],
            'date'          => ['required', 'date_format:Y-m-d'],
            'time_from'     => ['required', 'date_format:H:i'],
            'time_to'       => ['required', 'date_format:H:i'],
            'organizer_id'  => ['nullable', 'required_without:guest_name', 'integer', 'exists:users,id'],
            'guest_name'    => ['nullable', 'required_without:organizer_id', 'string', 'max:150'],
            'guest_phone'   => ['nullable', 'string', 'max:30'],
            'status'        => ['required', Rule::in([CourtBooking::STATUS_CONFIRMED, CourtBooking::STATUS_PAID])],
        ]);

        $court = LocationCourt::with('direction.location')->findOrFail($data['court_id']);

        try {
            $service->assertCanManageCourt($court, $user);
        } catch (\InvalidArgumentException $e) {
            abort(403, $e->getMessage());
        }

        $location = $court->direction->location;
        $tz = $location->effectiveTimezone();
        $startsAt = Carbon::parse($data['date'] . ' ' . $data['time_from'], $tz)->setTimezone('UTC');
        $endsAt = Carbon::parse($data['date'] . ' ' . $data['time_to'], $tz)->setTimezone('UTC');

        $bookingUser = !empty($data['organizer_id']) ? User::find($data['organizer_id']) : null;

        try {
            $service->createManual(
                $court,
                $startsAt,
                $endsAt,
                $bookingUser,
                $data['guest_name'] ?? null,
                $data['guest_phone'] ?? null,
                $data['status']
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('club.booking_saved'));
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

        return back()->with('success', 'Бронь подтверждена.');
    }

    public function reject(Request $request, CourtBooking $booking, CourtBookingService $service)
    {
        $user = $request->user();
        abort_unless($user, 403);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $service->reject($booking, $user, $data['reason'] ?? '');
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Бронь отклонена.');
    }
}
