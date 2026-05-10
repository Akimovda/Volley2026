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
							<img src="{{ $media->getUrl('event_thumb') }}" alt="{{ __('events.sp_event_photo_alt', ['n' => $index + 1]) }}" loading="lazy">
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
		<img src="/img/{{ $event->direction === 'beach' ? 'beach.webp' : 'classic.webp' }}" alt="{{ __('events.sp_event_photo_alt_simple') }}">
	</div>
	@endif
    @else
	{{-- Нет фото в галерее --}}
	<div class="hover-image">
		<img src="/img/{{ $event->direction === 'beach' ? 'beach.webp' : 'classic.webp' }}" alt="{{ __('events.sp_event_photo_alt_simple') }}">
	</div>
    @endif
</div>



<div class="ramka" style="z-index:5">
    <h2 class="-mt-05">{{ __('events.sp_h2') }}</h2>
	
    @php
	$maxPlayers    = $occurrence->effectiveMaxPlayers();
	$reserveMax    = (int) ($event->gameSettings?->reserve_players_max ?? 0);
	$totalCapacity = $maxPlayers + $reserveMax;

	$teamStatusMap = [
		'approved'        => ['label' => __('events.sp_status_approved'),       'color' => '#166534', 'bg' => '#f0fdf4'],
		'confirmed'       => ['label' => __('events.sp_status_approved'),       'color' => '#166534', 'bg' => '#f0fdf4'],
		'ready'           => ['label' => __('events.sp_status_ready'),             'color' => '#166534', 'bg' => '#f0fdf4'],
		'submitted'       => ['label' => __('events.sp_status_submitted'),      'color' => '#1e40af', 'bg' => '#dbeafe'],
		'draft'           => ['label' => __('events.sp_status_draft'),        'color' => '#6b7280', 'bg' => '#f3f4f6'],
		'pending'         => ['label' => __('events.sp_status_pending'),            'color' => '#92400e', 'bg' => '#fff7e6'],
		'pending_members' => ['label' => __('events.sp_status_pending_members'), 'color' => '#92400e', 'bg' => '#fff7e6'],
		'incomplete'      => ['label' => __('events.sp_status_incomplete'),           'color' => '#9f1239', 'bg' => '#fff1f2'],
		'cancelled'       => ['label' => __('events.sp_status_cancelled'),           'color' => '#9f1239', 'bg' => '#fff1f2'],
		'rejected'        => ['label' => __('events.sp_status_rejected'),          'color' => '#9f1239', 'bg' => '#fff1f2'],
	];
    @endphp
	
    {{-- ===============================
    СТАТИСТИКА ИГРОКОВ
    =============================== --}}
    @if($event->format === 'tournament')
	@php
	$teamsMax = $event->tournament_teams_count ?: ($event->tournamentSetting?->teams_count ?? 0);
	$teamSize = $event->tournamentSetting?->team_size_min ?? 2;
	// pending_members — неполные команды (досрочная заявка) тоже считаются как «занятые» слоты
	$teamsRegistered = \App\Models\EventTeam::where('event_id', $event->id)
	->where(fn($q) => $q->where('occurrence_id', $occurrence->id)->orWhereNull('occurrence_id'))
	->whereIn('status', ['ready','pending','pending_members','submitted','confirmed','approved'])
	->count();
	$playersRegistered = \App\Models\EventTeamMember::whereHas('team', fn($q) => $q->where('event_id', $event->id)->where(fn($q2) => $q2->where('occurrence_id', $occurrence->id)->orWhereNull('occurrence_id'))->whereIn('status', ['ready','pending','pending_members','submitted','confirmed','approved']))
	->where('confirmation_status', 'confirmed')
	->count();
	$playersMax = $teamsMax * $teamSize;
	@endphp
	<div class="text-muted small mb-1">
		{{ __('events.sp_teams_count') }} <strong>{{ $teamsRegistered }}</strong> / {{ $teamsMax }}
	</div>
	<div class="text-muted small mb-1">
		{{ __('events.sp_free_spots') }} <strong>{{ max(0, $playersMax - $playersRegistered) }}</strong> / {{ $playersMax }}
	</div>
	@if(auth()->check() && (auth()->user()->role === 'admin' || (int)($event->organizer_id ?? 0) === auth()->id()))
	<div class="mt-1 mb-1">
		<a href="{{ route('tournament.setup', $event) }}" class="btn btn-primary btn-sm">{{ __('events.sp_setup_btn') }}</a>
	</div>
	@endif
    @elseif(!is_null($registeredTotal))
	<div class="text-muted small mb-1">
		{{ __('events.sp_free_spots_label') }}
		<span id="players-count">{{ $totalCapacity > 0 ? max(0, $totalCapacity - $registeredTotal) : $registeredTotal }}</span>
		@if($totalCapacity)
		/ {{ $totalCapacity }}
		@endif
	</div>
    @endif

    @php
    $pCount = ($event->format === 'tournament') ? ($teamsRegistered ?? 0) : ($registeredTotal ?? 0);
    $pMax   = ($event->format === 'tournament') ? ($teamsMax ?? 0) : ($totalCapacity ?? 0);
    $percent = ($pMax > 0) ? min(100, ($pCount / $pMax) * 100) : 0;
	
	$barClass = 'bg-danger';
	if ($percent >= 75) {
	$barClass = 'bg-success';
	} elseif ($percent >= 40) {
	$barClass = 'bg-warning';
	}
    @endphp
	
	
	
    @if($pMax > 0)
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
		{{ __('events.sp_status_no_quorum') }}
	</div>
	
    @elseif($occurrence->isFinished())
	<div class="alert alert-info">
		{{ __('events.sp_status_finished') }}
	</div>
	
    @elseif($occurrence->isRunning())
	<div class="alert alert-warning">
		{{ __('events.sp_status_in_progress') }}
	</div>
	
    @else
	<div id="join-registration-block">
		
		{{-- ===============================
		УЖЕ ЗАПИСАН
		=============================== --}}
		@if ($isRegistered)
		<div class="alert alert-success">
			{{ __('events.sp_already_registered') }}
			@if($userPosition)
			<div><span class="f-16">{{ __('events.sp_position_label') }}</span> {{ $userPosition }}</div>
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
			{{ __('events.sp_confirm_subscription') }}
			{{ \Carbon\Carbon::parse($occurrence->starts_at)->subHours(12)->format('d.m H:i') }}
			{{ __('events.sp_confirm_or_cancel') }}
		</div>
		<form method="POST" action="{{ route('registrations.confirm', $myReg->id) }}">
			@csrf
			<button type="submit" class="btn w-100 mt-1">{{ __('events.sp_btn_confirm') }}</button>
		</form>
		@elseif($myReg && $myReg->subscription_id && $myReg->auto_booked && $myReg->confirmed_at)
		<div class="alert alert-success mt-1">{{ __('events.sp_confirmed') }}</div>
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
			{{ __('events.sp_pay_pending') }} <strong>{{ number_format($myPayment->amount_minor/100, 2) }} ₽</strong>
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
		<a href="{{ $payLinkUrl }}" target="_blank" class="btn w-100 mt-1">{{ __('events.sp_pay_btn_link') }}</a>
		@endif
		@if(!$myPayment->user_confirmed)
		<form method="POST" action="{{ route('payments.user_confirm', $myPayment->id) }}">
			@csrf
			<button type="submit" class="btn btn-secondary w-100 mt-1">{{ __('events.sp_pay_btn_paid') }}</button>
		</form>
		@else
		<div class="alert alert-info mt-1">{{ __('events.sp_pay_awaiting_admin') }}</div>
		@endif
	    @elseif($myPayment->method === 'yoomoney' && $myPayment->yoomoney_confirmation_url)
		<div class="alert alert-warning mt-1">{{ __('events.sp_pay_seat_reserved', ['time' => $myPayment->expires_at?->format('H:i')]) }}</div>
		<a href="{{ $myPayment->yoomoney_confirmation_url }}" target="_blank" class="btn w-100 mt-1">{{ __('events.sp_pay_btn_yoomoney') }}</a>
	    @endif
		@elseif($myPayment && $myPayment->status === 'paid')
	    <div class="alert alert-success mt-1">{{ __('events.sp_pay_paid') }} {{ number_format($myPayment->amount_minor/100, 2) }} ₽</div>
		@endif
		{{-- ======= КОНЕЦ БЛОКА ОПЛАТЫ ======= --}}
		
		@if ($cancel->allowed)
		<form method="POST" action="{{ $leaveAction }}">
			@csrf
			@method('DELETE')
			<button type="submit" class="mt-1 btn btn-danger w-100">
				{{ __('events.sp_btn_cancel_reg') }}
			</button>
		</form>
		@else
		<div class="alert alert-warning mt-1">
			{{ $cancel->message ?? $cancel->errors[0] ?? __('events.sp_cancel_default') }}
		</div>
		@endif
		
		{{-- ===============================
		GUARD ЗАПРЕЩАЕТ
		=============================== --}}
		@elseif (! $join->allowed)
		{{-- 
		<button class="btn btn-primary w-100" disabled>
			{{ __('events.sp_btn_register') }}
		</button>
		--}}
		@if (!empty($join->errors))
		<div class="alert alert-info mt-1">
			{{ $join->errors[0] }}
			@if (!empty($join->meta['profile_required']))
			<div class="mt-1">
				<a href="{{ route('profile.complete') }}" class="btn btn-small btn-primary">{{ __('events.sp_btn_complete_profile') }}</a>
			</div>
			@endif
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
			{{ __('events.sp_team_mode_hint') }}
		</div>
		@php
        $myTeams = $myTournamentTeams ?? collect();
		@endphp
		@if($myTeams->count() > 0)
        @foreach($myTeams as $myTeam)
        @php
        $myTeamStatusInfo = $teamStatusMap[$myTeam->status] ?? ['label' => $myTeam->status, 'color' => '#6b7280', 'bg' => '#f3f4f6'];
        @endphp
        <div class="card mt-1">
            <div class="b-600 cd">{{ $myTeam->name }}</div>
            <div class="f-16">
                {{ __('events.sp_team_status') }}
                <span style="display:inline-block;padding:1px 8px;border-radius:10px;font-size:13px;font-weight:600;background:{{ $myTeamStatusInfo['bg'] }};color:{{ $myTeamStatusInfo['color'] }}">{{ $myTeamStatusInfo['label'] }}</span>
                {{ __('events.sp_team_players') }} {{ $myTeam->members->count() }}
            </div>
            <a href="{{ route('tournamentTeams.show', [$event, $myTeam]) }}" class="btn btn-small btn-secondary mt-1">
                {{ __('events.sp_btn_open_team') }}
			</a>
		</div>
        @endforeach
		@endif
		@if(auth()->check() && $myTeams->count() === 0)
		@php
		$tournamentTeamsFull = isset($teamsMax, $teamsRegistered) && $teamsMax > 0 && $teamsRegistered >= $teamsMax;
		@endphp
		@if($tournamentTeamsFull)
		<div class="alert alert-warning mt-1">
			{!! __('events.sp_team_full_warn') !!}
		</div>
		@endif
		<form method="POST" action="{{ route('tournamentTeams.store', $event) }}" class="form mt-1">
			@csrf
			<input type="text" name="name" class="form-control mb-1" placeholder="{{ __('events.sp_team_name_ph') }}">
			<input type="hidden" name="occurrence_id" value="{{ $occurrence->id }}">
			<input type="hidden" name="team_kind" value="{{ $isTeamBeach ? 'beach_pair' : 'classic_team' }}">
			@if($isTeamClassic)
			<select name="captain_position_code" class="form-select mb-1" required>
				<option value="">{{ __('events.sp_position_in_team_ph') }}</option>
				<option value="setter">{{ __('events.positions.setter') }}</option>
				<option value="outside">{{ __('events.positions.outside') }}</option>
				<option value="opposite">{{ __('events.positions.opposite') }}</option>
				<option value="middle">{{ __('profile.positions.middle_full') }}</option>
				<option value="libero">{{ __('events.positions.libero') }}</option>
			</select>
			@endif
			<button type="submit" class="btn mt-1 {{ $tournamentTeamsFull ? 'btn-secondary' : 'btn-primary' }} w-100">
				{{ $tournamentTeamsFull ? __('events.sp_btn_apply_reserve') : __('events.sp_btn_create_team') }}
			</button>
		</form>
		@endif
		
		@elseif (empty($freePositions))
		<div class="alert alert-warning">
			{{ __('events.sp_no_spots') }}
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
		// Занятые основные позиции классики — нужны для disabled-кнопок и ссылки на waitlist
		$btnDirection    = (string)($event->direction ?? 'classic');
		$btnOccupied = [];
		if ($btnDirection === 'classic' && $event->gameSettings) {
		    $btnAllSlots = app(\App\Services\EventRoleSlotService::class)->getSlots($event);
		    $btnFreeKeys = collect($freePositions)->pluck('key')->toArray();
		    foreach ($btnAllSlots as $btnSlot) {
		        if ($btnSlot->role !== 'reserve' && !in_array($btnSlot->role, $btnFreeKeys)) {
		            $btnOccupied[$btnSlot->role] = position_name($btnSlot->role);
		        }
		    }
		}
		@endphp
		@if($activeSubscription)
		<div class="alert alert-success mt-1 mb-2">
			{!! __('events.sp_subscription_label') !!} {{ $activeSubscription->template->name }}
			{!! __('events.show_pl_subscription_visits_left', ['count' => $activeSubscription->visits_remaining]) !!}
		</div>
		@elseif($activeCoupon)
		<div class="alert alert-warning mt-1 mb-2">
			{!! __('events.show_pl_coupon_label') !!} {{ $activeCoupon->template->name }}
			{!! __('events.show_pl_coupon_discount', ['pct' => $activeCoupon->getDiscountPct()]) !!}
		</div>
		@endif
		@php $hasOnlyReserve = count($freePositions) === 1 && ($freePositions[0]['key'] ?? '') === 'reserve'; @endphp
		@if($hasOnlyReserve)
		<div class="alert alert-warning mb-1">
			{{ __('events.show_pl_only_reserve') }}
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
			class="d-flex between btn {{ $key === 'reserve' ? 'btn-secondary' : 'btn-primary' }} w-100 mb-1"
			{{ $free <= 0 ? 'disabled' : '' }}>
				{{ position_name($key) }}
				<span>
					<span class="pl-1 pr-1 f-11">{{ __('events.show_pl_free_label') }}</span> {{ $free }}
				</span>
			</button>
		</form>
		@endforeach

		{{-- Занятые позиции: показываем disabled + ссылка на waitlist --}}
		@if(!empty($btnOccupied) && auth()->check())
		@foreach ($btnOccupied as $occKey => $occLabel)
		<div class="d-flex between w-100 mb-1" style="padding:8px 14px;border-radius:8px;border:1px solid var(--bs-border-color,#dee2e6);opacity:.55;cursor:default">
			{{ $occLabel }}
			<span class="f-11">{{ __('events.show_pl_occupied_label') }}</span>
		</div>
		@endforeach
		<div class="text-muted small mt-1 mb-1">
			<a href="#waitlist-section">{{ __('events.show_pl_waitlist_pos_taken') }}</a>
		</div>
		@endif

		@if(!$hasOnlyReserve && empty($btnOccupied))
		<div class="text-muted small">
			{!! __('events.show_pl_pick_position') !!}
		</div>
		@endif
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

