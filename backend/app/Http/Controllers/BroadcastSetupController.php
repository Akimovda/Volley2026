<?php

namespace App\Http\Controllers;

use App\Models\BroadcastCourtOverride;
use App\Models\BroadcastChannel;
use App\Models\BroadcastDestination;
use App\Models\BroadcastLink;
use App\Models\BroadcastSetting;
use App\Models\BroadcastTeamStyle;
use App\Models\Event;
use App\Models\EventCourt;
use App\Models\EventOccurrence;
use App\Models\EventTeam;
use App\Models\TournamentMatch;
use App\Models\TournamentStage;
use App\Services\Broadcast\BroadcastControlService;
use App\Services\Broadcast\BroadcastLinkService;
use App\Services\Broadcast\CourtRegistry;
use App\Services\Broadcast\CourtStateBroadcaster;
use App\Services\Broadcast\StreamLinksService;
use App\Services\Broadcast\CourtStateService;
use App\Services\Broadcast\TeamStyleService;
use App\Services\EventAccessService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** «Настройка стрима» (она же пульт): площадки, ссылки оверлея, режимы, оформление, заставка, ссылки на эфир. */
class BroadcastSetupController extends Controller
{
    public function __construct(
        protected EventAccessService $access,
        protected CourtRegistry $registry,
        protected CourtStateBroadcaster $broadcaster,
        protected BroadcastControlService $control,
        protected BroadcastLinkService $links,
        protected TeamStyleService $styles,
    ) {}

    /* ------------------------------------------------------------------ */
    /*  Страница                                                           */
    /* ------------------------------------------------------------------ */

