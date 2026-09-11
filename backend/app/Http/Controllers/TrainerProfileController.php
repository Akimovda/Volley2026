<?php

namespace App\Http\Controllers;

use App\Services\TrainerProfileService;
use Illuminate\Http\Request;

class TrainerProfileController extends Controller
{
    public function edit(Request $request)
    {
        $user    = $request->user();
        $profile = $user->trainerProfile;

        $pendingMemberships = $user->schoolMemberships()
            ->where('status', \App\Models\SchoolTrainer::STATUS_PENDING)
            ->with('school:id,name,slug')
            ->get();

        $confirmedMemberships = $user->schoolMemberships()
            ->where('status', \App\Models\SchoolTrainer::STATUS_CONFIRMED)
            ->with('school:id,name,slug')
            ->get();

        return view('trainer.profile_edit', compact('profile', 'pendingMemberships', 'confirmedMemberships'));
    }

    public function update(Request $request, TrainerProfileService $service)
    {
        $data = $request->validate([
            'specialization'   => 'nullable|string|max:255',
            'bio'               => 'nullable|string|max:5000',
            'experience_years'  => 'nullable|integer|min:0|max:80',
            'is_public'         => 'boolean',
        ]);
        $data['is_public'] = $request->boolean('is_public');

        $service->upsert($request->user(), $data);

        return back()->with('status', __('trainers.profile_saved'));
    }
}
