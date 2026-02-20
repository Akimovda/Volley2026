<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    /**
     * Быстрое создание локации (AJAX) — строго только admin.
     * Роут дополнительно закрыт middleware can:is-admin.
     */
    public function quickStore(Request $request)
    {
        $user = $request->user();
        $role = (string)($user->role ?? 'user');

        // Строго только админ (даже если вдруг middleware снимут/сломают)
        if ($role !== 'admin') {
            abort(403);
        }

        $data = $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'address'    => ['nullable', 'string', 'max:255'],
            'city'       => ['nullable', 'string', 'max:255'],

            'short_text' => ['nullable', 'string', 'max:255'],
            'long_text'  => ['nullable', 'string'],

            // Берём диапазоны как у широты/долготы
            'lat'        => ['nullable', 'numeric', 'between:-90,90'],
            'lng'        => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $location = new Location();
        $location->organizer_id = null; // admin создаёт общую локацию
        $location->name = $data['name'];
        $location->address = $data['address'] ?? null;
        $location->city = $data['city'] ?? null;

        $location->short_text = $data['short_text'] ?? null;
        $location->long_text = $data['long_text'] ?? null;

        $location->lat = array_key_exists('lat', $data) ? $data['lat'] : null;
        $location->lng = array_key_exists('lng', $data) ? $data['lng'] : null;

        $location->save();

        return response()->json([
            'ok' => true,
            'message' => 'Локация создана ✅',
            'data' => [
                'id' => $location->id,
                'name' => $location->name,
                'address' => $location->address,
                'city' => $location->city,
                'short_text' => $location->short_text,
                'long_text' => $location->long_text,
                'lat' => $location->lat,
                'lng' => $location->lng,
            ],
        ], 200);
    }
}
