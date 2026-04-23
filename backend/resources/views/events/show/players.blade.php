{{-- COMPONENT: event registration --}}


<div class="ramka">	
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
    @if($event->format === 'tournament')
	@php
	$teamsMax = $event->tournament_teams_count ?: ($event->tournamentSettings->teams_count ?? 0);
	$teamSize = $event->tournamentSettings->team_size_min ?? 2;
	$teamsRegistered = \App\Models\EventTeam::where('event_id', $event->id)
	->whereIn('status', ['ready','pending','submitted','confirmed','approved'])
	->count();
	$playersRegistered = \App\Models\EventTeamMember::whereHas('team', fn($q) => $q->where('event_id', $event->id))
	->where('confirmation_status', 'confirmed')
	->count();
	$playersMax = $teamsMax * $teamSize;
	@endphp
	<div class="text-muted small mb-1">
		👥 Команд: <strong>{{ $teamsRegistered }}</strong> / {{ $teamsMax }}
	</div>
	<div class="text-muted small mb-1">
		👤 Участников: <strong>{{ $playersRegistered }}</strong> / {{ $playersMax }}
	</div>
    @elseif(!is_null($registeredTotal))
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
    @if($occurrence->isCancelled())
	<div class="alert alert-danger">
		⛔️ Отменено❗️ Мероприятие не состоится в связи с отсутствием кворума.
	</div>
	
    @elseif($occurrence->isFinished())
	<div class="alert alert-info">
		🏁 Мероприятие завершено!
	</div>
	
    @elseif($occurrence->isRunning())
	<div class="alert alert-warning">
		⚠️ Мероприятие уже идет!
	</div>
	
    @else
	<div id="join-registration-block">
		
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
		
		{{-- ======= БЛОК ОПЛАТЫ ======= --}}
		@php
		$myReg = null;
		if (auth()->check() && isset($occurrence)) {
        $myReg = \App\Models\EventRegistration::where('user_id', auth()->id())
		->where('occurrence_id', $occurrence->id)
		->where('is_cancelled', false)
		->first();
		}
		@endphp
		@if($myReg && $myReg->subscription_id && $myReg->auto_booked && !$myReg->confirmed_at)
		<div class="alert alert-warning mt-1">
			⏰ <strong>Подтвердите участие</strong> по абонементу до
			{{ \Carbon\Carbon::parse($occurrence->starts_at)->subHours(12)->format('d.m H:i') }}
			— иначе запись будет отменена автоматически.
		</div>
		<form method="POST" action="{{ route('registrations.confirm', $myReg->id) }}">
			@csrf
			<button type="submit" class="btn w-100 mt-1">✅ Подтвердить участие</button>
		</form>
		@elseif($myReg && $myReg->subscription_id && $myReg->auto_booked && $myReg->confirmed_at)
		<div class="alert alert-success mt-1">✅ Участие подтверждено</div>
		@endif
		@php
	    $myPayment = null;
	    if (auth()->check() && isset($occurrence)) {
		$myPayment = \App\Models\Payment::where('user_id', auth()->id())
		->where('occurrence_id', $occurrence->id)
		->whereIn('status', ['pending', 'paid'])
		->latest()
		->first();
	    }
		@endphp
		@if($myPayment && $myPayment->status === 'pending')
	    @if(in_array($myPayment->method, ['tbank_link', 'sber_link']))
		<div class="alert alert-warning mt-1">
			⏳ Ожидаем оплату — <strong>{{ number_format($myPayment->amount_minor/100, 2) }} ₽</strong>
		</div>
		@php
		$payLinkUrl = null;
		$ps = \Illuminate\Support\Facades\DB::table('payment_settings')
		->where('organizer_id', $event->organizer_id)->first();
		if ($ps) {
		$payLinkUrl = $myPayment->method === 'tbank_link' ? ($ps->tbank_link ?? null) : ($ps->sber_link ?? null);
		}
		@endphp
		@if($payLinkUrl)
		<a href="{{ $payLinkUrl }}" target="_blank" class="btn w-100 mt-1">💳 Перейти к оплате</a>
		@endif
		@if(!$myPayment->user_confirmed)
		<form method="POST" action="{{ route('payments.user_confirm', $myPayment->id) }}">
			@csrf
			<button type="submit" class="btn btn-secondary w-100 mt-1">✅ Я оплатил</button>
		</form>
		@else
		<div class="alert alert-info mt-1">👀 Ждём подтверждения от организатора</div>
		@endif
	    @elseif($myPayment->method === 'yoomoney' && $myPayment->yoomoney_confirmation_url)
		<div class="alert alert-warning mt-1">⏳ Место зарезервировано до {{ $myPayment->expires_at?->format('H:i') }}</div>
		<a href="{{ $myPayment->yoomoney_confirmation_url }}" target="_blank" class="btn w-100 mt-1">🟡 Оплатить через ЮМани</a>
	    @endif
		@elseif($myPayment && $myPayment->status === 'paid')
	    <div class="alert alert-success mt-1">✅ Оплачено — {{ number_format($myPayment->amount_minor/100, 2) }} ₽</div>
		@endif
		{{-- ======= КОНЕЦ БЛОКА ОПЛАТЫ ======= --}}
		
		@if ($cancel->allowed)
		<form method="POST" action="{{ $leaveAction }}">
			@csrf
			@method('DELETE')
			<button type="submit" class="mt-1 btn btn-danger w-100">
				Отменить запись
			</button>
		</form>
		@else
		<div class="alert alert-warning mt-1">
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
		<div class="alert alert-info mt-1">
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
			Для этого мероприятия используется командная запись.
		</div>
		@php
        $myTeams = $myTournamentTeams ?? collect();
		@endphp
		@if($myTeams->count() > 0)
        @foreach($myTeams as $myTeam)
        <div class="card mt-1">
            <div class="b-600 cd">{{ $myTeam->name }}</div>
            <div class="f-16">Статус: {{ $myTeam->status }} · Игроков: {{ $myTeam->members->count() }}</div>
            <a href="{{ route('tournamentTeams.show', [$event, $myTeam]) }}" class="btn btn-small btn-secondary mt-1">
                Открыть команду
			</a>
		</div>
        @endforeach
		@endif
		@if(auth()->check() && $myTeams->count() === 0)
		<form method="POST" action="{{ route('tournamentTeams.store', $event) }}" class="form mt-1">
			@csrf
			<input type="text" name="name" class="form-control mb-1" placeholder="Название команды" required>
			<input type="hidden" name="occurrence_id" value="{{ $occurrence->id }}">
			<input type="hidden" name="team_kind" value="{{ $isTeamBeach ? 'beach_pair' : 'classic_team' }}">
			@if($isTeamClassic)
			<select name="captain_position_code" class="form-select mb-1" required>
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
		@php
		$activeSubscription = null;
		$activeCoupon = null;
		if (auth()->check() && isset($event)) {
        $activeSubscription = app(\App\Services\SubscriptionService::class)
		->findActiveForEvent(auth()->id(), $event->id);
        if (!$activeSubscription) {
		$activeCoupon = app(\App\Services\CouponService::class)
		->findActiveForEvent(auth()->id(), $event->id);
        }
		}
		@endphp
		@if($activeSubscription)
		<div class="alert alert-success mt-1 mb-2">
			🎫 <strong>Абонемент:</strong> {{ $activeSubscription->template->name }}
			— осталось <strong>{{ $activeSubscription->visits_remaining }}</strong> посещений
		</div>
		@elseif($activeCoupon)
		<div class="alert alert-warning mt-1 mb-2">
			🎟 <strong>Купон:</strong> {{ $activeCoupon->template->name }}
			— скидка <strong>{{ $activeCoupon->getDiscountPct() }}%</strong>
		</div>
		@endif
		@foreach ($freePositions as $pos)
		@php
		$key = (string)($pos['key'] ?? '');
		$free = (int)($pos['free'] ?? 0);
		@endphp
		
		<form method="POST" action="{{ $joinAction }}" data-ajax-join>
			@csrf
			<input type="hidden" name="position" value="{{ $key }}">
			@if(!empty($activeSubscription))
			<input type="hidden" name="subscription_id" value="{{ $activeSubscription->id }}">
			@elseif(!empty($activeCoupon))
			<input type="hidden" name="coupon_code" value="{{ $activeCoupon->code }}">
			@endif
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
	</div>{{-- /join-registration-block --}}		
		@endif
