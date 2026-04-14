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
<div class="ramka" style="border: 2px solid #f5c842;">
    <h2 class="-mt-05">💰 Оплата размещения</h2>
    <p class="f-15">Для публикации рекламного мероприятия необходимо оплатить размещение.</p>
    <div class="alert alert-warning">
        Стоимость: <strong>{{ $adPrice }} ₽</strong><br>
        @if($adExpires)
        ⏰ Оплатите до: <strong>{{ \Carbon\Carbon::parse($adExpires)->format('d.m.Y H:i') }}</strong>
        @endif
    </div>

    @if($adMethod === 'tbank_link' && $platPaySettings?->tbank_link)
    <a href="{{ $platPaySettings->tbank_link }}" target="_blank" class="btn w-100 mb-1">
        💳 Оплатить через Т-Банк
    </a>
    @elseif($adMethod === 'sber_link' && $platPaySettings?->sber_link)
    <a href="{{ $platPaySettings->sber_link }}" target="_blank" class="btn w-100 mb-1">
        💳 Оплатить через Сбербанк
    </a>
    @elseif($adMethod === 'yoomoney')
    @if($event->ad_yookassa_payment_url)
    <a href="{{ $event->ad_yookassa_payment_url }}" target="_blank" class="btn w-100 mb-1">
        💳 Оплатить через ЮKassa
    </a>
    <p class="f-13 mt-1" style="opacity:.6">После оплаты мероприятие будет опубликовано автоматически.</p>
    @else
    <div class="alert alert-danger f-14">Ошибка создания платежа. Обратитесь к администратору.</div>
    @endif
    @endif

    @if(in_array($adMethod, ['tbank_link', 'sber_link']))
    @if($event->ad_organizer_notified ?? false)
    <div class="alert alert-info mt-2">⏳ Ожидаем подтверждения от администратора.</div>
    @else
    <form method="POST" action="{{ route('events.ad.paid', $event) }}">
        @csrf
        <button type="submit" class="btn btn-secondary w-100 mt-1">✅ Я оплатил — уведомить администратора</button>
    </form>
    @endif
    @endif
</div>
@elseif($adStatus === 'pending')
<div class="ramka">
    <div class="alert alert-warning">⏳ Мероприятие ожидает подтверждения оплаты.</div>
</div>
@endif

{{-- Блок организатора --}}
<div class="ramka">
    <h2 class="-mt-05">📣 Рекламное мероприятие</h2>
    <p class="f-15">Запись осуществляется напрямую через организатора.</p>
    @if($event->organizer)
    @php $org = $event->organizer; @endphp
    <div class="d-flex fvc gap-1 mt-2">
        <img src="{{ $org->profile_photo_url }}" style="width:44px;height:44px;border-radius:50%;object-fit:cover;">
        <div>
            <div class="f-13" style="opacity:.6">Организатор</div>
            <a class="blink b-600" href="{{ route('users.show', $org->id) }}">
                {{ trim(($org->last_name ?? '') . ' ' . ($org->first_name ?? $org->name)) }}
            </a>
        </div>
    </div>
    @if($org->phone)
    <div class="mt-2">
        <a href="tel:{{ $org->phone }}" class="btn btn-secondary w-100">📞 Позвонить организатору</a>
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
		</x-slot>
	</x-voll-layout>				