<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventOccurrence;
use App\Models\EventRegistration;
use App\Models\PremiumSubscription;
use App\Services\EventVisibilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OccurrenceParticipantsController extends Controller
{
    public function __construct(private EventVisibilityService $visibility)
    {
    }

    public function index(Request $request, $occurrenceId)
    {
        // Определяем направление мероприятия чтобы показать правильный уровень
        $occurrence = EventOccurrence::with('event')->find($occurrenceId);
        $event      = $occurrence?->event;

        // Приватное мероприятие — та же проверка видимости, что и на странице
        // события (EventShowService::buildEventPage), включая доступ по ?token=.
        if ($event && $this->visibility->isPrivateEventRow($event)
            && !$this->visibility->canViewPrivateEvent($event, $request->user())) {
            abort(404);
        }

        $direction  = $event?->direction ?? 'classic';
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

        // Батчем, не по одному — избегаем N+1 (isPremium() на модели делает отдельный запрос на каждого)
        $userIds = $registrations->pluck('user.id')->filter()->unique()->values();
        $premiumUserIds = PremiumSubscription::where('status', 'active')
            ->where('expires_at', '>', now())
            ->whereIn('user_id', $userIds)
            ->pluck('user_id')
            ->flip();

        $players = $registrations->map(function ($reg) use ($isBeach, $premiumUserIds) {
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
                'id'         => $u->id,
                'name'       => $displayName,
                'position'   => $reg->position,
                'avatar'     => $u->profile_photo_url,
                'level'      => $level,
                'is_bot'     => (bool) $u->is_bot,
                'is_premium' => $premiumUserIds->has($u->id),
                'group_key'  => $reg->group_key ?? null,
                'url'        => '/user/' . $u->id,
            ];
        })->filter()->values();

        return response()->json($players);
    }
}