</div>
{{-- ===============================
РЕЗЕРВ
=============================== --}}
@php
$regMode       = (string)($event->registration_mode ?? 'single');
$isTournament  = in_array($regMode, ['team_classic','team_beach'], true);
$isFinished    = $occurrence->isFinished();
$isRunning     = $occurrence->isRunning();
$eventStarted  = $isFinished || $isRunning;

$maxPlayers    = $occurrence->effectiveMaxPlayers();
$registered    = $registered_total ?? 0;
$isFull        = $maxPlayers > 0 && $registered >= $maxPlayers;

$direction     = (string)($event->direction ?? 'classic');
$isBeach       = $direction === 'beach';
$isClassic     = $direction === 'classic';

// Позиции для резерва (из game settings)
$gs            = $event->gameSettings ?? null;
$allPositions  = [];
if ($isClassic && $gs) {
$slots = app(\App\Services\EventRoleSlotService::class)->getSlots($event);
foreach ($slots as $slot) {
$allPositions[$slot->role] = position_name($slot->role);
}
}

// Текущий пользователь в резерве?
$myWaitlist = null;
if (auth()->check()) {
$myWaitlist = \App\Models\OccurrenceWaitlist::query()
->where('occurrence_id', $occurrence->id)
->where('user_id', auth()->id())
->first();
}

