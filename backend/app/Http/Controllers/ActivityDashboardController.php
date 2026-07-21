<?php

namespace App\Http\Controllers;

use App\Models\ActivitySession;
use App\Services\AthleteProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityDashboardController extends Controller
{
    public function __construct(private readonly AthleteProfileService $profileService) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        if (!$this->canRecordActivity($user)) abort(403);

        $canRecord = true; // гейт пройден
        $direction = $request->query('direction', 'all');
        $showGhosts = $request->boolean('show_ghosts');

        // Незавершённые сессии тоже показываем какое-то время — иначе после старта записи
        // на часах пользователь не видит её в списке вообще, пока не придёт finalize().
        // Дальше стеля (sync_stale_hours + 24ч) сессия перестаёт попадать в список.
        $pendingWindowHours = config('activity.sync_stale_hours', 6) + 24;

        $query = ActivitySession::where('user_id', $user->id)
            ->where(function ($q) use ($pendingWindowHours) {
                $q->where('status', 'completed')
                    ->orWhere('started_at', '>=', now()->subHours($pendingWindowHours));
            })
            ->with(['occurrence.event'])
            ->orderByDesc('started_at');

        if (in_array($direction, ['classic', 'beach'], true)) {
            $query->where('direction', $direction);
        }

        // "Призрачные" тренировки (см. ActivitySession::getIsGhostAttribute) — без пульса и прыжков;
        // либо короткая completed-заглушка, либо status=live, зависшая дольше sync_stale_hours
        // (см. report_activity_ghost_duplicates_2026-07-21.md — такие никогда не получат finalize(),
        // activity:cleanup-stale-sessions уберёт их физически не раньше ближайшего часового прогона —
        // до этого момента прячем их тем же фильтром, что и completed-пустышки).
        // По умолчанию скрыты из списка, но не удаляются автоматически.
        $isGhostSql = "COALESCE(samples_count, 0) = 0 AND COALESCE(jump_count, 0) = 0 AND ("
            . "(status = 'completed' AND COALESCE(duration_sec, 0) < 30)"
            . " OR (status = 'live' AND started_at < ?)"
            . ")";
        $isGhostBindings = [now()->subHours((int) config('activity.sync_stale_hours', 6))];

        $ghostCount = (clone $query)->whereRaw($isGhostSql, $isGhostBindings)->count();

        if (!$showGhosts) {
            $query->whereRaw("NOT ($isGhostSql)", $isGhostBindings);
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

        $preferredDeviceType = $profile?->preferred_device_type;
        $preferredDevice     = $profile?->preferredDevice;

        // Таймзона пользователя: из города → fallback UTC
        $userTimezone = $user->city?->timezone ?? 'UTC';

        return view('activity.index', compact(
            'sessions', 'direction', 'totalCount', 'lastSession',
            'zoneThresholds', 'canRecord', 'userTimezone',
            'preferredDeviceType', 'preferredDevice',
            'ghostCount', 'showGhosts'
        ));
    }

    public function destroy(Request $request, ActivitySession $session): RedirectResponse
    {
        if ($session->user_id !== $request->user()->id) {
            abort(403);
        }

        // FK на activity_hr_samples/activity_jump_events объявлены cascadeOnDelete — отдельно чистить не нужно.
        $session->delete();

        return redirect()->route('activity.index')->with('status', __('activity.session_deleted'));
    }

    public function show(Request $request, ActivitySession $session): View
    {
        $user = $request->user();
        if (!$this->canRecordActivity($user)) abort(403);

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

        // Показываем блок прыжков по факту данных, а не только по capability-флагу,
        // зафиксированному при старте сессии — он может быть устаревшим/неполным
        // (см. ActivitySessionService::start() — idempotent-возврат не пересчитывает capabilities),
        // а прыжки при этом уже реально приняты и агрегированы в finalize().
        $hasJumps = (is_array($session->tracked_capabilities) && in_array('jumps', $session->tracked_capabilities, true))
            || ($session->jump_count ?? 0) > 0
            || $session->jumps()->exists();

        $jumpEvents = $hasJumps
            ? $session->jumps()->orderBy('t_offset_ms')->get(['t_offset_ms', 'height_cm'])
            : collect();

        $sessionTitle  = $session->occurrence?->event?->title
            ?? __('activity.session_free_training');
        $userTimezone  = $user->city?->timezone ?? 'UTC';

        return view('activity.show', compact(
            'session', 'samples', 'zones', 'hasJumps', 'jumpEvents', 'sessionTitle', 'userTimezone'
        ));
    }
}
