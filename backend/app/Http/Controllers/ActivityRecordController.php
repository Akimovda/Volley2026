<?php

namespace App\Http\Controllers;

use App\Models\EventRegistration;
use App\Services\AthleteProfileService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityRecordController extends Controller
{
    public function __construct(private readonly AthleteProfileService $profileService) {}

    public function show(Request $request): View
    {
        $user = $request->user();

        if (!config('activity.recording_open') && !$user->isAdmin()) {
            abort(403);
        }

        $occurrenceId = $request->integer('occurrence') ?: null;

        $occurrences = collect();
        if (!$occurrenceId) {
            $occurrences = EventRegistration::where('user_id', $user->id)
                ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
                ->whereHas('occurrence', fn($q) => $q
                    ->where('starts_at', '>=', now()->subHours(3))
                    ->where('starts_at', '<=', now()->addDays(14))
                )
                ->with(['occurrence.event'])
                ->orderByDesc('id')
                ->limit(10)
                ->get()
                ->map(fn($r) => [
                    'id'         => $r->occurrence_id,
                    'title'      => $r->occurrence?->event?->title ?? '',
                    'starts_at'  => $r->occurrence?->starts_at?->format('d.m H:i') ?? '',
                ])
                ->filter(fn($o) => $o['id']);
        }

        $zones      = $this->profileService->zoneThresholds($user);
        $maxHr      = $this->profileService->effectiveMaxHr($user);
        $restingHr  = $this->profileService->effectiveRestingHr($user);

        return view('activity.record', compact('occurrenceId', 'occurrences', 'zones', 'maxHr', 'restingHr'));
    }
}