// Список резерва для организатора
$isOrganizer = auth()->check() && (
auth()->user()->role === 'admin' ||
(int)($event->organizer_id ?? 0) === auth()->id()
);

$waitlistCount = \App\Models\OccurrenceWaitlist::query()
->where('occurrence_id', $occurrence->id)
->count();

$showWaitlist = !$isTournament && !$eventStarted && $isFull && auth()->check();
@endphp

@if(!$isTournament && !$eventStarted && $isFull)
<div class="ramka">
    <h2 class="-mt-05">🔔 Резерв</h2>
	
    @if(!auth()->check())
	<div class="text-muted small">🔐 Войдите на сайт чтобы записаться в резерв.</div>
	
    @elseif($myWaitlist)
	{{-- Уже в резерве --}}
	<div class="alert alert-success">
		✅ Вы в резерве. Мы уведомим вас когда освободится место.
		@if($isClassic && !empty($myWaitlist->positions))
		<div class="mt-1 small">
			Ваши позиции: <strong>{{ implode(', ', array_map('position_name', $myWaitlist->positions)) }}</strong>
		</div>
		@endif
		@if($myWaitlist->notification_expires_at && $myWaitlist->notification_expires_at->isFuture())
		<div class="mt-1 small text-warning">
			⏳ Место зарезервировано для вас до {{ $myWaitlist->notification_expires_at->setTimezone($userTz ?? 'UTC')->format('H:i') }}
		</div>
		@endif
	</div>
	
	<form method="POST" action="{{ route('occurrences.waitlist.leave', $occurrence) }}" class="mt-1">
		@csrf
		@method('DELETE')
		<button type="submit" class="btn btn-outline-secondary">Покинуть резерв</button>
	</form>
	
    @else
	{{-- Форма записи в резерв --}}
	<div class="text-muted small mb-2">
		Все места заняты. Запишитесь в резерв — мы уведомим вас первым когда освободится место.
	</div>
	
	@if($waitlistCount > 0)
	<div class="text-muted small mb-2">
		В резерве: <strong>{{ $waitlistCount }}</strong> {{ $waitlistCount == 1 ? 'человек' : ($waitlistCount < 5 ? 'человека' : 'человек') }}
        </div>
			@endif
			
			<form method="POST" action="{{ route('occurrences.waitlist.join', $occurrence) }}">
				@csrf
				
				@if($isClassic && !empty($allPositions))
				<div class="mb-2">
					<label class="form-label mb-1">Выберите позиции (можно несколько):</label>
					<div class="d-flex flex-wrap gap-2">
						@foreach($allPositions as $key => $label)
						<label class="checkbox-item mb-0">
							<input type="checkbox" name="positions[]" value="{{ $key }}">
							<div class="custom-checkbox"></div>
							<span>{{ $label }}</span>
						</label>
						@endforeach
					</div>
					<div class="text-muted small mt-1">
						Если не выбрать позиции — уведомим при освобождении любого места.
					</div>
				</div>
				@endif
				
				<button type="submit" class="btn btn-primary">🔔 Записаться в резерв</button>
			</form>
			@endif
			
			{{-- Список резерва для организатора --}}
			@if($isOrganizer && $waitlistCount > 0)
			@php
			$waitlistEntries = \App\Models\OccurrenceWaitlist::query()
            ->where('occurrence_id', $occurrence->id)
            ->with('user')
            ->orderBy('created_at')
            ->get();
			@endphp
			<div class="mt-3">
				<div class="fw-semibold small mb-1">Список резерва:</div>
				@foreach($waitlistEntries as $i => $entry)
				<div class="d-flex align-items-center gap-2 small py-1 border-bottom">
					<span class="text-muted">{{ $i + 1 }}.</span>
					<span>{{ $entry->user->name ?? '#'.$entry->user_id }}</span>
					@if(!empty($entry->positions))
					<span class="text-muted">({{ implode(', ', array_map('position_name', $entry->positions)) }})</span>
					@endif
					@if($entry->isNotificationActive())
					<span class="badge bg-warning text-dark ms-auto">⏳ уведомлён</span>
					@endif
				</div>
				@endforeach
			</div>
			@endif
		</div>
		
		@endif
		
		{{-- ===============================
		ПРИГЛАСИТЬ ИГРОКА (объединённый блок)
		=============================== --}}
		@if (auth()->check() && !$eventStarted && !$isTournament)
		@php
		$inviteSearchUrl = route('api.users.search');
		$inviteAction    = route('events.invite', ['event' => $event->id]);
		$hasGroupUi      = !empty($groupUi['enabled']);
		$gs              = $event->gameSettings ?? null;
		$subtype         = (string)($gs->subtype ?? '');
		$isPair          = $subtype === '2x2';
		$inviteLabel     = $isPair ? 'в пару' : 'в команду';
		@endphp
	
		<div class="ramka" id="invite-players-block" style="z-index:5;display:{{ $isRegistered ? '' : 'none' }}">
			<h2 class="-mt-05">Пригласить игрока</h2>
			
			@if(session('status') && str_contains(session('status'), 'Приглашение'))
			<div class="alert alert-success">{{ session('status') }}</div>
			@endif
			
			{{-- Радио-переключатель типа приглашения --}}
			@if($hasGroupUi && !empty($groupUi['registration']))
			<div class="d-flex gap-3 mb-2 form">
                <label class="radio-item">
					<input type="radio" name="invite_mode" value="game" id="invite-mode-game" checked>
					<div class="custom-radio"></div>
					<span>На игру</span>
				</label>
				<label class="radio-item">
					<input type="radio" name="invite_mode" value="group" id="invite-mode-group">
					<div class="custom-radio"></div>
					<span>{{ $isPair ? 'В пару' : 'В команду' }}</span>
				</label>
			</div>
			@endif
			
			{{-- === БЛОК: Пригласить на игру === --}}
			<div id="invite-game-block">
				<div class="text-muted small mb-2">
					Игрок получит уведомление с информацией о мероприятии и ссылкой для записи.
				</div>
				<form class="form w-100" method="POST" action="{{ $inviteAction }}" id="invite-player-form">
					@csrf
					<input type="hidden" name="occurrence_id" value="{{ $occurrence->id }}">
					<div class="ac-box">
						<div id="invite-selected-list" class="mb-2"></div>
						<div style="position:relative" class="mb-2" id="invite-ac-wrap">
							<input type="text" id="invite-ac-input" autocomplete="off" class="form-control"
							placeholder="Введите имя или email игрока…">
							<div id="invite-ac-dd" class="form-select-dropdown trainer_dd"></div>
						</div>
						<button type="submit" id="invite-submit-btn" class="btn btn-primary w-100" disabled>
							Отправить приглашения
						</button>
					</div>
				</form>
			</div>
			
			{{-- === БЛОК: Пригласить в пару/команду === --}}
			@if($hasGroupUi && !empty($groupUi['registration']))
			<div id="invite-group-block" style="display:none">
				
				{{-- Состав текущей группы --}}
				@if(!empty($groupUi['group_key']))
				<div class="mb-2">
					@php
					$groupLabel = $isPair ? 'Состав пары' : 'Состав команды';
					$leaveLabel = $isPair ? 'Выйти из пары' : 'Выйти из команды';
					@endphp
					<div class="fw-bold mb-2">{{ $groupLabel }}</div>
					@if(!empty($groupUi['group_members']) && $groupUi['group_members']->count())
					<ul class="mb-2">
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
					<div class="text-muted small mb-2">Пока {{ $isPair ? 'в паре только вы' : 'в команде только вы' }}.</div>
					@endif
					<form method="POST" action="{{ route('events.group.leave', ['event' => $event->id]) }}" class="mb-2">
						@csrf
						<button type="submit" class="btn btn-sm btn-outline-danger">{{ $leaveLabel }}</button>
					</form>
				</div>
				@else
				{{-- Нет группы — кнопка объединиться --}}
				<form method="POST" action="{{ route('events.group.create', ['event' => $event->id]) }}" class="mb-2 w-100">
					@csrf
					<button type="submit" class="btn btn-outline-primary w-100">Объединиться</button>
				</form>
				@endif
				
				{{-- Форма поиска и приглашения --}}
				<div class="text-muted small mb-2">{{ $isPair ? 'Пригласить игрока в пару' : 'Пригласить игрока в команду' }}</div>
				<form class="w-100 form" method="POST" action="{{ route('events.group.invite', ['event' => $event->id]) }}" id="group-invite-form">
					@csrf
					<input type="hidden" name="to_user_id" id="group-invite-user-id" value="">
					<div style="position:relative" class="mb-2" id="group-invite-ac-wrap">
						<input type="text" id="group-invite-ac-input" autocomplete="off" class="form-control"
						placeholder="Введите имя, фамилию или ник…">
						<div id="group-invite-ac-dd" class="form-select-dropdown trainer_dd"></div>
					</div>
					<div id="group-invite-selected" class="mb-2 text-muted small"></div>
					<button type="submit" id="group-invite-btn" class="btn btn-outline-primary w-100" disabled>
						{{ $isPair ? 'Пригласить в пару' : 'Пригласить в команду' }}
					</button>
				</form>
				
				{{-- Входящие приглашения --}}
				@if(!empty($groupUi['pending_invites']) && $groupUi['pending_invites']->count())
				<div class="mt-3">
					<div class="fw-bold mb-2">Входящие приглашения</div>
					@foreach($groupUi['pending_invites'] as $invite)
					<div class="border rounded p-3 mb-2">
						<div class="text-sm mb-2">
							Приглашение от <strong>{{ $invite->from_user_name ?: $invite->from_user_email ?: ('#'.$invite->from_user_id) }}</strong>
						</div>
						<div class="d-flex gap-2 flex-wrap">
							<form method="POST" action="{{ route('events.group.accept', ['event' => $event->id, 'invite' => $invite->id]) }}">
								@csrf
								<button type="submit" class="btn btn-primary btn-sm">Принять</button>
							</form>
							<form method="POST" action="{{ route('events.group.decline', ['event' => $event->id, 'invite' => $invite->id]) }}">
								@csrf
								<button type="submit" class="btn btn-outline-secondary btn-sm">Отклонить</button>
							</form>
						</div>
					</div>
					@endforeach
				</div>
				@endif
			</div>
			@endif
			
		</div>
		
		<script>
			(function(){
				// Переключатель режима приглашения
				var modeGame  = document.getElementById('invite-mode-game');
				var modeGroup = document.getElementById('invite-mode-group');
				var blockGame  = document.getElementById('invite-game-block');
				var blockGroup = document.getElementById('invite-group-block');
				
				if (modeGame && modeGroup) {
					modeGame.addEventListener('change', function() {
						blockGame.style.display  = '';
						blockGroup.style.display = 'none';
					});
					modeGroup.addEventListener('change', function() {
						blockGame.style.display  = 'none';
						blockGroup.style.display = '';
					});
				}
			})();
		</script>
		
		@endif
		{{-- ===============================
		СПИСОК ИГРОКОВ
		=============================== --}}
		@if($showParticipants)
		
		<div class="ramka">
			<h2 class="-mt-05">Записанные игроки</h2>	
			
			@if($event->format === 'tournament')
			@php
            $tournamentTeams = \App\Models\EventTeam::where('event_id', $event->id)
			->whereIn('status', ['ready','pending','submitted','confirmed','approved'])
			->with(['captain', 'members.user'])
			->get();
			@endphp
			@if($tournamentTeams->isEmpty())
            <div class="alert alert-info">Пока нет команд</div>
			@else
            @foreach($tournamentTeams as $tTeam)
			<div class="card mb-1" style="padding: 0.5rem 0.8rem">
				<div class="d-flex between fvc mb-1">
					<a href="{{ route('tournamentTeams.show', [$event, $tTeam]) }}" class="blink f-16 b-600">{{ $tTeam->name }}</a>
					<span class="f-16"><strong class="cd">{{ $tTeam->members->count() }}</strong> чел.</span>
				</div>
				<div class="f-15">
					@foreach($tTeam->members as $m)
					{{ trim(($m->user->last_name ?? '') . ' ' . ($m->user->first_name ?? '')) ?: $m->user->name ?? '?' }}@if(!$loop->last), @endif
					@endforeach
				</div>
			</div>
            @endforeach
			@endif
			@else
			<div id="players-list"></div>
			@endif
		</div>
		@endif
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
			
			// ===== Group invite autocomplete =====
			(function () {
				var input   = document.getElementById('group-invite-ac-input');
				var dd      = document.getElementById('group-invite-ac-dd');
				var hidden  = document.getElementById('group-invite-user-id');
				var selected = document.getElementById('group-invite-selected');
				var btn     = document.getElementById('group-invite-btn');
				var form    = document.getElementById('group-invite-form');
				var timer   = null;
				
				if (!input) return;
				
				function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
				function showDd() { dd.classList.add('form-select-dropdown--active'); }
				function hideDd() { dd.classList.remove('form-select-dropdown--active'); }
				
				function pick(id, label) {
					hidden.value = String(id);
					selected.textContent = '✅ Выбран: ' + label;
					btn.disabled = false;
					hideDd();
					dd.innerHTML = '';
					input.value = label;
				}
				
				function reset() {
					hidden.value = '';
					selected.textContent = '';
					btn.disabled = true;
				}
				
				function render(items) {
					dd.innerHTML = '';
					if (!items.length) {
						dd.innerHTML = '<div class="city-message">Ничего не найдено</div>';
						showDd();
						return;
					}
					items.forEach(function(item) {
						var div = document.createElement('div');
						div.className = 'trainer-item form-select-option';
						div.innerHTML = '<div class="text-sm">' + esc(item.label || item.name) + '</div>';
						div.addEventListener('click', function() {
							pick(item.id, item.label || item.name);
						});
						dd.appendChild(div);
					});
					showDd();
				}
				
				input.addEventListener('input', function() {
					clearTimeout(timer);
					reset();
					var q = input.value.trim();
					if (q.length < 2) { hideDd(); return; }
					
					dd.innerHTML = '<div class="city-message">Поиск…</div>';
					showDd();
					
					timer = setTimeout(function() {
						fetch('/api/users/search?q=' + encodeURIComponent(q), {
							headers: { 'Accept': 'application/json' },
							credentials: 'same-origin'
						})
						.then(function(r) { return r.json(); })
						.then(function(data) { render(data.items || []); })
						.catch(function() {
							dd.innerHTML = '<div class="city-message">Ошибка загрузки</div>';
							showDd();
						});
					}, 250);
				});
				
				document.addEventListener('click', function(e) {
					var wrap = document.getElementById('group-invite-ac-wrap');
					if (wrap && !wrap.contains(e.target)) hideDd();
				});
				
				input.addEventListener('keydown', function(e) {
					if (e.key === 'Escape') hideDd();
				});
				
				form.addEventListener('submit', function(e) {
					if (!hidden.value) {
						e.preventDefault();
						input.focus();
					}
				});
			})();
		</script>		