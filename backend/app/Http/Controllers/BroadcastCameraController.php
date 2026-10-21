<?php

namespace App\Http\Controllers;

use App\Models\BroadcastLink;
use App\Models\Event;
use App\Models\EventCourt;
use App\Services\Broadcast\BroadcastLinkService;
use App\Services\Broadcast\CourtStateBroadcaster;
use App\Services\Broadcast\CourtStatusLine;
use App\Services\EventAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** «Трансляция» в приложении: выбор площадки и старт эфира (данные для нативного плагина Stream). */
class BroadcastCameraController extends Controller
{
    public function __construct(
        protected EventAccessService $access,
        protected BroadcastLinkService $links,
        protected CourtStateBroadcaster $broadcaster,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $linkByCourt = BroadcastLink::where('role', BroadcastLink::ROLE_OVERLAY)->whereNotNull('court_id')->whereNull('revoked_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderBy('id')->get()->keyBy('court_id');
        $hasLink = $linkByCourt->keys()->all();

        $courts = EventCourt::with(['event', 'occurrence', 'override', 'destinations'])
            ->whereIn('id', $hasLink)->where('is_active', true)
            ->whereHas('event', fn ($q) => $q->where(fn ($w) => $w->where('format', 'tournament')->orWhere('collect_stats', true)))
            ->get()
            ->filter(fn (EventCourt $c) => $c->event && $this->access->canManageEvent($user, (int) $c->event->organizer_id));

        $now = now();
        $rows = $courts->map(function (EventCourt $c) use ($now, $linkByCourt) {
            $e = $c->event;
            $tz = $c->occurrence?->timezone ?: ($e->timezone ?: 'Europe/Moscow');
            $at = $c->occurrence?->starts_at ?: $e->starts_at;
            $dest = $c->destinations->firstWhere('is_default', true) ?: $c->destinations->sortBy('id')->first();
            return [
                'sort'    => $at ? abs($at->getTimestamp() - $now->getTimestamp()) : PHP_INT_MAX,
                'past'    => $at ? $at->copy()->addHours(12)->lt($now) : false,
                'court_id' => (int) $c->id, 'court' => $c->name, 'event_id' => (int) $e->id, 'event' => $e->title,
                'date'    => $at ? $at->copy()->setTimezone($tz)->format('d.m H:i') : null,
                'today'   => $at ? $at->copy()->setTimezone($tz)->isSameDay($now->copy()->setTimezone($tz)) : false,
                'status'  => CourtStatusLine::of($this->broadcaster->cached($c), $tz),
                // платформа/подпись — без ключа и без адреса сервера
                'dest'    => $dest ? ['platform' => $dest->platform, 'label' => $dest->label] : null,
                'overlay_url' => route('broadcast.overlay', ['token' => $linkByCourt[$c->id]->token]),
                'setup_url' => route('broadcast.setup', $e) . ($c->occurrence_id ? '?occurrence_id=' . $c->occurrence_id : ''),
            ];
        })->filter(fn ($r) => !$r['past'])->sortBy('sort')->take(60)->values();

        return view('broadcast.camera', ['rows' => $rows]);
    }

    public function start(Request $request, EventCourt $court): JsonResponse
    {
        $user = $request->user();
        $event = Event::find($court->event_id);
        abort_unless($event, 404);
        abort_unless($this->access->canManageEvent($user, (int) $event->organizer_id), 403);
        abort_unless($event->format === 'tournament' || $event->collect_stats, 404);

        $dest = $court->destinations()->where('is_default', true)->first() ?: $court->destinations()->orderBy('id')->first();
        if (!$dest) {
            return $this->noStore(response()->json([
                'ok' => false, 'code' => 'no_destination', 'message' => __('stream.cam_no_destination'),
                'setup_url' => route('broadcast.setup', $event) . ($court->occurrence_id ? '?occurrence_id=' . $court->occurrence_id : ''),
            ], 422));
        }

        $link = BroadcastLink::where('court_id', $court->id)->where('role', BroadcastLink::ROLE_OVERLAY)->whereNull('revoked_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->latest('id')->first()
            ?? $this->links->create($user, $event, BroadcastLink::ROLE_OVERLAY, $court);

        return $this->noStore(response()->json([
            'ok' => true,
            'overlay_url' => route('broadcast.overlay', ['token' => $link->token]),
            'server_url'  => $dest->server_url,
            'stream_key'  => $dest->stream_key,
            'court_name'  => $court->name,
            'event_title' => $event->title,
        ]));
    }

    private function noStore(JsonResponse $r): JsonResponse
    {
        return $r->header('Cache-Control', 'no-store, private');
    }
}
