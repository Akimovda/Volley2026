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
			
			
			
			{{-- Кнопка архива --}}
			@if($archived->isNotEmpty())
			<div class="mb-2 d-flex" style="justify-content:flex-end;">
				<button type="button" id="toggle-archive" class="btn btn-secondary btn-small">
					🗄 Архивные ({{ $archived->count() }})
				</button>
			</div>
			@endif

			{{-- Текущие и будущие --}}
			<div class="ramka">
				@if($occurrences->isEmpty())
                <div class="p-6 text-sm text-gray-600">
                    Предстоящих повторов нет.
				</div>
				@else
				@include('events._partials.occurrences_table', ['occurrences' => $occurrences, 'event' => $event, 'isAdmin' => $isAdmin, 'tz' => $tz, 'seatMeta' => $seatMeta, 'fmtOccurrenceDt' => $fmtOccurrenceDt])
				@endif
			</div>

			{{-- Архивные --}}
			@if($archived->isNotEmpty())
			<div id="archive-section" class="ramka mt-2" style="display:none;">
				<div class="f-15 b-600 mb-2" style="color:#6b7280;">🗄 Архивные мероприятия</div>
				@include('events._partials.occurrences_table', ['occurrences' => $archived, 'event' => $event, 'isAdmin' => $isAdmin, 'tz' => $tz, 'seatMeta' => $seatMeta, 'fmtOccurrenceDt' => $fmtOccurrenceDt])
			</div>
			@endif

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

					document.getElementById('toggle-archive') && document.getElementById('toggle-archive').addEventListener('click', function() {
						var section = document.getElementById('archive-section');
						if (!section) return;
						var hidden = section.style.display === 'none' || section.style.display === '';
						section.style.display = hidden ? 'block' : 'none';
						this.textContent = hidden ? '▲ Скрыть архив' : '🗄 Архивные ({{ $archived->count() }})';
					});
				});
			</script>
		</x-slot>
		
	</x-voll-layout>
