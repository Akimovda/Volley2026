<?php

namespace App\Http\Controllers;

use App\Models\AthleteProfile;
use App\Services\AthleteProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AthleteProfileController extends Controller
{
    public function __construct(private readonly AthleteProfileService $service) {}

    public function show(Request $request): View
    {
        $user    = $request->user();
        $profile = $user->athleteProfile;

        $suggestedMaxHr = null;
        if ($user->birth_date) {
            $age            = (int) \Carbon\Carbon::parse($user->birth_date)->age;
            $suggestedMaxHr = $this->service->suggestMaxHr($age);
        }

        $devices         = $user->athleteDevices()->orderByDesc('last_connected_at')->get();
        $hasHealthConsent = $user->hasHealthConsent();

        return view('profile.athlete', compact('user', 'profile', 'suggestedMaxHr', 'devices', 'hasHealthConsent'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'resting_hr' => ['nullable', 'integer', 'min:30', 'max:100'],
            'max_hr'     => ['nullable', 'integer', 'min:100', 'max:250'],
            'weight_kg'  => ['nullable', 'numeric', 'min:30', 'max:300'],
        ]);

        AthleteProfile::updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'resting_hr' => $data['resting_hr'] ?: null,
                'max_hr'     => $data['max_hr'] ?: null,
                'weight_kg'  => $data['weight_kg'] ?: null,
            ]
        );

        return redirect()->route('profile.athlete')->with('status', __('activity.saved'));
    }
}