    public function show(Request $request, Event $event): View
    {
        $this->authorizeEvent($request, $event);

        $isFriendly = $this->isFriendly($event);
        $occurrences = $this->occurrencesOf($event, $isFriendly);
        $occurrence = $this->resolveOccurrence($event, $occurrences, (int) $request->query('occurrence_id', $request->query('occurrence', 0)));
        $occId = $occurrence?->id;

        $courts = $this->courtsOf($event, $occId)->load(['override', 'destinations.channel']);
        $activeLinks = BroadcastLink::where('event_id', $event->id)->where('role', BroadcastLink::ROLE_OVERLAY)
            ->whereNull('revoked_at')->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->get()->keyBy('court_id');

        $tz = $occurrence?->timezone ?: ($event->timezone ?: 'Europe/Moscow');

        $scorerLinks = BroadcastLink::with('assignedUser')->where('event_id', $event->id)->where('role', BroadcastLink::ROLE_SCORER)
            ->whereNull('revoked_at')->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderBy('id')->get()->groupBy('court_id');

        $canManageChannels = (bool) ($request->user()->isAdmin() || (int) $request->user()->id === (int) $event->organizer_id);
        $channels = BroadcastChannel::where('organizer_id', (int) $event->organizer_id)->orderBy('id')->get()->map(fn ($c) => $c->publicRow())->values();
        $courtData = $courts->map(function (EventCourt $c) use ($activeLinks, $tz, $scorerLinks, $canManageChannels) {
            $state = $this->broadcaster->cached($c);
            $link = $activeLinks[$c->id] ?? null;
            $o = $c->override;
            return [
                'id'        => (int) $c->id,
                'name'      => $c->name,
                'is_active' => (bool) $c->is_active,
                'link'      => $link ? ['id' => (int) $link->id, 'url' => route('broadcast.overlay', ['token' => $link->token])] : null,
                'status'    => $this->statusOf($state, $tz),
                'manual_mode' => $o?->manual_mode,
                'manual_match_id' => $o?->manual_match_id ? (int) $o->manual_match_id : null,
                'mode'      => $state['mode'],
                'match'     => $state['match'] ?? null,
                'streams'   => (array) ($o?->stream_urls ?? []),
                'destinations' => $c->destinations->sortBy('id')->map(fn ($d) => $this->destinationRow($d, $canManageChannels))->values()->all(),
                'matches'   => $this->matchesOfCourt($c),
                'scorer'    => $this->scorerRows($scorerLinks->get($c->id, collect()), $tz),
            ];
        })->values();

        // Страница редактирует настройки события (основные для всех туров); переопределения тура в UI нет
        $settings = BroadcastSetting::resolve((int) $event->id, null);
        $theme = (array) ($settings->theme ?? []);
        $splash = (array) ($settings->splash ?? []);
        $customAt = null;
        if (!empty($splash['manual_start_at'])) {
            $customAt = Carbon::parse($splash['manual_start_at'])->setTimezone($tz)->format('Y-m-d\TH:i');
        }
        $bg = $settings->exists ? $settings->getFirstMedia('stream_bg') : null;

        return view('broadcast.setup', [
            'event'       => $event,
            'isFriendly'  => $isFriendly,
            'occurrences' => $occurrences,
            'occurrence'  => $occurrence,
            'courts'      => $courtData,
            'channels'    => $channels,
            'canManageChannels' => $canManageChannels,
            'dayCourtPending' => $isFriendly && $occId && $courts->isEmpty(),
            'unassigned'  => $isFriendly ? collect() : $this->unassignedMatches($event, $occId),
            'teams'       => $this->teamRows($event, $occId, $isFriendly),
            'form'        => [
                'template'        => in_array($theme['template'] ?? '', ['compact', 'sets_table', 'glass', 'neon', 'light_tv'], true) ? $theme['template'] : 'compact',
                'corner'          => in_array($theme['corner'] ?? '', ['top-left', 'top-right', 'bottom-left', 'bottom-right'], true) ? $theme['corner'] : 'top-left',
                'show_set_scores' => (bool) ($theme['show_set_scores'] ?? true),
                'show_logos'      => (bool) ($theme['show_logos'] ?? true),
                'scale'           => CourtStateService::clampScale($theme['scale'] ?? null),
                'opacity'         => CourtStateService::clampOpacity($theme['opacity'] ?? null),
                'edge_margin'     => in_array($theme['edge_margin'] ?? '', ['small', 'medium', 'large'], true) ? $theme['edge_margin'] : 'medium',
                'show_team_names' => (bool) ($theme['show_team_names'] ?? true),
                'text'            => (string) ($splash['text'] ?? ''),
                'timer'           => $splash['timer'] ?? (($splash['show_countdown'] ?? true) ? 'schedule' : 'none'),
                'custom_at'       => $customAt,
                'summary_mode'    => $settings->summary_mode ?: BroadcastSetting::SUMMARY_EVERY_SET,
                'set_secs'        => (int) $settings->set_summary_seconds,
                'match_secs'      => (int) $settings->match_summary_seconds,
                'music'           => $settings->musicConfig(),
                'music_url'       => $settings->musicUrl(),
                'music_custom'    => $settings->exists && $settings->getFirstMedia('stream_music') !== null,
                'bg_url'          => $bg ? ($bg->hasGeneratedConversion('hd') ? $bg->getUrl('hd') : $bg->getUrl()) : null,
            ],
            'palette'     => BroadcastTeamStyle::PALETTE,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  Площадки                                                           */
    /* ------------------------------------------------------------------ */

    public function storeCourt(Request $request, Event $event): JsonResponse
    {
        $this->authorizeEvent($request, $event);
        abort_if($this->isFriendly($event), 422, 'У игры со статистикой одна площадка.');
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'occurrence_id' => ['required', 'integer']]);
        $occ = $this->ownOccurrence($event, (int) $data['occurrence_id']);

        $name = trim($data['name']);
        $exists = EventCourt::where('event_id', $event->id)->where('occurrence_id', $occ->id)->where('name', $name)->exists();
        abort_if($exists, 422, 'Площадка с таким названием уже есть.');

        $court = $this->registry->ensure((int) $event->id, (int) $occ->id, $name);
        return response()->json(['ok' => true, 'id' => $court->id]);
    }

    public function updateCourt(Request $request, Event $event, EventCourt $court): JsonResponse
    {
        $this->authorizeEvent($request, $event);
        $this->ownCourt($event, $court);
        $data = $request->validate(['name' => ['sometimes', 'string', 'max:100'], 'is_active' => ['sometimes', 'boolean']]);

        if (isset($data['name'])) {
            $name = trim($data['name']);
            abort_if($name === '', 422, 'Пустое название.');
            $dup = EventCourt::where('event_id', $event->id)->where('id', '!=', $court->id)->where('name', $name)
                ->when($court->occurrence_id === null, fn ($q) => $q->whereNull('occurrence_id'), fn ($q) => $q->where('occurrence_id', $court->occurrence_id))->exists();
            abort_if($dup, 422, 'Площадка с таким названием уже есть.');
            $court->name = $name;
        }
        if (array_key_exists('is_active', $data)) {
            $court->is_active = (bool) $data['is_active'];
        }
        $court->save();
        $this->broadcaster->forCourt($court);

        return response()->json(['ok' => true]);
    }

    public function assignMatch(Request $request, Event $event, TournamentMatch $match): JsonResponse
    {
        $this->authorizeEvent($request, $event);
        $match->loadMissing('stage');
        abort_unless($match->stage && (int) $match->stage->event_id === (int) $event->id, 404);
        $data = $request->validate(['court_id' => ['required', 'integer']]);
        $court = EventCourt::findOrFail($data['court_id']);
        $this->ownCourt($event, $court);

        $oldCourt = $match->court_id ? EventCourt::find($match->court_id) : null;
        $match->forceFill(['court_id' => $court->id])->save();

        if ($oldCourt) {
            $this->broadcaster->forCourt($oldCourt);
        }
        $this->broadcaster->forCourt($court);

        return response()->json(['ok' => true]);
    }

    /* ------------------------------------------------------------------ */
    /*  Ссылки оверлея                                                     */
    /* ------------------------------------------------------------------ */

    public function issueLink(Request $request, Event $event, EventCourt $court): JsonResponse
    {
        $this->authorizeEvent($request, $event);
        $this->ownCourt($event, $court);

        $old = BroadcastLink::where('court_id', $court->id)->where('role', BroadcastLink::ROLE_OVERLAY)->whereNull('revoked_at')->latest('id')->first();
        $link = $old
            ? $this->links->regenerate($request->user(), $old)
            : $this->links->create($request->user(), $event, BroadcastLink::ROLE_OVERLAY, $court);

        return response()->json(['ok' => true, 'url' => route('broadcast.overlay', ['token' => $link->token])]);
    }

    /** Friendly: площадка дня создаётся здесь, при первом изменяющем действии (GET страницы в БД не пишет). */
    public function issueDayLink(Request $request, Event $event): JsonResponse
    {
        $this->authorizeEvent($request, $event);
        abort_unless($this->isFriendly($event), 404);
        $occ = $this->ownOccurrence($event, (int) $request->validate(['occurrence_id' => ['required', 'integer']])['occurrence_id']);

        $court = EventCourt::where('event_id', $event->id)->where('occurrence_id', $occ->id)->orderBy('sort_order')->orderBy('id')->first()
            ?? $this->registry->ensure((int) $event->id, (int) $occ->id, 'Площадка');

        return $this->issueLink($request, $event, $court);
    }

    public function revokeLink(Request $request, Event $event, EventCourt $court): JsonResponse
    {
        $this->authorizeEvent($request, $event);
        $this->ownCourt($event, $court);
        BroadcastLink::where('court_id', $court->id)->where('role', BroadcastLink::ROLE_OVERLAY)->whereNull('revoked_at')->get()
            ->each(fn ($l) => $this->links->revoke($request->user(), $l));

        return response()->json(['ok' => true]);
    }

    /* ------------------------------------------------------------------ */
    /*  Ссылки ведущих (ввод счёта по токену)                              */
    /* ------------------------------------------------------------------ */

    /** @return array{anon:?array, invites:array<int,array>} */
    private function scorerRows($links, string $tz): array
    {
        $row = function (BroadcastLink $l) use ($tz) {
            $fmt = fn ($d) => $d ? $d->copy()->setTimezone($tz)->format('d.m H:i') : null;
            $u = $l->assignedUser;
            return [
                'id'         => (int) $l->id,
                'url'        => route('scorer.hub', ['token' => $l->token]),
                'user'       => $u ? (trim(($u->last_name ?? '') . ' ' . ($u->first_name ?? '')) ?: ('ID ' . $u->id)) : null,
                'expires'    => $fmt($l->expires_at),
                'used'       => $fmt($l->last_used_at),
                'actor'      => $l->last_actor_name,
                'actor_at'   => $fmt($l->last_actor_at),
            ];
        };
        $anon = $links->first(fn ($l) => !$l->assigned_user_id);

        return [
            'anon'    => $anon ? $row($anon) : null,
            'invites' => $links->filter(fn ($l) => $l->assigned_user_id)->map($row)->values()->all(),
        ];
    }

    /** Анонимная ссылка ведущего: выдать или перевыпустить (старая отзывается). */
    public function issueScorerLink(Request $request, Event $event, EventCourt $court): JsonResponse
    {
        $this->authorizeEvent($request, $event);
        $this->ownCourt($event, $court);

        BroadcastLink::where('court_id', $court->id)->where('role', BroadcastLink::ROLE_SCORER)
            ->whereNull('assigned_user_id')->whereNull('revoked_at')->get()
            ->each(fn ($l) => $this->links->revoke($request->user(), $l));
        $link = $this->links->createScorer($request->user(), $event, $court);

        return response()->json(['ok' => true, 'url' => route('scorer.hub', ['token' => $link->token])]);
    }

    public function inviteScorer(Request $request, Event $event, EventCourt $court): JsonResponse
    {
        $this->authorizeEvent($request, $event);
        $this->ownCourt($event, $court);
        $data = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);
        $user = \App\Models\User::findOrFail((int) $data['user_id']);

        // Прежнее приглашение этого же пользователя на эту площадку заменяется
        BroadcastLink::where('court_id', $court->id)->where('role', BroadcastLink::ROLE_SCORER)
            ->where('assigned_user_id', $user->id)->whereNull('revoked_at')->get()
            ->each(fn ($l) => $this->links->revoke($request->user(), $l));
        $link = $this->links->createScorer($request->user(), $event, $court, $user, true);

        return response()->json(['ok' => true, 'url' => route('scorer.hub', ['token' => $link->token])]);
    }

