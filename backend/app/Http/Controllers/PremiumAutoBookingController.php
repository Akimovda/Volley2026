<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\PremiumAutoBooking;
use App\Services\EventRegistrationGuard;
use App\Services\EventRoleSlotService;
use App\Services\EventVisibilityService;
use App\Services\PremiumService;
use Illuminate\Http\Request;

class PremiumAutoBookingController extends Controller
{
    public const MAX_JOBS = 5;

    public function searchEvents(Request $request)
    {
        $user = $request->user();
        $q = trim((string) $request->get('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['items' => []]);
        }

        $query = Event::query()
            ->with(['location.city'])
            ->where('allow_registration', true)
            ->whereNotIn('registration_mode', ['team_classic', 'team_beach'])
            ->where(function ($w) {
                $w->where('format', '!=', 'tournament')
                  ->orWhereNull('format')
                  ->orWhereIn('registration_mode', ['tournament_individual', 'king_beach']);
            })
            ->whereHas('occurrences', function ($oq) {
                $oq->where('starts_at', '>', now())
                    ->whereNull('cancelled_at')
                    ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)');
            });

        if (ctype_digit($q)) {
            $query->where('id', (int) $q);
        } else {
            $query->where(function ($w) use ($q) {
                $w->where('title', 'ILIKE', "%{$q}%")
                    ->orWhereHas('location', fn ($lq) => $lq->where('name', 'ILIKE', "%{$q}%"));
            });
        }

        app(EventVisibilityService::class)->applyPrivateVisibilityScope($query, $user);

        $events = $query->orderByDesc('id')->limit(20)->get();

        $slotService = app(EventRoleSlotService::class);

        $items = $events->map(function (Event $event) use ($slotService) {
            $nextOccurrence = $event->occurrences()
                ->where('starts_at', '>', now())
                ->whereNull('cancelled_at')
                ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
                ->orderBy('starts_at')
                ->first();

            $positions = $slotService->getSlots($event)
                ->where('role', '!=', 'reserve')
                ->values()
                ->map(fn ($slot) => [
                    'value' => $slot->role,
                    'label' => __('events.positions.' . $slot->role),
                ]);

            return [
                'id'         => $event->id,
                'label'      => '#' . $event->id . ' — ' . $event->title
                    . ($event->location ? ' («' . $event->location->name . '»)' : ''),
                'title'      => $event->title,
                'location'   => $event->location?->name,
                'city'       => $event->location?->city?->name,
                'next_at'    => $nextOccurrence?->starts_at
                    ? \Illuminate\Support\Carbon::parse($nextOccurrence->starts_at, 'UTC')->format('d.m.Y H:i')
                    : null,
                'positions'  => $positions->values(),
            ];
        })->filter(fn ($item) => $item['positions']->isNotEmpty())->values();

        return response()->json(['items' => $items]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        if (!app(PremiumService::class)->isPremium($user)) {
            return back()->with('error', __('premium.auto_booking_requires_premium'));
        }

        $data = $request->validate([
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'position' => ['required', 'string', 'max:32'],
        ]);

        $currentCount = PremiumAutoBooking::where('user_id', $user->id)->count();
        if ($currentCount >= self::MAX_JOBS) {
            return back()->with('error', __('premium.auto_booking_limit_reached', ['max' => self::MAX_JOBS]));
        }

        $event = Event::with('gameSettings')->findOrFail($data['event_id']);

        $visibility = app(EventVisibilityService::class);
        if ($visibility->isPrivateEventRow($event) && !$visibility->canViewPrivateEvent($event, $user)) {
            abort(403);
        }

        if (in_array($event->registration_mode, ['team_classic', 'team_beach'], true)) {
            return back()->with('error', __('premium.auto_booking_team_not_supported'));
        }

        $validRoles = app(EventRoleSlotService::class)->getSlots($event)
            ->where('role', '!=', 'reserve')
            ->pluck('role')
            ->all();

        if (!in_array($data['position'], $validRoles, true)) {
            return back()->with('error', __('premium.auto_booking_position_unavailable'));
        }

        $occurrence = $event->occurrences()
            ->where('starts_at', '>', now())
            ->whereNull('cancelled_at')
            ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
            ->orderBy('starts_at')
            ->first();

        if (!$occurrence) {
            return back()->with('error', __('premium.auto_booking_no_future_occurrences'));
        }

        $guardResult = app(EventRegistrationGuard::class)
            ->checkStaticEligibility($user, $occurrence, $data['position']);

        if (!$guardResult->allowed) {
            return back()->with('error', implode(' ', $guardResult->errors));
        }

        try {
            PremiumAutoBooking::create([
                'user_id'  => $user->id,
                'event_id' => $event->id,
                'position' => $data['position'],
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            return back()->with('error', __('premium.auto_booking_already_exists'));
        }

        return back()->with('status', __('premium.auto_booking_created'));
    }

    public function destroy(Request $request, PremiumAutoBooking $autoBooking)
    {
        if ($autoBooking->user_id !== $request->user()->id) {
            abort(403);
        }

        $autoBooking->delete();

        return back()->with('status', __('premium.auto_booking_deleted'));
    }
}
