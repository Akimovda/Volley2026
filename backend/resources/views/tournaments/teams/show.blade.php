<x-voll-layout body_class="tournament-team-page">
@php
$isCaptain   = auth()->check() && (int)$team->captain_user_id === (int)auth()->id();
$isOrganizer = auth()->check() && ((int)$event->organizer_id === (int)auth()->id() || auth()->user()->isAdmin());
$canManage   = $isCaptain || $isOrganizer;
@endphp
<x-slot name="title">{{ $team->name }} — команда</x-slot>
<x-slot name="h1">{{ $team->name }}</x-slot>

<x-slot name="h2">{{ $team->team_kind === 'classic_team' ? 'Классическая команда' : 'Пляжная команда' }}</x-slot>

<x-slot name="breadcrumbs">
    <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
        <a href="{{ route('events.show', $event) }}" itemprop="item"><span itemprop="name">{{ $event->title }}</span></a>
        <meta itemprop="position" content="2">
    </li>
    <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
        <span itemprop="name">Команда</span>
        <meta itemprop="position" content="3">
    </li>
</x-slot>

    <x-slot name="d_description">

			<div class="d-flex flex-wrap gap-1 m-center">
				<div class="mt-2" data-aos-delay="250" data-aos="fade-up">
					 <a href="{{ route('events.show', $event) }}{{ $team->occurrence_id ? '?occurrence=' . $team->occurrence_id : '' }}" class="btn btn-secondary">← К турниру</a>
				</div>
				
				
    @if($canManage)
	<div class="mt-2" id="team-name-block" data-aos-delay="350" data-aos="fade-up">	
        <div id="team-name-display-wrap" class="d-flex fvc gap-1">
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('team-name-form').style.display='flex';document.getElementById('team-name-display-wrap').style.display='none';document.getElementById('team-name-input').focus()">
               Редактировать
            </button>
        </div>
        <form id="team-name-form" method="POST" action="{{ route('tournamentTeams.update', [$event, $team]) }}"
              class="form" style="display:none;align-items:center;flex-wrap:wrap;gap:.5rem;margin-top:.4rem">
            @csrf @method('PATCH')
			<div class="card">
            <input type="text" id="team-name-input" name="name" value="{{ $team->name }}"
                   style="max-width:24rem" maxlength="255" required>
            <button type="submit" class="btn btn-small">✓</button>
            <button type="button" class="btn btn-small btn-secondary"
                onclick="document.getElementById('team-name-form').style.display='none';document.getElementById('team-name-display-wrap').style.display=''">✕</button>
			</div>	
        </form>
    </div>
    @endif				
		</div>		
	</x-slot>


@php
$settings       = $team->event->tournamentSetting ?? null;
$confirmedCount = $team->members->where('confirmation_status','confirmed')->count();
$pendingCount   = ($team->invites ?? collect())->where('status','pending')->count();
$isCaptain      = (int)$team->captain_user_id === (int)auth()->id();
$isOrganizer    = auth()->check() && ((int)$event->organizer_id === (int)auth()->id() || (auth()->user()->isAdmin()));
$canManage      = $isCaptain || $isOrganizer;
$posLabels = ['setter'=>'Связующий','outside'=>'Доигровщик','opposite'=>'Диагональный','middle'=>'Центральный','libero'=>'Либеро'];
$roleLabels = ['captain'=>'Капитан','player'=>'Основной игрок','reserve'=>'Запасной'];
$stLabels = ['confirmed'=>'Подтверждён','joined'=>'Ожидает подтверждения','invited'=>'Приглашён','declined'=>'Отклонён','requested'=>'Запрос на вступление'];
$stColor  = ['confirmed'=>'#166534','joined'=>'#92400e','invited'=>'#1e40af','declined'=>'#9f1239','requested'=>'#5b21b6'];
$stBg     = ['confirmed'=>'#f0fdf4','joined'=>'#fff7e6','invited'=>'#dbeafe','declined'=>'#fff1f2','requested'=>'#f5f3ff'];
$invStLabels = ['pending'=>'Ожидает','accepted'=>'Принято','declined'=>'Отклонено','revoked'=>'Отозвано','expired'=>'Истекло'];
$invStColor  = ['accepted'=>'#166534','declined'=>'#9f1239','revoked'=>'#6b7280','expired'=>'#6b7280','pending'=>'#92400e'];
$invStBg     = ['accepted'=>'#f0fdf4','declined'=>'#fff1f2','revoked'=>'#f3f4f6','expired'=>'#f3f4f6','pending'=>'#fff7e6'];
$appStLabels = ['pending'=>'На рассмотрении','approved'=>'Принята','rejected'=>'Отклонена','incomplete'=>__('events.tapp_status_incomplete')];
$appStBg     = ['pending'=>'#fff7e6','approved'=>'#f0fdf4','rejected'=>'#fff1f2','incomplete'=>'#fef9c3'];
$appStColor  = ['pending'=>'#92400e','approved'=>'#166534','rejected'=>'#9f1239','incomplete'=>'#854d0e'];
$appStIcon   = ['pending'=>'⏳','approved'=>'✅','rejected'=>'❌','incomplete'=>'🟡'];
@endphp

<div class="container">

@if(session('success'))
<div class="alert alert-success">✅ {{ session('success') }}</div>
@endif
@if(session('error'))
<div class="alert alert-danger">❌ {{ session('error') }}</div>
@endif
@if($errors->any())
<div class="alert alert-danger">
    @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
</div>
@endif

@if($isCaptain && !\App\Models\UserTeam::where('user_id', auth()->id())->where('name', $team->name)->exists())
<div class="alert alert-info mb-2 d-flex between fvc" style="flex-wrap:wrap;gap:1rem">
    <div class="f-16">Сохраните эту команду в профиль, чтобы быстро использовать её на других турнирах.</div>
    <form method="POST" action="{{ route('tournamentTeams.saveToProfile', [$event, $team]) }}">
        @csrf
        <input type="hidden" name="team_name" value="{{ $team->name }}">
        <button class="btn btn-secondary btn-small">Сохранить в профиль</button>
    </form>
