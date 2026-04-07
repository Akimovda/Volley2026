<?php
// app/Http/Controllers/Api/OccurrenceParticipantsController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class OccurrenceParticipantsController extends Controller
{
    public function index($occurrence)
    {
        // Один запрос: регистрации + пользователи + аватар (avatar) + fallback (photos)
        $players = DB::table('event_registrations')
            ->join('users', 'users.id', '=', 'event_registrations.user_id')
            ->leftJoin('media as m_avatar', function ($join) {
                $join->on('m_avatar.model_id', '=', 'users.id')
                     ->where('m_avatar.model_type', '=', 'App\\Models\\User')
                     ->where('m_avatar.collection_name', '=', 'avatar');
            })
            ->leftJoin('media as m_photo', function ($join) {
                $join->on('m_photo.model_id', '=', 'users.id')
                     ->where('m_photo.model_type', '=', 'App\\Models\\User')
                     ->where('m_photo.collection_name', '=', 'photos');
            })
            ->where('event_registrations.occurrence_id', $occurrence)
            ->where(function ($q) {
                $q->whereNull('event_registrations.is_cancelled')
                  ->orWhere('event_registrations.is_cancelled', false);
            })
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
            ->groupBy(
                'users.id', 'users.name', 'users.first_name', 'users.last_name',
                'users.classic_level', 'users.profile_photo_path',
                'event_registrations.position', 'event_registrations.id',
                'm_avatar.id', 'm_avatar.file_name',
                'm_photo.id', 'm_photo.file_name'
            )
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