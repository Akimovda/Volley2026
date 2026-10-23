{{-- Настройка стрима (она же пульт). Права и данные — BroadcastSetupController::show --}}
@php
    $qs = $occurrence ? ['occurrence_id' => $occurrence->id] : [];
    $tz = $occurrence?->timezone ?: ($event->timezone ?: 'Europe/Moscow');
    $backUrl = $isFriendly
        ? route('game.manage', $event) . ($occurrence ? '?occurrence=' . $occurrence->id : '')
        : route('tournament.setup', $event) . ($occurrence ? '?occurrence_id=' . $occurrence->id : '');
    $modes = ['auto', 'splash', 'live', 'set_summary', 'match_summary', 'off'];
    $modeLabels = [];
    foreach ($modes as $m) { $modeLabels[$m] = __('stream.mode_' . $m); }
    $platformLabels = ['vk' => __('stream.p_vk'), 'rutube' => __('stream.p_rutube'), 'youtube' => __('stream.p_youtube'), 'other' => __('stream.p_other')];
    $destPlatforms = [];
    foreach (['vk','rutube','youtube','telegram','custom'] as $dp) { $destPlatforms[$dp] = __('stream.dest_p_' . $dp); }
    $jsStrings = [
        'musicListen' => __('stream.music_listen'), 'musicStop' => __('stream.music_stop'),
        'saved' => __('stream.saved'), 'error' => __('stream.error'), 'copied' => __('stream.link_copied'),
        'regenQ' => __('stream.link_regen_q'), 'revokeQ' => __('stream.link_revoke_q'), 'yes' => __('stream.yes'), 'cancel' => __('stream.cancel'),
        'captionAuto' => __('stream.mode_caption_auto'), 'manualBanner' => __('stream.manual_banner'),
        'matchAuto' => __('stream.match_auto'), 'matchNone' => __('stream.match_none'),
        'modes' => $modeLabels, 'pick' => __('stream.assign_pick'),
        'destDeleteQ' => __('stream.dest_delete_q'), 'destShow' => __('stream.dest_show'), 'destHide' => __('stream.dest_hide'),
        'destDefault' => __('stream.dest_default'), 'destDelete' => __('stream.dest_delete'), 'destNone' => __('stream.dest_none'),
        'destPlatforms' => $destPlatforms,
        'chOnce' => __('stream.ch_once'), 'chOrphan' => __('stream.ch_orphan'), 'chDefaultUsed' => __('stream.ch_default_used'),
        'scorerRegenQ' => __('stream.scorer_regen_q'), 'scorerRevokeQ' => __('stream.scorer_revoke_q'),
        'scorerInvited' => __('stream.scorer_invited'), 'scorerInviteQ' => __('stream.scorer_invite_q'), 'scorerNoUsers' => __('stream.scorer_no_users'),
    ];
    $cfg = [
        'csrf' => csrf_token(),
        'base' => route('broadcast.setup', $event),
        'occurrenceId' => $occurrence?->id,
        'courts' => $courts,
        'channels' => $channels,
        'chDefault' => optional($channels->firstWhere('is_default', true)),
        's' => $jsStrings,
    ];