</div>
@endif

<div class="row row2">
<div class="col-lg-8">

    {{-- Состав --}}
    <div class="ramka">
        <h2 class="-mt-05">👥 Состав команды</h2>
        <div class="f-16 mb-2">
            Капитан: <strong>{{ $team->captain->name ?? ('#'.$team->captain_user_id) }}</strong>
            @if($team->occurrence)
                · {{ \Carbon\Carbon::parse($team->occurrence->starts_at)->format('d.m H:i') }}
            @endif
        </div>

        @php
            $activeMembers = $team->members->where('confirmation_status', '!=', 'requested');
            $joinRequests  = $team->members->where('confirmation_status', 'requested');
        @endphp
        @forelse($activeMembers as $member)
        <div class="card d-flex between fvc mb-1" style="flex-wrap:wrap;gap:1rem">
            <div class="d-flex fvc" style="gap:.8rem">
                <img src="{{ $member->user->profile_photo_url ?? '' }}" alt="" style="width:36px;height:36px;border-radius:50%;object-fit:cover;flex-shrink:0;">
                <div>
                    <div class="b-600 f-16">{{ $member->user->name ?? ('#'.$member->user_id) }}</div>
                    <div class="f-16">
                        {{ $roleLabels[$member->team_role] ?? $member->team_role }}
                        @if($team->team_kind==='classic_team' && $member->position_code)
                            · {{ $posLabels[$member->position_code] ?? $member->position_code }}
                        @endif
                    </div>
                    @php
                        $canEditPosition = $canManage
                            && $team->team_kind === 'classic_team'
                            && in_array($member->team_role, ['player','captain','reserve'], true)
                            && $member->confirmation_status === 'confirmed'
                            && !empty($positionCapacity);
                        $canToggleTeamRole = $canManage
                            && in_array($member->team_role, ['player','reserve'], true)
                            && $member->confirmation_status === 'confirmed';
                    @endphp
                    @if($canEditPosition || $canToggleTeamRole)
                    <div class="mt-05" style="display:flex;flex-wrap:wrap;gap:.6rem;align-items:center">
                        @if($canEditPosition)
                        <div style="width:15rem">
                            <form method="POST" action="{{ route('tournamentTeams.members.updatePosition',[$event,$team,$member]) }}" class="form">
                                @csrf @method('PATCH')
                                <select name="position_code" onchange="this.form.submit()">
                                    @if(!$member->position_code)
                                    <option value="" disabled selected>— амплуа —</option>
                                    @endif
                                    @foreach($positionCapacity as $code => $info)
                                        @php $full = $info['current'] >= $info['max'] && $member->position_code !== $code; @endphp
                                        <option value="{{ $code }}" @selected($member->position_code === $code) @disabled($full)>
                                            {{ $info['label'] }} ({{ $info['current'] }}/{{ $info['max'] }}){{ $full ? ' — занято' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </form>
                        </div>
                        @endif
                        @if($canToggleTeamRole)
                        <form method="POST" action="{{ route('tournamentTeams.members.updateTeamRole',[$event,$team,$member]) }}">
                            @csrf @method('PATCH')
                            <input type="hidden" name="team_role" value="{{ $member->team_role === 'reserve' ? 'player' : 'reserve' }}">
                            <button type="submit" class="btn btn-small btn-secondary">
                                {{ $member->team_role === 'reserve' ? '↑ В основу' : '↓ В запасные' }}
                            </button>
                        </form>
                        @endif
                    </div>
                    @endif
                </div>
            </div>
            <div class="d-flex gap-1 fvc">
                <span style="display:inline-block;padding:2px 10px;border-radius:10px;font-size:12px;font-weight:600;background:{{ $stBg[$member->confirmation_status] ?? '#f3f4f6' }};color:{{ $stColor[$member->confirmation_status] ?? '#6b7280' }}">
                    {{ $stLabels[$member->confirmation_status] ?? $member->confirmation_status }}
                </span>
                @if($isCaptain && (int)$member->user_id !== (int)$team->captain_user_id)
                    @if($member->confirmation_status !== 'confirmed')
                    <form method="POST" action="{{ route('tournamentTeams.members.confirm',[$event,$team,$member]) }}">
                        @csrf
                        <button class="btn btn-small">✅</button>
                    </form>
                    @endif
                    @if($member->confirmation_status !== 'declined')
                    <form method="POST" action="{{ route('tournamentTeams.members.decline',[$event,$team,$member]) }}">
                        @csrf
                        <button class="btn btn-small btn-secondary">✗</button>
                    </form>
                    @endif
                @endif
                @if($isCaptain && (int)$member->user_id !== (int)$team->captain_user_id && $member->confirmation_status === 'confirmed')
                    <form method="POST" action="{{ route('tournamentTeams.transferCaptain',[$event,$team]) }}"
                          onsubmit="return confirm('Передать капитанство игроку {{ addslashes($member->user->name ?? '') }}?')">
                        @csrf
                        <input type="hidden" name="new_captain_user_id" value="{{ $member->user_id }}">
                        <button type="submit" class="btn btn-small btn-secondary" title="Передать капитанство">👑</button>
                    </form>
                @endif
                @if($canManage && $leagueForSubs && !$tourStarted && $member->confirmation_status === 'confirmed' && (int)$member->user_id !== (int)$team->captain_user_id)
                    @php $existingSub = $existingSubstitutions[$member->user_id] ?? null; @endphp
                    @if($existingSub)
                        @if($existingSub->isConfirmed())
                            <span style="font-size:12px;padding:2px 8px;border-radius:10px;background:#f0fdf4;color:#166534;font-weight:600">
                                🔄 {{ $existingSub->substitutePlayer->last_name }} {{ $existingSub->substitutePlayer->first_name }}
                            </span>
                        @else
                            <span style="font-size:12px;padding:2px 8px;border-radius:10px;background:#fff7e6;color:#92400e;font-weight:600">
                                ⏳ {{ $existingSub->substitutePlayer->last_name }} {{ $existingSub->substitutePlayer->first_name }}
                            </span>
                            <form method="POST" action="{{ route('substitutions.cancel', $existingSub) }}" style="display:inline">
                                @csrf
                                <button class="btn btn-small btn-secondary btn-alert"
                                    data-title="Отменить замену?"
                                    data-icon="warning"
                                    data-confirm-text="Да"
                                    data-cancel-text="Отмена"
                                    title="Отменить замену">✕</button>
                            </form>
                        @endif
                    @else
                        <button type="button" class="btn btn-small btn-secondary"
                            title="Найти замену на этот тур"
                            data-member-id="{{ $member->user_id }}"
                            data-member-name="{{ $member->user->last_name }} {{ $member->user->first_name }}"
                            onclick="openSubModal(this)">🔄</button>
                    @endif
                @endif
                @if(($isCaptain || $isOrganizer) && (int)$member->user_id !== (int)$team->captain_user_id)
                    <form method="POST" action="{{ route('tournamentTeams.members.destroy',[$event,$team,$member]) }}">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-small btn-secondary btn-alert"
                                data-title="Удалить из команды?"
                                data-icon="warning"
                                data-confirm-text="Да"
                                data-cancel-text="Отмена">🗑</button>
                    </form>
                @endif
            </div>
        </div>
        @empty
        <div class="card text-center">Состав пока пуст</div>
        @endforelse

        {{-- Вакантный слот (beach_pair) --}}
        @if($team->team_kind === 'beach_pair' && $activeMembers->where('confirmation_status','confirmed')->count() < 2)
        <div class="card d-flex fvc mb-1" style="gap:.8rem;border:2px dashed var(--border-color,#ddd)">
            <div style="width:36px;height:36px;border-radius:50%;background:var(--bg2,#f5f5f5);flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:1.8rem;color:#aaa;">?</div>
            <span class="f-16" style="font-style:italic">Место партнёра свободно</span>
        </div>
        @endif
    </div>

    {{-- Запросы на вступление --}}
    @if($canManage && $joinRequests->isNotEmpty())
    <div class="ramka">
        <h2 class="-mt-05">🙋 Запросы на вступление ({{ $joinRequests->count() }})</h2>
        @foreach($joinRequests as $member)
        <div class="card d-flex between fvc mb-1" style="flex-wrap:wrap;gap:1rem">
            <div class="d-flex fvc" style="gap:.8rem">
                <img src="{{ $member->user->profile_photo_url ?? '' }}" alt="" style="width:40px;height:40px;border-radius:50%;object-fit:cover;flex-shrink:0;">
                <div>
                    <div class="b-600 f-16">{{ $member->user->name ?? ('#'.$member->user_id) }}</div>
                    <div class="f-16">{{ $member->joined_at?->format('d.m.Y H:i') }}</div>
                </div>
            </div>
            <div class="d-flex gap-1 fvc">
                <form method="POST" action="{{ route('tournamentTeams.members.confirm',[$event,$team,$member]) }}">
                    @csrf
                    <button class="btn btn-small">✅ Принять</button>
                </form>
                <form method="POST" action="{{ route('tournamentTeams.members.decline',[$event,$team,$member]) }}">
                    @csrf
                    <button class="btn btn-small btn-secondary">✗ Отклонить</button>
                </form>
            </div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Созданные приглашения --}}
    @if($canManage)
    <div class="ramka" style="z-index:8">
        <h2 class="-mt-05">📨 Созданные приглашения</h2>
        @forelse(($team->invites ?? collect())->sortByDesc('id') as $inv)
        @php
            $invMeta = is_array($inv->meta) ? $inv->meta : [];
            $invUrl  = $invMeta['invite_url'] ?? route('tournamentTeamInvites.show',['token'=>$inv->token]);
            $channels = collect($invMeta['sent_channels'] ?? [])->filter()->values();
        @endphp
        <div class="card mb-1">
            <div class="d-flex between fvc mb-05" style="flex-wrap:wrap;gap:.5rem">
                <div>
                    <span class="b-600">{{ $inv->invitedUser->name ?? $inv->invitedUser->email ?? ('#'.$inv->invited_user_id) }}</span>
                    <span class="f-16 ml-1">
                        {{ $roleLabels[$inv->team_role] ?? $inv->team_role }}
                        @if($inv->position_code) · {{ $posLabels[$inv->position_code] ?? $inv->position_code }} @endif
                    </span>
                </div>
                <span style="display:inline-block;padding:2px 10px;border-radius:10px;font-size:12px;font-weight:600;background:{{ $invStBg[$inv->status] ?? '#fff7e6' }};color:{{ $invStColor[$inv->status] ?? '#92400e' }}">
                    {{ $invStLabels[$inv->status] ?? $inv->status }}
                </span>
            </div>
            <div class="f-16 mb-1">
                {{ $channels->isNotEmpty() ? 'Отправлено: '.$channels->join(', ') : 'Ссылка создана' }}
                · {{ $inv->created_at?->format('d.m.Y H:i') }}
            </div>
            <div class="d-flex gap-1" style="flex-wrap:wrap">
                <a href="{{ $invUrl }}" target="_blank" class="btn btn-small btn-secondary">🔗 Открыть ссылку</a>
                @if($inv->status === 'pending')
                <form method="POST" action="{{ route('tournamentTeamInvites.revoke', [$event, $team, $inv]) }}"
                      onsubmit="return confirm(@json(__('events.tinv_revoke_confirm')))">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-small btn-danger">{{ __('events.tinv_revoke_btn') }}</button>
                </form>
                @endif
            </div>
        </div>
        @empty
        <div class="f-16">Приглашений пока нет</div>
        @endforelse
    </div>

    {{-- Создать приглашение --}}
    <div class="ramka" style="z-index:7">
        <h2 class="-mt-05">➕ Создать ссылку-приглашение</h2>
            <p>Игрок получит персональную ссылку с ролью и позицией. </p>
        <form method="POST" action="{{ route('tournamentTeamInvites.store',[$event,$team]) }}" class="form">
            @csrf
            <div class="row row2">
                <div class="col-md-5">
                    <label>Игрок</label>
                    <div style="position:relative" id="ti-wrap">
                        <input type="text" id="ti-input" autocomplete="off" placeholder="Имя или email…">
                        <input type="hidden" name="invited_user_id" id="ti-userid">
                        <div id="ti-dd" style="display:none;position:absolute;left:0;right:0;top:100%;margin-top:.4rem;z-index:50;background:var(--bg-card,#fff);border:.1rem solid var(--border-color,#eee);border-radius:1.2rem;box-shadow:0 1rem 3rem rgba(0,0,0,.1);max-height:22rem;overflow-y:auto"></div>
                    </div>
                    <div id="ti-selected" class="f-16 mt-05" style="color:#4caf50;display:none"></div>
                </div>
                <div class="col-md-3">
                    <label>Роль</label>
                    <select name="team_role" id="ti-role">
                        <option value="player">Основной</option>
                        <option value="reserve">Запасной</option>
                    </select>
                </div>
                @if($team->team_kind==='classic_team')
                <div class="col-md-4" id="ti-pos-wrap">
                    <label>Позиция</label>
                    <select name="position_code" id="ti-position">
                        <option value="">— без —</option>
                        @foreach($positionOptions ?? [] as $code => $label)
                        <option value="{{ $code }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
            </div>
            <button type="submit" class="btn mt-1">Создать ссылку</button>
        </form>
    </div>
    @endif

    {{-- Добавить игрока напрямую (только для организатора/админа) --}}
    @if($isOrganizer)
    <div class="ramka" style="z-index:6">
        <h2 class="-mt-05">➕ {{ __('events.org_add_player_h2') }}</h2>
        <div class="f-18 mb-2">{{ __('events.org_add_player_hint') }}</div>
        @error('add_member')<div class="alert alert-error mb-2">{{ $message }}</div>@enderror
        <form method="POST" action="{{ route('tournamentTeams.addMemberByOrganizer',[$event,$team]) }}" class="form" id="org-add-form">
            @csrf
            <div class="row row2">
                <div class="col-md-5">
                    <label>{{ __('events.show_pl_group_search_ph') }}</label>
                    <div style="position:relative" id="org-add-wrap">
                        <input type="text" id="org-add-input" autocomplete="off" class="form-control"
                            placeholder="{{ __('events.org_add_player_ph') }}">
                        <input type="hidden" name="user_id" id="org-add-user-id" value="">
                        <div id="org-add-dd" class="form-select-dropdown trainer_dd"></div>
                    </div>
                    <div id="org-add-selected" class="f-16 mt-05" style="color:#4caf50;display:none"></div>
                </div>
                <div class="col-md-3">
                    <label>Роль</label>
                    <select name="team_role">
                        <option value="player">Основной</option>
                        <option value="reserve">Запасной</option>
                    </select>
                </div>
                @if($team->team_kind === 'classic_team')
                <div class="col-md-4">
                    <label>Позиция</label>
                    <select name="position_code">
                        <option value="">— без —</option>
                        @foreach($positionOptions ?? [] as $code => $label)
                        <option value="{{ $code }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
            </div>
            <button type="submit" id="org-add-btn" class="btn mt-1" disabled>{{ __('events.org_add_player_btn') }}</button>
        </form>
    </div>
    <script>
    (function(){
        var input = document.getElementById('org-add-input');
        var dd    = document.getElementById('org-add-dd');
        var hidden = document.getElementById('org-add-user-id');
        var sel   = document.getElementById('org-add-selected');
        var btn   = document.getElementById('org-add-btn');
        var timer = null;
        if (!input) return;
        function esc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
        function showDd(){ dd.classList.add('form-select-dropdown--active'); }
        function hideDd(){ dd.classList.remove('form-select-dropdown--active'); }
        function pick(id, label){
            hidden.value = String(id);
            input.value  = label;
            sel.textContent = '✅ ' + label;
            sel.style.display = 'block';
            btn.disabled = false;
            hideDd();
        }
        function reset(){ hidden.value=''; btn.disabled=true; sel.style.display='none'; }
        input.addEventListener('input', function(){
            reset();
            clearTimeout(timer);
            var q = input.value.trim();
            if (q.length < 2){ hideDd(); return; }
            dd.innerHTML = '<div class="city-message">{{ __("events.show_pl_js_searching") }}</div>';
            showDd();
            timer = setTimeout(function(){
                fetch('/api/users/search?q=' + encodeURIComponent(q), {
                    headers:{'Accept':'application/json'}, credentials:'same-origin'
                }).then(function(r){ return r.json(); }).then(function(data){
                    var items = data.items || [];
                    dd.innerHTML = '';
                    if (!items.length){ dd.innerHTML='<div class="city-message">{{ __("events.show_pl_js_not_found") }}</div>'; showDd(); return; }
                    items.forEach(function(u){
                        var div = document.createElement('div');
                        div.className = 'trainer-item form-select-option';
                        var botBadge = u.is_bot ? '<span style="display:inline-block;padding:1px 8px;border-radius:10px;font-size:11px;font-weight:600;background:#fef3c7;color:#92400e;margin-left:.5rem">🤖 бот</span>' : '';
                        div.innerHTML = '<div class="text-sm text-gray-900">'+esc(u.label||u.name)+botBadge+'</div>';
                        div.addEventListener('click', function(){ pick(u.id, u.label||u.name); });
                        dd.appendChild(div);
                    });
                    showDd();
                }).catch(function(){ dd.innerHTML='<div class="city-message">{{ __("events.show_pl_js_error") }}</div>'; showDd(); });
            }, 250);
        });
        document.addEventListener('click', function(e){
            if (!input.contains(e.target) && !dd.contains(e.target)) hideDd();
        });
        input.addEventListener('keydown', function(e){ if (e.key==='Escape') hideDd(); });
    })();
    </script>
    @endif

    {{-- Заявка --}}
    <div class="ramka">
        <h2 class="-mt-05">📋 Подача заявки</h2>
        @if($team->application)
            @php $appSt = $team->application->status ?? 'pending'; @endphp
            <div class="d-flex fvc" style="gap:.6rem;margin-bottom:.75rem;padding:.6rem .9rem;border-radius:8px;background:{{ $appStBg[$appSt] ?? '#f8f9fa' }}">
                <span style="font-size:1.1rem">{{ $appStIcon[$appSt] ?? '📋' }}</span>
                <div>
                    <span class="f-16">Статус заявки</span>
                    <div class="b-600 f-16" style="color:{{ $appStColor[$appSt] ?? '#333' }}">{{ $appStLabels[$appSt] ?? $appSt }}</div>
                </div>
            </div>

            @if($canManage && in_array($appSt, ['incomplete','pending'], true))
                <form method="POST" action="{{ route('tournamentTeams.revokeApplication',[$event,$team]) }}"
                      onsubmit="return confirm(@json(__('events.tapp_revoke_confirm')))" class="mb-2">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-small btn-danger">{{ __('events.tapp_revoke_btn') }}</button>
                </form>
            @endif
            @if($canManage && $appSt === 'approved' && !$tourStarted && ($leagueTeamSelf?->status === 'active' || (!$leagueTeamSelf && ($team->reserve_position === null) && $team->status !== 'reserve')))
                <form method="POST" action="{{ route('tournamentTeams.withdraw', [$event, $team]) }}" class="mt-2">
                    @csrf
                    <button type="submit" class="btn btn-secondary btn-alert"
                            style="border:2px solid #dc2626;color:#dc2626"
                            data-title="{{ $leagueTeamSelf ? 'Отказаться от участия в этом туре?' : 'Снять команду с турнира?' }}"
                            data-icon="warning"
                            data-confirm-text="Да, перейти в резерв"
                            data-cancel-text="Отмена">
                        ↩ Отказаться от тура / перейти в резерв
                    </button>
                </form>
            @endif

            {{-- Блок оплаты (после подачи заявки) --}}
            @php
                $payService = app(\App\Services\TournamentPaymentService::class);
                $payInfo = $payService->getPaymentInfo($team);
            @endphp

            @if($payInfo['required'])
                <div class="card mt-2" id="payment-block">
                    <h4 class="f-16 fw-bold mb-2">💳 Оплата участия</h4>

                    @if($payInfo['mode'] === 'team')
                        {{-- Режим: капитан за всю команду --}}
                        <div class="f-18 mb-2">
                            Стоимость: <strong>{{ number_format($payInfo['amount'] / 100, 0, ',', ' ') }} {{ $payInfo['currency'] }}</strong>
                            · Оплачивает капитан за всю команду
                        </div>

                        @if($payInfo['team_status'] === 'paid' || $payInfo['team_status'] === 'subscription')
                            <div class="alert alert-success f-18">✅ Оплата подтверждена</div>
                        @elseif($payInfo['team_status'] === 'link_pending')
                            <div class="alert alert-warning f-18">⏳ Ожидает подтверждения организатором</div>
                        @elseif($canManage)
                            @if($payInfo['method'] === 'cash')
                                <div class="alert alert-info f-18">💵 Оплата наличными</div>
                            @else
                                @if($team->payment_id)
                                    <form method="POST" action="{{ route('payments.user_confirm', $team->payment_id) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-warning"
                                                onclick="return confirm('Подтверждаете что перевод выполнен?')">
                                            Я оплатил
                                        </button>
                                    </form>
                                    @if($event->payment_link)
                                        <a href="{{ $event->payment_link }}" target="_blank" class="btn btn-outline-primary mt-1">
                                            Перейти к оплате →
                                        </a>
                                    @endif
                                @endif
                            @endif
                        @else
                            <div class="f-18">Ожидаем оплату от капитана</div>
                        @endif

                    @elseif($payInfo['mode'] === 'per_player')
                        {{-- Режим: каждый сам --}}
                        <div class="f-18 mb-2">
                            Стоимость: <strong>{{ number_format($payInfo['amount'] / 100, 0, ',', ' ') }} {{ $payInfo['currency'] }}</strong> с каждого игрока
                        </div>

                        @if($payInfo['team_paid'])
                            <div class="alert alert-success f-18">✅ Все участники оплатили</div>
                        @endif

                        @if(!empty($payInfo['members']))
                            <table class="table table-sm f-18 mt-1">
                                <tbody>
                                @foreach($payInfo['members'] as $pm)
                                    <tr>
                                        <td>{{ $pm['name'] ?: 'Игрок #'.$pm['user_id'] }}</td>
                                        <td class="text-end">
                                            @if($pm['paid'])
                                                <span class="badge bg-success">Оплачено</span>
                                            @elseif($pm['payment_status'] === 'link_pending')
                                                <span class="badge bg-warning">Ожидает</span>
                                            @elseif($pm['user_id'] === auth()->id())
                                                @php
                                                    $memberModel = \App\Models\EventTeamMember::find($pm['id']);
                                                @endphp
                                                @if($memberModel && $memberModel->payment_id)
                                                    <form method="POST" action="{{ route('payments.user_confirm', $memberModel->payment_id) }}" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="btn btn-warning btn-sm py-0"
                                                                onclick="return confirm('Подтверждаете что перевод выполнен?')">
                                                            Я оплатил
                                                        </button>
                                                    </form>
                                                @else
                                                    <span class="badge bg-danger">Не оплачено</span>
                                                @endif
                                            @else
                                                <span class="badge bg-danger">Не оплачено</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        @endif
                    @endif
                </div>
            @endif

        @elseif(in_array($team->status, ['approved', 'submitted'], true))
            {{-- Команда принята/подана автоматически (autoApprove или подтверждение резерва) без явной заявки --}}
            @php $autoSt = $team->status === 'approved' ? 'approved' : 'pending'; @endphp
            <div class="d-flex fvc" style="gap:.6rem;margin-bottom:.75rem;padding:.6rem .9rem;border-radius:8px;background:{{ $appStBg[$autoSt] }}">
                <span style="font-size:1.1rem">{{ $appStIcon[$autoSt] }}</span>
                <div>
                    <span class="f-16">Статус заявки</span>
                    <div class="b-600 f-16" style="color:{{ $appStColor[$autoSt] }}">{{ $appStLabels[$autoSt] }}</div>
                </div>
            </div>
            @if($canManage && $team->status === 'approved' && !$tourStarted && ($leagueTeamSelf?->status === 'active' || (!$leagueTeamSelf && ($team->reserve_position === null))))
                <form method="POST" action="{{ route('tournamentTeams.withdraw', [$event, $team]) }}" class="mt-2">
                    @csrf
                    <button type="submit" class="btn btn-secondary btn-alert"
                            style="border:2px solid #dc2626;color:#dc2626"
                            data-title="{{ $leagueTeamSelf ? 'Отказаться от участия в этом туре?' : 'Снять команду с турнира?' }}"
                            data-icon="warning"
                            data-confirm-text="Да, перейти в резерв"
                            data-cancel-text="Отмена">
                        ↩ Отказаться от тура / перейти в резерв
                    </button>
                </form>
            @endif

        @elseif($canManage)
            <div class="f-16 mb-2">Если состав готов — подайте заявку на турнир.</div>

            @if($team->is_complete && $team->status === 'ready')
                <form method="POST" action="{{ route('tournamentTeams.submit',[$event,$team]) }}"
                      onsubmit="return confirm('Подать заявку команды на турнир?')">
                    @csrf
                    <button type="submit" class="btn">{{ __('events.tapp_submit_btn') }}</button>
                </form>
            @else
                @php
                    $hasCaptain = (bool) $team->captain_user_id;
                    $allowIncomplete = (bool) ($settings?->allow_incomplete_application ?? false);
                    $canEarlySubmit = $allowIncomplete && $hasCaptain;
                @endphp
                <div class="alert alert-warning f-18 mb-2">
                    ⚠️ Состав ещё не готов — обычная подача недоступна.
                </div>
                @if($canEarlySubmit)
                    <div class="f-16 mb-1">{{ __('events.tapp_submit_early_warn') }}</div>
                    <form method="POST" action="{{ route('tournamentTeams.submit',[$event,$team]) }}"
                          onsubmit="return confirm(@json(__('events.tapp_submit_early_btn') . '?'))">
                        @csrf
                        <input type="hidden" name="allow_incomplete" value="1">
                        <button type="submit" class="btn btn-secondary">{{ __('events.tapp_submit_early_btn') }}</button>
                    </form>
                @endif
            @endif
        @else
            <div class="f-16">Заявка ещё не подана</div>
        @endif
    </div>

