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
$userTz = \App\Support\DateTime::effectiveUserTz(auth()->user());
$eventTz = $occurrence?->timezone ?: ($event->timezone ?: 'UTC');

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

$durationLabel = ($h > 0 ? "{$h}ч " : '') . ($m > 0 ? "{$m}м" : ($h > 0 ? '' : "{$mins}м"));
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
				<div class="b-600 mb-1">🔗 Приватная ссылка</div>
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
					<span itemprop="name">Мероприятия</span>
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
					@include('events.show.players')
				</div>
			</div>
		</div>
		<x-slot name="script">
			@include('events.show.scripts')
		</x-slot>
	</x-voll-layout>				