    public function revokeScorerLink(Request $request, Event $event, EventCourt $court, BroadcastLink $link): JsonResponse
    {
        $this->authorizeEvent($request, $event);
        $this->ownCourt($event, $court);
        abort_unless((int) $link->court_id === (int) $court->id && $link->role === BroadcastLink::ROLE_SCORER, 404);
        $this->links->revoke($request->user(), $link);

        return response()->json(['ok' => true]);
    }

    /* ------------------------------------------------------------------ */
    /*  Пульт                                                              */
    /* ------------------------------------------------------------------ */

    public function control(Request $request, Event $event, EventCourt $court): JsonResponse
    {
        $this->authorizeEvent($request, $event);
        $this->ownCourt($event, $court);
        $data = $request->validate([
            'mode'     => ['sometimes', 'nullable', Rule::in(array_merge(['auto'], BroadcastCourtOverride::MODES))],
            'match_id' => ['sometimes', 'nullable', 'integer'],
        ]);

        $state = null;
        if (array_key_exists('mode', $data)) {
            $state = $this->control->setMode($court, ($data['mode'] === 'auto' || $data['mode'] === null) ? null : $data['mode']);
        }
        if (array_key_exists('match_id', $data)) {
            $mid = $data['match_id'] ? (int) $data['match_id'] : null;
            if ($mid) {
                $m = TournamentMatch::with('stage')->find($mid);
                abort_unless($m && $m->stage && (int) $m->stage->event_id === (int) $event->id && $m->hasTeams(), 422, 'Матч не подходит.');
            }
            $state = $this->control->setMatch($court, $mid);
        }
        $court->load('override');

        return response()->json([
            'ok' => true,
            'mode' => $state['mode'] ?? null,
            'manual_mode' => $court->override?->manual_mode,
            'manual_match_id' => $court->override?->manual_match_id,
            'match' => $state['match'] ?? null,
            'status' => $state ? $this->statusOf($state, $this->tzOf($event, $court)) : null,
        ]);
    }