</div>
<div class="col-lg-4">
<div class="sticky">



    {{-- Резерв: статус и кнопка подтверждения --}}
    @if($team->isInReserve())
    <div class="ramka">
        @if($team->isReserveOfferPending())
        <h2 class="-mt-05" style="color:#16a34a">🎉 Место предложено!</h2>
        <div class="f-16 mb-1">Для вашей команды освободилось место в основном составе.</div>
        <div class="f-18 mb-2" style="color:#dc2626">
            ⏰ Подтвердите до <strong>{{ $team->confirmation_expires_at->format('d.m.Y H:i') }}</strong>
        </div>
        @if($canManage)
        <form method="POST" action="{{ route('tournamentTeams.reserveConfirm', [$event, $team]) }}" class="mb-1">
            @csrf
            <input type="hidden" name="token" value="{{ $team->confirmation_token }}">
            <button type="submit" class="btn w-100 btn-alert"
                    data-title="Подтвердить участие?"
                    data-icon="success"
                    data-confirm-text="Да, подтверждаю"
                    data-cancel-text="Отмена">
                ✅ Подтвердить участие
            </button>
        </form>
        <form method="POST" action="{{ route('tournamentTeams.reserveDecline', [$event, $team]) }}">
            @csrf
            <button type="submit" class="btn btn-secondary w-100 btn-alert"
                    data-title="Отказаться от места?"
                    data-icon="warning"
                    data-confirm-text="Да, отказаться"
                    data-cancel-text="Отмена">
                ✗ Отказаться
            </button>
        </form>
        @endif
        @else
        <h2 class="-mt-05">⏳ Лист ожидания</h2>
        <div class="alert alert-warning">Позиция в очереди: <strong>#{{ $team->reserve_position }}</strong></div>
        <div class="f-18">Когда освободится место, вы получите уведомление и будете иметь 2 часа для подтверждения.</div>
        @endif
    </div>
    @endif

    {{-- Статус --}}
    <div class="ramka">
        <h2 class="-mt-05">📊 Состав</h2>
        <div class="card">
            <div class="f-16 mb-05">Подтверждено: <strong>{{ $confirmedCount }}</strong></div>
            <div class="f-16 mb-05">Ожидают: <strong>{{ $pendingCount }}</strong></div>
            <div class="f-16 mb-1">Всего: <strong>{{ $team->members->count() }}</strong></div>
            @if($settings)
            @php
            $schemeLabels = [
                '4x4'        => '4×4',
                '4x2'        => '4×2',
                '5x1'        => '5×1 ' . __('events.libero_without'),
                '5x1_libero' => '5×1 ' . __('events.libero_word'),
                '2x2'        => '2×2',
                '3x3'        => '3×3',
            ];
            @endphp
            <div class="f-16">
                Схема: <strong>{{ $schemeLabels[$settings->game_scheme ?? ''] ?? ($settings->game_scheme ?? '—') }}</strong>
                @if($team->team_kind === 'classic_team')
                <br>Запасных: <strong>{{ $settings->reserve_players_max ?? '—' }}</strong>
                @endif
                @if($settings->max_rating_sum)<br>Лимит рейтинга: <strong>{{ $settings->max_rating_sum }}</strong>@endif
            </div>
            @endif
        </div>
        @if($team->team_kind === 'classic_team' && !empty($positionBreakdown))
        <div class="card mt-1">
            <div class="f-16 mb-05 b-600">Разбивка по позициям (запасной с назначенным амплуа тоже занимает слот):</div>
            @php $playersOk = ($requirementsCheck['players_count'] ?? 0) >= (int)($requirementsCheck['limits']['min_players'] ?? 0); @endphp
            <div class="f-15 mb-05" style="color:{{ $playersOk ? '#16a34a' : '#dc2626' }}">
                {{ $playersOk ? '✅' : '❌' }} Основных игроков: <strong>{{ $requirementsCheck['players_count'] ?? 0 }}</strong> из <strong>{{ $requirementsCheck['limits']['min_players'] ?? ($settings->team_size_min ?? '—') }}</strong>
            </div>
            @foreach($positionBreakdown as $row)
            <div class="f-15" style="color:{{ $row['ok'] ? '#16a34a' : '#dc2626' }}">
                {{ $row['ok'] ? '✅' : '❌' }} {{ $row['label'] }}: <strong>{{ $row['current'] }}</strong> из <strong>{{ $row['required'] }}</strong>
            </div>
            @endforeach
        </div>
        @endif
        <div class="mt-1">
            @if($team->is_complete)
            <div class="alert alert-success">✅ Состав готов</div>
            @else
            <div class="alert alert-warning">
                ⚠️ Состав не готов
                @if(!empty($requirementsCheck['issues']))
                <ul class="mb-0" style="padding-left:1.2rem">
                    @foreach($requirementsCheck['issues'] as $issue)
                    <li>{{ $issue }}</li>
                    @endforeach
                </ul>
                @endif
            </div>
            @endif

            {{-- Кнопки выхода --}}
            @auth
                @php
                    $isMember = $team->members->contains('user_id', auth()->id());
                    $isCaptainSelf = (int)$team->captain_user_id === (int)auth()->id();
                @endphp

                @if($isMember && !$isCaptainSelf)
                    <button type="button" id="leave-team-btn" class="btn btn-secondary w-100 mt-1"
                            data-has-occurrence="{{ $team->occurrence_id ? '1' : '0' }}"
                            style="color:#dc2626">
                        🚪 Покинуть команду
                    </button>

                    <form id="leave-team-form" method="POST" action="{{ route('tournamentTeams.leave', [$event, $team]) }}" style="display:none">
                        @csrf
                        <input type="hidden" name="add_to_waitlist" id="leave-team-waitlist" value="0">
                    </form>

                    <script>
                    (function() {
                        var btn = document.getElementById('leave-team-btn');
                        if (!btn) return;
                        btn.addEventListener('click', function() {
                            var hasOccurrence = btn.dataset.hasOccurrence === '1';
                            var buttons = { cancel: { text: 'Отмена', value: null, visible: true, className: '' } };
                            if (hasOccurrence) {
                                buttons.waitlist = { text: '⏳ Покинуть и встать в лист ожидания', value: 'waitlist', visible: true, className: '' };
                            }
                            buttons.leave = { text: '🚪 Покинуть без добавления', value: 'leave', visible: true, className: 'swal-button--danger' };

                            swal({
                                title: 'Покинуть команду?',
                                text: 'Вы будете удалены из состава.' + (hasOccurrence ? ' Хотите встать в лист ожидания на это мероприятие?' : ''),
                                icon: 'warning',
                                buttons: buttons,
                                dangerMode: true
                            }).then(function(value) {
                                if (!value) return;
                                document.getElementById('leave-team-waitlist').value = (value === 'waitlist') ? '1' : '0';
                                document.getElementById('leave-team-form').submit();
                            });
                        });
                    })();
                    </script>
                @endif

                @if($isCaptainSelf || $isOrganizer)
                    <form method="POST" action="{{ route('tournamentTeams.disband', [$event, $team]) }}" class="mt-1">
                        @csrf
                        <button type="submit" class="btn btn-secondary btn-alert w-100"
                                data-title="Расформировать команду {{ $team->name }}?"
                                data-text="Команда будет удалена. Все участники получат уведомление. Оплата будет возвращена на внутренний счёт."
                                data-icon="warning"
                                data-confirm-text="Да, расформировать"
                                data-cancel-text="Отмена"
                                style="color:#dc2626">
                            💥 Расформировать команду
                        </button>
                    </form>
                @endif
            @endauth

        </div>
    </div>