$maxPlayers       = $occurrence->effectiveMaxPlayers();
$reserveMaxFull   = (int) ($event->gameSettings?->reserve_players_max ?? 0);
$totalCapacityFull = $maxPlayers + $reserveMaxFull;
$registered       = $registered_total ?? 0;
$isFull           = $totalCapacityFull > 0 && $registered >= $totalCapacityFull;

$direction     = (string)($event->direction ?? 'classic');
$isBeach       = $direction === 'beach';
$isClassic     = $direction === 'classic';

// Все позиции и занятые для чекбоксов waitlist (getSlots кешируется — лишнего SQL нет)
$gs           = $event->gameSettings ?? null;
$allPositions = [];
$occupiedPositions = [];
if ($isClassic && $gs) {
    $wlSlots    = app(\App\Services\EventRoleSlotService::class)->getSlots($event);
    $wlFreeKeys = collect($freePositions)->pluck('key')->toArray();
    foreach ($wlSlots as $wlSlot) {
        if ($wlSlot->role !== 'reserve') {
            $allPositions[$wlSlot->role] = position_name($wlSlot->role);
            if (!in_array($wlSlot->role, $wlFreeKeys)) {
                $occupiedPositions[$wlSlot->role] = position_name($wlSlot->role);
            }
        }
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

// Показываем блок waitlist при полном заполнении ИЛИ когда заняты классические позиции
$showWaitlist = !$isTournament && !$eventStarted && auth()->check() && (
    $isFull || ($isClassic && !empty($occupiedPositions))
);
@endphp

@if($showWaitlist)
<div class="ramka" id="waitlist-section">
    <h2 class="-mt-05">{{ __('events.show_pl_waitlist_h2') }}</h2>

    @if(!auth()->check())
	<div class="text-muted small">{{ __('events.show_pl_waitlist_login') }}</div>

    @elseif($myWaitlist)
	{{-- Уже в резерве --}}
	<div class="alert alert-success">
		{{ __('events.show_pl_waitlist_in') }}
		@if($isClassic && !empty($myWaitlist->positions))
		<div class="mt-1 small">
			{{ __('events.show_pl_waitlist_positions') }} <strong>{{ implode(', ', array_map('position_name', $myWaitlist->positions)) }}</strong>
		</div>
		@endif
		@if($myWaitlist->notification_expires_at && $myWaitlist->notification_expires_at->isFuture())
		<div class="mt-1 small text-warning">
			{{ __('events.show_pl_waitlist_reserved_until', ['time' => $myWaitlist->notification_expires_at->setTimezone($userTz ?? 'UTC')->format('H:i')]) }}
		</div>
		@endif
	</div>
	
	<form method="POST" action="{{ route('occurrences.waitlist.leave', $occurrence) }}" class="mt-1">
		@csrf
		@method('DELETE')
		<button type="submit" class="btn btn-outline-secondary">{{ __('events.show_pl_waitlist_leave') }}</button>
	</form>
	
    @else
	{{-- Форма записи в резерв --}}
	<div class="text-muted small mb-2">
		@if($isBeach)
			{{ __('events.show_pl_waitlist_full_beach') }}
		@elseif($isFull)
			{{ __('events.show_pl_waitlist_full') }}
		@else
			{{ __('events.show_pl_waitlist_pos_taken') }}
		@endif
	</div>

	@if($waitlistCount > 0)
	<div class="text-muted small mb-2">
		{!! trans_choice('events.show_pl_waitlist_count', $waitlistCount, ['count' => $waitlistCount]) !!}
	</div>
	@endif

	<form method="POST" action="{{ route('occurrences.waitlist.join', $occurrence) }}">
		@csrf

		@if($isClassic)
		@php
		// При $isFull все заняты → все позиции. Иначе — только занятые.
		$wlCheckboxPositions = !empty($occupiedPositions) ? $occupiedPositions : $allPositions;
		@endphp
		@if(!empty($wlCheckboxPositions))
		<div class="mb-2 form">
			<label>{{ __('events.show_pl_waitlist_pick_pos') }}</label>
			<div class="d-flex flex-wrap gap-2">
				@foreach($wlCheckboxPositions as $wlKey => $wlLabel)
				<label class="checkbox-item mb-0">
					<input type="checkbox" name="positions[]" value="{{ $wlKey }}">
					<div class="custom-checkbox"></div>
					<span>{{ $wlLabel }}</span>
				</label>
				@endforeach
			</div>
			<div class="text-muted small mt-1">
				{{ __('events.show_pl_waitlist_no_pick') }}
			</div>
		</div>
		@endif
		@endif

		<button type="submit" class="btn btn-primary">{{ __('events.show_pl_waitlist_join') }}</button>
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
				<div class="b-500 mb-1">{{ __('events.show_pl_waitlist_list') }}</div>
				@foreach($waitlistEntries as $i => $entry)
				<div class="d-flex align-items-center gap-1 small py-1 border-bottom">
					<span class="text-muted">{{ $i + 1 }}.</span>
					<span>{{ $entry->user->name ?? '#'.$entry->user_id }}</span>
					@if(!empty($entry->positions))
					<span class="text-muted">({{ implode(', ', array_map('position_name', $entry->positions)) }})</span>
					@endif
					@if($entry->isNotificationActive())
					<span class="badge bg-warning text-dark ms-auto">{{ __('events.show_pl_waitlist_notified') }}</span>
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
		$inviteLabel     = $isPair ? __('events.show_pl_invite_mode_pair') : __('events.show_pl_invite_mode_team');
		@endphp
	
		<div class="ramka" id="invite-players-block" style="z-index:5;display:{{ $isRegistered ? '' : 'none' }}">
			<h2 class="-mt-05">{{ __('events.show_pl_invite_h2') }}</h2>

			{{-- Радио-переключатель типа приглашения --}}
			@if($hasGroupUi && !empty($groupUi['registration']))
			<div class="d-flex gap-3 mb-2 form">
                <label class="radio-item">
					<input type="radio" name="invite_mode" value="game" id="invite-mode-game" checked>
					<div class="custom-radio"></div>
					<span>{{ __('events.show_pl_invite_mode_game') }}</span>
				</label>
				<label class="radio-item">
					<input type="radio" name="invite_mode" value="group" id="invite-mode-group">
					<div class="custom-radio"></div>
					<span>{{ $isPair ? __('events.show_pl_invite_mode_pair') : __('events.show_pl_invite_mode_team') }}</span>
				</label>
			</div>
			@endif
			
			{{-- === БЛОК: Пригласить на игру === --}}
			<div id="invite-game-block">
				<div class="text-muted small mb-2">
					{{ __('events.show_pl_invite_game_hint') }}
				</div>
				<form class="form w-100" method="POST" action="{{ $inviteAction }}" id="invite-player-form">
					@csrf
					<input type="hidden" name="occurrence_id" value="{{ $occurrence->id }}">
					<div class="ac-box">
						<div id="invite-selected-list" class="mb-2"></div>
						<div style="position:relative" class="mb-2" id="invite-ac-wrap">
							<input type="text" id="invite-ac-input" autocomplete="off" class="form-control"
							placeholder="{{ __('events.show_pl_invite_search_ph') }}">
							<div id="invite-ac-dd" class="form-select-dropdown trainer_dd"></div>
						</div>
						<button type="submit" id="invite-submit-btn" class="btn btn-primary w-100" disabled>
							{{ __('events.show_pl_invite_send') }}
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
					$groupLabel = $isPair ? __('events.show_pl_group_pair_lineup') : __('events.show_pl_group_team_lineup');
					$leaveLabel = $isPair ? __('events.show_pl_group_pair_leave') : __('events.show_pl_group_team_leave');
					@endphp
					<div class="fw-bold mb-2">{{ $groupLabel }}</div>
					@if(!empty($groupUi['group_members']) && $groupUi['group_members']->count())
					<ul class="mb-2">
						@foreach($groupUi['group_members'] as $member)
						<li>
							{{ $member->name ?: $member->email ?: ('#'.$member->user_id) }}
							@if((int)$member->user_id === (int)auth()->id())
							<strong>{{ __('events.show_pl_group_you') }}</strong>
							@endif
						</li>
						@endforeach
					</ul>
					@else
					<div class="text-muted small mb-2">{{ $isPair ? __('events.show_pl_group_pair_alone') : __('events.show_pl_group_team_alone') }}</div>
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
					<button type="submit" class="btn btn-outline-primary w-100">{{ __('events.show_pl_group_unite') }}</button>
				</form>
				@endif
				
				{{-- Форма поиска и приглашения --}}
				<div class="text-muted small mb-2">{{ $isPair ? __('events.show_pl_group_invite_pair') : __('events.show_pl_group_invite_team') }}</div>
				<form class="w-100 form" method="POST" action="{{ route('events.group.invite', ['event' => $event->id]) }}" id="group-invite-form">
					@csrf
					<input type="hidden" name="to_user_id" id="group-invite-user-id" value="">
					<div style="position:relative" class="mb-2" id="group-invite-ac-wrap">
						<input type="text" id="group-invite-ac-input" autocomplete="off" class="form-control"
						placeholder="{{ __('events.show_pl_group_search_ph') }}">
						<div id="group-invite-ac-dd" class="form-select-dropdown trainer_dd"></div>
					</div>
					<div id="group-invite-selected" class="mb-2 text-muted small"></div>
					<button type="submit" id="group-invite-btn" class="btn btn-outline-primary w-100" disabled>
						{{ $isPair ? __('events.show_pl_group_invite_pair_btn') : __('events.show_pl_group_invite_team_btn') }}
					</button>
				</form>
				
				{{-- Входящие приглашения --}}
				@if(!empty($groupUi['pending_invites']) && $groupUi['pending_invites']->count())
				<div class="mt-3">
					<div class="fw-bold mb-2">{{ __('events.show_pl_group_inbox') }}</div>
					@foreach($groupUi['pending_invites'] as $invite)
					<div class="border rounded p-3 mb-2">
						<div class="text-sm mb-2">
							{!! __('events.show_pl_group_inv_from', ['name' => '<strong>'.e($invite->from_user_name ?: $invite->from_user_email ?: ('#'.$invite->from_user_id)).'</strong>']) !!}
						</div>
						<div class="d-flex gap-2 flex-wrap">
							<form method="POST" action="{{ route('events.group.accept', ['event' => $event->id, 'invite' => $invite->id]) }}">
								@csrf
								<button type="submit" class="btn btn-primary btn-sm">{{ __('events.show_pl_group_accept') }}</button>
							</form>
							<form method="POST" action="{{ route('events.group.decline', ['event' => $event->id, 'invite' => $invite->id]) }}">
								@csrf
								<button type="submit" class="btn btn-outline-secondary btn-sm">{{ __('events.show_pl_group_decline') }}</button>
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
			<h2 class="-mt-05">{{ $event->format === 'tournament' ? __('events.show_pl_list_h2_tournament') : __('events.show_pl_list_h2') }}</h2>
			
			@if($event->format === 'tournament')
			@php
            $tournamentTeams = \App\Models\EventTeam::where('event_id', $event->id)
			->where(fn($q) => $q->where('occurrence_id', $occurrence->id)->orWhereNull('occurrence_id'))
			->whereIn('status', ['ready','pending_members','draft','submitted','confirmed','approved','incomplete'])
			->with(['captain', 'members.user'])
			->orderByRaw("CASE WHEN status IN ('ready','submitted','confirmed','approved') THEN 0 ELSE 1 END")
			->get();

			// Определяем команду текущего пользователя (confirmed/joined)
			$myTeamIdOnEvent = null;
			$myPendingRequestTeamIds = collect();
			$myPendingInvitesByTeam  = collect(); // pending-приглашения: event_team_id → invite
			if (auth()->check()) {
				$myMemberships = \App\Models\EventTeamMember::whereHas('team', fn($q) => $q->where('event_id', $event->id))
					->where('user_id', auth()->id())
					->whereIn('confirmation_status', ['confirmed', 'joined', 'requested'])
					->get();
				$myTeamIdOnEvent = $myMemberships->whereIn('confirmation_status', ['confirmed','joined'])->first()?->event_team_id;
				$myPendingRequestTeamIds = $myMemberships->where('confirmation_status', 'requested')->pluck('event_team_id');

				// Pending-приглашения в команды этого мероприятия
				$myPendingInvitesByTeam = \App\Models\EventTeamInvite::query()
					->where('event_id', $event->id)
					->where('invited_user_id', auth()->id())
					->where('status', 'pending')
					->get()
					->keyBy('event_team_id');
			}
			@endphp
			@if($tournamentTeams->isEmpty())
            <div class="alert alert-info">{{ __('events.show_pl_no_teams') }}</div>
			@else
            @foreach($tournamentTeams as $tTeam)
			@php
				$confirmedMembers = $tTeam->members->where('confirmation_status', 'confirmed')->sortBy(fn($m) => $m->role_code === 'captain' ? 0 : 1);
				$isBeachPair = $tTeam->team_kind === 'beach_pair';
				$hasVacancy  = $isBeachPair && $confirmedMembers->count() < 2;
				$iMyTeam     = (int)$myTeamIdOnEvent === (int)$tTeam->id;
				$iAlreadyRequested = $myPendingRequestTeamIds->contains($tTeam->id);
				$myInvite    = $myPendingInvitesByTeam->get($tTeam->id); // pending-приглашение именно в эту команду
				$cancelUntil = $occurrence->effectiveCancelSelfUntil();
				$joinOpen    = !$cancelUntil || now('UTC')->lessThanOrEqualTo($cancelUntil);
				$canJoin     = $hasVacancy && auth()->check() && !$iMyTeam && !$iAlreadyRequested && !$myTeamIdOnEvent && $joinOpen && !$myInvite;
				$statusInfo = $teamStatusMap[$tTeam->status] ?? ['label' => $tTeam->status, 'color' => '#6b7280', 'bg' => '#f3f4f6'];
			@endphp
			<div class="card mb-1" style="padding: 0.5rem 0.8rem{{ $iMyTeam ? ';border:1.5px solid #2563eb' : '' }}">
				<div class="d-flex between fvc mb-05">
					<a href="{{ route('tournamentTeams.show', [$event, $tTeam]) }}" class="blink f-16 b-600">{{ $tTeam->name }}</a>
					<div class="d-flex fvc" style="gap:0.4rem;flex-wrap:wrap;justify-content:flex-end">
						@if($iMyTeam)
						<span class="f-12 b-600" style="color:#2563eb">{{ __('events.show_pl_my_team') }}</span>
						@endif
						<span style="display:inline-block;padding:1px 8px;border-radius:10px;font-size:11px;font-weight:600;background:{{ $statusInfo['bg'] }};color:{{ $statusInfo['color'] }}">{{ $statusInfo['label'] }}</span>
						@if($hasVacancy && !$iMyTeam)
						<span class="f-12 b-600" style="color:#f97316">{{ __('events.show_pl_seek_partner') }}</span>
						@endif
					</div>
				</div>
				@foreach($confirmedMembers as $m)
				@php
					$mUser   = $m->user;
					$mLevel  = (int)($mUser->beach_level ?? $mUser->classic_level ?? 0);
					$mColor  = $mLevel > 0 ? level_color($mLevel) : '#aaaaaa';
					$mName   = trim(($mUser->last_name ?? '') . ' ' . ($mUser->first_name ?? '')) ?: ($mUser->name ?? '?');
				@endphp
				<div class="d-flex fvc" style="gap:0.5rem;margin-bottom:0.3rem;">
					<span class="f-13 text-muted" style="width:16px;text-align:right;flex-shrink:0;">{{ $loop->iteration }}.</span>
					<a href="{{ route('users.show', $mUser) }}">
						<img src="{{ $mUser->profile_photo_url }}" alt="" style="width:34px;height:34px;border-radius:50%;object-fit:cover;flex-shrink:0;">
					</a>
					<span style="width:10px;height:10px;border-radius:50%;background:{{ $mColor }};display:inline-block;flex-shrink:0;border:1px solid rgba(0,0,0,.15);"></span>
					<a href="{{ route('users.show', $mUser) }}" class="blink f-15">{{ $mName }}</a>
				</div>
				@endforeach
				{{-- Вакантный слот --}}
				@if($hasVacancy)
				<div class="d-flex fvc" style="gap:0.5rem;margin-bottom:0.3rem;">
					<span class="f-13 text-muted" style="width:16px;text-align:right;flex-shrink:0;">2.</span>
					<div style="width:34px;height:34px;border-radius:50%;background:var(--bg2,#f5f5f5);flex-shrink:0;display:flex;align-items:center;justify-content:center;border:2px dashed #ccc;font-size:1.8rem;color:#aaa;">?</div>
					<span style="width:10px;height:10px;border-radius:50%;background:#ccc;display:inline-block;flex-shrink:0;"></span>
					@if($myInvite)
					{{-- Игроку отправлено персональное приглашение → кнопка принятия --}}
					<a href="{{ route('tournamentTeamInvites.show', ['token' => $myInvite->token]) }}"
					   class="btn btn-primary btn-small" style="font-size:1.2rem;padding:3px 10px">
						✅ {{ __('events.show_pl_accept_invite') }}
					</a>
					@elseif($canJoin)
					<form method="POST" action="{{ route('tournamentTeams.joinRequest', [$event, $tTeam]) }}">
						@csrf
						<button class="btn btn-small" style="font-size:1.2rem;padding:3px 10px">{{ __('events.show_pl_join_pair') }}</button>
					</form>
					@elseif($iAlreadyRequested)
					<span class="f-13" style="color:#f97316;font-style:italic">{{ __('events.show_pl_request_sent') }}</span>
					@elseif($iMyTeam)
					<span class="f-13" style="opacity:.4;font-style:italic">{{ __('events.show_pl_my_pair') }}</span>
					@else
					<span class="f-13" style="opacity:.4;font-style:italic">{{ __('events.show_pl_seat_free') }}</span>
					@endif
				</div>
				@endif
			</div>
            @endforeach
			@if($errors->has('join'))
			<div class="alert alert-danger mt-1">{{ $errors->first('join') }}</div>
			@endif
			@endif
			@else
			<div id="players-list"></div>
			@if($reserveMax > 0)
			<div id="reserve-players-section" style="display:none;margin-top:1rem;">
				<div class="text-muted small b-600 mb-05">{{ __('events.show_pl_reserve_players') }}</div>
				<div id="reserve-players-list"></div>
			</div>
			@endif
			@endif
		</div>
		@endif

		{{-- ===============================
		РЕЗЕРВ ТУРНИРА (лига)
		=============================== --}}
		@if($event->format === 'tournament' && $event->season_id)
		@php
		$_leagueForReserve = \App\Models\TournamentLeague::whereHas('season', fn($q) => $q->where('id', $event->season_id))
			->first();
		$_reserveLeagueTeams = $_leagueForReserve
			? $_leagueForReserve->reserveTeams()->with('team.captain', 'team.members.user', 'user')->orderBy('reserve_position')->get()
			: collect();
		@endphp
		@if($_reserveLeagueTeams->count() > 0)
		<div class="ramka">
			<h2 class="-mt-05">{{ __('events.show_pl_reserve_league_h2') }}</h2>
			@foreach($_reserveLeagueTeams as $_lt)
			<div class="card mb-1" style="padding: 0.5rem 0.8rem">
				<div class="d-flex between fvc">
					<div>
						@if($_lt->team)
						<span class="f-16 b-600">{{ $loop->iteration }}. {{ $_lt->team->name }}</span>
						@else
						<span class="f-16 b-600">{{ $loop->iteration }}. {{ trim(($_lt->user?->last_name ?? '') . ' ' . ($_lt->user?->first_name ?? '')) ?: ($_lt->user?->name ?? '—') }}</span>
						@endif
						@if($_lt->status === 'pending_confirmation')
						<span class="f-13 text-warning ms-2">{{ __('events.show_pl_pending_confirm') }}</span>
						@endif
					</div>
					<span class="f-13 text-muted">#{{ $_lt->reserve_position }}</span>
				</div>
				@if($_lt->team?->members->count())
				<div class="f-14 text-muted mt-05">
					@foreach($_lt->team->members as $_m)
					{{ trim(($_m->user->last_name ?? '') . ' ' . ($_m->user->first_name ?? '')) ?: ($_m->user->name ?? '?') }}@if($_m !== $_lt->team->members->last()), @endif
					@endforeach
				</div>
				@endif
			</div>
			@endforeach
		</div>
		@endif
		@endif
		@php
		$showPlJsI18n = [
			'searching' => __('events.show_pl_js_searching'),
			'not_found' => __('events.show_pl_js_not_found'),
			'error' => __('events.show_pl_js_error'),
			'send_invites' => __('events.show_pl_js_send_invites'),
			'selected_prefix' => __('events.show_pl_js_selected_prefix'),
		];
		@endphp
		<script>
			const i18n = @json($showPlJsI18n);
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
					? i18n.send_invites + ' (' + count + ')'
					: i18n.send_invites;
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
						dd.innerHTML = '<div class="city-message">' + i18n.not_found + '</div>';
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

						var botBadge = item.is_bot ? '<span style="display:inline-block;padding:1px 8px;border-radius:10px;font-size:11px;font-weight:600;background:#fef3c7;color:#92400e;margin-left:.5rem">🤖 бот</span>' : '';
						div.innerHTML = '<div class="text-sm text-gray-900">' + esc(item.label || item.name) + botBadge + '</div>';
						
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
					
					dd.innerHTML = '<div class="city-message">' + i18n.searching + '</div>';
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
							dd.innerHTML = '<div class="city-message">' + i18n.error + '</div>';
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
					selected.textContent = i18n.selected_prefix + ' ' + label;
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
						dd.innerHTML = '<div class="city-message">' + i18n.not_found + '</div>';
						showDd();
						return;
					}
					items.forEach(function(item) {
						var div = document.createElement('div');
						div.className = 'trainer-item form-select-option';
						var botBadge = item.is_bot ? '<span style="display:inline-block;padding:1px 8px;border-radius:10px;font-size:11px;font-weight:600;background:#fef3c7;color:#92400e;margin-left:.5rem">🤖 бот</span>' : '';
						div.innerHTML = '<div class="text-sm">' + esc(item.label || item.name) + botBadge + '</div>';
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
					
					dd.innerHTML = '<div class="city-message">' + i18n.searching + '</div>';
					showDd();
					
					timer = setTimeout(function() {
						fetch('/api/users/search?exclude_bots=1&q=' + encodeURIComponent(q), {
							headers: { 'Accept': 'application/json' },
							credentials: 'same-origin'
						})
						.then(function(r) { return r.json(); })
						.then(function(data) { render(data.items || []); })
						.catch(function() {
							dd.innerHTML = '<div class="city-message">' + i18n.error + '</div>';
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