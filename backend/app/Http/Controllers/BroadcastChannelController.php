<?php

namespace App\Http\Controllers;

use App\Models\BroadcastChannel;
use App\Models\BroadcastDestination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** «Мои каналы трансляции»: постоянные RTMP-ключи организатора. Управляет только владелец и админ; ключ в HTML не попадает. */
class BroadcastChannelController extends Controller
{
    private function authorizeOwner(Request $request, ?BroadcastChannel $channel = null): void
    {
        $u = $request->user();
        abort_unless($u && ($u->isAdmin() || $u->role === 'organizer'), 403);
        if ($channel) {
            abort_unless($u->isAdmin() || (int) $channel->organizer_id === (int) $u->id, 403);
        }
    }

    public function index(Request $request): View
    {
        $this->authorizeOwner($request);
        $channels = BroadcastChannel::where('organizer_id', $request->user()->id)->withCount([
            'destinations as courts_count' => fn ($q) => $q->select(DB::raw('count(distinct court_id)')),
        ])->orderBy('id')->get()->map(fn ($c) => $c->publicRow() + ['server_url' => $c->server_url, 'courts' => (int) $c->courts_count]);

        return view('broadcast.channels', ['channels' => $channels]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeOwner($request);
        $data = $request->validate([
            'platform'   => ['required', Rule::in(BroadcastDestination::PLATFORMS)],
            'label'      => ['nullable', 'string', 'max:100'],
            'server_url' => ['nullable', 'string', 'max:300'],
            'stream_key' => ['required', 'string', 'max:300'],
            'is_default' => ['nullable', 'boolean'],
        ]);
        $server = $this->server($data);
        $uid = (int) $request->user()->id;

        $ch = DB::transaction(function () use ($uid, $data, $server) {
            $makeDefault = !BroadcastChannel::where('organizer_id', $uid)->exists() || !empty($data['is_default']);
            if ($makeDefault) {
                BroadcastChannel::where('organizer_id', $uid)->update(['is_default' => false]);
            }
            return BroadcastChannel::create([
                'organizer_id' => $uid, 'platform' => $data['platform'],
                'label' => ($data['label'] ?? null) ? trim($data['label']) : null,
                'server_url' => $server, 'stream_key' => trim($data['stream_key']), 'is_default' => $makeDefault,
            ]);
        });

        return response()->json(['ok' => true, 'channel' => $ch->publicRow()]);
    }

    public function update(Request $request, BroadcastChannel $channel): JsonResponse
    {
        $this->authorizeOwner($request, $channel);
        $data = $request->validate([
            'platform'   => ['required', Rule::in(BroadcastDestination::PLATFORMS)],
            'label'      => ['nullable', 'string', 'max:100'],
            'server_url' => ['nullable', 'string', 'max:300'],
            'stream_key' => ['nullable', 'string', 'max:300'], // пусто = оставить прежний
        ]);
        $upd = ['platform' => $data['platform'], 'label' => ($data['label'] ?? null) ? trim($data['label']) : null, 'server_url' => $this->server($data)];
        if (trim((string) ($data['stream_key'] ?? '')) !== '') {
            $upd['stream_key'] = trim($data['stream_key']);
        }
        $channel->update($upd);

        return response()->json(['ok' => true, 'channel' => $channel->publicRow()]);
    }

    public function makeDefault(Request $request, BroadcastChannel $channel): JsonResponse
    {
        $this->authorizeOwner($request, $channel);
        DB::transaction(function () use ($channel) {
            BroadcastChannel::where('organizer_id', $channel->organizer_id)->update(['is_default' => false]);
            $channel->forceFill(['is_default' => true])->save();
        });

        return response()->json(['ok' => true]);
    }

    public function reveal(Request $request, BroadcastChannel $channel): JsonResponse
    {
        $this->authorizeOwner($request, $channel);

        return response()->json(['ok' => true, 'stream_key' => $channel->stream_key])->header('Cache-Control', 'no-store, private');
    }

    /** Если канал выбран на площадках — без confirm=1 отвечает 409 с числом площадок; после удаления эти площадки остаются без эфира. */
    public function destroy(Request $request, BroadcastChannel $channel): JsonResponse
    {
        $this->authorizeOwner($request, $channel);
        $used = (int) $channel->destinations()->distinct('court_id')->count('court_id');
        if ($used > 0 && !$request->boolean('confirm')) {
            return response()->json(['ok' => false, 'code' => 'in_use', 'used' => $used, 'message' => __('stream.ch_in_use', ['n' => $used])], 409);
        }
        DB::transaction(function () use ($channel) {
            $was = $channel->is_default;
            $oid = $channel->organizer_id;
            $channel->delete();
            if ($was && ($next = BroadcastChannel::where('organizer_id', $oid)->orderBy('id')->first())) {
                $next->forceFill(['is_default' => true])->save();
            }
        });

        return response()->json(['ok' => true]);
    }

    private function server(array $data): string
    {
        $server = trim((string) ($data['server_url'] ?? ''));
        if ($server === '' && $data['platform'] === 'youtube') {
            $server = BroadcastDestination::YOUTUBE_SERVER;
        }
        abort_unless(BroadcastDestination::validServer($server), 422, __('stream.dest_bad_server'));

        return $server;
    }
}
