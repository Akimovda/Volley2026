<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class OccurrenceParticipantsController extends Controller
{
    public function index($occurrence)
    {
        $players = DB::table('event_registrations')
            ->join('users', 'users.id', '=', 'event_registrations.user_id')
            ->leftJoinSub(
                DB::table('media')
                    ->where('model_type', 'App\\Models\\User')
                    ->where('collection_name', 'avatar')
                    ->select('model_id', DB::raw('MIN(id) as id'), DB::raw('MIN(file_name) as file_name'))
                    ->groupBy('model_id'),
                'm_avatar',
                'm_avatar.model_id', '=', 'users.id'
            )
            ->leftJoinSub(
                DB::table('media')
                    ->where('model_type', 'App\\Models\\User')
                    ->where('collection_name', 'photos')
                    ->select('model_id', DB::raw('MIN(id) as id'), DB::raw('MIN(file_name) as file_name'))
                    ->groupBy('model_id'),
                'm_photo',
                'm_photo.model_id', '=', 'users.id'
            )
            ->where('event_registrations.occurrence_id', $occurrence)
            ->where(function ($q) {
                $q->whereNull('event_registrations.is_cancelled')
                  ->orWhere('event_registrations.is_cancelled', false);
            })
            ->where('event_registrations.status', 'confirmed')
            ->select(
                'users.id',
                'users.name',
                'users.first_name',
                'users.last_name',
                'users.classic_level',
                'users.profile_photo_path',
                'event_registrations.position',
                DB::raw('COALESCE(m_avatar.id, m_photo.id) as media_id'),
                DB::raw('COALESCE(m_avatar.file_name, m_photo.file_name) as media_file')
            )
            ->orderBy('event_registrations.id')
            ->get()
            ->map(function ($u) {
                $displayName = $u->name;
                if ($u->first_name || $u->last_name) {
                    $displayName = trim(($u->last_name ?? '') . ' ' . ($u->first_name ?? ''));
                }

                $avatar = null;
                if ($u->media_id && $u->media_file) {
                    $baseName = pathinfo($u->media_file, PATHINFO_FILENAME);
                    $avatar = asset('storage/' . $u->media_id . '/conversions/' . $baseName . '-thumb.jpg');
                } elseif ($u->profile_photo_path) {
                    $avatar = asset('storage/' . $u->profile_photo_path);
                } else {
                    $avatar = 'https://ui-avatars.com/api/?name=' . urlencode($displayName) . '&size=64&background=random';
                }

                return [
                    'id'       => $u->id,
                    'name'     => $displayName,
                    'position' => $u->position,
                    'avatar'   => $avatar,
                    'level'    => $u->classic_level ?? 0,
                ];
            })
            ->values();

        return response()->json($players);
    }
}