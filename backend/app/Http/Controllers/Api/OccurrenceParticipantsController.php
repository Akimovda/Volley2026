<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventOccurrence;
use App\Models\EventRegistration;
use Illuminate\Support\Facades\DB;

class OccurrenceParticipantsController extends Controller
{
    public function index($occurrenceId)
    {
        // Определяем направление мероприятия чтобы показать правильный уровень
        $occurrence = EventOccurrence::with('event:id,direction')->find($occurrenceId);
        $direction  = $occurrence?->event?->direction ?? 'classic';
        $isBeach    = $direction === 'beach';

        $registrations = EventRegistration::query()
            ->where('occurrence_id', $occurrenceId)
            ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
            ->where('status', 'confirmed')
            ->with(['user' => function($q) {
                $q->select('id', 'name', 'first_name', 'last_name',
                           'classic_level', 'beach_level',
                           'avatar_media_id', 'profile_photo_path', 'is_bot');
            }])
            ->orderBy('id')
            ->get();

        $players = $registrations->map(function ($reg) use ($isBeach) {
            $u = $reg->user;
            if (!$u) return null;

            $displayName = $u->name;
            if ($u->first_name || $u->last_name) {
                $displayName = trim(($u->last_name ?? '') . ' ' . ($u->first_name ?? ''));
            }

            // Уровень зависит от типа мероприятия
            $level = $isBeach
                ? ($u->beach_level   ?? 0)
                : ($u->classic_level ?? 0);

            return [
                'id'        => $u->id,
                'name'      => $displayName,
                'position'  => $reg->position,
                'avatar'    => $u->profile_photo_url,
                'level'     => $level,
                'is_bot'    => (bool) $u->is_bot,
                'group_key' => $reg->group_key ?? null,
                'url'       => '/user/' . $u->id,
            ];
        })->filter()->values();

        return response()->json($players);
    }
}
