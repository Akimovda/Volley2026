{{-- COMPONENT: event registration --}}


<div class="ramka no-highlight p-1">	
    {{-- Галерея со Swiper + Fancybox --}}
    @if(isset($event->event_photos) && count($event->event_photos) > 0)
	@php
	// Фильтруем только существующие фото
	$validPhotos = [];
	foreach ($event->event_photos as $photoId) {
	$media = \Spatie\MediaLibrary\MediaCollections\Models\Media::find($photoId);
	if ($media) {
	$validPhotos[] = $media;
	}
	}
	// Обновляем массив в базе если есть удаленные
	if (count($validPhotos) !== count($event->event_photos)) {
	$event->update(['event_photos' => array_column($validPhotos, 'id')]);
	}
	@endphp
	
	@if(count($validPhotos) > 0)
	<div class="event-gallery">
		<div class="event-swiper swiper">
			<div class="swiper-wrapper">
				@foreach($validPhotos as $index => $media)
				<div class="swiper-slide">
					<div class="hover-image">
						<a href="{{ $media->getUrl() }}" class="fancybox" data-fancybox="event-gallery">
							<img src="{{ $media->getUrl('event_thumb') }}" alt="Фото мероприятия {{ $index + 1 }}" loading="lazy">
							<span></span>
							<div class="hover-image-circle"></div>
						</a>
					</div>						
				</div>
				@endforeach
			</div>
			<div class="swiper-pagination"></div>
		</div>
	</div>
	@else
	{{-- Фото есть в массиве, но все удалены --}}
	<div class="hover-image">
		<img src="/img/{{ $event->direction === 'beach' ? 'beach.webp' : 'classic.webp' }}" alt="Фото мероприятия">
	</div>
	@endif
    @else
	{{-- Нет фото в галерее --}}
	<div class="hover-image">
		<img src="/img/{{ $event->direction === 'beach' ? 'beach.webp' : 'classic.webp' }}" alt="Фото мероприятия">
	</div>
    @endif
</div>