</div>
</div>
</div>
</div>

@if($leagueForSubs && $team->occurrence_id && !$tourStarted)
<div id="subModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center">
    <div class="card p-3" style="max-width:480px;width:95%;max-height:90vh;overflow-y:auto;position:relative">
        <button onclick="closeSubModal()" style="position:absolute;top:10px;right:12px;background:none;border:none;font-size:18px;cursor:pointer">✕</button>
        <h3 class="-mt-05 mb-1" id="subModalTitle">🔄 Замена на тур</h3>
        <div class="f-13 mb-2" style="opacity:.6" id="subReplacingLabel"></div>

        {{-- Вкладки --}}
        <div class="d-flex gap-1 mb-2">
            <button class="btn sub-tab-btn" data-tab="reserve" onclick="switchSubTab('reserve')">Из резерва</button>
            <button class="btn btn-secondary sub-tab-btn" data-tab="external" onclick="switchSubTab('external')">Поиск игрока</button>
        </div>

        {{-- Из резерва --}}
        <div id="subTabReserve">
            @if($reserveForSubs->isNotEmpty())
            @foreach($reserveForSubs as $rlt)
            @if($rlt->user)
            <div class="d-flex" style="padding:6px 0;border-bottom:1px solid rgba(128,128,128,.08);align-items:center;gap:8px">
                <span style="flex:1">{{ $rlt->user->last_name }} {{ $rlt->user->first_name }}</span>
                <button type="button" class="btn btn-small"
                    onclick="selectSubstitute({{ $rlt->user_id }}, '{{ addslashes($rlt->user->last_name.' '.$rlt->user->first_name) }}', 'reserve')">
                    Пригласить
                </button>
            </div>
            @endif
            @endforeach
            @else
            <div class="f-13" style="opacity:.5">Резерв пуст</div>
            @endif
        </div>

        {{-- Поиск внешнего --}}
        <div id="subTabExternal" style="display:none">
            <input type="text" id="subSearchInput" placeholder="Поиск по имени…" autocomplete="off" style="width:100%;margin-bottom:.5rem">
            <div id="subSearchResults"></div>
        </div>

        {{-- Форма подтверждения (скрытая) --}}
        <form method="POST" id="subForm" action="{{ route('leagues.substitutions.store', $leagueForSubs) }}" style="display:none">
            @csrf
            <input type="hidden" name="occurrence_id" value="{{ $team->occurrence_id }}">
            <input type="hidden" name="team_id" value="{{ $team->id }}">
            <input type="hidden" name="original_player_id" id="subOriginalId">
            <input type="hidden" name="substitute_player_id" id="subSubstituteId">
            <input type="hidden" name="substitute_source" id="subSource">
            <div class="mt-3 p-2" style="background:rgba(128,128,128,.08);border-radius:8px" id="subConfirmBlock">
                <div class="f-13 mb-2" id="subConfirmText"></div>
                <button type="submit" class="btn w-100">Отправить приглашение</button>
            </div>
        </form>
    </div>
