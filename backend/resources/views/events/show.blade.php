{{-- resources/views/events/show.blade.php --}}
@php
use Illuminate\Support\Carbon;

/** @var \App\Models\Event $event */
/** @var \App\Models\EventOccurrence $occurrence */
/** @var \App\Services\GuardResult $join */
/** @var \App\Services\GuardResult $cancel */

$meta = $join->meta ?? [];
$data = $join->data ?? [];

$isRegistered = $meta['is_registered'] ?? false;
$userPosition = $meta['user_position'] ?? null;
$freePositions = $data['free_positions'] ?? [];

$showParticipants = $event->show_participants ?? false;

$joinAction = route('occurrences.join', ['occurrence' => $occurrence->id]);
$leaveAction = route('occurrences.leave', ['occurrence' => $occurrence->id]);

$user = auth()->user();
$registeredTotal = $registered_total ?? $join->data['meta']['registered_total'] ?? 0;

// =========================
// Timezones + Date/Time labels
// =========================
$eventTz = $occurrence?->timezone ?: ($event->timezone ?: 'UTC');
$userTz = \App\Support\DateTime::effectiveUserTz(auth()->user(), $eventTz);

$startsUtc = $occurrence?->starts_at ?: $event->starts_at;
$durationSec = $occurrence?->duration_sec ?? $event->duration_sec ?? null;

$starts = $startsUtc
? Carbon::parse($startsUtc, 'UTC')->setTimezone($userTz)
: null;

$ends = ($starts && $durationSec)
? (clone $starts)->addSeconds((int) $durationSec)
: null;

$dateHuman = $starts ? $starts->translatedFormat('l, d F') : '—';
$userTzLabel = $starts ? ($starts->format('T') . ' (UTC' . $starts->format('P') . ')') : $userTz;

$timeLabel = $starts
? $starts->format('H:i') . ($ends ? '–' . $ends->format('H:i') : '')
: '—';

$durationLabel = null;
if ($starts && $ends && $ends->greaterThan($starts)) {
$mins = $starts->diffInMinutes($ends);
$h = intdiv($mins, 60);
$m = $mins % 60;

$dHr = __('events.dur_hours_short');
$dMn = __('events.dur_min_short');
$durationLabel = ($h > 0 ? "{$h}{$dHr} " : '') . ($m > 0 ? "{$m}{$dMn}" : ($h > 0 ? '' : "{$mins}{$dMn}"));
}

// =========================
// Address + Map
// =========================
$loc = $occurrence?->location ?? $event->location;

$addressParts = array_filter([
$loc?->name,
$loc?->city?->name,
$loc?->address,
]);

$address = $addressParts ? implode(', ', $addressParts) : null;

$latRaw = $loc?->lat ?? $loc?->latitude ?? null;
$lngRaw = $loc?->lng ?? $loc?->longitude ?? null;

// нормализуем "55,123" -> "55.123"
$lat = is_null($latRaw) ? null : (float) str_replace(',', '.', trim((string) $latRaw));
$lng = is_null($lngRaw) ? null : (float) str_replace(',', '.', trim((string) $lngRaw));