@endphp
<x-voll-layout body_class="stream-setup-page">

    <x-slot name="title">{{ __('stream.title') }} — {{ $event->title }}</x-slot>
    <x-slot name="h1">📡 {{ __('stream.title') }} <x-beta-badge /></x-slot>
    <x-slot name="h2">{{ $event->title }}</x-slot>

    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('events.show', $event) }}{{ $occurrence ? '?occurrence=' . $occurrence->id : '' }}" itemprop="item"><span itemprop="name">{{ $event->title }}</span></a>
            <meta itemprop="position" content="2">
        </li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <span itemprop="name">{{ __('stream.breadcrumb') }}</span>
            <meta itemprop="position" content="3">
        </li>
    </x-slot>

    <x-slot name="style">
        <style>
            .bc-page .ramka { margin-bottom: 2rem; }
            .bc-row { display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap; padding:1rem 0; border-bottom:1px solid rgba(0,0,0,.08); }
            .bc-row:last-child { border-bottom:0; }
            body.dark .bc-row { border-bottom-color: rgba(255,255,255,.1); }
            .bc-court { padding:1.2rem 0; border-bottom:1px solid rgba(0,0,0,.08); }
            .bc-court:last-child { border-bottom:0; }
            body.dark .bc-court { border-bottom-color: rgba(255,255,255,.1); }
            .bc-court-name { font-weight:700; font-size:1.7rem; }
            .bc-status { font-size:1.4rem; opacity:.75; margin-top:.2rem; }
            .bc-status.live { opacity:1; color:#d32f2f; font-weight:600; }
            .bc-actions { display:flex; gap:.8rem; flex-wrap:wrap; margin-top:.9rem; align-items:center; }
            .bc-linkbox { display:flex; gap:.8rem; flex-wrap:wrap; align-items:center; margin-top:.9rem; }
            .bc-linkbox input { flex:1 1 22rem; min-width:0; font-size:1.3rem; }
            .bc-qr { margin-top:1rem; display:none; }
            .bc-qr.on { display:block; }
            .bc-qr svg { width:18rem; height:18rem; background:#fff; padding:1rem; border-radius:1rem; }
            .bc-warn { font-size:1.25rem; opacity:.65; margin-top:.6rem; }
            .bc-scorer { margin-top:1.4rem; padding-top:1rem; border-top:1px dashed rgba(128,128,128,.35); }
            .bc-court-sub { font-weight:700; font-size:1.5rem; margin-bottom:.3rem; }
            .bc-invite-dd .bc-invite-item { padding:.7rem 1rem; cursor:pointer; font-size:1.3rem; }
            .bc-invite-dd .bc-invite-item:hover { background:rgba(41,103,186,.12); }
            .bc-chips { display:flex; gap:.8rem; flex-wrap:wrap; margin-bottom:1.2rem; }
            .bc-preview { position:relative; width:100%; padding-top:56.25%; background:#555; border-radius:1rem; overflow:hidden; }
            .bc-preview iframe { position:absolute; inset:0; width:100%; height:100%; border:0; }
            .bc-preview .bc-empty { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; color:#fff; font-size:1.5rem; padding:2rem; text-align:center; }
            .bc-modes { display:flex; gap:.7rem; flex-wrap:wrap; margin-top:1.2rem; }
            .bc-modes .btn.on { outline:3px solid #E7612F; outline-offset:1px; }
            .bc-pultrow { margin-top:1.4rem; }
            .bc-pultrow .bc-rl { font-weight:700; font-size:1.3rem; text-transform:uppercase; letter-spacing:.04em; opacity:.7; margin-bottom:.5rem; }
            .bc-demos { display:flex; gap:.7rem; flex-wrap:wrap; padding:1rem 1.2rem; border:2px dashed rgba(0,0,0,.25); border-radius:1rem; }
            body.dark .bc-demos { border-color:rgba(255,255,255,.3); }
            .bc-demos .btn { background:transparent; border:1.5px solid currentColor; color:inherit; }
            .bc-demos .btn.on { background:#2967BA; border-color:#2967BA; color:#fff; }
            .bc-demo-note { font-size:1.25rem; opacity:.7; margin-top:.5rem; }
            .bc-scale { display:flex; align-items:center; gap:1.2rem; } .bc-scale input[type=range] { flex:1; max-width:30rem; } .bc-scale b { min-width:5rem; }
            .bc-short { width:7rem; text-transform:uppercase; }
            .bc-banner { display:none; margin-top:1.2rem; padding:1.2rem 1.5rem; border-radius:1rem; background:#E7612F; color:#fff; font-weight:700; font-size:1.5rem; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap; }
            .bc-banner.on { display:flex; }
            .bc-banner .btn { background:#fff; color:#E7612F; }
            .bc-caption { margin-top:1rem; font-size:1.4rem; opacity:.8; }
            .bc-matchline { margin-top:1rem; font-size:1.5rem; }
            .bc-matchlist { display:none; margin-top:.8rem; }
            .bc-matchlist.on { display:block; }
            .bc-matchlist button { display:block; width:100%; text-align:left; padding:.9rem 1.1rem; margin-bottom:.5rem; border-radius:.8rem; border:1px solid rgba(0,0,0,.15); background:transparent; color:inherit; font-size:1.4rem; cursor:pointer; }
            body.dark .bc-matchlist button { border-color: rgba(255,255,255,.2); }
            .bc-matchlist button.sel { border-color:#E7612F; font-weight:700; }
            .bc-field { margin-bottom:1.3rem; }
            .bc-field label.bc-l { display:block; font-weight:600; font-size:1.4rem; margin-bottom:.4rem; }
            .bc-grid { display:grid; grid-template-columns:1fr; gap:0 2rem; }
            @media (min-width: 768px) { .bc-grid { grid-template-columns:1fr 1fr; } }
            .bc-team { display:flex; gap:1.2rem; align-items:center; flex-wrap:wrap; padding:1.2rem 0; border-bottom:1px solid rgba(0,0,0,.08); }
            body.dark .bc-team { border-bottom-color: rgba(255,255,255,.1); }
            .bc-team:last-child { border-bottom:0; }
            .bc-team-name { font-weight:600; flex:1 1 14rem; min-width:0; }
            .bc-sw { display:inline-block; width:2.6rem; height:2.6rem; border-radius:50%; border:2px solid rgba(0,0,0,.2); cursor:pointer; padding:0; vertical-align:middle; }
            .bc-sw.sel { outline:3px solid #E7612F; outline-offset:2px; }
            .bc-swatches { display:flex; gap:.5rem; flex-wrap:wrap; align-items:center; }
            .bc-hex { width:11rem !important; }
            .bc-logo { width:5rem; height:5rem; object-fit:contain; border-radius:.6rem; background:rgba(128,128,128,.15); }
            .bc-hint { font-size:1.25rem; opacity:.65; }
            .bc-bg { max-width:24rem; width:100%; aspect-ratio:16/9; object-fit:cover; border-radius:.8rem; display:block; margin-bottom:.8rem; }
            .bc-howto li { margin-bottom:.7rem; font-size:1.45rem; line-height:1.4; }
            .bc-toast { position:fixed; left:50%; bottom:3rem; transform:translateX(-50%) translateY(2rem); background:#2e7d32; color:#fff; padding:1rem 2rem; border-radius:3rem; font-size:1.5rem; opacity:0; pointer-events:none; transition:.25s; z-index:99999; }
            .bc-toast.on { opacity:1; transform:translateX(-50%); }
            .bc-toast.err { background:#c62828; }
            .bc-rounds { display:flex; gap:.8rem; flex-wrap:wrap; }
            .bc-tag { font-size:1.2rem; padding:.2rem .8rem; border-radius:1rem; background:rgba(128,128,128,.2); margin-left:.6rem; }
        </style>
    </x-slot>

    <div class="container form bc-page">

        <div class="ramka">
            <div class="d-flex between fvc" style="flex-wrap:wrap;gap:1rem">
                <a href="{{ $backUrl }}" class="btn btn-outline btn-small">← {{ $isFriendly ? __('games.manage_title') : __('tournaments.setup_breadcrumb') }}</a>
                @if($occurrences->count() > 1)
                <div class="bc-rounds">
                    @foreach($occurrences as $o)
                    <a href="{{ route('broadcast.setup', $event) }}?occurrence_id={{ $o->id }}" class="btn btn-small {{ $occurrence && $o->id === $occurrence->id ? '' : 'btn-secondary' }}">
                        {{ $o->starts_at?->copy()->timezone($o->timezone ?: $tz)->format('d.m H:i') }}
                    </a>
                    @endforeach
                </div>
                @endif
            </div>
        </div>

        @if(!$occurrence)
        <div class="ramka"><p>{{ __('stream.court_none') }}</p></div>
        @else

        {{-- 1. Площадки --}}
        <div class="ramka">
            <h2 class="-mt-05">{{ __('stream.courts_h') }}</h2>
            @if($isFriendly)<div class="bc-hint">{{ __('stream.court_single') }}</div>@endif

            @foreach($courts as $c)
            <div class="bc-court" data-court="{{ $c['id'] }}">
                <div class="bc-court-name">
                    {{ $c['name'] }}
                    @unless($c['is_active'])<span class="bc-tag">{{ __('stream.court_hidden') }}</span>@endunless
                </div>
                <div class="bc-status {{ $c['status']['kind'] }}">{{ $c['status']['text'] }}</div>

                <div class="bc-linkbox">
                    @if($c['link'])
                    <input type="text" readonly value="{{ $c['link']['url'] }}" class="bc-link-input" aria-label="{{ __('stream.link_h') }}">
                    <button type="button" class="btn btn-small btn-outline bc-copy">{{ __('stream.link_copy') }}</button>
                    <button type="button" class="btn btn-small btn-outline bc-qr-btn">{{ __('stream.qr') }}</button>
                    <button type="button" class="btn btn-small btn-outline bc-regen">{{ __('stream.link_regen') }}</button>
                    <button type="button" class="btn btn-small btn-outline bc-revoke">{{ __('stream.link_revoke') }}</button>
                    @else
                    <span class="bc-hint">{{ __('stream.link_none') }}</span>
                    <button type="button" class="btn btn-small bc-issue">{{ __('stream.link_issue') }}</button>
                    @endif
                </div>
                @if($c['link'])
                <div class="bc-qr" data-url="{{ $c['link']['url'] }}"></div>
                <div class="bc-warn">{{ __('stream.link_warn') }}</div>
                @endif

                {{-- Ведущий матча: ввод счёта по токен-ссылке (без аккаунта) или приглашение пользователя --}}
                <div class="bc-scorer" data-scorer>
                    <div class="bc-court-sub">🏐 {{ __('stream.scorer_h') }}</div>
                    <div class="bc-hint">{{ __('stream.scorer_hint') }}</div>
                    <div class="bc-linkbox">
                        @if($c['scorer']['anon'])
                        <input type="text" readonly value="{{ $c['scorer']['anon']['url'] }}" class="bc-link-input bc-scorer-input" aria-label="{{ __('stream.scorer_h') }}">
                        <button type="button" class="btn btn-small btn-outline bc-scorer-copy">{{ __('stream.link_copy') }}</button>
                        <button type="button" class="btn btn-small btn-outline bc-scorer-qr-btn">{{ __('stream.qr') }}</button>
                        <button type="button" class="btn btn-small btn-outline bc-scorer-regen">{{ __('stream.link_regen') }}</button>
                        <button type="button" class="btn btn-small btn-outline bc-scorer-revoke" data-link="{{ $c['scorer']['anon']['id'] }}">{{ __('stream.link_revoke') }}</button>
                        @else
                        <span class="bc-hint">{{ __('stream.scorer_none') }}</span>
                        <button type="button" class="btn btn-small bc-scorer-issue">{{ __('stream.link_issue') }}</button>
                        @endif
                    </div>
                    @if($c['scorer']['anon'])
                    <div class="bc-qr bc-scorer-qr" data-url="{{ $c['scorer']['anon']['url'] }}"></div>
                    <div class="bc-hint">
                        {{ __('stream.scorer_expires', ['t' => $c['scorer']['anon']['expires'] ?? '—']) }}
                        @if($c['scorer']['anon']['actor'])
                        · {{ __('stream.scorer_last', ['name' => $c['scorer']['anon']['actor'], 't' => $c['scorer']['anon']['actor_at']]) }}
                        @elseif($c['scorer']['anon']['used'])
                        · {{ __('stream.scorer_opened', ['t' => $c['scorer']['anon']['used']]) }}
                        @endif
                    </div>
                    @endif

                    <div class="bc-actions bc-invite" style="position:relative">
                        <input type="text" class="bc-invite-search" placeholder="{{ __('stream.scorer_invite_ph') }}" autocomplete="off" style="max-width:24rem" aria-label="{{ __('stream.scorer_invite') }}">
                        <div class="form-select-dropdown bc-invite-dd" style="display:none;position:absolute;top:100%;left:0;z-index:20;max-height:22rem;overflow:auto;min-width:24rem"></div>
                    </div>
                    @if(!empty($c['scorer']['invites']))
                    <div class="bc-invites">
                        @foreach($c['scorer']['invites'] as $inv)
                        <div class="bc-invite-row" style="display:flex;gap:.8rem;align-items:center;flex-wrap:wrap;margin-top:.5rem">
                            <b>{{ $inv['user'] }}</b>
                            <span class="bc-hint">
                                {{ __('stream.scorer_expires', ['t' => $inv['expires'] ?? '—']) }}
                                @if($inv['actor']) · {{ __('stream.scorer_last', ['name' => $inv['actor'], 't' => $inv['actor_at']]) }}@elseif($inv['used']) · {{ __('stream.scorer_opened', ['t' => $inv['used']]) }}@endif
                            </span>
                            <button type="button" class="btn btn-small btn-outline bc-scorer-copy-url" data-url="{{ $inv['url'] }}">{{ __('stream.link_copy') }}</button>
                            <button type="button" class="btn btn-small btn-outline bc-scorer-revoke" data-link="{{ $inv['id'] }}">{{ __('stream.link_revoke') }}</button>
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>

                <div class="bc-actions">
                    <input type="text" class="bc-rename-input" value="{{ $c['name'] }}" maxlength="100" style="max-width:20rem" aria-label="{{ __('stream.court_name') }}">
                    <button type="button" class="btn btn-small btn-outline bc-rename">{{ __('stream.court_rename') }}</button>
                    @unless($isFriendly)
                    <button type="button" class="btn btn-small btn-outline bc-toggle" data-active="{{ $c['is_active'] ? 1 : 0 }}">{{ $c['is_active'] ? __('stream.court_hide') : __('stream.court_show') }}</button>
                    @endunless
                </div>
            </div>
            @endforeach

            @if($dayCourtPending)
            <div class="bc-court">
                <p>{{ __('stream.day_court_pending') }}</p>
                <div class="bc-actions"><button type="button" class="btn btn-small" id="bc-issue-day">{{ __('stream.link_issue') }}</button></div>
            </div>
            @endif

            @unless($isFriendly)
            <div class="bc-actions" style="margin-top:1.5rem">
                <input type="text" id="bc-new-court" maxlength="100" placeholder="{{ __('stream.court_add_ph') }}" style="max-width:24rem" aria-label="{{ __('stream.court_name') }}">
                <button type="button" class="btn btn-small" id="bc-add-court">+ {{ __('stream.court_add') }}</button>
            </div>
            @endunless

            @if($unassigned->isNotEmpty() && $courts->isNotEmpty())
            <h3 style="margin-top:2.5rem">{{ __('stream.unassigned_h') }}</h3>
            <div class="bc-hint">{{ __('stream.unassigned_hint') }}</div>
            @foreach($unassigned as $m)
            <div class="bc-row" data-match="{{ $m->id }}">
                <div>
                    <div class="b-600">{{ $m->teamHome?->name ?? '?' }} — {{ $m->teamAway?->name ?? '?' }}</div>
                    <div class="bc-hint">{{ $m->stage?->name }}@if($m->scheduled_at) · {{ $m->scheduled_at->copy()->timezone($tz)->format('d.m H:i') }}@endif</div>
                </div>
                <div class="bc-actions" style="margin:0">
                    <select class="bc-assign-select" aria-label="{{ __('stream.assign_pick') }}">
                        @foreach($courts as $c)<option value="{{ $c['id'] }}">{{ $c['name'] }}</option>@endforeach
                    </select>
                    <button type="button" class="btn btn-small bc-assign">{{ __('stream.assign') }}</button>
                </div>
            </div>
            @endforeach
            @endif
        </div>

        {{-- 2. Пульт --}}
        <div class="ramka" id="bc-pult">
            <h2 class="-mt-05">{{ __('stream.pult_h') }}</h2>
            @if($courts->isEmpty())
            <p>{{ __('stream.pult_no_courts') }}</p>
            @else
            <div class="bc-chips" id="bc-pult-chips">
                @foreach($courts as $c)
                <button type="button" class="btn btn-small btn-secondary" data-court-chip="{{ $c['id'] }}">{{ $c['name'] }}</button>
                @endforeach
            </div>
            <div class="bc-preview"><iframe id="bc-frame" title="preview" allowtransparency="true" style="display:none"></iframe><div class="bc-empty" id="bc-frame-empty">{{ __('stream.pult_no_link') }}</div></div>

            <div class="bc-banner" id="bc-banner"><span id="bc-banner-text"></span><button type="button" class="btn btn-small" id="bc-back-auto">{{ __('stream.manual_back') }}</button></div>

            <div class="bc-pultrow"><div class="bc-rl">{{ __('stream.pult_live_h') }}</div>
            <div class="bc-modes" id="bc-modes" style="margin-top:0">
                @foreach($modes as $m)
                <button type="button" class="btn btn-small btn-outline" data-mode="{{ $m }}">{{ $modeLabels[$m] }}</button>
                @endforeach
            </div></div>
            <div class="bc-pultrow"><div class="bc-rl">{{ __('stream.demo_h') }}</div>
            <div class="bc-demos" id="bc-demos">
                @foreach(['splash', 'live', 'sub', 'set_summary', 'match_summary'] as $d)
                <button type="button" class="btn btn-small" data-demo="{{ $d }}">{{ __('stream.demo_' . $d) }}</button>
                @endforeach
                <button type="button" class="btn btn-small on" data-demo="">{{ __('stream.demo_live_air') }}</button>
            </div>
            <div class="bc-demo-note">{{ __('stream.demo_note') }}</div></div>
            <div class="bc-caption" id="bc-caption"></div>
            <div class="bc-matchline">{{ __('stream.match_label') }}: <b id="bc-match-name">—</b> · <a href="javascript:void(0)" class="blink" id="bc-match-pick">{{ __('stream.match_pick') }}</a></div>
            <div class="bc-matchlist" id="bc-matchlist"></div>
            @endif
        </div>

        {{-- 3. Оформление --}}
        <div class="ramka">
            <h2 class="-mt-05">{{ __('stream.style_h') }}</h2>
            <form id="bc-theme-form" onsubmit="return false">
                <div class="bc-grid">
                    <div class="bc-field">
                        <label class="bc-l" for="bc-template">{{ __('stream.tpl') }}</label>
                        <select id="bc-template" name="template">
                            <option value="compact" @selected($form['template'] === 'compact')>{{ __('stream.tpl_compact') }}</option>
                            <option value="sets_table" @selected($form['template'] === 'sets_table')>{{ __('stream.tpl_sets_table') }}</option>
                            <option value="glass" @selected($form['template'] === 'glass')>{{ __('stream.tpl_glass') }}</option>
                            <option value="neon" @selected($form['template'] === 'neon')>{{ __('stream.tpl_neon') }}</option>
                            <option value="light_tv" @selected($form['template'] === 'light_tv')>{{ __('stream.tpl_light_tv') }}</option>
                        </select>
                    </div>
                    <div class="bc-field">
                        <label class="bc-l" for="bc-corner">{{ __('stream.corner') }}</label>
                        <select id="bc-corner" name="corner">
                            @foreach(['top-left', 'top-right', 'bottom-left', 'bottom-right'] as $k)
                            <option value="{{ $k }}" @selected($form['corner'] === $k)>{{ __('stream.corner_' . $k) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="bc-grid">
                    <div class="bc-field">
                        <label class="bc-l" for="bc-scale">{{ __('stream.scale') }}</label>
                        <div class="bc-scale"><input type="range" id="bc-scale" min="50" max="150" step="10" value="{{ $form['scale'] }}"><b id="bc-scale-v">{{ $form['scale'] }} %</b></div>
                    </div>
                    <div class="bc-field">
                        <label class="bc-l" for="bc-opacity">{{ __('stream.opacity') }}</label>
                        <div class="bc-scale"><input type="range" id="bc-opacity" min="30" max="100" step="5" value="{{ $form['opacity'] }}"><b id="bc-opacity-v">{{ $form['opacity'] }} %</b></div>
                    </div>
                    <div class="bc-field">
                        <label class="bc-l" for="bc-edge">{{ __('stream.edge') }}</label>
                        <select id="bc-edge" name="edge_margin">
                            @foreach(['small', 'medium', 'large'] as $k)
                            <option value="{{ $k }}" @selected($form['edge_margin'] === $k)>{{ __('stream.edge_' . $k) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <label class="checkbox-item bc-field">
                    <input type="checkbox" id="bc-team-names" name="show_team_names" value="1" @checked($form['show_team_names'])>
                    <div class="custom-checkbox"></div>
                    <span>{{ __('stream.show_team_names') }}</span>
                </label>
                <label class="checkbox-item bc-field">
                    <input type="checkbox" id="bc-set-scores" name="show_set_scores" value="1" @checked($form['show_set_scores'])>
                    <div class="custom-checkbox"></div>
                    <span>{{ __('stream.show_set_scores') }}</span>
                </label>
                <label class="checkbox-item bc-field">
                    <input type="checkbox" id="bc-show-logos" name="show_logos" value="1" @checked($form['show_logos'])>
                    <div class="custom-checkbox"></div>
                    <span>{{ __('stream.show_logos') }}</span>
                </label>
                <button type="button" class="btn btn-small" id="bc-theme-save">{{ __('stream.save') }}</button>
            </form>

            <h3 style="margin-top:2.5rem">{{ __('stream.teams_h') }}</h3>
            <div class="bc-hint">{{ __('stream.team_logo_hint') }}</div>
            @forelse($teams as $t)
            <div class="bc-team" data-team="{{ $t['id'] }}" data-friendly="{{ $t['friendly'] ? 1 : 0 }}">
                <div class="bc-team-name">{{ $t['name'] }}</div>
                @if($t['friendly'])
                <div class="bc-swatches"><span class="bc-sw sel" style="background:{{ $t['color'] }};cursor:default"></span><span class="bc-hint">{{ __('stream.team_friendly_color') }}</span></div>
                @else
                <div class="bc-swatches">
                    @foreach($palette as $p)
                    <button type="button" class="bc-sw {{ strtoupper($t['color']) === $p ? 'sel' : '' }}" style="background:{{ $p }}" data-color="{{ $p }}" aria-label="{{ $p }}"></button>
                    @endforeach
                    <input type="text" class="bc-hex" value="{{ $t['color'] }}" maxlength="7" placeholder="#RRGGBB" aria-label="{{ __('stream.team_color_hex') }}">
                </div>
                @endif
                <div class="d-flex fvc" style="gap:.6rem">
                    <label class="bc-hint" style="margin:0" for="bc-short-{{ $t['id'] }}">{{ __('stream.short_name') }}</label>
                    <input type="text" class="bc-short" id="bc-short-{{ $t['id'] }}" value="{{ $t['short_name'] }}" maxlength="4" placeholder="{{ $t['short_default'] }}">
                </div>
                <div class="d-flex fvc" style="gap:.8rem;flex-wrap:wrap">
                    @if($t['logo'])<img class="bc-logo" src="{{ $t['logo'] }}" alt="">@endif
                    <label class="btn btn-small btn-outline" style="margin:0;cursor:pointer">{{ __('stream.team_logo_upload') }}<input type="file" class="bc-logo-file" accept="image/jpeg,image/png,image/webp" style="display:none"></label>
                    @if($t['logo'])<button type="button" class="btn btn-small btn-outline bc-logo-del">{{ __('stream.team_logo_delete') }}</button>@endif
                </div>
            </div>
            @empty
            <p class="bc-hint">{{ __('stream.teams_empty') }}</p>
            @endforelse
        </div>

        {{-- 4. Заставка и итоги --}}
        <div class="ramka">
            <h2 class="-mt-05">{{ __('stream.splash_h') }}</h2>
            <div class="bc-field">
                <span class="bc-l" style="display:block;font-weight:600;font-size:1.4rem;margin-bottom:.4rem">{{ __('stream.bg') }}</span>
                @if($form['bg_url'])<img src="{{ $form['bg_url'] }}" class="bc-bg" id="bc-bg-img" alt="">@else<img src="" class="bc-bg" id="bc-bg-img" alt="" style="display:none">@endif
                <label class="btn btn-small btn-outline" style="margin:0;cursor:pointer">{{ __('stream.bg_upload') }}<input type="file" id="bc-bg-file" accept="image/jpeg,image/png,image/webp" style="display:none"></label>
                <button type="button" class="btn btn-small btn-outline" id="bc-bg-del" @if(!$form['bg_url']) style="display:none" @endif>{{ __('stream.bg_delete') }}</button>
                <div class="bc-hint" style="margin-top:.5rem">{{ __('stream.bg_hint') }}</div>
            </div>
            <form id="bc-splash-form" onsubmit="return false">
                <div class="bc-field">
                    <label class="bc-l" for="bc-text">{{ __('stream.greeting') }}</label>
                    <input type="text" id="bc-text" name="text" maxlength="80" value="{{ $form['text'] }}" placeholder="{{ __('stream.greeting_ph') }}">
                </div>
                <div class="bc-grid">
                    <div class="bc-field">
                        <label class="bc-l" for="bc-timer">{{ __('stream.timer') }}</label>
                        <select id="bc-timer" name="timer">
                            @foreach(['schedule', 'custom', 'none'] as $k)
                            <option value="{{ $k }}" @selected($form['timer'] === $k)>{{ __('stream.timer_' . $k) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="bc-field" id="bc-custom-wrap" @if($form['timer'] !== 'custom') style="display:none" @endif>
                        <label class="bc-l" for="bc-custom-at">{{ __('stream.timer_at') }} ({{ $tz }})</label>
                        <input type="datetime-local" id="bc-custom-at" name="custom_at" value="{{ $form['custom_at'] }}">
                    </div>
                    <div class="bc-field">
                        <label class="bc-l" for="bc-summary-mode">{{ __('stream.summary_mode') }}</label>
                        <select id="bc-summary-mode" name="summary_mode">
                            <option value="every_set" @selected($form['summary_mode'] === 'every_set')>{{ __('stream.sm_every_set') }}</option>
                            <option value="match_end" @selected($form['summary_mode'] === 'match_end')>{{ __('stream.sm_match_end') }}</option>
                        </select>
                    </div>
                    <div class="bc-field">
                        <label class="bc-l" for="bc-set-secs">{{ __('stream.set_secs') }}</label>
                        <input type="number" id="bc-set-secs" name="set_secs" min="5" max="120" value="{{ $form['set_secs'] }}">
                    </div>
                    <div class="bc-field">
                        <label class="bc-l" for="bc-match-secs">{{ __('stream.match_secs') }}</label>
                        <input type="number" id="bc-match-secs" name="match_secs" min="10" max="300" value="{{ $form['match_secs'] }}">
                    </div>
                </div>
                <button type="button" class="btn btn-small" id="bc-splash-save">{{ __('stream.save') }}</button>
            </form>
        </div>

        {{-- 4b. Музыка --}}
        <div class="ramka" id="bc-music">
            <h2 class="-mt-05">{{ __('stream.music_h') }}</h2>
            <form id="bc-music-form" onsubmit="return false">
                <div style="margin-bottom:1rem">
                    <label style="cursor:pointer"><input type="checkbox" id="bc-music-enabled" @checked($form['music']['enabled'])><span style="margin-left:.6rem">{{ __('stream.music_enabled') }}</span></label>
                </div>
                <div class="bc-field">
                    <span class="bc-l" style="display:block;font-weight:600;font-size:1.4rem;margin-bottom:.4rem">{{ __('stream.music_modes') }}</span>
                    @foreach(\App\Models\BroadcastSetting::MUSIC_MODES as $mm)
                    <div><label style="cursor:pointer"><input type="checkbox" class="bc-music-mode" value="{{ $mm }}" @checked(in_array($mm, $form['music']['modes'], true))><span style="margin-left:.6rem">{{ __('stream.music_m_' . $mm) }}</span></label></div>
                    @endforeach
                </div>
                <div class="bc-field">
                    <label class="bc-l" for="bc-music-volume">{{ __('stream.music_volume') }}: <b id="bc-music-volume-v">{{ $form['music']['volume'] }}</b>%</label>
                    <input type="range" id="bc-music-volume" min="0" max="100" step="5" value="{{ $form['music']['volume'] }}" style="width:100%;max-width:32rem">
                </div>
                <div class="bc-field">
                    <label class="bc-l" for="bc-music-track">{{ __('stream.music_track') }}</label>
                    @php $curTrack = $form['music']['track']; if ($curTrack === 'custom' && !$form['music_custom']) { $curTrack = 'calm'; } @endphp
                    <input type="hidden" id="bc-music-track" value="{{ $curTrack }}">
                    @foreach(array_keys(\App\Models\BroadcastSetting::MUSIC_TRACKS) as $tk)
                    <div class="bc-music-opt" style="display:flex;align-items:center;gap:.8rem;margin-bottom:.4rem">
                        <label style="cursor:pointer;margin:0"><input type="radio" name="bc-music-tr" class="bc-music-tr" value="{{ $tk }}" @checked($curTrack === $tk)><span style="margin-left:.6rem">{{ __('stream.music_track_' . $tk) }}</span></label>
                        <button type="button" class="btn btn-small btn-outline bc-music-play" data-url="{{ asset(\App\Models\BroadcastSetting::MUSIC_TRACKS[$tk]) }}" title="{{ __('stream.music_listen') }}">▶</button>
                    </div>
                    @endforeach
                    <div class="bc-music-opt" style="display:flex;align-items:center;gap:.8rem">
                        <label style="cursor:pointer;margin:0"><input type="radio" name="bc-music-tr" class="bc-music-tr" value="custom" @checked($curTrack === 'custom')><span style="margin-left:.6rem">{{ __('stream.music_track_custom') }}</span></label>
                        <button type="button" class="btn btn-small btn-outline bc-music-play" id="bc-music-play-custom" data-url="{{ $form['music_custom'] ? $form['music_url'] : '' }}" title="{{ __('stream.music_listen') }}" @if(!$form['music_custom']) style="display:none" @endif>▶</button>
                    </div>
                </div>
                <div class="bc-field">
                    <label class="btn btn-small btn-outline" style="margin:0;cursor:pointer">{{ __('stream.music_upload') }}<input type="file" id="bc-music-file" accept="audio/mpeg,audio/mp4,audio/aac,.mp3,.m4a,.aac" style="display:none"></label>
                    <button type="button" class="btn btn-small btn-outline" id="bc-music-del" @if(!$form['music_custom']) style="display:none" @endif>{{ __('stream.music_delete') }}</button>
                    <div class="bc-hint" style="margin-top:.5rem">{{ __('stream.music_file_hint') }}</div>
                    <div class="bc-hint" style="margin-top:.4rem;color:#c0392b">⚠️ {{ __('stream.music_copyright') }}</div>
                </div>
                <audio id="bc-music-audio" preload="none"></audio>
                <button type="button" class="btn btn-small" id="bc-music-save">{{ __('stream.save') }}</button>
                <div class="bc-hint" style="margin-top:.8rem">{{ __('stream.music_hint') }}</div>
            </form>
        </div>

        {{-- 5. Ссылки на эфир --}}
        <div class="ramka" id="bc-streams">
            <h2 class="-mt-05">{{ __('stream.streams_h') }}</h2>
            <div class="bc-hint">{{ __('stream.streams_hint') }}</div>
            @if($courts->isEmpty())
            <p>{{ __('stream.pult_no_courts') }}</p>
            @else
            <div class="bc-chips" id="bc-stream-chips" style="margin-top:1rem">
                @foreach($courts as $c)
                <button type="button" class="btn btn-small btn-secondary" data-stream-chip="{{ $c['id'] }}">{{ $c['name'] }}</button>
                @endforeach
            </div>
            <form id="bc-streams-form" onsubmit="return false">
                @foreach($platformLabels as $k => $label)
                <div class="bc-field">
                    <label class="bc-l" for="bc-s-{{ $k }}">{{ $label }}</label>
                    <input type="url" id="bc-s-{{ $k }}" data-platform="{{ $k }}" maxlength="300" placeholder="https://">
                </div>
                @endforeach
                <button type="button" class="btn btn-small" id="bc-streams-save">{{ __('stream.save') }}</button>
            </form>
            @endif
        </div>

        {{-- 5б. Эфир из приложения VolleyClub: куда отправлять видео --}}
        <div class="ramka" id="bc-dest">
            <h2 class="-mt-05">{{ __('stream.dest_h') }}</h2>
            <div class="bc-hint">{{ __('stream.dest_hint') }}</div>
            <x-beta-note />
            @if($courts->isEmpty())
            <p>{{ __('stream.pult_no_courts') }}</p>
            @else
            <div class="bc-chips" id="bc-dest-chips" style="margin-top:1rem">
                @foreach($courts as $c)
                <button type="button" class="btn btn-small btn-secondary" data-dest-chip="{{ $c['id'] }}">{{ $c['name'] }}</button>
                @endforeach
            </div>
            <div class="bc-field" style="margin-top:1rem">
                <label class="bc-l" for="bc-d-channel">{{ __('stream.ch_select') }}</label>
                <select id="bc-d-channel" name="bc_d_channel"></select>
                @if($canManageChannels)
                <div class="bc-hint"><a class="blink b-600" href="{{ route('broadcast.channels') }}">{{ __('stream.ch_manage') }}</a></div>
                @endif
            </div>
            <div id="bc-dest-list" style="margin-top:1rem"></div>
            <form id="bc-dest-form" onsubmit="return false" autocomplete="off" style="margin-top:1.2rem">
                <div class="bc-field">
                    <label class="bc-l" for="bc-d-platform">{{ __('stream.dest_platform') }}</label>
                    <select id="bc-d-platform" name="bc_d_platform">
                        @foreach($destPlatforms as $k => $label)
                        <option value="{{ $k }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="bc-field">
                    <label class="bc-l" for="bc-d-server">{{ __('stream.dest_server') }}</label>
                    <input type="text" id="bc-d-server" name="bc_d_server" maxlength="300" placeholder="rtmp://">
                </div>
                <div class="bc-field">
                    <label class="bc-l" for="bc-d-key">{{ __('stream.dest_key') }}</label>
                    <input type="password" id="bc-d-key" name="bc_d_key" maxlength="300" autocomplete="new-password" placeholder="{{ __('stream.dest_key_ph') }}">
                </div>
                <div class="bc-field">
                    <label class="bc-l" for="bc-d-label">{{ __('stream.dest_label') }}</label>
                    <input type="text" id="bc-d-label" name="bc_d_label" maxlength="100">
                </div>
                <div class="bc-field">
                    <label class="checkbox-item">
                        <input type="checkbox" id="bc-d-default" name="bc_d_default" value="1">
                        <div class="custom-checkbox"></div>
                        <span>{{ __('stream.dest_default') }}</span>
                    </label>
                </div>
                <button type="button" class="btn btn-small" id="bc-dest-add">{{ __('stream.dest_add') }}</button>
            </form>

            <h3 style="margin-top:2rem">{{ __('stream.dest_where_h') }}</h3>
            @foreach(['vk','rutube','youtube','telegram','custom'] as $pk)
            <details class="bc-where" data-where="{{ $pk }}" style="margin-top:.6rem">
                <summary>{{ $destPlatforms[$pk] }}</summary>
                <div class="bc-hint" style="opacity:.85;margin-top:.5rem">{!! __('stream.dest_where_' . $pk) !!}</div>
            </details>
            @endforeach
            @endif
        </div>

        {{-- 6. Как подключить камеру --}}
        <div class="ramka">
            <h2 class="-mt-05">{{ __('stream.howto_h') }}</h2>
            <ul class="bc-howto">
                <li>{!! __('stream.howto_phone') !!}</li>
                <li>{!! __('stream.howto_laptop') !!}</li>
                <li>{{ __('stream.howto_quality') }}</li>
            </ul>
        </div>

        @endif
    </div>

    <div class="bc-toast" id="bc-toast"></div>

    <x-slot name="script">
        <script src="{{ asset('assets/qrcode.js') }}?v={{ filemtime(public_path('assets/qrcode.js')) }}"></script>
        <script>
        (function () {
            var CFG = @json($cfg);
            if (!CFG.occurrenceId) { return; }
            var $ = jQuery, S = CFG.s;
            var courts = CFG.courts, byId = {};
            courts.forEach(function (c) { byId[c.id] = c; });

            /* ---------- общие ---------- */
            var toastTimer;
            function toast(msg, err) {
                var el = document.getElementById('bc-toast');
                el.textContent = msg; el.classList.toggle('err', !!err); el.classList.add('on');
                clearTimeout(toastTimer); toastTimer = setTimeout(function () { el.classList.remove('on'); }, 2200);
            }
            function errText(xhr) {
                var j = xhr && xhr.responseJSON;
                if (j && j.errors) { var k = Object.keys(j.errors)[0]; return j.errors[k][0]; }
                return (j && j.message) || S.error;
            }
            function fail(xhr) {
                var t = errText(xhr);
                if (window.swal) { swal({ text: t, icon: 'error' }); } else { toast(t, true); }
            }
            function call(method, url, data, ok, bad) {
                var fd = (data instanceof FormData);
                if (fd) { data.append('_method', method); } else { data = $.extend({ _method: method }, data || {}); }
                $.ajax({
                    url: url, type: 'POST', data: data, dataType: 'json',
                    processData: !fd, contentType: fd ? false : 'application/x-www-form-urlencoded; charset=UTF-8',
                    headers: { 'X-CSRF-TOKEN': CFG.csrf, 'Accept': 'application/json' },
                    xhrFields: { withCredentials: true },
                    success: function (r) { if (ok) { ok(r); } },
                    error: function (xhr) { if (bad) { bad(xhr); } else { fail(xhr); } }
                });
            }
            function confirmThen(text, fn) {
                if (!window.swal) { if (confirm(text)) { fn(); } return; }
                swal({ text: text, icon: 'warning', buttons: { cancel: { text: S.cancel, visible: true }, confirm: { text: S.yes } } })
                    .then(function (v) { if (v) { fn(); } });
            }
            function reload() { location.reload(); }
            function esc(s) { return $('<div>').text(s == null ? '' : s).html(); }
            function selVal(sel) { var v = $(sel).val(); return v; }

            /* ---------- площадки ---------- */
            $('#bc-add-court').on('click', function () {
                var name = $.trim($('#bc-new-court').val());
                if (!name) { return; }
                call('POST', CFG.base + '/courts', { name: name, occurrence_id: CFG.occurrenceId }, reload);
            });
            $(document).on('click', '.bc-rename', function () {
                var box = $(this).closest('[data-court]');
                call('PATCH', CFG.base + '/courts/' + box.data('court'), { name: $.trim(box.find('.bc-rename-input').val()) }, reload);
            });
            $(document).on('click', '.bc-toggle', function () {
                var box = $(this).closest('[data-court]');
                call('PATCH', CFG.base + '/courts/' + box.data('court'), { is_active: $(this).data('active') ? 0 : 1 }, reload);
            });
            $(document).on('click', '.bc-assign', function () {
                var row = $(this).closest('[data-match]');
                call('POST', CFG.base + '/matches/' + row.data('match') + '/court', { court_id: row.find('.bc-assign-select').val() },
                    function () { toast(S.saved); setTimeout(reload, 500); });
            });

            /* ---------- ссылки оверлея ---------- */
            $('#bc-issue-day').on('click', function () {
                call('POST', CFG.base + '/day-court/link', { occurrence_id: CFG.occurrenceId }, reload);
            });
            $(document).on('click', '.bc-issue', function () {
                call('POST', CFG.base + '/courts/' + $(this).closest('[data-court]').data('court') + '/link', {}, reload);
            });
            $(document).on('click', '.bc-regen', function () {
                var id = $(this).closest('[data-court]').data('court');
                confirmThen(S.regenQ, function () { call('POST', CFG.base + '/courts/' + id + '/link', {}, reload); });
            });
            $(document).on('click', '.bc-revoke', function () {
                var id = $(this).closest('[data-court]').data('court');
                confirmThen(S.revokeQ, function () { call('DELETE', CFG.base + '/courts/' + id + '/link', {}, reload); });
            });
            $(document).on('click', '.bc-copy', function () {
                var inp = $(this).closest('[data-court]').find('.bc-link-input')[0];
                inp.focus(); inp.select(); inp.setSelectionRange(0, 9999);
                var done = function () { toast(S.copied); };
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(inp.value).then(done, function () { try { document.execCommand('copy'); done(); } catch (e) {} });
                } else { try { document.execCommand('copy'); done(); } catch (e) {} }
            });
            $(document).on('click', '.bc-qr-btn', function () {
                var box = $(this).closest('[data-court]').find('.bc-qr');
                if (!box.hasClass('on') && !box.children().length) {
                    var qr = qrcode(0, 'M'); qr.addData(box.data('url')); qr.make();
                    box.html(qr.createSvgTag({ cellSize: 4, margin: 0, scalable: true }));
                }
                box.toggleClass('on');
            });

            /* ---------- ссылки ведущих ---------- */
            function courtId(el) { return $(el).closest('[data-court]').data('court'); }
            $(document).on('click', '.bc-scorer-issue', function () {
                call('POST', CFG.base + '/courts/' + courtId(this) + '/scorer-link', {}, reload);
            });
            $(document).on('click', '.bc-scorer-regen', function () {
                var id = courtId(this);
                confirmThen(S.scorerRegenQ, function () { call('POST', CFG.base + '/courts/' + id + '/scorer-link', {}, reload); });
            });
            $(document).on('click', '.bc-scorer-revoke', function () {
                var id = courtId(this), link = $(this).data('link');
                confirmThen(S.scorerRevokeQ, function () { call('DELETE', CFG.base + '/courts/' + id + '/scorer-links/' + link, {}, reload); });
            });
            function copyText(text) {
                var done = function () { toast(S.copied); };
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(text).then(done, function () {});
                } else {
                    var t = document.createElement('textarea'); t.value = text; document.body.appendChild(t); t.select();
                    try { document.execCommand('copy'); done(); } catch (e) {} document.body.removeChild(t);
                }
            }
            $(document).on('click', '.bc-scorer-copy', function () { copyText($(this).closest('[data-scorer]').find('.bc-scorer-input').val()); });
            $(document).on('click', '.bc-scorer-copy-url', function () { copyText($(this).data('url')); });
            $(document).on('click', '.bc-scorer-qr-btn', function () {
                var box = $(this).closest('[data-scorer]').find('.bc-scorer-qr');
                if (!box.hasClass('on') && !box.children().length) {
                    var qr = qrcode(0, 'M'); qr.addData(box.data('url')); qr.make();
                    box.html(qr.createSvgTag({ cellSize: 4, margin: 0, scalable: true }));
                }
                box.toggleClass('on');
            });
            // Приглашение пользователя: поиск как при выборе капитана (debounce 250 мс, от 2 символов)
            $(document).on('input', '.bc-invite-search', function () {
                var inp = $(this), dd = inp.siblings('.bc-invite-dd'), q = $.trim(inp.val()), timer = inp.data('t');
                clearTimeout(timer);
                if (q.length < 2) { dd.hide().empty(); return; }
                inp.data('t', setTimeout(function () {
                    $.ajax({ url: '/ajax/users/search', data: { q: q, exclude_bots: 0 }, dataType: 'json', xhrFields: { withCredentials: true } })
                        .done(function (j) {
                            dd.empty();
                            var items = (j && j.items) || [];
                            if (!items.length) { dd.append($('<div class="bc-invite-item">').text(S.scorerNoUsers)); }
                            items.forEach(function (it) {
                                dd.append($('<div class="bc-invite-item">').text(it.label || it.name).data('id', it.id));
                            });
                            dd.show();
                        });
                }, 250));
            });
            $(document).on('click', '.bc-invite-item', function () {
                var uid = $(this).data('id'), name = $(this).text(), id = courtId(this);
                if (!uid) { return; }
                confirmThen(S.scorerInviteQ.replace(':name', name), function () {
                    call('POST', CFG.base + '/courts/' + id + '/scorer-invite', { user_id: uid }, function () { toast(S.scorerInvited); setTimeout(reload, 600); });
                });
            });
            $(document).on('click', function (e) { if (!$(e.target).closest('.bc-invite').length) { $('.bc-invite-dd').hide(); } });

            /* ---------- пульт ---------- */
            var cur = null, pollTimer = null, demo = '';
            var saved = parseInt(sessionStorage.getItem('bc_court_' + CFG.occurrenceId), 10);
            var first = courts.filter(function (c) { return c.is_active; })[0] || courts[0];
            if (first) { cur = byId[saved] || first; }

            function modeName(m) { return S.modes[m] || m; }
            function liveMode(c) { return c.mode === 'live' ? 'live' : c.mode; }

            function renderPult() {
                if (!cur) { return; }
                $('#bc-pult-chips [data-court-chip]').each(function () {
                    $(this).toggleClass('btn-secondary', $(this).data('court-chip') !== cur.id);
                });
                var frame = document.getElementById('bc-frame'), empty = document.getElementById('bc-frame-empty');
                var url = cur.link ? cur.link.url + (cur.link.url.indexOf('?') < 0 ? '?preview=1' : '&preview=1') + (demo ? '&demo=' + demo : '') : '';
                if (url) {
                    if (frame.getAttribute('data-src') !== url) { frame.setAttribute('data-src', url); frame.src = url; }
                    frame.style.display = ''; empty.style.display = 'none';
                } else {
                    frame.removeAttribute('data-src'); frame.removeAttribute('src'); frame.style.display = 'none'; empty.style.display = '';
                }
                var manual = cur.manual_mode;
                var ban = $('#bc-banner');
                ban.toggleClass('on', !!manual);
                $('#bc-banner-text').text(manual ? S.manualBanner.replace(':m', modeName(manual)) : '');
                $('#bc-modes [data-mode]').each(function () {
                    var m = $(this).data('mode');
                    $(this).toggleClass('on', manual ? m === manual : m === 'auto');
                });
                $('#bc-caption').text(manual ? '' : S.captionAuto.replace(':m', modeName(cur.mode)));
                var mt = cur.match ? ((cur.match.home || '?') + ' — ' + (cur.match.away || '?')) : '—';
                $('#bc-match-name').text(mt + (cur.manual_match_id ? '' : ' (' + S.matchAuto + ')'));
                renderMatchList();
            }
            function renderMatchList() {
                var box = $('#bc-matchlist').empty();
                var reset = $('<button type="button">').text('↺ ' + $('#bc-match-pick').data('reset')).toggleClass('sel', !cur.manual_match_id).data('id', 0);
                box.append(reset);
                if (!cur.matches.length) { box.append($('<div class="bc-hint">').text(S.matchNone)); }
                cur.matches.forEach(function (m) {
                    box.append($('<button type="button">').text(m.label + (m.stage ? ' · ' + m.stage : '')).toggleClass('sel', cur.manual_match_id === m.id).data('id', m.id));
                });
            }
            function applyControl(r) {
                cur.mode = r.mode; cur.manual_mode = r.manual_mode; cur.manual_match_id = r.manual_match_id ? parseInt(r.manual_match_id, 10) : null;
                cur.match = r.match;
                renderPult(); toast(S.saved);
            }
            function control(data) { call('POST', CFG.base + '/courts/' + cur.id + '/control', data, applyControl); }

            $('#bc-match-pick').data('reset', @json(__('stream.match_reset')));
            $('#bc-pult-chips').on('click', '[data-court-chip]', function () {
                cur = byId[$(this).data('court-chip')];
                sessionStorage.setItem('bc_court_' + CFG.occurrenceId, cur.id);
                renderPult(); poll();
            });
            function reloadFrame() { var f = document.getElementById('bc-frame'); if (demo && f.getAttribute('data-src')) { f.src = f.getAttribute('data-src'); } }
            $('#bc-demos').on('click', '[data-demo]', function () {
                demo = String($(this).data('demo') || '');
                $('#bc-demos [data-demo]').removeClass('on'); $(this).addClass('on');
                renderPult();
            });
            $('#bc-modes').on('click', '[data-mode]', function () { control({ mode: $(this).data('mode') }); });
            $('#bc-back-auto').on('click', function () { control({ mode: 'auto' }); });
            $('#bc-match-pick').on('click', function () { $('#bc-matchlist').toggleClass('on'); });
            $('#bc-matchlist').on('click', 'button', function () {
                var id = $(this).data('id');
                control({ match_id: id ? id : '' });
                $('#bc-matchlist').removeClass('on');
            });

            // Подпись «сейчас …» следует за автоматикой: публичный state той же ссылки
            function poll() {
                clearTimeout(pollTimer);
                if (!cur || !cur.link) { return; }
                var c = cur, stateUrl = c.link.url.replace(/\/?(\?.*)?$/, '') + '/state';
                $.ajax({ url: stateUrl, dataType: 'json', cache: false }).done(function (st) {
                    if (cur !== c) { return; }
                    if (c.manual_mode && st.manual === false) { c.manual_mode = null; }
                    if (!c.manual_mode) { c.mode = st.mode; c.match = st.match; }
                    renderPult();
                }).always(function () { pollTimer = setTimeout(poll, 5000); });
            }
            renderPult(); poll();

            /* ---------- оформление ---------- */
            function themeNow() {
                return { template: $('#bc-template').val(), corner: $('#bc-corner').val(),
                    show_set_scores: $('#bc-set-scores').is(':checked'), show_logos: $('#bc-show-logos').is(':checked'),
                    scale: parseInt($('#bc-scale').val(), 10), opacity: parseInt($('#bc-opacity').val(), 10), edge_margin: $('#bc-edge').val(), show_team_names: $('#bc-team-names').is(':checked') };
            }
            function pushTheme() {
                $('#bc-scale-v').text($('#bc-scale').val() + ' %'); $('#bc-opacity-v').text($('#bc-opacity').val() + ' %');
                var f = document.getElementById('bc-frame');
                if (f && f.contentWindow) { f.contentWindow.postMessage({ type: 'bc-theme', theme: themeNow() }, location.origin); }
            }
            $('#bc-theme-form').on('input change', 'input, select', pushTheme);
            $(document).on('change', '.bc-short', function () {
                var v = $.trim($(this).val());
                call('POST', CFG.base + '/teams/' + $(this).closest('[data-team]').data('team') + '/short-name', { short_name: v }, function () { toast(S.saved); reloadFrame(); });
            });
            $('#bc-theme-save').on('click', function () {
                call('POST', CFG.base + '/theme', {
                    occurrence_id: CFG.occurrenceId,
                    template: $('#bc-template').val(), corner: $('#bc-corner').val(),
                    show_set_scores: $('#bc-set-scores').is(':checked') ? 1 : 0,
                    show_logos: $('#bc-show-logos').is(':checked') ? 1 : 0,
                    scale: $('#bc-scale').val(), opacity: $('#bc-opacity').val(), edge_margin: $('#bc-edge').val(),
                    show_team_names: $('#bc-team-names').is(':checked') ? 1 : 0
                }, function () { toast(S.saved); });
            });

            function setColor(box, color) {
                call('POST', CFG.base + '/teams/' + box.data('team') + '/color', { color: color }, function (r) {
                    box.find('.bc-sw').removeClass('sel').filter(function () { return String($(this).data('color')).toUpperCase() === r.color.toUpperCase(); }).addClass('sel');
                    box.find('.bc-hex').val(r.color); toast(S.saved); reloadFrame();
                });
            }
            $(document).on('click', '.bc-team .bc-sw[data-color]', function () { setColor($(this).closest('[data-team]'), $(this).data('color')); });
            $(document).on('change', '.bc-hex', function () {
                var v = $.trim($(this).val()); if (v && v.charAt(0) !== '#') { v = '#' + v; }
                if (!/^#[0-9a-fA-F]{6}$/.test(v)) { toast(S.error, true); return; }
                setColor($(this).closest('[data-team]'), v);
            });
            $(document).on('change', '.bc-logo-file', function () {
                if (!this.files.length) { return; }
                var fd = new FormData(); fd.append('file', this.files[0]);
                call('POST', CFG.base + '/teams/' + $(this).closest('[data-team]').data('team') + '/logo', fd, function () { toast(S.saved); setTimeout(reload, 400); });
            });
            $(document).on('click', '.bc-logo-del', function () {
                call('DELETE', CFG.base + '/teams/' + $(this).closest('[data-team]').data('team') + '/logo', {}, function () { toast(S.saved); setTimeout(reload, 400); });
            });

            /* ---------- заставка ---------- */
            $('#bc-timer').on('change', function () { $('#bc-custom-wrap').toggle($(this).val() === 'custom'); });
            $('#bc-splash-save').on('click', function () {
                call('POST', CFG.base + '/splash', {
                    occurrence_id: CFG.occurrenceId, text: $('#bc-text').val(), timer: $('#bc-timer').val(),
                    custom_at: $('#bc-custom-at').val(), summary_mode: $('#bc-summary-mode').val(),
                    set_secs: $('#bc-set-secs').val(), match_secs: $('#bc-match-secs').val()
                }, function () { toast(S.saved); });
            });
            $('#bc-bg-file').on('change', function () {
                if (!this.files.length) { return; }
                var fd = new FormData(); fd.append('file', this.files[0]); fd.append('occurrence_id', CFG.occurrenceId);
                call('POST', CFG.base + '/bg', fd, function (r) {
                    if (r.url) { $('#bc-bg-img').attr('src', r.url + (r.url.indexOf('?') < 0 ? '?t=' : '&t=') + Date.now()).show(); $('#bc-bg-del').show(); }
                    toast(S.saved);
                });
                this.value = '';
            });
            $('#bc-bg-del').on('click', function () {
                call('DELETE', CFG.base + '/bg', { occurrence_id: CFG.occurrenceId }, function () { $('#bc-bg-img').hide(); $('#bc-bg-del').hide(); toast(S.saved); });
            });

            /* ---------- музыка ---------- */
            var mAudio = document.getElementById('bc-music-audio');
            var mPlaying = null;
            function mReset() { $('.bc-music-play').text('▶'); mPlaying = null; }
            $('#bc-music-volume').on('input', function () { $('#bc-music-volume-v').text(this.value); mAudio.volume = this.value / 100; });
            $(document).on('click', '.bc-music-play', function () {
                var btn = this;
                if (mPlaying === btn) { mAudio.pause(); mReset(); return; }
                mAudio.pause(); mReset();
                mAudio.src = $(btn).data('url'); mAudio.volume = $('#bc-music-volume').val() / 100;
                mAudio.play(); $(btn).text('■'); mPlaying = btn;
            });
            $(mAudio).on('ended', mReset);
            $('.bc-music-tr').on('change', function () { $('#bc-music-track').val(this.value); });
            $('#bc-music-save').on('click', function () {
                var modes = $('.bc-music-mode:checked').map(function () { return this.value; }).get();
                call('POST', CFG.base + '/music', {
                    occurrence_id: CFG.occurrenceId, enabled: $('#bc-music-enabled').is(':checked') ? 1 : 0,
                    track: $('#bc-music-track').val(), modes: modes, volume: $('#bc-music-volume').val()
                }, function () { toast(S.saved); });
            });
            $('#bc-music-file').on('change', function () {
                if (!this.files.length) { return; }
                var fd = new FormData(); fd.append('file', this.files[0]); fd.append('occurrence_id', CFG.occurrenceId);
                call('POST', CFG.base + '/music/file', fd, function (r) {
                    mAudio.pause(); mReset();
                    $('#bc-music-play-custom').data('url', r.url).show();
                    $('#bc-music-track').val('custom'); $('.bc-music-tr[value=custom]').prop('checked', true);
                    $('#bc-music-del').show(); toast(S.saved);
                });
                this.value = '';
            });
            $('#bc-music-del').on('click', function () {
                call('DELETE', CFG.base + '/music/file', { occurrence_id: CFG.occurrenceId }, function () {
                    mAudio.pause(); mReset(); $('#bc-music-play-custom').data('url', '').hide();
                    $('#bc-music-track').val('calm'); $('.bc-music-tr[value=calm]').prop('checked', true);
                    $('#bc-music-del').hide(); toast(S.saved);
                });
            });

            /* ---------- ссылки на эфир ---------- */
            var curStream = first ? (byId[saved] || first) : null;
            function fillStreams() {
                if (!curStream) { return; }
                $('#bc-stream-chips [data-stream-chip]').each(function () { $(this).toggleClass('btn-secondary', $(this).data('stream-chip') !== curStream.id); });
                $('#bc-streams-form [data-platform]').each(function () { $(this).val((curStream.streams || {})[$(this).data('platform')] || ''); });
            }
            $('#bc-stream-chips').on('click', '[data-stream-chip]', function () { curStream = byId[$(this).data('stream-chip')]; fillStreams(); });
            $('#bc-streams-save').on('click', function () {
                var urls = {};
                $('#bc-streams-form [data-platform]').each(function () { urls[$(this).data('platform')] = $.trim($(this).val()); });
                call('POST', CFG.base + '/courts/' + curStream.id + '/streams', { urls: urls }, function (r) { curStream.streams = r.urls; toast(S.saved); });
            });
            fillStreams();

            /* ---------- эфир из приложения (RTMP + ключ) ---------- */
            var YT_SERVER = 'rtmp://a.rtmp.youtube.com/live2';
            var curDest = first ? (byId[saved] || first) : null;
            function renderDests() {
                if (!curDest) { return; }
                $('#bc-dest-chips [data-dest-chip]').each(function () { $(this).toggleClass('btn-secondary', $(this).data('dest-chip') !== curDest.id); });
                var sel = $('#bc-d-channel').empty().append($('<option value="">').text(S.chOnce));
                var chosen = (curDest.destinations.filter(function (d) { return d.channel_id; })[0] || {}).channel_id || '';
                (CFG.channels || []).forEach(function (c) {
                    sel.append($('<option>').val(c.id).prop('selected', String(c.id) === String(chosen))
                        .text((S.destPlatforms[c.platform] || c.platform) + (c.label ? ' · ' + c.label : '') + (c.is_default ? ' ★' : '')));
                });
                $('#bc-dest-form').toggle(!chosen);
                var box = $('#bc-dest-list').empty();
                if (!curDest.destinations.length) {
                    box.append($('<p class="bc-hint">').text(CFG.chDefault && CFG.chDefault.id ? S.chDefaultUsed : S.destNone));
                    return;
                }
                curDest.destinations.forEach(function (d) {
                    var row = $('<div class="bc-row" data-dest>').attr('data-dest', d.id);
                    var info = $('<div>');
                    if (d.orphan) {
                        row.append($('<div>').append($('<b>').text(S.chOrphan)), $('<div class="bc-actions" style="margin-top:0">').append($('<button type="button" class="btn btn-small btn-outline bc-d-del">').text(S.destDelete)));
                        box.append(row); return;
                    }
                    info.append($('<b>').text((S.destPlatforms[d.platform] || d.platform) + (d.label ? ' · ' + d.label : '')));
                    if (d.is_default) { info.append($('<span class="bc-tag">').text(S.destDefault)); }
                    if (d.server_url) { info.append($('<div class="bc-hint">').text(d.server_url)); }
                    if (d.can_reveal) { info.append($('<div class="bc-hint bc-key">').text('••••••••')); }
                    var act = $('<div class="bc-actions" style="margin-top:0">');
                    if (d.can_reveal) { act.append($('<button type="button" class="btn btn-small btn-secondary bc-d-show">').text(S.destShow)); }
                    if (!d.is_default) { act.append($('<button type="button" class="btn btn-small btn-secondary bc-d-def">').text(S.destDefault)); }
                    act.append($('<button type="button" class="btn btn-small btn-outline bc-d-del">').text(S.destDelete));
                    box.append(row.append(info, act));
                });
            }
            function destUrl(id, tail) { return CFG.base + '/courts/' + curDest.id + '/destinations' + (id ? '/' + id : '') + (tail || ''); }
            $('#bc-dest-chips').on('click', '[data-dest-chip]', function () { curDest = byId[$(this).data('dest-chip')]; renderDests(); });
            $('#bc-d-channel').on('change', function () {
                if (!curDest) { return; }
                call('POST', CFG.base + '/courts/' + curDest.id + '/channel', { channel_id: $(this).val() || '' }, function () { toast(S.saved); setTimeout(reload, 400); });
            });
            $('#bc-d-platform').on('change', function () {
                var srv = $('#bc-d-server');
                if ($(this).val() === 'youtube') { if (!srv.val()) { srv.val(YT_SERVER); } }
                else if (srv.val() === YT_SERVER) { srv.val(''); }
            });
            $('#bc-dest-add').on('click', function () {
                if (!curDest) { return; }
                call('POST', destUrl(), {
                    platform: $('#bc-d-platform').val(), server_url: $.trim($('#bc-d-server').val()), stream_key: $.trim($('#bc-d-key').val()),
                    label: $.trim($('#bc-d-label').val()), is_default: $('#bc-d-default').is(':checked') ? 1 : 0
                }, function () { toast(S.saved); setTimeout(reload, 400); });
            });
            $('#bc-dest-list').on('click', '.bc-d-show', function () {
                var btn = $(this), row = btn.closest('[data-dest]'), keyEl = row.find('.bc-key');
                if (btn.data('shown')) { keyEl.text('••••••••'); btn.data('shown', 0).text(S.destShow); return; }
                call('POST', destUrl(row.data('dest'), '/reveal'), {}, function (r) {
                    keyEl.text(r.stream_key); btn.data('shown', 1).text(S.destHide);
                });
            });
            $('#bc-dest-list').on('click', '.bc-d-def', function () {
                call('POST', destUrl($(this).closest('[data-dest]').data('dest'), '/default'), {}, reload);
            });
            $('#bc-dest-list').on('click', '.bc-d-del', function () {
                var id = $(this).closest('[data-dest]').data('dest');
                confirmThen(S.destDeleteQ, function () { call('DELETE', destUrl(id), {}, reload); });
            });
            renderDests();
        })();
        </script>
    </x-slot>
</x-voll-layout>