<div class="ramka">
    <h2 class="-mt-05">Запись на мероприятие</h2>
	
    @php
	$maxPlayers = $occurrence->effectiveMaxPlayers();
    @endphp
	
    {{-- ===============================
    СТАТИСТИКА ИГРОКОВ
    =============================== --}}
    @if(!is_null($registeredTotal))
	<div class="text-muted small mb-1">
		Участников:
		<span id="players-count">{{ $registeredTotal }}</span>
		@if($maxPlayers)
		/ {{ $maxPlayers }}
		@endif
	</div>
    @endif
	
    @php
	$percent = ($maxPlayers && $registeredTotal !== null)
	? min(100, ($registeredTotal / $maxPlayers) * 100)
	: 0;
	
	$barClass = 'bg-danger';
	if ($percent >= 75) {
	$barClass = 'bg-success';
	} elseif ($percent >= 40) {
	$barClass = 'bg-warning';
	}
    @endphp
	
	
	
    @if($maxPlayers)
	<div class="progress mb-2">
		<div
		id="players-progress"
		class="progress-bar {{ $barClass }}"
		role="progressbar"
		aria-valuenow="{{ $percent }}"
		aria-valuemin="0"
		aria-valuemax="100"
		style="width: {{ $percent }}%">
		</div>
	</div>
    @endif
	
    {{-- ===============================
    СТАТУС СОБЫТИЯ
    =============================== --}}
    @if($occurrence->isFinished())
	<div class="alert alert-info">
		🏁 Мероприятие завершено!
	</div>
	
    @elseif($occurrence->isRunning())
	<div class="alert alert-warning">
		⚠️ Мероприятие уже идет!
	</div>
	
    @else
	
	{{-- ===============================
	УЖЕ ЗАПИСАН
	=============================== --}}
	@if ($isRegistered)
	<div class="alert alert-success">
		Вы уже записаны
		@if($userPosition)
		<div><span class="f-16">позиция:</span> {{ $userPosition }}</div>
		@endif
	</div>
	
	@if ($cancel->allowed)
	<form method="POST" action="{{ $leaveAction }}">
		@csrf
		@method('DELETE')
		<button type="submit" class="mt-2 btn btn-danger w-100">
			Отменить запись
		</button>
	</form>
	@else
	<div class="alert alert-warning mt-2">
		{{ $cancel->message ?? $cancel->errors[0] ?? 'Отмена записи недоступна.' }}
	</div>
	@endif
	
	{{-- ===============================
	GUARD ЗАПРЕЩАЕТ
	=============================== --}}
	@elseif (! $join->allowed)
	{{-- 
	<button class="btn btn-primary w-100" disabled>
		Записаться
	</button>
	--}}
	@if (!empty($join->errors))
	<div class="alert alert-info mt-2">
		{{ $join->errors[0] }}
	</div>
	@endif
	
	{{-- ===============================
	МОЖНО ЗАПИСАТЬСЯ
	=============================== --}}
	@else
	@php
        $regMode = $registrationMode ?? 'single';
        $isTeamClassic = in_array($regMode, ['team_classic', 'team']);
        $isTeamBeach   = $regMode === 'team_beach';
    @endphp

    @if ($isTeamClassic || $isTeamBeach)
    {{-- ===== КОМАНДНАЯ ЗАПИСЬ ===== --}}
    <div class="alert alert-info">
        🏐 Для этого мероприятия используется командная запись.
    </div>
    @php
        $myTeams = $myTournamentTeams ?? collect();
    @endphp
    @if($myTeams->count() > 0)
        @foreach($myTeams as $myTeam)
        <div class="border rounded p-2 mb-2" style="font-size:14px">
            <div class="fw-bold">{{ $myTeam->name }}</div>
            <div class="text-muted small">Статус: {{ $myTeam->status }} · Игроков: {{ $myTeam->members->count() }}</div>
            <a href="{{ route('tournamentTeams.show', [$event, $myTeam]) }}" class="btn btn-sm btn-outline-primary mt-1">
                Открыть команду
            </a>
        </div>
        @endforeach
    @endif
    @if(auth()->check())
    <form method="POST" action="{{ route('tournamentTeams.store', $event) }}" class="mt-2">
        @csrf
        <input type="text" name="name" class="form-control mb-2" placeholder="Название команды" required>
        <input type="hidden" name="occurrence_id" value="{{ $occurrence->id }}">
        <input type="hidden" name="team_kind" value="{{ $isTeamBeach ? 'beach_pair' : 'classic_team' }}">
        @if($isTeamClassic)
        <select name="captain_position_code" class="form-select mb-2" required>
            <option value="">— ваша позиция в команде —</option>
            <option value="setter">Связующий</option>
            <option value="outside">Доигровщик</option>
            <option value="opposite">Диагональный</option>
            <option value="middle">Центральный</option>
            <option value="libero">Либеро</option>
        </select>
        @endif
        <button type="submit" class="btn btn-primary w-100">
            Создать команду
        </button>
    </form>
    @endif

    @elseif (empty($freePositions))
	<div class="alert alert-warning">
		Свободных мест нет.
	</div>
	@else
	@foreach ($freePositions as $pos)
	@php
	$key = (string)($pos['key'] ?? '');
	$free = (int)($pos['free'] ?? 0);
	@endphp
	
	<form method="POST" action="{{ $joinAction }}">
		@csrf
		<input type="hidden" name="position" value="{{ $key }}">
		<button
		type="submit"
		class="d-flex between btn btn-primary w-100 mb-1"
		{{ $free <= 0 ? 'disabled' : '' }}>
			{{ position_name($key) }}
			<span>
				<span class="pl-1 pr-1 f-11">Свободно:</span> {{ $free }} 
			</span>
		</button>
	</form>
	@endforeach
	
	<div class="text-muted small">
		Выбери позицию<br>(показаны только свободные).
	</div>
	@endif
	@endif
    @endif
</div>	
{{-- ===============================
ПРИГЛАСИТЬ ИГРОКА
=============================== --}}
@if ($isRegistered && auth()->check())
@php
    $inviteSearchUrl = route('api.users.search');
    $inviteAction    = route('events.invite', ['event' => $event->id]);
