<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OccurrenceParticipantsController extends Controller
{
    public function index($occurrence)
    {
        $registrations = EventRegistration::query()
            ->where('occurrence_id', $occurrence)
            ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
            ->where('status', 'confirmed')
            ->with(['user' => function($q) {
                $q->select('id', 'name', 'first_name', 'last_name', 'classic_level', 'avatar_media_id', 'profile_photo_path');
            }])
            ->orderBy('id')
            ->get();

        $players = $registrations->map(function ($reg) {
            $u = $reg->user;
            if (!$u) return null;

            $displayName = $u->name;
            if ($u->first_name || $u->last_name) {
                $displayName = trim(($u->last_name ?? '') . ' ' . ($u->first_name ?? ''));
            }

            return [
                'id'        => $u->id,
                'name'      => $displayName,
                'position'  => $reg->position,
                'avatar'    => $u->profile_photo_url,
                'level'     => $u->classic_level ?? 0,
                'group_key' => $reg->group_key ?? null,
                'url'       => '/user/' . $u->id,
            ];
        })->filter()->values();

        return response()->json($players);
    }
}