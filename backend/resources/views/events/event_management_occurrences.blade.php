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
return ['label' => __('events.reg_off'), 'registered' => $registered];
}

if ($max <= 0) {
return ['label' => __('events.seats_dash'), 'registered' => $registered];
	}
	
	$free = max(0, $max - $registered);
	
	return [
	'label' => __('events.seats_n_of_m', ['free' => $free, 'max' => $max]),
	'registered' => $registered,
	];
    };
	@endphp
	
	<x-voll-layout>
		<x-slot name="title">{{ __('events.occ_title') }}</x-slot>
		<x-slot name="h1">{{ __('events.occ_title') }}</x-slot>
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
						{{ __('events.edit_series') }}
					</a>
				</div>
				<div class="mt-2" data-aos-delay="350" data-aos="fade-up">
					<a href="{{ url('/events/' . (int)$event->id) }}"
					class="btn btn-secondary">
						{{ __('events.open_event') }}
					</a>
				</div>						
			</div>
		</x-slot>
		<x-slot name="breadcrumbs">
			<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
				<a href="{{ route('events.create.event_management') }}" itemprop="item">
					<span itemprop="name">{{ __('events.mgmt_breadcrumb') }}</span>
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
				<button type="button" id="toggle-archive" class="btn btn-secondary btn-small"
					onclick="var s=document.getElementById('archive-section');if(s){var h=s.style.display==='none'||s.style.display==='';s.style.display=h?'block':'none';this.textContent=h?@json(__('events.occ_archive_hide')):@json(__('events.occ_archive_btn', ['n' => $archived->count()]));}">
					{{ __('events.occ_archive_btn', ['n' => $archived->count()]) }}
				</button>
			</div>
			@endif

			{{-- Текущие и будущие --}}
			<div class="ramka">
				@if($occurrences->isEmpty())
                <div class="p-6 text-sm text-gray-600">
                    {{ __('events.occ_no_upcoming') }}
				</div>
				@else
				@include('events._partials.occurrences_table', ['occurrences' => $occurrences, 'event' => $event, 'isAdmin' => $isAdmin, 'tz' => $tz, 'seatMeta' => $seatMeta, 'fmtOccurrenceDt' => $fmtOccurrenceDt, 'isArchived' => false])
				@endif
			</div>

			{{-- Архивные --}}
			@if($archived->isNotEmpty())
			<div id="archive-section" class="ramka mt-2" style="display:none;">
				<div class="f-15 b-600 mb-2" style="color:#6b7280;">{{ __('events.occ_archive_section') }}</div>
				@include('events._partials.occurrences_table', ['occurrences' => $archived, 'event' => $event, 'isAdmin' => $isAdmin, 'tz' => $tz, 'seatMeta' => $seatMeta, 'fmtOccurrenceDt' => $fmtOccurrenceDt, 'isArchived' => true])
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
        btn.attr('title', @json(__('events.bot_on')));
    } else {
        btn.removeClass('btn-danger icon-stop').addClass('btn-success icon-play');
        btn.html('');
        initIcons();
        btn.attr('title', @json(__('events.bot_off')));
    }
								
								
								btn.attr('title', on ? @json(__('events.bot_on')) : @json(__('events.bot_off')));
								swal({
									title: on ? @json(__('events.bot_on_title')) : @json(__('events.bot_off_title')),
									text: on ? @json(__('events.bot_on_text_occ')) : @json(__('events.bot_off_text_occ')),
									icon: on ? 'success' : 'info',
									button: 'OK',
								});
							},
							error: function() {
								swal({ title: @json(__('ui.error_title')), text: @json(__('events.bot_change_error')), icon: 'error', button: 'OK' });
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