    public function streams(Request $request, Event $event, EventCourt $court): JsonResponse
    {
        $this->authorizeEvent($request, $event);
        $this->ownCourt($event, $court);
        $request->validate(array_fill_keys(array_map(fn ($p) => "urls.$p", StreamLinksService::PLATFORMS), ['nullable', 'string', 'max:300']));

        $clean = [];
        foreach (StreamLinksService::PLATFORMS as $p) {
            $raw = trim((string) $request->input("urls.$p", ''));
            if ($raw === '') {
                continue;
            }
            $u = StreamLinksService::cleanUrl($raw);
            abort_if($u === null, 422, __('stream.streams_bad'));
            $clean[$p] = $u;
        }
        BroadcastCourtOverride::updateOrCreate(['court_id' => $court->id], ['stream_urls' => $clean ?: null]);

        return response()->json(['ok' => true, 'urls' => $clean]);
    }

    /* ------------------------------------------------------------------ */
    /*  Эфир из приложения: куда отправлять видео (RTMP + ключ)            */
    /* ------------------------------------------------------------------ */

    public function storeDestination(Request $request, Event $event, EventCourt $court): JsonResponse
    {
        $this->authorizeEvent($request, $event);
        $this->ownCourt($event, $court);
        $data = $request->validate([
            'platform'   => ['required', Rule::in(BroadcastDestination::PLATFORMS)],
            'label'      => ['nullable', 'string', 'max:100'],
            'server_url' => ['nullable', 'string', 'max:300'],
            'stream_key' => ['required', 'string', 'max:300'],
            'is_default' => ['nullable', 'boolean'],
        ]);
        $server = trim((string) ($data['server_url'] ?? ''));
        if ($server === '' && $data['platform'] === 'youtube') {
            $server = BroadcastDestination::YOUTUBE_SERVER;
        }
        abort_unless(BroadcastDestination::validServer($server), 422, __('stream.dest_bad_server'));

        $d = DB::transaction(function () use ($court, $data, $server) {
            $isFirst = !$court->destinations()->exists();
            $makeDefault = $isFirst || !empty($data['is_default']);
            if ($makeDefault) {
                $court->destinations()->update(['is_default' => false]);
            }
            return $court->destinations()->create([
                'platform'   => $data['platform'],
                'label'      => ($data['label'] ?? null) ? trim($data['label']) : null,
                'server_url' => $server,
                'stream_key' => trim($data['stream_key']),
                'is_default' => $makeDefault,
            ]);
        });

        return response()->json(['ok' => true, 'destination' => $this->destinationRow($d)]);
    }

    /** Выбор канала организатора для площадки (channel_id) либо возврат к разовому ключу (пусто). Помощник тоже может выбирать — ключа он не получает. */
    public function selectChannel(Request $request, Event $event, EventCourt $court): JsonResponse
    {
        $this->authorizeEvent($request, $event);
        $this->ownCourt($event, $court);
        $data = $request->validate(['channel_id' => ['nullable', 'integer']]);
        $channel = null;
        if (!empty($data['channel_id'])) {
            $channel = BroadcastChannel::where('id', $data['channel_id'])->where('organizer_id', (int) $event->organizer_id)->first();
            abort_unless($channel, 422, __('stream.ch_not_found'));
        }
        DB::transaction(function () use ($court, $channel) {
            // у площадки не больше одного назначения-канала; осиротевшие (канал удалён) убираем
            $court->destinations()->where(fn ($q) => $q->whereNotNull('channel_id')->orWhereNull('server_url'))->delete();
            if ($channel) {
                $court->destinations()->update(['is_default' => false]);
                $court->destinations()->create(['channel_id' => $channel->id, 'platform' => $channel->platform, 'label' => $channel->label, 'is_default' => true]);
            } elseif (!$court->destinations()->where('is_default', true)->exists() && ($next = $court->destinations()->orderBy('id')->first())) {
                $next->forceFill(['is_default' => true])->save();
            }
        });

        return response()->json(['ok' => true]);
    }

    public function defaultDestination(Request $request, Event $event, EventCourt $court, BroadcastDestination $destination): JsonResponse
    {
        $this->authorizeEvent($request, $event);
        $this->ownCourt($event, $court);
        abort_unless((int) $destination->court_id === (int) $court->id, 404);
        DB::transaction(function () use ($court, $destination) {
            $court->destinations()->update(['is_default' => false]);
            $destination->forceFill(['is_default' => true])->save();
        });

        return response()->json(['ok' => true]);
    }

    public function destroyDestination(Request $request, Event $event, EventCourt $court, BroadcastDestination $destination): JsonResponse
    {
        $this->authorizeEvent($request, $event);
        $this->ownCourt($event, $court);
        abort_unless((int) $destination->court_id === (int) $court->id, 404);
        DB::transaction(function () use ($court, $destination) {
            $was = $destination->is_default;
            $destination->delete();
            if ($was && ($next = $court->destinations()->orderBy('id')->first())) {
                $next->forceFill(['is_default' => true])->save();
            }
        });

        return response()->json(['ok' => true]);
    }

