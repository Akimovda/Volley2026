<?php

namespace App\Http\Controllers;

use App\Models\SchoolTrainer;
use App\Models\VolleyballSchool;
use App\Services\SchoolTrainerService;
use App\Services\TrainerRateService;
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

        return view('volleyball_school.trainers', compact('school', 'memberships', 'currentRates'));
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
