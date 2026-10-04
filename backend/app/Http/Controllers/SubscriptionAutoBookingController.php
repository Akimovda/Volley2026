<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Subscription;
use App\Models\SubscriptionAutoBooking;
use App\Services\EventRegistrationGuard;
use App\Services\EventRoleSlotService;
use App\Services\EventVisibilityService;
use Illuminate\Http\Request;

class SubscriptionAutoBookingController extends Controller
{
    public const MAX_PER_SUBSCRIPTION = 10;

    public function store(Request $request, Subscription $subscription)
    {
        $user = $request->user();
        abort_unless((int) $subscription->user_id === (int) $user->id, 403);

        $subscription->loadMissing('template');
        if (!$subscription->isActive() || !$subscription->template?->auto_booking_enabled) {
            return back()->with('error', __('subscriptions.ab_not_available'));
        }

        $data = $request->validate([
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'position' => ['nullable', 'string', 'max:32'],
        ]);

        if ($subscription->autoBookings()->count() >= self::MAX_PER_SUBSCRIPTION) {
            return back()->with('error', __('subscriptions.ab_limit', ['max' => self::MAX_PER_SUBSCRIPTION]));
        }

        $event = Event::with('gameSettings')->findOrFail($data['event_id']);

        if (!$subscription->template->appliesToEvent($event->id)) {
            return back()->with('error', __('subscriptions.ab_event_not_applicable'));
        }

        $visibility = app(EventVisibilityService::class);
        if ($visibility->isPrivateEventRow($event) && !$visibility->canViewPrivateEvent($event, $user)) {
            abort(403);
        }

        if (in_array($event->registration_mode, ['team_classic', 'team_beach'], true)) {
            return back()->with('error', __('subscriptions.ab_team_not_supported'));
        }

        $slotService = app(EventRoleSlotService::class);
        $mainRoles = $slotService->mainRoles($event);
        $position = $data['position'] ?? null;

        if ($slotService->requiresPositionChoice($event)) {
            // Классика с амплуа: позиция ОБЯЗАТЕЛЬНА — без неё автозапись не сработает.
            if (!$position) {
                return back()->with('error', __('subscriptions.ab_position_required'));
            }
            if (!in_array($position, $mainRoles, true)) {
                return back()->with('error', __('subscriptions.ab_position_unavailable'));
            }
        } else {
            $position = $mainRoles[0] ?? null;
        }

        $occurrence = $event->occurrences()
            ->where('starts_at', '>', now())
            ->whereNull('cancelled_at')
            ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
            ->orderBy('starts_at')
            ->first();

        if (!$occurrence) {
            return back()->with('error', __('subscriptions.ab_no_future_occurrences'));
        }

        $guardResult = app(EventRegistrationGuard::class)
            ->checkStaticEligibility($user, $occurrence, $position);
        if (!$guardResult->allowed) {
            return back()->with('error', implode(' ', $guardResult->errors));
        }

        try {
            SubscriptionAutoBooking::create([
                'subscription_id' => $subscription->id,
                'user_id'         => $user->id,
                'event_id'        => $event->id,
                'position'        => $position,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            return back()->with('error', __('subscriptions.ab_already_exists'));
        }

        return back()->with('status', __('subscriptions.ab_created'));
    }

    public function destroy(Request $request, SubscriptionAutoBooking $autoBooking)
    {
        abort_unless((int) $autoBooking->user_id === (int) $request->user()->id, 403);

        $autoBooking->delete();

        return back()->with('status', __('subscriptions.ab_deleted'));
    }
}
