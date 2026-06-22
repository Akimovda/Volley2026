<?php

namespace App\Http\Controllers;

use App\Models\ActivitySession;
use App\Services\AthleteProfileService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityDashboardController extends Controller
{
    public function __construct(private readonly AthleteProfileService $profileService) {}

    private function checkGate(Request $request): void
    {
        $user = $request->user();
        if (!config('activity.recording_open') && !$user->isAdmin()) {
            abort(403);
        }
    }

    public function index(Request $request): View
    {
        $this->checkGate($request);

        $user      = $request->user();
        $direction = $request->query('direction', 'all');

        $query = ActivitySession::where('user_id', $user->id)
            ->where('status', 'completed')
            ->with(['occurrence.event'])
            ->orderByDesc('started_at');

        if (in_array($direction, ['classic', 'beach'], true)) {
            $query->where('direction', $direction);
        }

        $sessions = $query->paginate(20)->withQueryString();

        $totalCount  = ActivitySession::where('user_id', $user->id)->where('status', 'completed')->count();
        $lastSession = ActivitySession::where('user_id', $user->id)
            ->where('status', 'completed')
            ->orderByDesc('started_at')
            ->first();

        $profile         = $user->athleteProfile;
        $hasThresholds   = ($profile && $profile->max_hr) || $user->birth_date;
        $zoneThresholds  = $hasThresholds ? $this->profileService->zoneThresholds($user) : null;

        return view('activity.index', compact('sessions', 'direction', 'totalCount', 'lastSession', 'zoneThresholds'));
    }

    public function show(Request $request, ActivitySession $session): View
    {
        $this->checkGate($request);

        if ($session->user_id !== $request->user()->id) {
            abort(403);
        }

        $user    = $request->user();
        $zones   = $this->profileService->zoneThresholds($user);
        $samples = $session->samples()
            ->orderBy('t_offset_sec')
            ->get(['t_offset_sec', 'bpm'])
            ->toArray();

        // Прорядить если сэмплов > 2000
        if (count($samples) > 2000) {
            $step    = (int) ceil(count($samples) / 2000);
            $samples = array_values(array_filter(
                $samples,
                fn ($_, $i) => $i % $step === 0,
                ARRAY_FILTER_USE_BOTH
            ));
        }

        $hasJumps = is_array($session->tracked_capabilities)
            && in_array('jumps', $session->tracked_capabilities, true);

        $sessionTitle = $session->occurrence?->event?->title
            ?? __('activity.session_free_training');

        return view('activity.show', compact(
            'session', 'samples', 'zones', 'hasJumps', 'sessionTitle'
        ));
    }
}
