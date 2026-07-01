<?php

namespace App\Http\Controllers;

use App\Models\AthleteDevice;
use App\Models\AthleteProfile;
use App\Services\AthleteProfileService;
use Illuminate\Http\JsonResponse;
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

        $devices          = $user->athleteDevices()->orderByDesc('last_connected_at')->get();
        $hasHealthConsent = $user->hasHealthConsent();
        $zoneThresholds   = $this->service->zoneThresholds($user);
        $usingDefaultHr   = !$user->birth_date && !($profile?->max_hr) && !($profile?->resting_hr);
        $preferredDevice  = $profile?->preferredDevice;

        return view('profile.athlete', compact('user', 'profile', 'suggestedMaxHr', 'devices', 'hasHealthConsent', 'zoneThresholds', 'usingDefaultHr', 'preferredDevice'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'resting_hr'       => ['nullable', 'integer', 'min:30', 'max:100'],
            'max_hr'           => ['nullable', 'integer', 'min:100', 'max:250'],
            'weight_kg'        => ['nullable', 'numeric', 'min:30', 'max:300'],
            'reach_classic_cm' => ['nullable', 'integer', 'min:100', 'max:350'],
            'reach_beach_cm'   => ['nullable', 'integer', 'min:100', 'max:350'],
        ]);

        AthleteProfile::updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'resting_hr'       => $data['resting_hr'] ?: null,
                'max_hr'           => $data['max_hr'] ?: null,
                'weight_kg'        => $data['weight_kg'] ?: null,
                'reach_classic_cm' => $data['reach_classic_cm'] ?: null,
                'reach_beach_cm'   => $data['reach_beach_cm'] ?: null,
            ]
        );

        return redirect()->route('profile.athlete')->with('status', __('activity.saved'));
    }

    public function setPreferredDevice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type'      => ['required', 'in:healthkit,ble'],
            'device_id' => ['nullable', 'integer', 'exists:athlete_devices,id'],
        ]);

        if ($validated['type'] === 'ble' && empty($validated['device_id'])) {
            return response()->json(['error' => 'device_id required for ble'], 422);
        }

        if ($validated['type'] === 'ble') {
            AthleteDevice::where('id', $validated['device_id'])
                ->where('user_id', $request->user()->id)
                ->firstOrFail();
        }

        $user = $request->user();
        AthleteProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'preferred_device_type' => $validated['type'],
                'preferred_device_id'   => $validated['type'] === 'ble' ? $validated['device_id'] : null,
            ]
        );

        return response()->json(['ok' => true]);
    }
}