    /** Показ ключа по кнопке: POST, не кешируется, в HTML страницы ключа нет. */
    public function revealDestination(Request $request, Event $event, EventCourt $court, BroadcastDestination $destination): JsonResponse
    {
        $this->authorizeEvent($request, $event);
        $this->ownCourt($event, $court);
        abort_unless((int) $destination->court_id === (int) $court->id, 404);
        $key = $destination->stream_key;
        if ($destination->channel_id) {
            // ключ канала — только владельцу и админу, помощнику нет
            $u = $request->user();
            abort_unless($u->isAdmin() || (int) $u->id === (int) $event->organizer_id, 403);
            $key = $destination->channel?->stream_key;
        }
        abort_if($key === null || $key === '', 404);

        return response()->json(['ok' => true, 'stream_key' => $key])
            ->header('Cache-Control', 'no-store, private');
    }

    /* ------------------------------------------------------------------ */
    /*  Оформление, заставка, итоги                                        */
    /* ------------------------------------------------------------------ */

    public function saveTheme(Request $request, Event $event): JsonResponse
    {
        $this->authorizeEvent($request, $event);
        $data = $request->validate([
            'occurrence_id'   => ['required', 'integer'],
            'template'        => ['required', Rule::in(['compact', 'sets_table', 'glass', 'neon', 'light_tv'])],
            'corner'          => ['required', Rule::in(['top-left', 'top-right', 'bottom-left', 'bottom-right'])],
            'show_set_scores' => ['required', 'boolean'],
            'show_logos'      => ['required', 'boolean'],
            'scale'           => ['required', 'integer', 'between:50,150', 'multiple_of:10'],
            'opacity'         => ['required', 'integer', 'between:30,100', 'multiple_of:5'],
            'edge_margin'     => ['required', Rule::in(['small', 'medium', 'large'])],
            'show_team_names' => ['required', 'boolean'],
        ]);
        $occ = $this->ownOccurrence($event, (int) $data['occurrence_id']);
        $s = $this->settingsRow($event, $occ);
        $s->theme = array_merge((array) $s->theme, [
            'template' => $data['template'], 'corner' => $data['corner'],
            'show_set_scores' => (bool) $data['show_set_scores'], 'show_logos' => (bool) $data['show_logos'],
            'scale' => (int) $data['scale'], 'opacity' => (int) $data['opacity'], 'edge_margin' => $data['edge_margin'],
            'show_team_names' => (bool) $data['show_team_names'],
        ]);
        $s->save();
        $this->refreshCourts($event, $occ->id);

        return response()->json(['ok' => true]);
    }

    public function saveSplash(Request $request, Event $event): JsonResponse
    {
        $this->authorizeEvent($request, $event);
        $data = $request->validate([
            'occurrence_id' => ['required', 'integer'],
            'text'          => ['nullable', 'string', 'max:80'],
            'timer'         => ['required', Rule::in(['schedule', 'custom', 'none'])],
            'custom_at'     => ['nullable', 'date_format:Y-m-d\TH:i'],
            'summary_mode'  => ['required', Rule::in([BroadcastSetting::SUMMARY_EVERY_SET, BroadcastSetting::SUMMARY_MATCH_END])],
            'set_secs'      => ['required', 'integer', 'between:5,120'],
            'match_secs'    => ['required', 'integer', 'between:10,300'],
        ]);
        abort_if($data['timer'] === 'custom' && empty($data['custom_at']), 422, 'Укажите время начала.');
        $occ = $this->ownOccurrence($event, (int) $data['occurrence_id']);
        $tz = $occ->timezone ?: ($event->timezone ?: 'Europe/Moscow');

        $s = $this->settingsRow($event, $occ);
        $splash = (array) $s->splash;
        $splash['text'] = trim(strip_tags((string) ($data['text'] ?? ''))) ?: null;
        $splash['timer'] = $data['timer'];
        $splash['show_countdown'] = $data['timer'] !== 'none';
        $splash['manual_start_at'] = $data['timer'] === 'custom'
            ? Carbon::createFromFormat('Y-m-d\TH:i', $data['custom_at'], $tz)->utc()->toIso8601String()
            : null;
        $s->splash = $splash;
        $s->summary_mode = $data['summary_mode'];
        $s->set_summary_seconds = (int) $data['set_secs'];
        $s->match_summary_seconds = (int) $data['match_secs'];
        $s->save();
        $this->refreshCourts($event, $occ->id);

        return response()->json(['ok' => true]);
    }

    public function saveMusic(Request $request, Event $event): JsonResponse
    {
        $this->authorizeEvent($request, $event);
        $data = $request->validate([
            'occurrence_id' => ['required', 'integer'],
            'enabled'       => ['required', 'boolean'],
            'track'         => ['required', Rule::in(BroadcastSetting::musicTrackKeys())],
            'modes'         => ['nullable', 'array'],
            'modes.*'       => [Rule::in(BroadcastSetting::MUSIC_MODES)],
            'volume'        => ['required', 'integer', 'between:0,100'],
        ]);
        $occ = $this->ownOccurrence($event, (int) $data['occurrence_id']);
        $s = $this->settingsRow($event, $occ);
        abort_if($data['track'] === 'custom' && !$s->getFirstMedia('stream_music'), 422, 'Сначала загрузите свой трек.');
        $theme = (array) $s->theme;
        $theme['music'] = [
            'enabled' => (bool) $data['enabled'], 'track' => $data['track'],
            'modes' => array_values(array_intersect(BroadcastSetting::MUSIC_MODES, (array) ($data['modes'] ?? []))),
            'volume' => (int) $data['volume'], 'fade_ms' => $s->musicConfig()['fade_ms'],
        ];
        $s->theme = $theme;
        $s->save();
        $this->refreshCourts($event, $occ->id);

        return response()->json(['ok' => true]);
    }