$hasCoords =
!is_null($latRaw) && !is_null($lngRaw)
&& $lat !== 0.0 && $lng !== 0.0
&& $lat >= -90 && $lat <= 90
&& $lng >= -180 && $lng <= 180;
	
    // ссылки наружу
    $yandexLink = null;
    if ($hasCoords) {
	$yandexLink = 'https://yandex.ru/maps/?ll=' . urlencode($lng . ',' . $lat)
	. '&z=16&pt=' . urlencode($lng . ',' . $lat . ',pm2rdm');
    } elseif ($address) {
	$yandexLink = 'https://yandex.ru/maps/?text=' . urlencode($address);
    }
	
    $osmUrl = $hasCoords
	? ('https://www.openstreetmap.org/?mlat=' . urlencode((string) $lat)
	. '&mlon=' . urlencode((string) $lng)
	. '#map=16/' . urlencode((string) $lat) . '/' . urlencode((string) $lng))
	: ($address ? ('https://www.openstreetmap.org/search?query=' . rawurlencode($address)) : null);
	
    $gMapsUrl = $hasCoords
	? ('https://www.google.com/maps?q=' . urlencode($lat . ',' . $lng))
	: ($address ? ('https://www.google.com/maps?q=' . urlencode($address)) : null);
	@endphp
	
	<x-voll-layout body_class="event-page">
		<x-slot name="title">
			{{ $event->title }}
		</x-slot>
		
		<x-slot name="description">
			{{ $event->title }} - {{ $dateHuman }} - {{ $timeLabel }} - {{ $address }}
		</x-slot>
		
		<x-slot name="canonical">
			{{ route('events.show', ['event' => $event->id, 'occurrence' => $occurrence?->id]) }}
		</x-slot>
		
		<x-slot name="h1">
			{{ $event->title }}
		</x-slot>
		
		<x-slot name="h2">
			{{ $dateHuman }} - {{ $timeLabel }}
		</x-slot>
		@if (session('private_link'))
        <div class="ramka">
			<div class="alert alert-info">
				<div class="b-600 mb-1">{{ __('events.show_private_link_title') }}</div>
				<a
                class="blink"
                href="{{ session('private_link') }}"
                target="_blank"
                rel="noopener"
				>
					{{ session('private_link') }}
				</a>
			</div>
		</div>
		@endif
		<x-slot name="t_description">
			{{ $address }}
		</x-slot>
		
		<x-slot name="breadcrumbs">
			<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
				<a href="{{ route('events.index') }}" itemprop="item">
					<span itemprop="name">{{ __('events.index_title') }}</span>
				</a>
				<meta itemprop="position" content="2">
			</li>
			<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
				<a href="{{ route('events.show', ['event' => $event->id, 'occurrence' => $occurrence?->id]) }}" itemprop="item">
					<span itemprop="name">{{ $event->title }}</span>
				</a>
				<meta itemprop="position" content="3">
			</li>
		</x-slot>
		
		<x-slot name="d_description">
		</x-slot>
		
		<x-slot name="style">
			<style>
				.event-photo {		
				display: flex;
				flex-flow: column;
				align-items: center;
				justify-content: center;
				aspect-ratio: 16 / 9;	
				}
				.event-photo img {
				object-fit: cover;
				width: 100%;
				height: 100%;
				}
				.event-summary {
				display: flex;
				flex-direction: column;
				}
				
				.event-row {
				display:flex;
				justify-content:space-between;
				border-bottom:1px dashed rgba(0,0,0,0.2);
				padding: 1rem 0;
				}	
				.event-row:last-child {
				border-bottom:1px dashed transparent!important;
				}				
				body.dark .event-row {
				border-bottom: 1px dashed rgba(255,255,255,0.2);
				}	
				.progress {
				height:10px;
				background: rgba(0, 0, 0, 0.1);
				border-radius: 10px;
				}
				body.dark .progress {
				height:10px;
				background: rgba(255, 255, 255, 0.1);
				}	
				.progress-bar {
				height: 10px;
				border-radius: 10px;
				}
				body.dark .level-color-badge {
				text-shadow: 0 0 8px rgba(255,255,255,.85), 0 0 3px rgba(255,255,255,.6);
				}
			</style>
		</x-slot>
		
		<div class="container">
			
			{{-- FLASH --}}
			@if(session('status'))
            <div class="ramka">
                <div class="alert alert-success">
                    {{ session('status') }}
				</div>
			</div>
			@endif
			
			@if(session('error'))
            <div class="ramka">
                <div class="alert alert-danger">
                    {{ session('error') }}
				</div>
			</div>
			@endif
			
			@include('events.show.tournament')
			
			<div class="row row2">
				<div class="col-lg-8">
					@include('events.show.description')
					@include('events.show.info')
					
				</div>
				
				<div class="col-lg-4">
					@if($event->allow_registration ?? true)
					@include('events.show.players')
					@else
					@php
					$isOwner = auth()->check() && auth()->id() === (int)($event->organizer_id ?? 0);
					$adStatus = $event->ad_payment_status ?? null;
					$adMethod = $platPaySettings?->method ?? null;
					$adPrice  = $event->ad_price_rub ?? 0;
					$adExpires = $event->ad_payment_expires_at;
					@endphp
					
					{{-- Блок оплаты для организатора --}}
					@if($isOwner && $adStatus === 'pending' && $adPrice > 0)
					<div class="ramka">
						<h2 class="-mt-05">{{ __('events.show_ad_payment_title') }}</h2>
						<p class="f-15">{{ __('events.show_ad_payment_lead') }}</p>
						<div class="alert alert-warning">
							{{ __('events.show_ad_price_label') }} <strong>{{ $adPrice }} ₽</strong><br>
							@if($adExpires)
							{{ __('events.show_ad_pay_until') }} <strong>{{ \Carbon\Carbon::parse($adExpires)->format('d.m.Y H:i') }}</strong>
							@endif
						</div>
						
						@if($adMethod === 'tbank_link' && $platPaySettings?->tbank_link)
						<a href="{{ $platPaySettings->tbank_link }}" target="_blank" class="btn w-100 mb-1">
							{{ __('events.show_ad_pay_tbank') }}
						</a>
						@elseif($adMethod === 'sber_link' && $platPaySettings?->sber_link)
						<a href="{{ $platPaySettings->sber_link }}" target="_blank" class="btn w-100 mb-1">
							{{ __('events.show_ad_pay_sber') }}
						</a>
						@elseif($adMethod === 'yoomoney')
						@if($event->ad_yookassa_payment_url)
						<a href="{{ $event->ad_yookassa_payment_url }}" target="_blank" class="btn w-100 mb-1">
							{{ __('events.show_ad_pay_yookassa') }}
						</a>
						<p class="f-13 mt-1" style="opacity:.6">{{ __('events.show_ad_pay_yookassa_note') }}</p>
						@else
						<div class="alert alert-danger f-14">{{ __('events.show_ad_pay_error') }}</div>
						@endif
						@endif
						
						@if(in_array($adMethod, ['tbank_link', 'sber_link']))
						@if($event->ad_organizer_notified ?? false)
						<div class="alert alert-info mt-2">{{ __('events.show_ad_waiting_admin') }}</div>
						@else
						<form method="POST" action="{{ route('events.ad.paid', $event) }}">
							@csrf
							<button type="submit" class="btn btn-secondary w-100 mt-1">{{ __('events.show_ad_paid_btn') }}</button>
						</form>
						@endif
						@endif
					</div>
					@elseif($adStatus === 'pending')
					<div class="ramka">
						<div class="alert alert-warning">{{ __('events.show_ad_status_pending') }}</div>
					</div>
					@endif
					
					{{-- Блок организатора --}}
					<div class="ramka">
						<h2 class="-mt-05">{{ __('events.show_ad_section_title') }}</h2>
						<p class="f-15">{{ __('events.show_ad_section_lead') }}</p>
						@if($event->organizer)
						@php $org = $event->organizer; @endphp
						<div class="d-flex fvc gap-1 mt-2">
							<img src="{{ $org->profile_photo_url }}" style="width:44px;height:44px;border-radius:50%;object-fit:cover;">
							<div>
								<div class="f-13" style="opacity:.6">{{ __('events.show_organizer_label') }}</div>
								<a class="blink b-600" href="{{ route('users.show', $org->id) }}">
									{{ trim(($org->last_name ?? '') . ' ' . ($org->first_name ?? $org->name)) }}
								</a>
							</div>
						</div>
						@if($org->phone)
						<div class="mt-2">
							<a href="tel:{{ $org->phone }}" class="btn btn-secondary w-100">{{ __('events.show_call_organizer') }}</a>
						</div>
						@endif
						@endif
					</div>
					@endif
				</div>
			</div>
		</div>
		<x-slot name="script">
			@include('events.show.scripts')
			@php
				$calStart = $starts ? $starts->toIso8601String() : '';
				$calEnd   = $ends
					? $ends->toIso8601String()
					: ($starts ? $starts->copy()->addHours(2)->toIso8601String() : '');
				$calLoc   = $address ?? '';
				$calNotes = strip_tags($event->description ?? '');
			@endphp
			<script>
			(function () {
				var shareBtn = document.getElementById('btn-share-event');
				var calBtn   = document.getElementById('btn-add-calendar');

				if (shareBtn) {
					shareBtn.addEventListener('click', function () {
						if (window.VolleyNative && window.VolleyNative.isApp) {
							window.VolleyNative.share({
								title: @json($event->title ?? ''),
								text: @json(__('events.show_share_text')),
								url: window.location.href
							});
						} else if (navigator.share) {
							navigator.share({
								title: @json($event->title ?? ''),
								text: @json(__('events.show_share_text')),
								url: window.location.href
							}).catch(function() {});
						} else {
							navigator.clipboard.writeText(window.location.href).then(function() {
								shareBtn.textContent = @json(__('events.show_link_copied'));
								setTimeout(function() { shareBtn.textContent = @json(__('events.show_share_btn')); }, 2000);
							}).catch(function() {});
						}
					});
				}

				if (calBtn) {
					calBtn.addEventListener('click', function () {
						if (window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.VolleyCalendar) {
							window.Capacitor.Plugins.VolleyCalendar.addEvent({
								title: @json($event->title ?? __('events.show_calendar_default_title')),
								location: @json($event->location->name ?? ''),
								notes: @json(strip_tags($event->description ?? '')),
								startDate: @json($event->starts_at?->toIso8601String() ?? ''),
								endDate: @json($event->ends_at?->toIso8601String() ?? '')
							}).then(function(r) {
								swal(@json(__('events.show_calendar_done_title')), @json(__('events.show_calendar_done_text')), 'success');
							}).catch(function(e) {
								console.log('[Calendar] error:', e);
							});
						} else {
							var title    = @json($event->title ?? '');
							var location = @json($calLoc);
							var notes    = @json($calNotes);
							var start    = new Date(@json($calStart));
							var end      = new Date(@json($calEnd));

							function icsDate(d) {
								return d.toISOString().replace(/[-:]/g, '').split('.')[0] + 'Z';
							}
							var uid = 'event-' + @json($occurrence->id ?? 0) + '@volleyplay.club';
							var ics = [
								'BEGIN:VCALENDAR',
								'VERSION:2.0',
								'PRODID:-//VolleyPlay//RU',
								'BEGIN:VEVENT',
								'UID:' + uid,
								'DTSTAMP:' + icsDate(new Date()),
								'DTSTART:' + icsDate(start),
								'DTEND:' + icsDate(end),
								'SUMMARY:' + title.replace(/\n/g, '\\n'),
								'LOCATION:' + location.replace(/\n/g, '\\n'),
								'DESCRIPTION:' + notes.replace(/\n/g, '\\n').substring(0, 500),
								'END:VEVENT',
								'END:VCALENDAR'
							].join('\r\n');

							var blob = new Blob([ics], { type: 'text/calendar;charset=utf-8' });
							var a = document.createElement('a');
							a.href = URL.createObjectURL(blob);
							a.download = 'event.ics';
							a.click();
						}
					});
				}
			})();
			</script>
		</x-slot>
	</x-voll-layout>					