@endphp
<div class="ramka" style="z-index:5">
    <h2 class="-mt-05">Пригласить игрока</h2>
    <div class="text-muted small mb-2">
        Игрок получит уведомление с информацией о мероприятии и ссылкой для записи.
    </div>

    @if(session('status') && str_contains(session('status'), 'Приглашение'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

<form class="form" method="POST" action="{{ $inviteAction }}" id="invite-player-form">
    @csrf
    <input type="hidden" name="occurrence_id" value="{{ $occurrence->id }}">

    <div class="ac-box">
        {{-- chips --}}
        <div id="invite-selected-list" class="mb-2"></div>
        
        <div style="position: relative" class="mb-2" id="invite-ac-wrap">
            <input type="text"
                id="invite-ac-input"
                autocomplete="off"
                class="form-control"
                placeholder="Введите имя или email игрока…"
            >
            <div id="invite-ac-dd" class="form-select-dropdown trainer_dd"></div>
        </div>
        
        <button type="submit" id="invite-submit-btn" class="btn btn-primary w-100" disabled>
            Отправить приглашения
        </button>
    </div>
</form>
</div>
<script>
(function(){
    var input   = document.getElementById('invite-ac-input');
    var dd      = document.getElementById('invite-ac-dd');
    var selList = document.getElementById('invite-selected-list');
    var btn     = document.getElementById('invite-submit-btn');
    var form    = document.getElementById('invite-player-form');
    var timer   = null;
    var url     = '/api/users/search';
    var selected = {}; // id -> label

    if (!input) return;

    function esc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

    function showDd() {
        dd.classList.add('form-select-dropdown--active');
    }

    function hideDd() {
        dd.classList.remove('form-select-dropdown--active');
    }

    function renderSelected() {
        selList.innerHTML = '';
        var ids = Object.keys(selected);
        
        if (ids.length === 0) {
            selList.innerHTML = '';
            return;
        }
        
        ids.forEach(function(id) {
            var span = document.createElement('span');
            span.className = 'd-flex mb-1 between f-16 fvc pl-1 pr-1';
            
            var t = document.createElement('span');
            t.textContent = selected[id];
            
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'trainer-chip-remove btn btn-small btn-secondary';
            btn.setAttribute('data-id', id);
            btn.textContent = '×';
            btn.addEventListener('click', function() {
                delete selected[id];
                var f = form.querySelector('input[name="to_user_ids[]"][value="'+id+'"]');
                if (f) f.remove();
                renderSelected();
                updateBtn();
            });
            
            span.appendChild(t);
            span.appendChild(btn);
            selList.appendChild(span);
            
            var hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'to_user_ids[]';
            hidden.value = id;
            hidden.setAttribute('data-invite-hidden', id);
            form.appendChild(hidden);
        });
    }

    function updateBtn() {
        var count = Object.keys(selected).length;
        btn.disabled = count === 0;
        btn.textContent = count > 0
            ? 'Отправить приглашения (' + count + ')'
            : 'Отправить приглашения';
    }

    function pick(id, label) {
        id = String(id);
        if (selected[id]) {
            hideDd();
            input.value = '';
            return;
        }
        selected[id] = label;
        renderSelected();
        updateBtn();
        hideDd();
        dd.innerHTML = '';
        input.value = '';
        input.focus();
    }

    function render(items) {
        dd.innerHTML = '';
        if (!items.length) {
            dd.innerHTML = '<div class="city-message">Ничего не найдено</div>';
            showDd();
            return;
        }
        
        items.forEach(function(item) {
            var id = String(item.id);
            var already = !!selected[id];
            var div = document.createElement('div');
            div.className = 'trainer-item form-select-option';
            if (already) div.style.opacity = '0.4';
            div.setAttribute('data-id', id);
            div.setAttribute('data-label', item.label || item.name);
            
            div.innerHTML = '<div class="text-sm text-gray-900">' + esc(item.label || item.name) + '</div>';
            
            if (!already) {
                div.addEventListener('click', function() {
                    pick(id, item.label || item.name);
                });
            }
            
            dd.appendChild(div);
        });
        
        showDd();
    }

    input.addEventListener('input', function() {
        clearTimeout(timer);
        var q = input.value.trim();
        if (q.length < 2) {
            hideDd();
            return;
        }
        
        dd.innerHTML = '<div class="city-message">Поиск…</div>';
        showDd();
        
        timer = setTimeout(function() {
            fetch(url + '?q=' + encodeURIComponent(q), {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                render(data.items || []);
            })
            .catch(function() {
                dd.innerHTML = '<div class="city-message">Ошибка загрузки</div>';
                showDd();
            });
        }, 250);
    });

    document.addEventListener('click', function(e) {
        var wrap = document.getElementById('invite-ac-wrap');
        if (wrap && !wrap.contains(e.target)) {
            hideDd();
        }
    });

    input.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') hideDd();
    });

    form.addEventListener('submit', function(e) {
        if (Object.keys(selected).length === 0) {
            e.preventDefault();
            input.focus();
        }
    });
})();
</script>

{{-- ===============================
ГРУППА НА ПЛЯЖКУ
=============================== --}}
@endif

@if((!empty($groupUi['enabled'])) && ($isRegistered))