    public function uploadMusic(Request $request, Event $event): JsonResponse
    {
        $this->authorizeEvent($request, $event);
        $request->validate([
            'occurrence_id' => ['required', 'integer'],
            'file'          => ['required', 'file', 'mimes:mp3,m4a,aac', 'mimetypes:audio/mpeg,audio/mp3,audio/mp4,audio/x-m4a,audio/m4a,audio/aac,audio/x-aac', 'max:15360'],
        ]);
        $occ = $this->ownOccurrence($event, (int) $request->input('occurrence_id'));
        $s = $this->settingsRow($event, $occ);
        $ext = strtolower($request->file('file')->guessExtension() ?: $request->file('file')->getClientOriginalExtension());
        $ext = in_array($ext, ['mp3', 'm4a', 'aac', 'mp4'], true) ? $ext : 'mp3';
        $s->addMediaFromRequest('file')->usingFileName('music_' . uniqid() . '.' . $ext)->toMediaCollection('stream_music');
        $theme = (array) $s->theme;
        $theme['music'] = array_merge($s->musicConfig(), ['track' => 'custom']);
        $s->theme = $theme;
        $s->save();
        $this->refreshCourts($event, $occ->id);

        return response()->json(['ok' => true, 'url' => $s->fresh()->musicUrl()]);
    }

    public function deleteMusic(Request $request, Event $event): JsonResponse
    {
        $this->authorizeEvent($request, $event);
        $occ = $this->ownOccurrence($event, (int) $request->input('occurrence_id'));
        $row = $this->settingsQuery($event, $this->settingsScope($event, (int) $occ->id))->first();
        if ($row) {
            $row->clearMediaCollection('stream_music');
            $theme = (array) $row->theme;
            $theme['music'] = array_merge($row->musicConfig(), ['track' => 'calm']);
            $row->theme = $theme;
            $row->save();
        }
        $this->refreshCourts($event, $occ->id);

        return response()->json(['ok' => true, 'url' => asset(BroadcastSetting::MUSIC_TRACKS['calm'])]);
    }

    public function uploadBg(Request $request, Event $event): JsonResponse
    {
        $this->authorizeEvent($request, $event);
        $request->validate([
            'occurrence_id' => ['required', 'integer'],
            'file'          => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120'],
        ]);
        $occ = $this->ownOccurrence($event, (int) $request->input('occurrence_id'));
        $s = $this->settingsRow($event, $occ);
        $s->addMediaFromRequest('file')->usingFileName('bg_' . uniqid() . '.' . $request->file('file')->guessExtension())->toMediaCollection('stream_bg');
        $this->refreshCourts($event, $occ->id);
        $m = $s->fresh()->getFirstMedia('stream_bg');

        return response()->json(['ok' => true, 'url' => $m ? ($m->hasGeneratedConversion('hd') ? $m->getUrl('hd') : $m->getUrl()) : null]);
    }

    public function deleteBg(Request $request, Event $event): JsonResponse
    {
        $this->authorizeEvent($request, $event);
        $occ = $this->ownOccurrence($event, (int) $request->input('occurrence_id'));
        $row = $this->settingsQuery($event, $this->settingsScope($event, (int) $occ->id))->first();
        $row?->clearMediaCollection('stream_bg');
        $this->refreshCourts($event, $occ->id);

        return response()->json(['ok' => true]);
    }

    /* ------------------------------------------------------------------ */
    /*  Команды: цвет и логотип                                            */
    /* ------------------------------------------------------------------ */

    public function teamColor(Request $request, Event $event, EventTeam $team): JsonResponse
    {
        $this->authorizeEvent($request, $event);
        $this->ownTeam($event, $team);
        $data = $request->validate(['color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/']]);
        BroadcastTeamStyle::updateOrCreate(['event_team_id' => $team->id], ['color' => !empty($data['color']) ? strtoupper($data['color']) : null]);
        $this->refreshCourts($event, $team->occurrence_id);

