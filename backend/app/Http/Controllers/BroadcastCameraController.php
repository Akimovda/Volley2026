<?php

namespace App\Http\Controllers;

use App\Models\BroadcastChannel;
use App\Models\BroadcastDestination;
use App\Models\BroadcastLink;
use App\Models\Event;
use App\Models\EventCourt;
use App\Models\TournamentMatch;
use App\Services\Broadcast\BroadcastLinkService;
use App\Services\Broadcast\BroadcastTargetResolver;
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
        protected BroadcastTargetResolver $targets,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $linkByCourt = BroadcastLink::where('role', BroadcastLink::ROLE_OVERLAY)->whereNotNull('court_id')->whereNull('revoked_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderBy('id')->get()->keyBy('court_id');
        $hasLink = $linkByCourt->keys()->all();

        $courts = EventCourt::with(['event', 'occurrence', 'override'])
            ->whereIn('id', $hasLink)->where('is_active', true)
            ->whereHas('event', fn ($q) => $q->where(fn ($w) => $w->where('format', 'tournament')->orWhere('collect_stats', true)))
            ->get()
            ->filter(fn (EventCourt $c) => $c->event && $this->access->canManageEvent($user, (int) $c->event->organizer_id));

        $now = now();
        $rows = $courts->map(function (EventCourt $c) use ($now, $linkByCourt, $user) {
            $e = $c->event;
            $tz = $c->occurrence?->timezone ?: ($e->timezone ?: 'Europe/Moscow');
            $at = $c->occurrence?->starts_at ?: $e->starts_at;
            $t = $this->targets->resolve($c, $e);
            $state = $this->broadcaster->cached($c);
            return [
                'sort'    => $at ? abs($at->getTimestamp() - $now->getTimestamp()) : PHP_INT_MAX,
                'past'    => $at ? $at->copy()->addHours(12)->lt($now) : false,
                'court_id' => (int) $c->id, 'court' => $c->name, 'event_id' => (int) $e->id, 'event' => $e->title,
                'date'    => $at ? $at->copy()->setTimezone($tz)->format('d.m H:i') : null,
                'today'   => $at ? $at->copy()->setTimezone($tz)->isSameDay($now->copy()->setTimezone($tz)) : false,
                'status'  => CourtStatusLine::of($state, $tz),
                'teams'   => $this->teamsLines($state, $e),
                // платформа/подпись — без ключа и без адреса сервера
                'dest'    => $t['ok'] ? ['platform' => $t['platform'], 'label' => $t['label']] : null,
                'vk'      => $t['ok'] && $this->isVk($t),
                'key_masked' => $t['ok'] ? $this->mask((string) $t['stream_key']) : null,
                'can_replace' => $t['ok'] && $this->canReplaceKey($user, $t),
                'need_channel' => $t['error'] === BroadcastTargetResolver::NEED_CHANNEL,
                'overlay_url' => route('broadcast.overlay', ['token' => $linkByCourt[$c->id]->token]),
                'setup_url' => route('broadcast.setup', $e) . ($c->occurrence_id ? '?occurrence_id=' . $c->occurrence_id : ''),
            ];
        })->filter(fn ($r) => !$r['past'])->sortBy('sort')->take(60)->values();

        return view('broadcast.camera', ['rows' => $rows]);
    }

    /** Команды текущего (live) или следующего матча площадки с подписью под названием: пляжка — состав, классика — капитан. */
    private function teamsLines(array $state, $event): array
    {
        $m = $state['match'] ?? null;
        $id = ($m && ($m['status'] ?? '') === 'live' && ($state['mode'] ?? '') !== 'splash') ? ($m['id'] ?? null) : ($state['next_match']['id'] ?? null);
        $match = $id ? TournamentMatch::with(['teamHome', 'teamAway'])->find($id) : null;
        if (!$match) {
            return [];
        }
        $names = app(\App\Services\TeamDisplayNames::class);

        return array_values(array_filter(array_map(fn ($t) => $t ? ['name' => $t->name, 'lines' => $names->cameraLines($t, $event)] : null,
            [$match->teamHome, $match->teamAway])));
    }

    public function start(Request $request, EventCourt $court): JsonResponse
    {
        $user = $request->user();
        $event = Event::find($court->event_id);
        abort_unless($event, 404);
        abort_unless($this->access->canManageEvent($user, (int) $event->organizer_id), 403);
        abort_unless($event->format === 'tournament' || $event->collect_stats, 404);

        $t = $this->targets->resolve($court, $event);
        if (!$t['ok']) {
            $needChannel = $t['error'] === BroadcastTargetResolver::NEED_CHANNEL;
            return $this->noStore(response()->json([
                'ok' => false, 'code' => $needChannel ? 'no_channel' : 'no_destination',
                'message' => __($needChannel ? 'stream.cam_no_channel' : 'stream.cam_no_destination'),
                'setup_url' => route('broadcast.setup', $event) . ($court->occurrence_id ? '?occurrence_id=' . $court->occurrence_id : ''),
            ], 422));
        }

        $link = BroadcastLink::where('court_id', $court->id)->where('role', BroadcastLink::ROLE_OVERLAY)->whereNull('revoked_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->latest('id')->first()
            ?? $this->links->create($user, $event, BroadcastLink::ROLE_OVERLAY, $court);

        return $this->noStore(response()->json([
            'ok' => true,
            'overlay_url' => route('broadcast.overlay', ['token' => $link->token]),
            'server_url'  => $t['server_url'],
            'stream_key'  => $t['stream_key'],
            'court_name'  => $court->name,
            'event_title' => $event->title,
            'channel_key' => $court->channel_key,
            'channel'     => 'court.' . $court->channel_key,
            'reverb'      => \App\Services\Broadcast\ReverbClientConfig::get(),
        ]));
    }

    /** Заменить ключ эфира, не уходя со страницы: меняется то, откуда ключ берётся (канал организатора или разовое назначение). */
    public function replaceKey(Request $request, EventCourt $court): JsonResponse
    {
        $user = $request->user();
        $event = Event::find($court->event_id);
        abort_unless($event, 404);
        abort_unless($this->access->canManageEvent($user, (int) $event->organizer_id), 403);
        $data = $request->validate(['stream_key' => ['required', 'string', 'max:300']]);
        $key = trim($data['stream_key']);
        abort_if($key === '', 422);

        $t = $this->targets->resolve($court, $event);
        abort_unless($t['ok'] && $this->canReplaceKey($user, $t), 403, __('stream.cam_key_forbidden'));

        if ($t['channel_id']) {
            BroadcastChannel::whereKey($t['channel_id'])->first()?->update(['stream_key' => $key]);
        } else {
            BroadcastDestination::whereKey($t['destination_id'])->where('court_id', $court->id)->first()?->update(['stream_key' => $key]);
        }

        return $this->noStore(response()->json(['ok' => true, 'key_masked' => $this->mask($key)]));
    }

    private function isVk(array $t): bool
    {
        $host = (string) parse_url((string) $t['server_url'], PHP_URL_HOST);

        return $t['platform'] === 'vk' || preg_match('/(^|\.)(vk\.com|vkvideo\.ru|vk\.ru|vkuserlive\.com)$/i', $host) === 1;
    }

    /** Ключ канала меняет только его владелец/админ; разовый ключ площадки — любой, кто управляет мероприятием. */
    private function canReplaceKey($user, array $t): bool
    {
        if (!$t['channel_id']) {
            return (bool) $t['destination_id'];
        }
        $ch = BroadcastChannel::find($t['channel_id']);

        return $ch && ($user->isAdmin() || (int) $ch->organizer_id === (int) $user->id);
    }

    private function mask(string $key): string
    {
        return $key === '' ? '' : '••••' . mb_substr($key, -4);
    }

    private function noStore(JsonResponse $r): JsonResponse
    {
        return $r->header('Cache-Control', 'no-store, private');
    }
}