<div class="ramka">
	<h2 class="-mt-05">Группа на пляжку</h2>
	
	
	{{-- Пользователь еще не записан --}}
	@if(empty($groupUi['registration']))
	@if(!empty($groupUi['pending_invites']) && $groupUi['pending_invites']->count())
	<div class="alert alert-info mt-2">
		У вас есть приглашения в группу для этого мероприятия.
	</div>
	
	@foreach($groupUi['pending_invites'] as $invite)
	<div class="border rounded p-3 mt-2">
		<div class="text-sm">
			Приглашение от
			<strong>{{ $invite->from_user_name ?: $invite->from_user_email ?: ('#'.$invite->from_user_id) }}</strong>
		</div>
		
		<div class="mt-2">
			<div class="alert alert-secondary mb-2">
				Чтобы принять приглашение, сначала зарегистрируйтесь в системе, затем запишитесь на мероприятие.
				После этого вы сможете вернуться и принять приглашение в группу.
			</div>
			
			<form method="POST" action="{{ route('events.group.decline', ['event' => $event->id, 'invite' => $invite->id]) }}">
				@csrf
				<button type="submit" class="btn btn-outline-secondary">
					Отклонить
				</button>
			</form>
		</div>
	</div>
	@endforeach
	@endif
	
	{{-- Пользователь записан --}}
	@else
	
	{{-- Пока нет группы --}}
	@if(empty($groupUi['group_key']))
	<form method="POST" action="{{ route('events.group.create', ['event' => $event->id]) }}" class="mt-2">
		@csrf
		<button type="submit" class="btn btn-outline-primary">
			Объединиться
		</button>
	</form>
	@endif
	
	{{-- Уже есть группа --}}
	@if(!empty($groupUi['group_key']))
	<div class="mt-3">
		<div class="fw-bold">Состав группы</div>
		
		@if(!empty($groupUi['group_members']) && $groupUi['group_members']->count())
		<ul class="mt-2 mb-2">
			@foreach($groupUi['group_members'] as $member)
			<li>
				{{ $member->name ?: $member->email ?: ('#'.$member->user_id) }}
				@if((int)$member->user_id === (int)auth()->id())
				<strong>(Вы)</strong>
				@endif
			</li>
			@endforeach
		</ul>
		@else
		<div class="text-muted small mt-2">
			Пока в группе только вы.
		</div>
		@endif
		
		<form method="POST" action="{{ route('events.group.leave', ['event' => $event->id]) }}" class="mt-2">
			@csrf
			<button type="submit" class="btn btn-outline-danger">
				Выйти из группы
			</button>
		</form>
	</div>
	@endif
	
	{{-- Приглашение других игроков --}}
	@if(!empty($groupUi['invite_candidates']) && $groupUi['invite_candidates']->count())
	<div class="mt-4">
		<label class="form-label">Пригласить игрока в группу</label>
		
		<form method="POST" action="{{ route('events.group.invite', ['event' => $event->id]) }}">
			@csrf
			
			<select name="to_user_id" class="form-select" required>
				<option value="">— выбрать игрока —</option>
				@foreach($groupUi['invite_candidates'] as $candidate)
				<option value="{{ $candidate->id }}">
					{{ $candidate->name ?: $candidate->email ?: ('#'.$candidate->id) }}
				</option>
				@endforeach
			</select>
			
			<button type="submit" class="btn btn-outline-primary mt-2">
				Пригласить
			</button>
		</form>
	</div>
	@endif
	
	{{-- Входящие приглашения --}}
	@if(!empty($groupUi['pending_invites']) && $groupUi['pending_invites']->count())
	<div class="mt-4">
		<div class="fw-bold">Входящие приглашения</div>
		
		@foreach($groupUi['pending_invites'] as $invite)
		<div class="border rounded p-3 mt-2">
			<div class="text-sm">
				Приглашение от
				<strong>{{ $invite->from_user_name ?: $invite->from_user_email ?: ('#'.$invite->from_user_id) }}</strong>
			</div>
			
			<div class="mt-2 d-flex gap-2 flex-wrap">
				<form method="POST" action="{{ route('events.group.accept', ['event' => $event->id, 'invite' => $invite->id]) }}">
					@csrf
					<button type="submit" class="btn btn-primary">
						Принять
					</button>
				</form>
				
				<form method="POST" action="{{ route('events.group.decline', ['event' => $event->id, 'invite' => $invite->id]) }}">
					@csrf
					<button type="submit" class="btn btn-outline-secondary">
						Отклонить
					</button>
				</form>
			</div>
		</div>
		@endforeach
	</div>
	@endif
	@endif
</div>
@endif

{{-- ===============================
СПИСОК ИГРОКОВ
=============================== --}}
@if($showParticipants)
<div class="ramka">
    <h2 class="-mt-05">Записанные игроки</h2>	
	
    <div id="players-list"></div>
</div>
@endif