<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LocationController extends Controller
{
    public function quickStore(Request $request)
    {
        $user = $request->user();
        $role = (string)($user->role ?? 'user');

        if (!in_array($role, ['admin', 'organizer', 'staff'], true)) {
            abort(403);
        }

        // staff -> organizer_id через organizer_staff
        $organizerId = null;
        if ($role === 'organizer') {
            $organizerId = (int)$user->id;
        } elseif ($role === 'staff') {
            $row = DB::table('organizer_staff')
                ->where('staff_user_id', (int)$user->id)
                ->orderBy('id')
                ->first(['organizer_id']);

            if (!$row) {
                return $this->respond($request, false, 'Staff не привязан к organizer — создание локаций запрещено.');
            }
            $organizerId = (int)$row->organizer_id;
        } else {
            // admin: может создавать общую или под organizer (упростим: создаём общую)
            $organizerId = null;
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $location = new Location();
        $location->name = $data['name'];
        $location->address = $data['address'] ?? null;
        $location->organizer_id = $organizerId; // organizer/staff => их organizer, admin => общая
        $location->save();

        return $this->respond($request, true, 'Локация создана ✅', [
            'id' => $location->id,
            'name' => $location->name,
        ]);
    }

    private function respond(Request $request, bool $ok, string $message, array $payload = [])
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => $ok,
                'message' => $message,
                'data' => $payload,
            ], $ok ? 200 : 422);
        }

        return $ok
            ? back()->with('status', $message)
            : back()->with('error', $message);
    }
}
