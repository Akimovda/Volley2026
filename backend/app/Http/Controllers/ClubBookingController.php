<?php

namespace App\Http\Controllers;

use App\Models\CourtBooking;
use App\Models\Location;
use App\Models\LocationCourt;
use App\Services\CourtBookingService;
use Illuminate\Http\Request;

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

        return view('club.bookings', compact('pending', 'active', 'history'));
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
