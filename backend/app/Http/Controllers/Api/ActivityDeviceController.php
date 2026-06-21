<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AthleteDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityDeviceController extends Controller
{
    public function destroy(Request $request, AthleteDevice $device): JsonResponse
    {
        if ($device->user_id !== $request->user()->id) {
            abort(403);
        }

        $device->delete();

        return response()->json(['ok' => true]);
    }

    public function upsert(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ble_identifier' => ['required', 'string', 'max:255'],
            'name'           => ['required', 'string', 'max:255'],
            'model'          => ['nullable', 'string', 'max:255'],
            'protocol'       => ['nullable', 'string', 'max:50'],
        ]);

        $device = AthleteDevice::updateOrCreate(
            [
                'user_id'        => $request->user()->id,
                'ble_identifier' => $data['ble_identifier'],
            ],
            [
                'name'              => $data['name'],
                'model'             => $data['model'] ?? null,
                'protocol'          => $data['protocol'] ?? 'ble_hrp',
                'last_connected_at' => now(),
            ]
        );

        return response()->json(['device_id' => $device->id]);
    }
}