</div>
@endif

<x-slot name="script">
<script>
@if($leagueForSubs && $team->occurrence_id && !$tourStarted)
(function(){
    function openSubModal(btn) {
        document.getElementById('subOriginalId').value = btn.dataset.memberId;
        document.getElementById('subReplacingLabel').textContent = 'Замена для: ' + btn.dataset.memberName;
        document.getElementById('subForm').style.display = 'none';
        document.getElementById('subSearchInput').value = '';
        document.getElementById('subSearchResults').innerHTML = '';
        switchSubTab('reserve');
        document.getElementById('subModal').style.display = 'flex';
    }
    function closeSubModal() { document.getElementById('subModal').style.display = 'none'; }
    function switchSubTab(tab) {
        document.querySelectorAll('.sub-tab-btn').forEach(function(b){ b.classList.toggle('btn-secondary', b.dataset.tab !== tab); });
        document.getElementById('subTabReserve').style.display = tab === 'reserve' ? '' : 'none';
        document.getElementById('subTabExternal').style.display = tab === 'external' ? '' : 'none';
    }
    function selectSubstitute(id, name, source) {
        document.getElementById('subSubstituteId').value = id;
        document.getElementById('subSource').value = source;
        document.getElementById('subConfirmText').textContent = 'Выбран: ' + name;
        document.getElementById('subForm').style.display = '';
    }
    window.openSubModal = openSubModal;
    window.closeSubModal = closeSubModal;
    window.switchSubTab = switchSubTab;
    window.selectSubstitute = selectSubstitute;

    var t;
    document.getElementById('subSearchInput').addEventListener('input', function(){
        clearTimeout(t); var q = this.value.trim();
        if (q.length < 2) return;
        t = setTimeout(function(){
            jQuery.ajax({url: '/api/users/search', data: {q: q}, success: function(r){
                var el = document.getElementById('subSearchResults'); el.innerHTML = '';
                (r.items || []).forEach(function(u){
                    var d = document.createElement('div');
                    d.style.cssText = 'padding:5px 0;border-bottom:1px solid rgba(128,128,128,.08);display:flex;align-items:center;gap:8px';
                    d.innerHTML = '<span style="flex:1">' + (u.label || u.name) + '</span>'
                        + '<button type="button" class="btn btn-small" onclick="selectSubstitute(' + u.id + ',\''
                        + (u.label || u.name).replace(/'/g, "\\'") + '\',\'external\')">Пригласить</button>';
                    el.appendChild(d);
                });
            }});
        }, 300);
    });
    document.getElementById('subModal').addEventListener('click', function(e){ if (e.target === this) closeSubModal(); });
})();
@endif
(function(){
    var input=document.getElementById('ti-input'),dd=document.getElementById('ti-dd'),
        hid=document.getElementById('ti-userid'),sel=document.getElementById('ti-selected'),
        role=document.getElementById('ti-role'),posW=document.getElementById('ti-pos-wrap'),
        timer=null;
    if(!input)return;
    function syncPos(){if(posW)posW.style.display=(role&&role.value==='reserve')?'none':'';}
    if(role){role.addEventListener('change',syncPos);syncPos();}
    function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
    input.addEventListener('input',function(){
        clearTimeout(timer);hid.value='';sel.style.display='none';
        var q=input.value.trim();
        if(q.length<2){dd.style.display='none';return;}
        dd.innerHTML='<div style="padding:1rem 1.6rem;font-size:1.5rem">Поиск…</div>';dd.style.display='block';
        timer=setTimeout(function(){
            fetch('/api/users/search?exclude_bots=1&q='+encodeURIComponent(q),{headers:{'Accept':'application/json'},credentials:'same-origin'})
            .then(function(r){return r.json();}).then(function(data){
                var items=data.items||[];dd.innerHTML='';
                if(!items.length){dd.innerHTML='<div style="padding:1rem 1.6rem;font-size:1.5rem">Ничего не найдено</div>';return;}
                items.forEach(function(item){
                    var div=document.createElement('div');
                    div.style='padding:1rem 1.6rem;cursor:pointer;font-size:1.5rem;border-bottom:.1rem solid var(--border-color,#eee)';
                    var botBadge = item.is_bot ? '<span style="display:inline-block;padding:1px 8px;border-radius:10px;font-size:11px;font-weight:600;background:#fef3c7;color:#92400e;margin-left:.5rem">🤖 бот</span>' : '';
                    div.innerHTML='<span class="b-600">'+esc(item.label||item.name)+'</span>'+botBadge;
                    div.addEventListener('mouseover',function(){this.style.background='var(--bg2,#f5f5f5)';});
                    div.addEventListener('mouseout',function(){this.style.background='';});
                    div.addEventListener('click',function(){
                        hid.value=item.id;input.value=item.label||item.name;
                        sel.textContent='✅ '+input.value;sel.style.display='block';dd.style.display='none';
                    });
                    dd.appendChild(div);
                });
            });
        },250);
    });
    document.addEventListener('click',function(e){if(!document.getElementById('ti-wrap').contains(e.target))dd.style.display='none';});
})();
</script>
</x-slot>
</x-voll-layout>
