<?php

namespace App\Http\Controllers;

use App\Models\EventOccurrence;
use App\Models\SchoolTrainer;
use App\Models\VolleyballSchool;
use App\Services\SchoolTrainerService;
use App\Services\TrainerRateService;
use App\Services\TrainerResolverService;
use App\Support\DateTime as DateTimeSupport;
use Carbon\Carbon;
use DomainException;
use Illuminate\Http\Request;

class SchoolTrainerController extends Controller
{
    private function authorizeManage(Request $request, VolleyballSchool $school): void
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || (int) $school->organizer_id === (int) $user->id, 403);
    }

    private function scopedMembership(VolleyballSchool $school, SchoolTrainer $membership): void
    {
        abort_if((int) $membership->school_id !== (int) $school->id, 404);
    }

    public function index(Request $request, VolleyballSchool $school)
    {
        $this->authorizeManage($request, $school);

        $memberships = $school->trainers()
            ->with(['trainer:id,first_name,last_name,name,avatar_media_id', 'invitedBy:id,first_name,last_name,name'])
            ->where('status', '!=', SchoolTrainer::STATUS_REMOVED)
            ->orderByDesc('id')
            ->get();

        $rateService = app(TrainerRateService::class);
        $now = now();
        $currentRates = [];
        foreach ($memberships as $m) {
            $currentRates[$m->user_id] = $rateService->effectiveRate($school, $m->user_id, $now);
        }

        $calendar = $this->buildWeekCalendar($request, $school, $memberships);

        return view('volleyball_school.trainers', compact('school', 'memberships', 'currentRates', 'calendar'));
    }

    /**
     * Фаза 4 (§6.3): недельный календарь занятости confirmed-тренеров школы.
     * Строки = confirmed-тренеры, колонки = дни недели (Пн..Вс, TZ школы),
     * блоки = occurrences событий организатора школы, где тренер эффективен (§2.1).
     */
    private function buildWeekCalendar(Request $request, VolleyballSchool $school, \Illuminate\Support\Collection $memberships): array
    {
        $tz = $school->effectiveTimezone();

        $confirmed = $memberships->where('status', SchoolTrainer::STATUS_CONFIRMED)->values();
        $confirmedIds = $confirmed->pluck('user_id')->map(fn ($v) => (int) $v)->all();

        $weekParam = $request->query('week');
        $anchor = null;
        if ($weekParam) {
            try {
                $anchor = Carbon::createFromFormat('Y-m-d', $weekParam, $tz);
            } catch (\Exception $e) {
                $anchor = null;
            }
        }
        $anchor ??= Carbon::now($tz);

        $weekStart = $anchor->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $weekEndExclusive = $weekStart->copy()->addDays(7);

        $utcFrom = DateTimeSupport::parseLocalToUtc($weekStart->format('Y-m-d H:i:s'), $tz);
        $utcTo   = DateTimeSupport::parseLocalToUtc($weekEndExclusive->format('Y-m-d H:i:s'), $tz);

        $days = collect(range(0, 6))->map(fn ($i) => $weekStart->copy()->addDays($i));

        $grid = [];
        foreach ($confirmedIds as $tid) {
            $grid[$tid] = array_fill(0, 7, []);
        }

        if ($confirmedIds) {
            $occurrences = EventOccurrence::query()
                ->whereHas('event', fn ($q) => $q->where('organizer_id', $school->organizer_id))
                ->whereBetween('starts_at', [$utcFrom, $utcTo])
                ->where(function ($q) {
                    $q->whereNull('is_cancelled')->orWhere('is_cancelled', false);
                })
                ->with(['event:id,title,direction,format', 'trainers:id'])
                ->get();

            $resolver = app(TrainerResolverService::class);

            foreach ($occurrences as $occ) {
                $effIds = array_values(array_intersect($resolver->effectiveTrainerIds($occ), $confirmedIds));
                if (!$effIds || !$occ->event) {
                    continue;
                }

                $rawUtc = $occ->getRawOriginal('starts_at');
                $local = DateTimeSupport::utcToLocal($rawUtc, $tz);
                if (!$local) {
                    continue;
                }
                $dayIndex = $local->dayOfWeekIso - 1;
                if ($dayIndex < 0 || $dayIndex > 6) {
                    continue;
                }

                $direction = $occ->event->direction ?? 'classic';
                $block = [
                    'title'      => $occ->event->title,
                    'time'       => $local->format('H:i'),
                    'url'        => route('events.show', ['event' => $occ->event_id]) . '?occurrence=' . $occ->id,
                    'color'      => $direction === 'beach' ? '#E7612F' : '#2967BA',
                    'tournament' => ($occ->event->format ?? null) === 'tournament',
                ];

                foreach ($effIds as $tid) {
                    $grid[$tid][$dayIndex][] = $block;
                }
            }
        }

        return [
            'weekStart' => $weekStart,
            'weekEndDisplay' => $weekStart->copy()->addDays(6),
            'prevWeek'  => $weekStart->copy()->subDays(7)->format('Y-m-d'),
            'nextWeek'  => $weekStart->copy()->addDays(7)->format('Y-m-d'),
            'thisWeek'  => Carbon::now($tz)->startOfWeek(Carbon::MONDAY)->format('Y-m-d'),
            'days'      => $days,
            'trainers'  => $confirmed,
            'grid'      => $grid,
        ];
    }

    public function store(Request $request, VolleyballSchool $school, SchoolTrainerService $service)
    {
        $this->authorizeManage($request, $school);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $service->invite($school, (int) $data['user_id'], (int) $request->user()->id);

        return back()->with('status', __('trainers.school_invite_sent'));
    }

    public function updatePermissions(Request $request, VolleyballSchool $school, SchoolTrainer $membership, SchoolTrainerService $service)
    {
        $this->authorizeManage($request, $school);
        $this->scopedMembership($school, $membership);

        $data = $request->validate([
            'can_manage_schedule'      => ['sometimes', 'boolean'],
            'can_manage_registrations' => ['sometimes', 'boolean'],
            'can_view_analytics'       => ['sometimes', 'boolean'],
        ]);

        $service->setPermissions($membership, [
            'can_manage_schedule'      => $request->boolean('can_manage_schedule'),
            'can_manage_registrations' => $request->boolean('can_manage_registrations'),
            'can_view_analytics'       => $request->boolean('can_view_analytics'),
        ]);

        return back()->with('status', __('trainers.school_permissions_saved'));
    }

    public function destroy(Request $request, VolleyballSchool $school, SchoolTrainer $membership, SchoolTrainerService $service)
    {
        $this->authorizeManage($request, $school);
        $this->scopedMembership($school, $membership);

        $service->remove($membership);

        return back()->with('status', __('trainers.school_trainer_removed'));
    }

    public function rate(Request $request, VolleyballSchool $school, SchoolTrainer $membership, TrainerRateService $service)
    {
        $this->authorizeManage($request, $school);
        $this->scopedMembership($school, $membership);

        $data = $request->validate([
            'rate_type'      => ['required', 'in:hourly,per_session,fixed_monthly'],
            'rate'            => ['required', 'numeric', 'min:0'],
            'effective_from'  => ['nullable', 'date'],
        ]);

        $effectiveFrom = null;
        if (!empty($data['effective_from'])) {
            $effectiveFrom = Carbon::createFromFormat('Y-m-d', $data['effective_from'], $school->effectiveTimezone())
                ->startOfDay();
        }

        try {
            $service->setRate(
                school: $school,
                userId: (int) $membership->user_id,
                rateType: $data['rate_type'],
                rate: (float) $data['rate'],
                effectiveFrom: $effectiveFrom,
                createdByUserId: (int) $request->user()->id,
            );
        } catch (DomainException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
            return back()->withErrors(['effective_from' => $e->getMessage()])->withInput();
        }

        return back()->with('status', __('trainers.school_rate_saved'));
    }
}
