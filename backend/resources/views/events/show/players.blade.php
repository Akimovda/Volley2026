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
	@if (empty($freePositions))
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
<div class="ramka">
    <h2 class="-mt-05">Пригласить игрока</h2>
    <div class="text-muted small mb-2">
        Игрок получит уведомление с информацией о мероприятии и ссылкой для записи.
    </div>

    @if(session('status') && str_contains(session('status'), 'Приглашение'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ $inviteAction }}" id="invite-player-form">
        @csrf
        <input type="hidden" name="occurrence_id" value="{{ $occurrence->id }}">
        <input type="hidden" name="to_user_id" id="invite-userid">

        <div class="relative mb-2" id="invite-ac-wrap">
            <input
                type="text"
                id="invite-ac-input"
                autocomplete="off"
                class="form-control"
                placeholder="Введите имя или email игрока…"
            >
            <div id="invite-ac-dd"
                 style="display:none;position:absolute;left:0;right:0;top:100%;z-index:50;background:#fff;border:1px solid #ddd;border-radius:8px;max-height:200px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,.1)">
            </div>
        </div>
        <div id="invite-ac-selected" style="display:none;font-size:13px;color:green;margin-bottom:6px;"></div>

        <button type="submit" id="invite-submit-btn" class="btn btn-primary w-100" disabled>
            Отправить приглашение
        </button>
    </form>
</div>

<script>
(function(){
    var input   = document.getElementById('invite-ac-input');
    var dd      = document.getElementById('invite-ac-dd');
    var hidden  = document.getElementById('invite-userid');
    var sel     = document.getElementById('invite-ac-selected');
    var btn     = document.getElementById('invite-submit-btn');
    var timer   = null;
    var url     = '{{ $inviteSearchUrl }}';

    if (!input) return;

    function clear() { hidden.value=''; btn.disabled=true; sel.style.display='none'; }
    function pick(id, label) {
        hidden.value=id; btn.disabled=false;
        sel.textContent='✅ '+label; sel.style.display='block';
        dd.style.display='none'; dd.innerHTML='';
        input.value=label;
    }
    function esc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

    function render(items){
        dd.innerHTML='';
        if(!items.length){ dd.innerHTML='<div style="padding:10px;color:#999;font-size:13px">Ничего не найдено</div>'; dd.style.display='block'; return; }
        items.forEach(function(item){
            var d=document.createElement('div');
            d.style.cssText='padding:8px 12px;cursor:pointer;font-size:14px;border-bottom:1px solid #f0f0f0';
            d.innerHTML='<span style="font-weight:600">'+esc(item.label||item.name)+'</span>'+(item.meta?'<span style="color:#999;font-size:12px;margin-left:8px">'+esc(item.meta)+'</span>':'');
            d.onmouseover=function(){d.style.background='#f8f8f8'};
            d.onmouseout=function(){d.style.background=''};
            d.onclick=function(){ pick(item.id, item.label||item.name); };
            dd.appendChild(d);
        });
        dd.style.display='block';
    }

    input.addEventListener('input', function(){
        clear();
        clearTimeout(timer);
        var q=input.value.trim();
        if(q.length<2){ dd.style.display='none'; return; }
        dd.innerHTML='<div style="padding:10px;color:#999;font-size:13px">Поиск…</div>'; dd.style.display='block';
        timer=setTimeout(function(){
            fetch(url+'?q='+encodeURIComponent(q),{headers:{'Accept':'application/json'},credentials:'same-origin'})
            .then(function(r){return r.json();})
            .then(function(data){render(data.items||[]);})
            .catch(function(){ dd.innerHTML='<div style="padding:10px;color:red;font-size:13px">Ошибка</div>'; dd.style.display='block'; });
        },250);
    });

    document.addEventListener('click',function(e){
        if(!document.getElementById('invite-ac-wrap').contains(e.target)) dd.style.display='none';
    });

    document.getElementById('invite-player-form').addEventListener('submit',function(e){
        if(!hidden.value){ e.preventDefault(); input.focus(); }
    });
})();
</script>

{{-- ===============================
ГРУППА НА ПЛЯЖКУ
=============================== --}}
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