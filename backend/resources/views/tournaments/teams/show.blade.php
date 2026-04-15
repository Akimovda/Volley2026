<x-voll-layout body_class="tournament-team-page">
<x-slot name="title">{{ $team->name }} — команда</x-slot>
<x-slot name="h1">{{ $team->name }}</x-slot>

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
    <div class="d-flex between fvc mt-1" style="flex-wrap:wrap;gap:1rem">
        <div class="f-15" style="opacity:.6">
            {{ $team->team_kind === 'classic_team' ? '🏐 Классическая команда' : '🏖 Пляжная команда' }}
        </div>
        <a href="{{ route('events.show', $event) }}" class="btn btn-secondary">← К турниру</a>
    </div>
</x-slot>

@php
$settings       = $team->event->tournamentSetting ?? null;
$confirmedCount = $team->members->where('confirmation_status','confirmed')->count();
$pendingCount   = ($team->invites ?? collect())->where('status','pending')->count();
$isCaptain      = (int)$team->captain_user_id === (int)auth()->id();
$posLabels = ['setter'=>'Связующий','outside'=>'Доигровщик','opposite'=>'Диагональный','middle'=>'Центральный','libero'=>'Либеро'];
$roleLabels = ['captain'=>'Капитан','player'=>'Основной игрок','reserve'=>'Запасной'];
$stLabels = ['confirmed'=>'Подтверждён','joined'=>'Ожидает подтверждения','invited'=>'Приглашён','declined'=>'Отклонён'];
$stColors = ['confirmed'=>'#4caf50','joined'=>'#ff9800','invited'=>'#2967BA','declined'=>'#f44336'];
$invStColors = ['accepted'=>'#4caf50','declined'=>'#f44336','revoked'=>'#999','expired'=>'#999','pending'=>'#ff9800'];
$invStLabels = ['pending'=>'Ожидает','accepted'=>'Принято','declined'=>'Отклонено','revoked'=>'Отозвано','expired'=>'Истекло'];
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

<div class="row row2">
<div class="col-lg-8">

    {{-- Состав --}}
    <div class="ramka">
        <h2 class="-mt-05">👥 Состав команды</h2>
        <div class="f-15 mb-2" style="opacity:.6">
            Капитан: <strong>{{ $team->captain->name ?? ('#'.$team->captain_user_id) }}</strong>
            @if($team->occurrence)
                · {{ \Carbon\Carbon::parse($team->occurrence->starts_at)->format('d.m H:i') }}
            @endif
        </div>

        @forelse($team->members as $member)
        <div class="card d-flex between fvc mb-1" style="flex-wrap:wrap;gap:1rem">
            <div>
                <div class="b-600 f-16">{{ $member->user->name ?? ('#'.$member->user_id) }}</div>
                <div class="f-13" style="opacity:.6">
                    {{ $roleLabels[$member->team_role] ?? $member->team_role }}
                    @if($team->team_kind==='classic_team' && $member->position_code)
                        · {{ $posLabels[$member->position_code] ?? $member->position_code }}
                    @endif
                </div>
            </div>
            <div class="d-flex gap-1 fvc">
                <span class="f-13 b-600" style="color:{{ $stColors[$member->confirmation_status] ?? '#999' }}">
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
                    <form method="POST" action="{{ route('tournamentTeams.members.destroy',[$event,$team,$member]) }}"
                          onsubmit="return confirm('Удалить из команды?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-small btn-secondary">🗑</button>
                    </form>
                @endif
            </div>
        </div>
        @empty
        <div class="card text-center" style="padding:2rem;opacity:.5">Состав пока пуст</div>
        @endforelse
    </div>

    {{-- Созданные приглашения --}}
    @if($isCaptain)
    <div class="ramka">
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
                    <span class="f-13 ml-1" style="opacity:.6">
                        {{ $roleLabels[$inv->team_role] ?? $inv->team_role }}
                        @if($inv->position_code) · {{ $posLabels[$inv->position_code] ?? $inv->position_code }} @endif
                    </span>
                </div>
                <span class="f-13 b-600" style="color:{{ $invStColors[$inv->status] ?? '#ff9800' }}">
                    {{ $invStLabels[$inv->status] ?? $inv->status }}
                </span>
            </div>
            <div class="f-13 mb-1" style="opacity:.5">
                {{ $channels->isNotEmpty() ? 'Отправлено: '.$channels->join(', ') : 'Ссылка создана' }}
                · {{ $inv->created_at?->format('d.m.Y H:i') }}
            </div>
            <a href="{{ $invUrl }}" target="_blank" class="btn btn-small btn-secondary">🔗 Открыть ссылку</a>
        </div>
        @empty
        <div class="f-15" style="opacity:.5">Приглашений пока нет</div>
        @endforelse
    </div>

    {{-- Создать приглашение --}}
    <div class="ramka">
        <h2 class="-mt-05">➕ Создать ссылку-приглашение</h2>
        <div class="f-15 mb-2" style="opacity:.6">
            Игрок получит персональную ссылку с ролью и позицией.
        </div>
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
                    <div id="ti-selected" class="f-13 mt-05" style="color:#4caf50;display:none"></div>
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

    {{-- Заявка --}}
    <div class="ramka">
        <h2 class="-mt-05">📋 Подача заявки</h2>
        @if($team->application)
            <div class="alert alert-info">Заявка подана · Статус: <strong>{{ $team->application->status }}</strong></div>
        @elseif($isCaptain)
            <div class="f-15 mb-2" style="opacity:.6">Если состав готов — подайте заявку на турнир.</div>
            <form method="POST" action="{{ route('tournamentTeams.submit',[$event,$team]) }}"
                  onsubmit="return confirm('Подать заявку команды на турнир?')">
                @csrf
                <button type="submit" class="btn">Подать заявку</button>
            </form>
        @else
            <div class="f-15" style="opacity:.5">Заявка ещё не подана</div>
        @endif
    </div>