        return response()->json(['ok' => true, 'color' => $this->styles->colorFor($team->fresh())]);
    }

    public function teamShortName(Request $request, Event $event, EventTeam $team): JsonResponse
    {
        $this->authorizeEvent($request, $event);
        $this->ownTeam($event, $team);
        $data = $request->validate(['short_name' => ['nullable', 'string', 'max:4']]);
        $v = trim(strip_tags((string) ($data['short_name'] ?? '')));
        BroadcastTeamStyle::updateOrCreate(['event_team_id' => $team->id], ['short_name' => $v !== '' ? mb_strtoupper($v) : null]);
        $this->refreshCourts($event, $team->occurrence_id);

        return response()->json(['ok' => true, 'short_name' => $this->styles->shortFor($team->fresh())]);
    }

    public function teamLogo(Request $request, Event $event, EventTeam $team): JsonResponse
    {
        $this->authorizeEvent($request, $event);
        $this->ownTeam($event, $team);
        $request->validate(['file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120']]);
        $style = BroadcastTeamStyle::firstOrCreate(['event_team_id' => $team->id]);
        $style->addMediaFromRequest('file')->usingFileName('logo_' . uniqid() . '.' . $request->file('file')->guessExtension())->toMediaCollection('team_logo');
        $this->refreshCourts($event, $team->occurrence_id);

        return response()->json(['ok' => true, 'url' => $this->styles->logoUrlFor($team)]);
    }

    public function deleteTeamLogo(Request $request, Event $event, EventTeam $team): JsonResponse
    {
        $this->authorizeEvent($request, $event);
        $this->ownTeam($event, $team);
        BroadcastTeamStyle::where('event_team_id', $team->id)->first()?->clearMediaCollection('team_logo');
        $this->refreshCourts($event, $team->occurrence_id);

        return response()->json(['ok' => true]);
    }

    /* ------------------------------------------------------------------ */
    /*  Вспомогательное                                                    */
    /* ------------------------------------------------------------------ */

    /** Строка для страницы: ключ НЕ отдаём — только маска; сам ключ — отдельным запросом (revealDestination). */
    private function destinationRow(BroadcastDestination $d, bool $canManageChannels = true): array
    {
        $ch = $d->channel;
        if ($d->channel_id && $ch) {
            // канал: платформа/подпись из канала; адрес сервера и ключ в строку не попадают
            return [
                'id' => (int) $d->id, 'platform' => $ch->platform, 'label' => $ch->label, 'server_url' => null,
                'is_default' => (bool) $d->is_default, 'channel_id' => (int) $ch->id, 'orphan' => false, 'can_reveal' => $canManageChannels,
            ];
        }

        return [
            'id' => (int) $d->id, 'platform' => $d->platform, 'label' => $d->label,
            'server_url' => $d->isOrphan() ? null : $d->server_url, 'is_default' => (bool) $d->is_default,
            'channel_id' => null, 'orphan' => $d->isOrphan(), 'can_reveal' => !$d->isOrphan(),
        ];
    }

    private function authorizeEvent(Request $request, Event $event): void
    {
        $user = $request->user();
        abort_unless($user && $this->access->canManageEvent($user, (int) $event->organizer_id), 403);
        abort_unless($event->format === 'tournament' || $event->collect_stats, 404);
    }

    private function isFriendly(Event $event): bool
    {
        return $event->format !== 'tournament';
    }

    private function occurrencesOf(Event $event, bool $isFriendly)
    {
        $q = EventOccurrence::where('event_id', $event->id)->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)');
        if ($isFriendly) {
            $ids = TournamentStage::where('event_id', $event->id)->where('type', TournamentStage::TYPE_FRIENDLY)->pluck('occurrence_id');
            $q->whereIn('id', $ids);
        } else {
            $stageIds = TournamentStage::where('event_id', $event->id)->whereNotNull('occurrence_id')->pluck('occurrence_id')->unique();
            if ($stageIds->isNotEmpty()) {
                $q->whereIn('id', $stageIds);
            }
        }
        return $q->orderBy('starts_at')->get();
    }

    private function resolveOccurrence(Event $event, $occurrences, int $id): ?EventOccurrence
    {
        if ($id && ($o = $occurrences->firstWhere('id', $id))) {
            return $o;
        }
        if ($occurrences->isEmpty()) {
            return null;
        }
        // Ближайший к «сейчас» тур: первый ещё не прошедший, иначе последний
        return $occurrences->first(fn ($o) => $o->starts_at && $o->starts_at->gte(now()->subHours(12))) ?? $occurrences->last();
    }

    private function ownOccurrence(Event $event, int $id): EventOccurrence
    {
        $o = EventOccurrence::where('event_id', $event->id)->find($id);
        abort_unless($o, 404);
        return $o;
    }

    private function ownCourt(Event $event, EventCourt $court): void
    {
        abort_unless((int) $court->event_id === (int) $event->id, 404);
    }

    private function ownTeam(Event $event, EventTeam $team): void
    {
        abort_unless((int) $team->event_id === (int) $event->id, 404);
    }

    private function courtsOf(Event $event, ?int $occId)
    {
        return EventCourt::where('event_id', $event->id)
            ->when($occId, fn ($q) => $q->where(fn ($w) => $w->where('occurrence_id', $occId)->orWhereNull('occurrence_id')))
            ->orderBy('sort_order')->orderBy('id')->get();
    }

    private function tzOf(Event $event, EventCourt $court): string
    {
        return $court->occurrence?->timezone ?: ($event->timezone ?: 'Europe/Moscow');
    }

    private function settingsQuery(Event $event, ?int $occId)
    {
        return BroadcastSetting::where('event_id', $event->id)
            ->when($occId === null, fn ($q) => $q->whereNull('occurrence_id'), fn ($q) => $q->where('occurrence_id', $occId));
    }

    /**
     * Область настроек: страница всегда пишет в запись СОБЫТИЯ (основные для всех туров). Запись уровня тура
     * (occurrence_id) — только переопределение, если существует; читает её CourtStateService (тур → иначе событие).
     */
    private function settingsScope(Event $event, ?int $occId): ?int
    {
        return null;
    }

    /** Строка настроек нужной области: при первом сохранении наследует то, что действовало до неё (вместе с фоном). */
    private function settingsRow(Event $event, EventOccurrence $occ): BroadcastSetting
    {
        $scope = $this->settingsScope($event, (int) $occ->id);
        $row = $this->settingsQuery($event, $scope)->first();
        if ($row) {
            return $row;
        }
        $base = BroadcastSetting::resolve((int) $event->id, $scope);
        $row = BroadcastSetting::create([
            'event_id' => $event->id, 'occurrence_id' => $scope,
            'theme' => $base->theme, 'splash' => $base->splash,
            'summary_mode' => $base->summary_mode, 'set_summary_seconds' => $base->set_summary_seconds,
            'match_summary_seconds' => $base->match_summary_seconds, 'aspect' => $base->aspect ?: '16:9',
        ]);
        if ($base->exists && ($bg = $base->getFirstMedia('stream_bg'))) {
            $bg->copy($row, 'stream_bg');
        }
        if ($base->exists && ($mu = $base->getFirstMedia('stream_music'))) {
            $mu->copy($row, 'stream_music');
        }
        return $row;
    }

    /** CourtStateUpdated для всех площадок тура (тема/команды/заставка влияют на каждую). */
    private function refreshCourts(Event $event, ?int $occId): void
    {
        // Настройки события общие для всех туров — обновляем площадки всех туров
        foreach ($this->courtsOf($event, null) as $c) {
            $this->broadcaster->forCourt($c);
        }
    }

    private function statusOf(array $state, string $tz): array
    {
        return \App\Services\Broadcast\CourtStatusLine::of($state, $tz);
    }

    private function matchesOfCourt(EventCourt $court): array
    {
        $rows = TournamentMatch::with(['stage', 'teamHome', 'teamAway'])
            ->where('court_id', $court->id)
            ->whereNotIn('status', [TournamentMatch::STATUS_CANCELLED, TournamentMatch::STATUS_FORFEIT])
            ->orderByRaw('scheduled_at IS NULL')->orderBy('scheduled_at')->orderBy('id')->limit(80)->get();

        // Friendly: матчи служебной стадии без court_id принадлежат первой площадке тура (как в CourtStateService)
        if ($court->occurrence_id) {
            $first = EventCourt::where('event_id', $court->event_id)->where('occurrence_id', $court->occurrence_id)->orderBy('sort_order')->orderBy('id')->value('id');
            if ((int) $first === (int) $court->id) {
                $fs = TournamentStage::where('event_id', $court->event_id)->where('occurrence_id', $court->occurrence_id)->where('type', TournamentStage::TYPE_FRIENDLY)->value('id');
                if ($fs) {
                    $rows = $rows->concat(TournamentMatch::with(['stage', 'teamHome', 'teamAway'])->where('stage_id', $fs)->whereNull('court_id')
                        ->whereNotIn('status', [TournamentMatch::STATUS_CANCELLED, TournamentMatch::STATUS_FORFEIT])->orderBy('id')->limit(80)->get());
                }
            }
        }

        return $rows->filter(fn ($m) => $m->hasTeams())->map(fn ($m) => [
            'id' => (int) $m->id,
            'label' => ($m->teamHome?->name ?? '?') . ' — ' . ($m->teamAway?->name ?? '?'),
            'stage' => $m->stage?->name,
            'status' => $m->status,
        ])->values()->all();
    }

    private function unassignedMatches(Event $event, ?int $occId)
    {
        return TournamentMatch::with(['stage', 'teamHome', 'teamAway'])
            ->whereNull('court_id')
            ->whereHas('stage', fn ($q) => $q->where('event_id', $event->id)->where('type', '!=', TournamentStage::TYPE_FRIENDLY)
                ->when($occId, fn ($w) => $w->where('occurrence_id', $occId)))
            ->whereNotIn('status', [TournamentMatch::STATUS_CANCELLED, TournamentMatch::STATUS_FORFEIT, TournamentMatch::STATUS_COMPLETED])
            ->orderByRaw('scheduled_at IS NULL')->orderBy('scheduled_at')->orderBy('id')->limit(100)->get();
    }

    private function teamRows(Event $event, ?int $occId, bool $isFriendly): array
    {
        $teams = EventTeam::where('event_id', $event->id)
            ->when($occId, fn ($q) => $q->where('occurrence_id', $occId))
            ->orderBy('id')->get();
        $styles = BroadcastTeamStyle::whereIn('event_team_id', $teams->pluck('id'))->get()->keyBy('event_team_id');

        return $teams->map(function (EventTeam $t) use ($styles) {
            $logo = $styles[$t->id] ?? null ? $styles[$t->id]->getFirstMedia('team_logo') : null;
            return [
                'id' => (int) $t->id,
                'name' => $t->name,
                'color' => $this->styles->colorFor($t),
                'short_name' => (string) ($styles[$t->id]->short_name ?? ''),
                'short_default' => TeamStyleService::defaultShort((string) $t->name),
                'logo' => $logo ? ($logo->hasGeneratedConversion('logo') ? $logo->getUrl('logo') : $logo->getUrl()) : null,
                'friendly' => is_string($t->meta['color'] ?? null),
            ];
        })->all();
    }
}
