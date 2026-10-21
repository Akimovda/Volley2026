<?php

namespace App\Http\Controllers;

use App\Models\BroadcastCourtOverride;
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

        $courts = $this->courtsOf($event, $occId)->load('override');
        $activeLinks = BroadcastLink::where('event_id', $event->id)->where('role', BroadcastLink::ROLE_OVERLAY)
            ->whereNull('revoked_at')->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->get()->keyBy('court_id');

        $tz = $occurrence?->timezone ?: ($event->timezone ?: 'Europe/Moscow');

        $courtData = $courts->map(function (EventCourt $c) use ($activeLinks, $tz) {
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
                'matches'   => $this->matchesOfCourt($c),
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
            'dayCourtPending' => $isFriendly && $occId && $courts->isEmpty(),
            'unassigned'  => $isFriendly ? collect() : $this->unassignedMatches($event, $occId),
            'teams'       => $this->teamRows($event, $occId, $isFriendly),
            'form'        => [
                'template'        => in_array($theme['template'] ?? '', ['compact', 'sets_table'], true) ? $theme['template'] : 'compact',
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
    /*  Оформление, заставка, итоги                                        */
    /* ------------------------------------------------------------------ */

    public function saveTheme(Request $request, Event $event): JsonResponse
    {
        $this->authorizeEvent($request, $event);
        $data = $request->validate([
            'occurrence_id'   => ['required', 'integer'],
            'template'        => ['required', Rule::in(['compact', 'sets_table'])],
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
        $m = $state['match'] ?? null;
        if (($state['mode'] ?? '') !== 'splash' && $m && ($m['status'] ?? '') === 'live') {
            return ['kind' => 'live', 'text' => __('stream.court_live', ['a' => $m['home'], 'b' => $m['away']])];
        }
        $n = $state['next_match'] ?? null;
        if ($n) {
            $t = !empty($n['scheduled_at']) ? Carbon::parse($n['scheduled_at'])->setTimezone($tz)->format('H:i') : null;
            return ['kind' => 'next', 'text' => $t
                ? __('stream.court_next', ['a' => $n['home'], 'b' => $n['away'], 't' => $t])
                : __('stream.court_next_nt', ['a' => $n['home'], 'b' => $n['away']])];
        }
        return ['kind' => 'none', 'text' => __('stream.court_none')];
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
