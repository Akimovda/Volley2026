{{-- resources/views/events/event_management_occurrences.blade.php --}}
@php
$tz = $event->timezone ?: 'UTC';
$isAdmin = (auth()->user()?->role ?? null) === 'admin';

$fmtLocation = function ($event) {
$parts = array_filter([
$event->location?->name,
$event->location?->city?->name,
$event->location?->address,
]);

return $parts ? implode(', ', $parts) : '—';
};

$fmtOccurrenceDt = function ($occ, $tz) {
$s = $occ->starts_at ? \Carbon\Carbon::parse($occ->starts_at, 'UTC')->setTimezone($tz) : null;
if (!$s) return '—';

$e = null;
if (!empty($occ->duration_sec)) {
$e = $s->copy()->addSeconds((int)$occ->duration_sec);
}

return $s->format('d.m.Y') . ' · ' . $s->format('H:i') . ($e ? '–' . $e->format('H:i') : '') . ' (' . $tz . ')';
};

$seatMeta = function ($occ) {
$max = (int)($occ->max_players ?? $occ->event?->max_players ?? 0);
$registered = (int)($occ->active_regs ?? 0);

if (!(bool)($occ->event?->allow_registration ?? false)) {
return ['label' => 'Регистрация выключена', 'registered' => $registered];
}

if ($max <= 0) {
return ['label' => 'Мест: —', 'registered' => $registered];
	}
	
	$free = max(0, $max - $registered);
	
	return [
	'label' => "Мест: {$free}/{$max}",
	'registered' => $registered,
	];
    };
	@endphp
	
	<x-voll-layout>
		<x-slot name="title">Повторы мероприятия</x-slot>
		<x-slot name="h1">Повторы мероприятия</x-slot>
		<x-slot name="h2">{{ $event->title }}</x-slot>
		
		
		<x-slot name="t_description">
			{{ strtoupper((string)$event->direction) }} · {{ (string)$event->format }}
			<div class="f-16 pt-1">
				📍 {{ $fmtLocation($event) }}
			</div>		
		</x-slot>
		
		<x-slot name="d_description">
			<div class="d-flex flex-wrap gap-1 m-center">
				<div class="mt-2" data-aos-delay="250" data-aos="fade-up">
					<a href="{{ route('events.event_management.edit', ['event' => (int)$event->id]) }}"
					class="btn">
						Изменить серию
					</a>
				</div>
				<div class="mt-2" data-aos-delay="350" data-aos="fade-up">
					<a href="{{ url('/events/' . (int)$event->id) }}"
					class="btn btn-secondary">
						Открыть мероприятие
					</a>
				</div>						
			</div>
		</x-slot>
		<x-slot name="breadcrumbs">
			<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
				<a href="{{ route('events.create.event_management') }}" itemprop="item">
					<span itemprop="name">Управление мероприятиями</span>
				</a>
				<meta itemprop="position" content="2">
			</li>
			<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
				<a href="{{ url('/events/' . (int)$event->id) }}" itemprop="item">
					<span itemprop="name">#{{ (int)$event->id }} {{ $event->title }}</span>
				</a>
				<meta itemprop="position" content="3">
			</li>
		</x-slot>
		
		<div class="container">
			@if (session('status'))
            <div class="ramka">
                <div class="alert alert-success">{{ session('status') }}</div>
			</div>
			@endif
			
			@if (session('error'))
            <div class="ramka">
                <div class="alert alert-error">{{ session('error') }}</div>
			</div>
			@endif
			
			
			
			<div class="ramka">
				@if(empty($occurrences) || $occurrences->isEmpty())
                <div class="p-6 text-sm text-gray-600">
                    Повторов пока нет.
				</div>
				@else
                <div class="form table-scrollable mb-0">
                    <div class="table-drag-indicator"></div>
					
                    <table class="table">
                        <colgroup>
                            <col style="width:7rem" />
                            <col style="width:24%" />
                            <col style="width:18%" />
                            <col style="width:18%" />
                            <col style="width:18rem" />
						</colgroup>
						
                        <thead class="bg-gray-50 text-gray-600">
                            <tr>
                                <th>ID</th>
                                <th>Дата и время</th>
                                <th>Статус</th>
                                <th>Регистрация</th>
                                <th>Действия</th>
							</tr>
						</thead>
						
                        <tbody>
                            @php
                            $showBotToggle = ($event->format ?? 'game') !== 'tournament'
							&& ($event->registration_type ?? 'individual') !== 'team'
							&& (bool)($event->allow_registration ?? false);
							@endphp
							@foreach($occurrences as $occ)
							@php
							$seat = $seatMeta($occ);
							$isCancelled = !empty($occ->cancelled_at);
							$occBotRaw = $occ->getRawOriginal('bot_assistant_enabled');
							$effectiveBot = $occBotRaw === null
							? (bool)($event->bot_assistant_enabled ?? false)
							: (bool)$occBotRaw;
							@endphp
							
							<tr>
								<td class="align-top nowrap">
									#{{ (int)$occ->id }}
								</td>
								
								<td class="align-top f-16">
									{{ $fmtOccurrenceDt($occ, $tz) }}
								</td>
								
								<td class="text-center f-16">
									@if($isCancelled)
									<span class="f-16 p-1 pt-05 pb-05 alert-error">Отменено</span>
									@else
									<span class="f-16 p-1 pt-05 pb-05 alert-success">Активно</span>
									@endif
								</td>
								
								<td class="align-top f-16">
									<div class="b-600">{{ $seat['label'] }}</div>
									<div>Записано: <strong>{{ (int)$seat['registered'] }}</strong></div>
								</td>
								
								
								
								<td class="text-center f-0">
									<div class="d-flex gap-1 text-center">
										@if($showBotToggle)
										<button type="button"
										data-url="{{ route('events.occurrences.toggle-bot', ['event' => (int)$event->id, 'occurrence' => (int)$occ->id]) }}"
										data-enabled="{{ $effectiveBot ? '1' : '0' }}"
										title="{{ $effectiveBot ? 'Бот включён (нажми чтобы выключить)' : 'Бот выключен (нажми чтобы включить)' }}"
										class="{{ $effectiveBot ? 'occ-bot-toggle btn-danger btn btn-svg icon-stop' : 'occ-bot-toggle btn-success btn btn-svg icon-play' }}"
										></button>
										@endif
										@if($event->format === 'tournament')
										<a href="{{ route('tournament.setup', $event) }}"
										class="btn btn-small btn-secondary"
										title="Управление турниром">
											🏆
										</a>
                                        @else
										<a href="{{ route('events.registrations.index', ['event' => (int)$event->id, 'occurrence' => (int)$occ->id]) }}"
										class="btn btn-svg icon-users"
										title="Список участников"></a>
                                        @endif
										<a href="{{ route('events.occurrences.edit', ['event' => (int)$event->id, 'occurrence' => (int)$occ->id]) }}"
										class="btn btn-svg icon-edit"
										title="Редактировать дату"></a>
										
										<form method="POST"
										action="{{ route('occurrences.destroy', ['occurrence' => (int)$occ->id]) }}"
										class="d-inline-block">
											@csrf
											@method('DELETE')
											<input type="hidden" name="delete_mode" value="single">
											
											<button type="submit"
											class="btn-alert btn btn-danger btn-svg icon-stop"
											data-title="Отменить повтор?"
											data-text="Будет отменена только эта дата. История сохранится."
											data-confirm-text="Да, отменить"
											data-cancel-text="Отмена">
											</button>
										</form>
										
										@if($isAdmin)
										<form method="POST"
										action="{{ route('occurrences.destroy', ['occurrence' => (int)$occ->id]) }}"
										class="d-inline-block">
											@csrf
											@method('DELETE')
											<input type="hidden" name="delete_mode" value="force">
											
											<button type="submit"
											class="btn-alert btn btn-danger btn-svg icon-delete"
											data-title="Удалить повтор навсегда?"
											data-text="Будет удалена только эта дата без возможности восстановления."
											data-confirm-text="Да, удалить"
											data-cancel-text="Отмена">
											</button>
										</form>
										@endif
									</div>
								</td>
							</tr>
                            @endforeach
						</tbody>
					</table>
				</div>
				@endif
			</div>
		</div>
		
		<x-slot name="script">
			<script>
				$(function() {
					$(document).on('click', '.occ-bot-toggle', function() {
						var btn = $(this);
						var url = btn.data('url');
						btn.prop('disabled', true);
						
						$.ajax({
							url: url,
							method: 'POST',
							headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
							dataType: 'json',
							success: function(data) {
								var on = !!data.enabled;
								btn.data('enabled', on ? '1' : '0');
								
    if (on) {
        btn.removeClass('btn-success icon-play').addClass('btn-danger icon-stop');
        btn.html('');
        initIcons();
        btn.attr('title', 'Бот включён (нажми чтобы выключить)');
    } else {
        btn.removeClass('btn-danger icon-stop').addClass('btn-success icon-play');
        btn.html('');
        initIcons();
        btn.attr('title', 'Бот выключен (нажми чтобы включить)');
    }
								
								
								btn.attr('title', on ? 'Бот включён (нажми чтобы выключить)' : 'Бот выключен (нажми чтобы включить)');
								swal({
									title: on ? '🤖 Бот включён' : '🤖 Бот выключен',
									text: on ? 'Помощник записи активирован для этой даты' : 'Помощник записи отключён для этой даты',
									icon: on ? 'success' : 'info',
									button: 'OK',
								});
							},
							error: function() {
								swal({ title: 'Ошибка', text: 'Не удалось изменить статус бота', icon: 'error', button: 'OK' });
							},
							complete: function() {
								btn.prop('disabled', false);
							}
						});
					});
				});
			</script>
		</x-slot>
		
	</x-voll-layout>
