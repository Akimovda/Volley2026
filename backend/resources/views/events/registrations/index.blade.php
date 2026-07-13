@php
$posLabels = $availablePositions ?? [
'setter'   => __('events.positions.setter'),
'outside'  => __('events.positions.outside'),
'opposite' => __('events.positions.opposite'),
'middle'   => __('events.positions.middle_full'),
'libero'   => __('events.positions.libero'),
];

$isBeach   = ($direction ?? 'classic') === 'beach';
$isClassic = !$isBeach;
$hasPositions = $isClassic && count($posLabels) > 0;
$freeSlots = $freePositionSlots ?? []; // role => free_count (пусто = нет конфига слотов)

$eventTitle = (string)($event->title ?? __('events.fmt_game'));

$addrParts = array_filter([
$event->location?->city?->name,
$event->location?->address,
$event->location?->name,
]);
$address = $addrParts ? implode(', ', $addrParts) : '—';

$dateLine = '—';
if ($startsLocal) {
$dateLine = $startsLocal->translatedFormat('l, j F') . ' @ ' . $startsLocal->format('H:i');
if ($endsLocal) $dateLine .= ' - ' . $endsLocal->format('H:i');
}

$capacityLine = ($maxPlayers > 0) ? "{$freeCount}/{$maxPlayers}" : "—/—";

$statusText = function ($r) {
if (property_exists($r, 'cancelled_at') && $r->cancelled_at) return __('events.regs_status_cancelled');
if (property_exists($r, 'is_cancelled') && !is_null($r->is_cancelled) && (bool)$r->is_cancelled) return __('events.regs_status_cancelled');
if (property_exists($r, 'status') && (string)$r->status === 'cancelled') return __('events.regs_status_cancelled');
return __('events.regs_status_confirmed');
};

$isCancelled = function ($r) use ($statusText) {
return $statusText($r) === __('events.regs_status_cancelled');
};

$activeRegistrations = $registrations->filter(fn($r) => !$isCancelled($r))->values();
$searchUrl = route('api.users.search');

$actionLabel = fn(string $a) => match($a) {
'registered'                    => ['text' => __('events.regs_action_registered'),               'cls' => 'alert-success'],
'cancelled'                     => ['text' => __('events.regs_action_cancelled'),                 'cls' => 'alert-error'],
'restored'                      => ['text' => __('events.regs_action_restored'),                  'cls' => 'alert-info'],
'waitlist_joined'               => ['text' => __('events.regs_action_waitlist_joined'),           'cls' => 'alert-info'],
'waitlist_left'                 => ['text' => __('events.regs_action_waitlist_left'),             'cls' => 'alert-warning'],
'waitlist_auto_booked'          => ['text' => __('events.regs_action_waitlist_auto_booked'),      'cls' => 'alert-success'],
'waitlist_removed_by_organizer' => ['text' => __('events.regs_action_waitlist_removed_by_organizer'), 'cls' => 'alert-error'],
default                         => ['text' => $a,             'cls' => ''],
};

// Деталь позиции из meta (jsonb) — для waitlist-типов, где нет привязки к
// event_registrations.position (её может ещё/уже не быть).
$logPositionDetail = function ($log) {
    if (empty($log->meta)) return null;
    $meta = json_decode($log->meta, true);
    if (!is_array($meta)) return null;
    if (!empty($meta['position'])) return position_name($meta['position']);
    if (!empty($meta['positions']) && is_array($meta['positions'])) {
        return implode(', ', array_map('position_name', $meta['positions']));
    }
    return null;
};
@endphp