</div>
<div class="col-lg-4">

    {{-- Статус --}}
    <div class="ramka">
        <h2 class="-mt-05">📊 Состав</h2>
        <div class="card">
            <div class="f-15 mb-05">Подтверждено: <strong>{{ $confirmedCount }}</strong></div>
            <div class="f-15 mb-05">Ожидают: <strong>{{ $pendingCount }}</strong></div>
            <div class="f-15 mb-1">Всего: <strong>{{ $team->members->count() }}</strong></div>
            @if($settings)
            <div class="f-13" style="opacity:.6;line-height:1.8">
                Схема: <strong>{{ $settings->game_scheme ?? '—' }}</strong><br>
                Мин.: <strong>{{ $settings->team_size_min ?? '—' }}</strong> ·
                Макс.: <strong>{{ $settings->total_players_max ?? $settings->team_size_max ?? '—' }}</strong><br>
                Запасных: <strong>{{ $settings->reserve_players_max ?? '—' }}</strong><br>
                Либеро: <strong>{{ $settings->require_libero ? 'Да' : 'Нет' }}</strong>
                @if($settings->max_rating_sum)<br>Лимит рейтинга: <strong>{{ $settings->max_rating_sum }}</strong>@endif
            </div>
            @endif
        </div>
        <div class="mt-1">
            @if($team->is_complete)
            <div class="alert alert-success">✅ Состав готов</div>
            @else
            <div class="alert alert-warning">⚠️ Состав не готов</div>
            @endif
        </div>
    </div>

</div>
</div>
</div>

<x-slot name="script">
<script>
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
        dd.innerHTML='<div style="padding:1rem 1.6rem;opacity:.5;font-size:1.5rem">Поиск…</div>';dd.style.display='block';
        timer=setTimeout(function(){
            fetch('/api/users/search?q='+encodeURIComponent(q),{headers:{'Accept':'application/json'},credentials:'same-origin'})
            .then(function(r){return r.json();}).then(function(data){
                var items=data.items||[];dd.innerHTML='';
                if(!items.length){dd.innerHTML='<div style="padding:1rem 1.6rem;opacity:.5;font-size:1.5rem">Ничего не найдено</div>';return;}
                items.forEach(function(item){
                    var div=document.createElement('div');
                    div.style='padding:1rem 1.6rem;cursor:pointer;font-size:1.5rem;border-bottom:.1rem solid var(--border-color,#eee)';
                    div.innerHTML='<span class="b-600">'+esc(item.label||item.name)+'</span>';
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