<x-voll-layout body_class="registrations-page">
	
    <x-slot name="title">{{ __('events.regs_title', ['title' => $eventTitle]) }}</x-slot>
    <x-slot name="h1">{{ __('events.regs_h1') }}</x-slot>
	<x-slot name="h2">{{ $eventTitle }}</x-slot>
    <x-slot name="t_description">
		@if($isBeach)
		{{ __('events.regs_beach') }}
		@else
		{{ __('events.regs_classic') }} @if(!empty($gameSubtype)) · {{ $gameSubtype }} @endif
		@endif	
	</x-slot>
	
    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('events.create.event_management') }}" itemprop="item"><span itemprop="name">{{ __('events.regs_breadcrumb_my') }}</span></a>
            <meta itemprop="position" content="2">
		</li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('events.event_management.occurrences', $event->id) }}" itemprop="item"><span itemprop="name">{{ $eventTitle }}</span></a>
            <meta itemprop="position" content="3">
		</li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <span itemprop="name">{{ __('events.regs_breadcrumb_reg') }}</span>
            <meta itemprop="position" content="4">
		</li>
	</x-slot>
	
    <x-slot name="d_description">
		
		<div class="d-flex flex-wrap gap-1 m-center">
			<div class="mt-2" data-aos-delay="250" data-aos="fade-up">
				<a href="{{ route('events.create.event_management') }}" class="btn">{{ __('events.go_to_mgmt') }}</a>
			</div>
			<div class="mt-2" data-aos-delay="350" data-aos="fade-up">
				<a href="{{ route('events.event_management.occurrences', $event->id) }}" class="btn btn-secondary">{{ __('events.go_to_dates') }}</a>
			</div>						
		</div>
		
	</x-slot>
	
	<x-slot name="style">
		<style>
			
			
			.badge { display: inline-block; padding: .3rem 1rem; border-radius: 2rem; font-size: 1.5rem; font-weight: 600; }
			.badge-ok  { background: rgba(76,175,80,.12); color: #2e7d32; }
			.badge-err { background: rgba(244,67,54,.12);  color: #c62828; }
			.summary-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 2rem; }
			@media (max-width: 768px) { .summary-grid { grid-template-columns: 1fr; } }
		
			
			
		</style>
	</x-slot>
	
	<div class="container form">
		
		{{-- Flash --}}
		@if(session('status'))
		<div class="ramka">
			<div class="alert alert-success">
				{{ session('status') }}
			</div>
		</div>
		@endif
		@if(session('error'))
		<div class="ramka">
			<div class="alert alert-error">
				{{ session('error') }}
			</div>
		</div>
		@endif
		
		{{-- Сводка --}}
		<div class="ramka">
			<div class="summary-grid">
				<div class="card text-center">
					<div>{{ __('events.regs_free_seats') }}</div>
					<div class="f-22 b-600 cd">{{ $capacityLine }}</div>
					<div>{{ __('events.regs_active_count', ['n' => $activeCount]) }}</div>
				</div>
				<div class="card text-center">
					<div>{{ __('events.regs_date_section') }}</div>
					<div class="b-600">{{ $dateLine }}</div>
					<div>{{ $tz }}</div>
				</div>
				<div class="card text-center">
					<div>{{ __('events.regs_place_section') }}</div>
					<div class="b-600">{{ $address }}</div>
				</div>
			</div>
		</div>
		
		{{-- Добавить игрока --}}
		<div class="ramka" style="z-index:6">
			<h2 class="-mt-05">{{ __('events.regs_add_player_title') }}</h2>
			<p>
				{!! __('events.regs_add_player_hint') !!}
			</p>
			<form method="POST"
			action="{{ route('events.registrations.add', ['event' => $event->id]) }}"
			id="add-player-form"
			class="form">
				@csrf
				@if(!empty($occurrenceId))
				<input type="hidden" name="occurrence_id" value="{{ $occurrenceId }}">
				@endif
				<div class="row">
					
					<div class="col-md-6">
						<div class="card">
							<label>{{ __('events.regs_player_label') }}</label>
							<div style="position:relative;" id="ac-wrap">
								<input type="text" id="ac-input" autocomplete="off" class="form-control"
								placeholder="{{ __('events.regs_player_ph') }}">
								<input type="hidden" name="user_id" id="ac-userid">
								<div id="ac-dd" class="form-select-dropdown trainer_dd"></div>
							</div>
							<div id="ac-selected" style="display:none;margin-top:.5rem;font-size:1.4rem;color:#4caf50;font-weight:600;"></div>
						</div>
					</div>
					@if($hasPositions)
					<div class="col-md-6">
						<div class="card">
							<label>{{ __('events.regs_position_required') }} <span style="color:red">*</span></label>
							<select name="position" required>
								<option value="" disabled selected>{{ __('events.regs_choose_position') }}</option>
								@foreach($posLabels as $k => $lbl)
								@php $slotFree = array_key_exists($k, $freeSlots) ? (int)$freeSlots[$k] : null; @endphp
								@if($slotFree === null || $slotFree > 0)
								<option value="{{ $k }}">{{ $lbl }}@if($slotFree !== null) {{ __('events.regs_pos_free', ['n' => $slotFree]) }}@endif</option>
								@endif
								@endforeach
							</select>
						</div>
					</div>
					@endif
				</div>
				<div class="text-center mt-2">
					<button type="submit" id="add-player-btn" disabled
					class="btn" style="opacity:.4;">
						{{ __('events.regs_btn_add') }}
					</button>
				</div>		
				
				
			</form>
		</div>
		
		{{-- Таблица игроков --}}
		<div class="ramka" style="z-index:7">
			<h2 class="-mt-05">{{ __('events.regs_section_list') }}</h2>
			
			@if($registrations->isEmpty())
			<div class="alert alert-info">
				{{ __('events.regs_empty') }}
			</div>
			@else
			<div class="table-scrollable mb-0">
				<div class="table-drag-indicator"></div>
				<table class="table">
					<thead>
						<tr>
							<th>{{ __('events.col_player') }}</th>
							<th>{{ __('events.col_phone') }}</th>
							@if($hasPositions)<th style="max-width:13rem">{{ __('events.col_position') }}</th>@endif
							@if($isBeach)<th>{{ __('events.col_group') }}</th>@endif
							<th>{{ __('events.col_status') }}</th>
							<th style="min-width:13rem">{{ __('events.col_date') }}</th>
							<th class="text-center">{{ __('events.col_actions') }}</th>
						</tr>
					</thead>
					<tbody>
						@foreach($registrations as $r)
						@php
						$fullName = trim(implode(' ', array_filter([
						$r->last_name  ?? '',
						$r->first_name ?? '',
						$r->patronymic ?? '',
						])));
						$name     = $fullName !== '' ? $fullName : ($r->name ?: ($r->email ?: ('User_' . $r->user_id)));
						$phone    = $r->phone ?: '—';
						$posKey   = $r->position ?: '';
						$posLabel = $posKey ? ($posLabels[$posKey] ?? $posKey) : '—';
						$st       = $statusText($r);
						$groupKey = $r->group_key ?: '';
						$isBot    = !empty($r->is_bot);
						@endphp
						<tr class="{{ $st === __('events.regs_status_cancelled') ? 'cancelled' : '' }}">
							<td>
								<div class="d-flex fvc gap-1">
									<a href="{{ route('users.show', $r->user_id) }}" class="b-600 blink">
										{{ $name }}
									</a>
									@if($isBot)<span title="{{ __('events.bot_label') }}">🤖</span>@endif
								</div>
								<p class="pt-1 f-16">#{{ $r->user_id }} · reg #{{ $r->id }}</p>
							</td>
							<td>{{ $phone }}</td>
							@if($hasPositions)
							<td>
								@if($st === __('events.regs_status_cancelled'))
								{{-- Отменённый: выбор позиции + восстановление в один шаг --}}
								<form class="d-flex gap-1 fvc" method="POST"
								action="{{ route('events.registrations.cancel', ['event' => $event->id, 'registration' => $r->id]) }}">
									@csrf @method('PATCH')
									<select name="position" class="{{ $loop->last ? 'dropdown-up' : '' }}">
										@foreach($posLabels as $k => $lbl)
										@php
										$slotFree = array_key_exists($k, $freeSlots) ? (int)$freeSlots[$k] : null;
										$isFull   = $slotFree !== null && $slotFree <= 0;
										@endphp
										<option value="{{ $k }}" @selected($posKey === $k) @disabled($isFull)>{{ $lbl }}@if($isFull) {{ __('events.regs_pos_full') }}@endif</option>
										@endforeach
									</select>
									<button class="btn btn-svg icon-copy" type="submit" title="{{ __('events.regs_btn_restore') }}"></button>
								</form>
								@else
								{{-- Активный: только смена позиции --}}
								<form class="d-flex gap-1 fvc" method="POST"
								action="{{ route('events.registrations.position', ['event' => $event->id, 'registration' => $r->id]) }}">
									@csrf @method('PATCH')
									<select name="position" class="{{ $loop->last ? 'dropdown-up' : '' }}">
										@if(!$posKey)
										<option value="" disabled selected>{{ __('events.regs_choose_position_short') }}</option>
										@endif
										@foreach($posLabels as $k => $lbl)
										@php
										$slotFree = array_key_exists($k, $freeSlots) ? (int)$freeSlots[$k] : null;
										$isFull   = $slotFree !== null && $slotFree <= 0 && $posKey !== $k;
										@endphp
										<option value="{{ $k }}" @selected($posKey === $k) @disabled($isFull)>{{ $lbl }}@if($isFull) {{ __('events.regs_pos_full') }}@endif</option>
										@endforeach
									</select>
									<button class="btn btn-secondary btn-small" type="submit">✓</button>
								</form>
								@endif
							</td>
							@endif
							@if($isBeach)
							<td>
								@if($groupKey)
								<div class="b-600">{{ $groupKey }}</div>
								@if($st !== __('events.regs_status_cancelled'))
								<form method="POST" action="{{ route('events.registrations.group.leave', ['event' => $event->id, 'registration' => $r->id]) }}">
									@csrf @method('PATCH')
									<button class="btn btn-small btn-secondary mt-05">{{ __('events.regs_remove_group') }}</button>
								</form>
								@endif
								@else
								@if($st !== __('events.regs_status_cancelled'))
								<form method="POST" action="{{ route('events.registrations.group.create', ['event' => $event->id, 'registration' => $r->id]) }}">
									@csrf
									<button class="btn btn-small btn-secondary">{{ __('events.regs_add_group') }}</button>
								</form>
								@endif
								@endif
							</td>
							@endif
							<td class="text-center">
								<span class="f-15 p-1 pt-05 pb-05 {{ $st === __('events.regs_status_cancelled') ? 'alert-error' : 'alert-success' }}">
									{{ $st }}
								</span>
							</td>
							<td>
								@php
								$dateTs = $st === __('events.regs_status_cancelled') && $r->cancelled_at
								? \Carbon\Carbon::parse($r->cancelled_at, 'UTC')->setTimezone($tz)
								: \Carbon\Carbon::parse($r->created_at, 'UTC')->setTimezone($tz);
								@endphp
								<div class="f-15">{{ $dateTs->format('d.m.Y') }}</div>
								<div class="f-15">{{ $dateTs->format('H:i') }}</div>
								@if($st === __('events.regs_status_cancelled'))<div class="text-center f-15 p-1 pt-05 pb-05 alert-error">{{ __('events.regs_status_cancelled') }}</div>
								@else<div class="text-center f-15 p-1 pt-05 pb-05 alert-success">{{ __('events.regs_status_registered') }}</div>@endif
							</td>

							
							<td>
								<div class="d-flex gap-1 text-center">
									@if($st !== __('events.regs_status_cancelled'))
									{{-- Активный: кнопка отмены --}}
									<form method="POST"
									action="{{ route('events.registrations.cancel', ['event' => $event->id, 'registration' => $r->id]) }}">
										@csrf @method('PATCH')
										<button class="btn btn-danger btn-svg icon-stop"
										title="{{ __('events.regs_btn_reject') }}">
										</button>
									</form>
									@elseif(!$hasPositions)
									{{-- Отменённый без позиций (пляжка): кнопка восстановления --}}
									<form method="POST"
									action="{{ route('events.registrations.cancel', ['event' => $event->id, 'registration' => $r->id]) }}">
										@csrf @method('PATCH')
										<button class="btn btn-svg icon-copy"
										title="{{ __('events.regs_btn_restore') }}">
										</button>
									</form>
									@endif
									{{-- Отменённый с позициями (классика): restore уже в колонке позиций --}}
									<form method="POST"
									action="{{ route('events.registrations.destroy', ['event' => $event->id, 'registration' => $r->id]) }}"
									onsubmit="return confirm({!! json_encode(__('events.regs_delete_full')) !!});">
										@csrf @method('DELETE')
										<button type="submit" 
										class="icon-delete btn-alert btn btn-danger btn-svg"
										data-title="{{ __('events.regs_delete_title') }}"
										data-text="{{ __('events.regs_delete_text') }}"
										data-icon="warning"
										data-confirm-text="{{ __('events.force_yes') }}"
										data-cancel-text="{{ __('events.cancel_no') }}">
										</button>	
									</form>
									</div>	 
									@if($hasOrgNote ?? false)
									
									<a data-src="#{{ $r->user_id }}-{{ $r->id }}" href="javascript:;" data-fancybox="" class="mt-1 btn btn-small {{ empty($r->organizer_note) ? 'btn-secondary' : '' }}">{{ __('events.col_comment') }}</a>
									
									<div class="form" id="{{ $r->user_id }}-{{ $r->id }}" style="max-width: 500px; display:none">
									<h3 class="title-h -mt-05">{{ __('events.col_comment') }}</h3>
										<textarea class="org-note-input" data-url="{{ route('events.registrations.note', ['event' => $event->id, 'registration' => $r->id]) }}"
										placeholder="{{ __('events.regs_org_note_ph') }}">{{ $r->organizer_note ?? '' }}</textarea>								
									</div>	
									@endif

								
								</td>
							</tr>
							@endforeach
						</tbody>
					</table>
				</div>
				@endif
			</div>
			
			{{-- Лист ожидания --}}
			@if($occurrenceId && isset($waitlistEntries))
			<div class="ramka">
				<h2 class="-mt-05">
					{{ __('events.waitlist_title') }}
					@if($waitlistEntries->isNotEmpty())
					<span style="font-size:1.4rem;font-weight:400;opacity:.6;margin-left:.5rem;">({{ $waitlistEntries->count() }})</span>
					@endif
				</h2>
				
				{{-- Форма добавления --}}
				<div style="margin-bottom:1.5rem;">
					<button type="button" id="wl-add-toggle" class="btn btn-secondary btn-small">
						+ {{ __('events.waitlist_add_btn') }}
					</button>
					<div id="wl-add-form-wrap" style="display:none;margin-top:1rem;">
						<form method="POST"
						action="{{ route('events.waitlist.management.store', $event->id) }}"
						id="wl-add-form" class="form">
							@csrf
							<input type="hidden" name="occurrence_id" value="{{ $occurrenceId }}">
							<div class="row">
								<div class="col-md-6">
									<div class="card" style="overflow:visible">
										<label>{{ __('events.regs_player_label') }}</label>
										<div style="position:relative;" id="wl-ac-wrap">
											<input type="text" id="wl-ac-input" autocomplete="off" class="form-control"
											placeholder="{{ __('events.regs_player_ph') }}">
											<input type="hidden" name="user_id" id="wl-ac-userid">
											<div id="wl-ac-dd" class="form-select-dropdown trainer_dd"></div>
										</div>
										<div id="wl-ac-selected" style="display:none;margin-top:.5rem;font-size:1.4rem;color:#4caf50;font-weight:600;"></div>
									</div>
								</div>
								@if($hasPositions)
								<div class="col-md-6">
									<div class="card">
										<label>{{ __('events.waitlist_positions_label') }}</label>
										<div class="d-flex flex-wrap gap-1" style="margin-top:.4rem;">
											@foreach($posLabels as $k => $lbl)
											@if($k === 'reserve') @continue @endif
											<label class="checkbox-item">
												<input type="checkbox" name="positions[]" value="{{ $k }}">
												<div class="custom-checkbox"></div>
												<span>{{ $lbl }}</span>
											</label>
											@endforeach
											@if(isset($posLabels['reserve']))
											<label class="checkbox-item">
												<input type="checkbox" name="positions[]" value="reserve">
												<div class="custom-checkbox"></div>
												<span>{{ $posLabels['reserve'] }}</span>
											</label>
											@endif
										</div>
										<p class="f-13" style="opacity:.6;margin-top:.4rem;">{{ __('events.waitlist_positions_hint') }}</p>
									</div>
								</div>
								@endif
							</div>
							<div class="text-center mt-2">
								<button type="submit" id="wl-add-btn" disabled class="btn" style="opacity:.4;">
									{{ __('events.waitlist_add_submit') }}
								</button>
							</div>
						</form>
					</div>
				</div>
				
				{{-- Таблица --}}
				@if($waitlistEntries->isEmpty())
				<div class="alert alert-info">{{ __('events.waitlist_empty') }}</div>
				@else
				<div class="table-scrollable mb-0">
					<div class="table-drag-indicator"></div>
					<table class="table" id="wl-table">
						<thead>
							<tr>
								<th style="width:3rem">#</th>
								<th>{{ __('events.col_player') }}</th>
								<th>{{ __('events.waitlist_col_positions') }}</th>
								<th style="white-space:nowrap">{{ __('events.col_date') }}</th>
								<th class="text-center" style="min-width:130px">{{ __('events.col_actions') }}</th>
							</tr>
						</thead>
						<tbody>
							@foreach($waitlistEntries as $i => $wl)
							@php
							$wlPositions = is_string($wl->positions)
							? (json_decode($wl->positions, true) ?: [])
							: (array)($wl->positions ?? []);
							$wlPosLabels = empty($wlPositions)
							? [__('events.waitlist_any_position')]
							: array_map(fn($p) => $posLabels[$p] ?? __('events.positions.'.$p, [], null) ?? $p, $wlPositions);
							$wlDate = \Carbon\Carbon::parse($wl->created_at, 'UTC')->setTimezone($tz);
							@endphp
							<tr data-wl-id="{{ $wl->id }}">
								<td class="f-15 b-600 cd">{{ $i + 1 }}</td>
								<td>
									<a href="{{ route('users.show', $wl->user_id) }}" class="b-600 blink">
										{{ $wl->full_name }}
									</a>
									<p class="pt-1 f-13" style="opacity:.6">#{{ $wl->user_id }}</p>
								</td>
								<td>
									{{-- Нормальный вид --}}
									<div id="wl-pos-view-{{ $wl->id }}">
										<span class="f-14">{{ implode(', ', $wlPosLabels) }}</span>
										<button type="button"
										class="btn btn-small btn-secondary"
										style="margin-left:.5rem;padding:.2rem .6rem;font-size:1.2rem;"
										onclick="wlToggleEdit({{ $wl->id }})">
											✏️
										</button>
									</div>
									{{-- Режим редактирования позиций --}}
									<div id="wl-pos-edit-{{ $wl->id }}" style="display:none;">
										<form class="form" method="POST"
										action="{{ route('events.waitlist.management.positions', [$event->id, $wl->id]) }}"
										class="d-flex flex-wrap gap-05 fvc">
											@csrf @method('PATCH')
											<input type="hidden" name="occurrence_id" value="{{ $occurrenceId }}">
											@if($hasPositions)
											@foreach($posLabels as $k => $lbl)
											@if($k === 'reserve') @continue @endif
											<label class="checkbox-item">
												<input type="checkbox" name="positions[]" value="{{ $k }}"
												{{ in_array($k, $wlPositions) ? 'checked' : '' }}>
												<div class="custom-checkbox"></div>
												<span>{{ $lbl }}</span>
											</label>
											@endforeach
											@if(isset($posLabels['reserve']))
											<label class="checkbox-item">
												<input type="checkbox" name="positions[]" value="reserve"
												{{ in_array('reserve', $wlPositions) ? 'checked' : '' }}>
												<div class="custom-checkbox"></div>
												<span>{{ $posLabels['reserve'] }}</span>
											</label>
											@endif
											@else
											<span class="f-13" style="opacity:.6">{{ __('events.waitlist_beach_pos') }}</span>
											@endif
											<button type="submit" class="btn btn-small" style="padding:.25rem .7rem;">✓</button>
											<button type="button" class="btn btn-small btn-secondary"
											onclick="wlToggleEdit({{ $wl->id }})">✕</button>
										</form>
									</div>
								</td>
								<td class="f-14" style="white-space:nowrap;opacity:.75;">
									<div>{{ $wlDate->format('d.m.Y') }}</div>
									<div class="f-13">{{ $wlDate->format('H:i') }}</div>
								</td>
								<td>
									<div class="d-flex gap-1 text-center fvc">
										{{-- Вверх --}}
										<form method="POST"
										action="{{ route('events.waitlist.management.move', [$event->id, $wl->id]) }}">
											@csrf
											<input type="hidden" name="direction" value="up">
											<input type="hidden" name="occurrence_id" value="{{ $occurrenceId }}">
											<button type="submit"
											class="btn btn-secondary btn-svg btn-small"
											title="{{ __('events.waitlist_move_up') }}"
											style="font-size:1.3rem;padding:.3rem .6rem;"
											{{ $i === 0 ? 'disabled' : '' }}>↑</button>
										</form>
										{{-- Вниз --}}
										<form method="POST"
										action="{{ route('events.waitlist.management.move', [$event->id, $wl->id]) }}">
											@csrf
											<input type="hidden" name="direction" value="down">
											<input type="hidden" name="occurrence_id" value="{{ $occurrenceId }}">
											<button type="submit"
											class="btn btn-secondary btn-svg btn-small"
											title="{{ __('events.waitlist_move_down') }}"
											style="font-size:1.3rem;padding:.3rem .6rem;"
											{{ $i === $waitlistEntries->count() - 1 ? 'disabled' : '' }}>↓</button>
										</form>
										{{-- Удалить --}}
										<form method="POST"
										action="{{ route('events.waitlist.management.destroy', [$event->id, $wl->id]) }}"
										onsubmit="return confirm({{ json_encode(__('events.waitlist_delete_confirm')) }});">
											@csrf @method('DELETE')
											<input type="hidden" name="occurrence_id" value="{{ $occurrenceId }}">
											<button type="submit"
											class="btn btn-danger btn-svg icon-delete btn-small btn-alert"
											data-title="{{ __('events.waitlist_delete_title') }}"
											data-text="{{ __('events.waitlist_delete_text') }}"
											data-icon="warning"
											data-confirm-text="{{ __('events.force_yes') }}"
											data-cancel-text="{{ __('events.cancel_no') }}"
											title="{{ __('events.waitlist_delete_title') }}">
											</button>
										</form>
									</div>
								</td>
							</tr>
							@endforeach
						</tbody>
					</table>
				</div>
				@endif
			</div>
			@endif
			
			{{-- Группы (только пляжка) --}}
			@if($isBeach)
			<div class="ramka" style="z-index:6">
				<h2 class="-mt-05">{{ __('events.regs_group_pair') }}</h2>
				<form method="POST"
				action="{{ route('events.registrations.group.invite', ['event' => $event->id]) }}"
				class="form">
					@csrf
					<div class="row row2">
						<div class="col-md-4">
							<label>{{ __('events.regs_player_first') }}</label>
							<select name="from_user_id" required>
								<option value="">{{ __('events.regs_choose_position_short') }}</option>
								@foreach($activeRegistrations as $r)
								<option value="{{ $r->user_id }}">#{{ $r->user_id }} — {{ trim(($r->last_name ?? '').' '.($r->first_name ?? '')) ?: ($r->name ?? '') }}</option>
								@endforeach
							</select>
						</div>
						<div class="col-md-4">
							<label>{{ __('events.regs_player_second') }}</label>
							<select name="to_user_id" required>
								<option value="">{{ __('events.regs_choose_position_short') }}</option>
								@foreach($activeRegistrations as $r)
								<option value="{{ $r->user_id }}">#{{ $r->user_id }} — {{ trim(($r->last_name ?? '').' '.($r->first_name ?? '')) ?: ($r->name ?? '') }}</option>
								@endforeach
							</select>
						</div>
						<div class="col-md-4 d-flex" style="align-items:flex-end;">
							<button class="btn btn-secondary w-100">{{ __('events.regs_invite_send') }}</button>
						</div>
					</div>
				</form>
			</div>
			@endif
			
			{{-- История действий --}}
			@if(($registrationLogs ?? collect())->isNotEmpty())
			<div class="ramka">
				<h2 class="-mt-05">{{ __('events.regs_history') }}</h2>
				<p class="f-14" style="opacity:.6">{{ __('events.regs_history_hint', ['n' => $registrationLogs->count()]) }}</p>
				<div class="table-scrollable">
					<div class="table-drag-indicator"></div>
					<table class="table">
						<thead>
							<tr>
								<th>{{ __('events.col_when') }}</th>
								<th>{{ __('events.col_player') }}</th>
								<th>{{ __('events.col_action') }}</th>
								<th>{{ __('events.col_by_whom') }}</th>
							</tr>
						</thead>
						<tbody>
							@foreach($registrationLogs as $log)
							@php
							$logTs      = \Carbon\Carbon::parse($log->created_at, 'UTC')->setTimezone($tz);
							$badge      = $actionLabel($log->action);
							$isSelf     = $log->actor_id && $log->actor_id == $log->user_id;
							$posDetail  = $logPositionDetail($log);
							@endphp
							<tr>
								<td class="f-14" style="white-space:nowrap">
									<div>{{ $logTs->format('d.m.Y') }}</div>
									<div class="f-13" style="opacity:.65">{{ $logTs->format('H:i:s') }}</div>
								</td>
								<td>
									<a href="{{ route('users.show', $log->user_id) }}" class="blink b-600">{{ $log->user_name ?: ('User #'.$log->user_id) }}</a>
									@if($log->registration_id)
									<div class="f-13" style="opacity:.5">reg #{{ $log->registration_id }}</div>
									@endif
								</td>
								<td>
									<span class="f-14 p-1 pt-05 pb-05 {{ $badge['cls'] }}">{{ $badge['text'] }}</span>
									@if($posDetail)
									<div class="f-13" style="opacity:.6">{{ $posDetail }}</div>
									@endif
								</td>
								<td class="f-14">
									@if($log->actor_id === null)
									<span style="opacity:.6">{{ __('events.regs_actor_system') }}</span>
									@elseif($isSelf)
									<span style="opacity:.6">{{ __('events.regs_actor_self') }}</span>
									@else
									<a href="{{ route('users.show', $log->actor_id) }}" class="blink">{{ $log->actor_name ?: ('User #'.$log->actor_id) }}</a>
									@endif
								</td>
							</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			</div>
			@endif
			
			{{-- Экспорт --}}
			@php
			$exportOccurrenceParam = ($occurrenceId ?? null) ? 'occurrence=' . $occurrenceId . '&' : '';
			$exportBasePdf = route('events.registrations.pdf', ['event' => $event->id]) . '?' . $exportOccurrenceParam;
			$exportBaseTxt = route('events.registrations.txt', ['event' => $event->id]) . '?' . $exportOccurrenceParam;
			@endphp
			<div class="ramka">
				<h2 class="-mt-05">{{ __('events.regs_export_title') }}</h2>
				<p class="mb-2">{{ __('events.regs_export_hint') }}</p>
				<div class="form d-flex gap-2 flex-wrap mb-3">
					<label class="checkbox-item">
						<input type="checkbox" id="exp-field-name" checked>
						<div class="custom-checkbox"></div>
						<span>{{ __('events.regs_export_field_name') }}</span>
					</label>
					<label class="checkbox-item">
						<input type="checkbox" id="exp-field-phone" checked>
						<div class="custom-checkbox"></div>
						<span>{{ __('events.regs_export_field_phone') }}</span>
					</label>
					<label class="checkbox-item">
						<input type="checkbox" id="exp-field-position" checked>
						<div class="custom-checkbox"></div>
						<span>{{ __('events.regs_export_field_pos') }}</span>
					</label>
				</div>
				<div class="d-flex gap-2 justify-content-center flex-wrap">
					<a id="exp-pdf-link"
					href="{{ $exportBasePdf }}fields=name,phone,position"
					class="btn btn-secondary">
						{{ __('events.regs_export_pdf') }}
					</a>
					<a id="exp-txt-link"
					href="{{ $exportBaseTxt }}fields=name,phone,position"
					class="btn btn-secondary">
						{{ __('events.regs_export_txt') }}
					</a>
				</div>
			</div>
			
		</div>
		
		<x-slot name="script">
		<script src="/assets/fas.js"></script>
			<script>
				(function () {
					var input     = document.getElementById('ac-input');
					var dd        = document.getElementById('ac-dd');
					var hidden    = document.getElementById('ac-userid');
					var sel       = document.getElementById('ac-selected');
					var addBtn    = document.getElementById('add-player-btn');
					var posSelect = document.querySelector('#add-player-form select[name="position"]');
					var timer     = null;
					var searchUrl = '{{ $searchUrl }}';
					
					if (!input) return;
					
					function showDd() { dd.classList.add('form-select-dropdown--active'); }
					function hideDd() { dd.classList.remove('form-select-dropdown--active'); }
					
					function esc(s) {
						return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
					}
					
					function updateBtn() {
						var userOk = !!hidden.value;
						var posOk  = !posSelect || posSelect.value !== '';
						addBtn.disabled = !(userOk && posOk);
						addBtn.style.opacity = (userOk && posOk) ? '1' : '.4';
					}
					
					function clearSel() {
						hidden.value = '';
						updateBtn();
						sel.style.display = 'none';
					}
					
					function setSel(id, label) {
						hidden.value = id;
						updateBtn();
						sel.style.display = 'block';
						sel.textContent = '✅ ' + label;
						dd.innerHTML = '';
						hideDd();
						input.value = label.replace(/^🤖\s*/, '');
					}
					
					function render(items) {
						dd.innerHTML = '';
						if (!items.length) {
							dd.innerHTML = '<div class="city-message">' + @json(__('events.regs_search_no_results')) + '</div>';
							showDd();
							return;
						}
						items.forEach(function(item) {
							var div = document.createElement('div');
							div.className = 'trainer-item form-select-option';
							div.innerHTML =
							'<span class="b-500">' + (item.is_bot ? '🤖 ' : '') + esc(item.label || item.name) + '</span>' +
							(item.meta ? '<span class="f-13" style="opacity:.5;">' + esc(item.meta) + '</span>' : '');
							div.addEventListener('click', function() {
								setSel(item.id, (item.is_bot ? '🤖 ' : '') + (item.label || item.name));
							});
							dd.appendChild(div);
						});
						showDd();
					}
					
					function search(q) {
						fetch(searchUrl + '?q=' + encodeURIComponent(q), {
							headers: { 'Accept': 'application/json' },
							credentials: 'same-origin'
						})
						.then(function(r) { return r.json(); })
						.then(function(data) { render(data.items || []); })
						.catch(function() {
							dd.innerHTML = '<div class="city-message">' + @json(__('events.regs_search_error')) + '</div>';
							showDd();
						});
					}
					
					input.addEventListener('input', function() {
						clearSel();
						clearTimeout(timer);
						var q = input.value.trim();
						if (q.length < 2) { hideDd(); return; }
						dd.innerHTML = '<div class="city-message">' + @json(__('events.regs_search_searching')) + '</div>';
						showDd();
						timer = setTimeout(function() { search(q); }, 250);
					});
					
					input.addEventListener('keydown', function(e) {
						if (e.key === 'Escape') hideDd();
					});
					
					document.addEventListener('click', function(e) {
						if (!document.getElementById('ac-wrap').contains(e.target)) hideDd();
					});
					
					if (posSelect) {
						posSelect.addEventListener('change', updateBtn);
					}
					
					document.getElementById('add-player-form').addEventListener('submit', function(e) {
						if (!hidden.value) {
							e.preventDefault();
							input.focus();
							return;
						}
						if (posSelect && !posSelect.value) {
							e.preventDefault();
							posSelect.focus();
						}
					});
				})();
				
				// --- Обновление ссылок экспорта при смене чекбоксов ---
				(function() {
					var basePdf = @json($exportBasePdf);
					var baseTxt = @json($exportBaseTxt);
					var cbName  = document.getElementById('exp-field-name');
					var cbPhone = document.getElementById('exp-field-phone');
					var cbPos   = document.getElementById('exp-field-position');
					var lPdf    = document.getElementById('exp-pdf-link');
					var lTxt    = document.getElementById('exp-txt-link');
					
					if (!cbName || !lPdf) return;
					
					function updateLinks() {
						var f = [];
						if (cbName.checked)  f.push('name');
						if (cbPhone.checked) f.push('phone');
						if (cbPos.checked)   f.push('position');
						var qs = 'fields=' + (f.length ? f.join(',') : 'name');
						lPdf.href = basePdf + qs;
						lTxt.href = baseTxt + qs;
					}
					
					[cbName, cbPhone, cbPos].forEach(function(cb) {
						cb.addEventListener('change', updateLinks);
					});
					
					// Android WebView не обрабатывает Content-Disposition: attachment —
					// открываем через системный браузер (Chrome), который умеет скачивать файлы
					if (window.Capacitor && window.Capacitor.getPlatform() === 'android') {
						[lPdf, lTxt].forEach(function(link) {
							link.addEventListener('click', function(e) {
								e.preventDefault();
								window.open(this.href, '_system');
							});
						});
					}
				})();
				
				// --- Лист ожидания: toggle формы добавления ---
				(function () {
					var toggleBtn  = document.getElementById('wl-add-toggle');
					var formWrap   = document.getElementById('wl-add-form-wrap');
					var input      = document.getElementById('wl-ac-input');
					var dd         = document.getElementById('wl-ac-dd');
					var hidden     = document.getElementById('wl-ac-userid');
					var sel        = document.getElementById('wl-ac-selected');
					var addBtn     = document.getElementById('wl-add-btn');
					var searchUrl  = '{{ $searchUrl }}';
					var timer      = null;
					
					if (!toggleBtn) return;
					
					toggleBtn.addEventListener('click', function () {
						var visible = formWrap.style.display !== 'none';
						formWrap.style.display = visible ? 'none' : 'block';
						toggleBtn.textContent  = visible
						? '+ {{ __('events.waitlist_add_btn') }}'
						: '✕ {{ __('events.waitlist_add_cancel') }}';
					});
					
					if (!input) return;
					
					function showDd() { dd.classList.add('form-select-dropdown--active'); }
					function hideDd() { dd.classList.remove('form-select-dropdown--active'); }
					function esc(s)   { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
					
					function updateBtn() { addBtn.disabled = !hidden.value; addBtn.style.opacity = hidden.value ? '1' : '.4'; }
					
					function setSel(id, label) {
						hidden.value       = id;
						sel.style.display  = 'block';
						sel.textContent    = '✅ ' + label;
						dd.innerHTML       = '';
						hideDd();
						input.value = label.replace(/^🤖\s*/, '');
						updateBtn();
					}
					
					function render(items) {
						dd.innerHTML = '';
						if (!items.length) {
							dd.innerHTML = '<div class="city-message">' + @json(__('events.regs_search_no_results')) + '</div>';
							showDd(); return;
						}
						items.forEach(function (item) {
							var div = document.createElement('div');
							div.className = 'trainer-item form-select-option';
							div.innerHTML = '<span class="b-500">' + (item.is_bot ? '🤖 ' : '') + esc(item.label || item.name) + '</span>' +
							(item.meta ? '<span class="f-13" style="opacity:.5;">' + esc(item.meta) + '</span>' : '');
							div.addEventListener('click', function () {
								setSel(item.id, (item.is_bot ? '🤖 ' : '') + (item.label || item.name));
							});
							dd.appendChild(div);
						});
						showDd();
					}
					
					function search(q) {
						fetch(searchUrl + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
						.then(function (r) { return r.json(); })
						.then(function (d) { render(d.items || []); })
						.catch(function () {
							dd.innerHTML = '<div class="city-message">' + @json(__('events.regs_search_error')) + '</div>';
							showDd();
						});
					}
					
					input.addEventListener('input', function () {
						hidden.value = ''; updateBtn(); sel.style.display = 'none';
						clearTimeout(timer);
						var q = input.value.trim();
						if (q.length < 2) { hideDd(); return; }
						dd.innerHTML = '<div class="city-message">' + @json(__('events.regs_search_searching')) + '</div>';
						showDd();
						timer = setTimeout(function () { search(q); }, 250);
					});
					input.addEventListener('keydown', function (e) { if (e.key === 'Escape') hideDd(); });
					document.addEventListener('click', function (e) {
						var wrap = document.getElementById('wl-ac-wrap');
						if (wrap && !wrap.contains(e.target)) hideDd();
					});
				})();
				
				// --- Лист ожидания: редактирование позиций inline ---
				function wlToggleEdit(id) {
					var view = document.getElementById('wl-pos-view-' + id);
					var edit = document.getElementById('wl-pos-edit-' + id);
					if (!view || !edit) return;
					var editing = edit.style.display !== 'none';
					view.style.display = editing ? '' : 'none';
					edit.style.display = editing ? 'none' : '';
				}
				
				// --- Авторасширение textarea ---
				function autoResize(el) { el.style.height = '2.4rem'; if (el.scrollHeight > el.offsetHeight) el.style.height = el.scrollHeight + 'px'; }
				$(document).on('input', '.org-note-input', function() { autoResize(this); });
				$('.org-note-input').each(function() { autoResize(this); });
				
				// --- Автосохранение комментария организатора (blur) ---
				$(document).on('blur', '.org-note-input', function() {
					var ta  = $(this);
					var url = ta.data('url');
					var val = ta.val();
					$.ajax({
						url: url,
						method: 'POST',
						headers: {
							'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
							'X-HTTP-Method-Override': 'PATCH',
							'Accept': 'application/json',
						},
						data: { organizer_note: val, _method: 'PATCH' },
						success: function() {
							ta.css('border-color', '#10b981');
							setTimeout(function() { ta.css('border-color', ''); }, 1500);
						},
						error: function() {
							ta.css('border-color', '#ef4444');
						}
					});
				});
			</script>
		</x-slot>
		
	</x-voll-layout>
