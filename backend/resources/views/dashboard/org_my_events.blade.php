{{-- resources/views/dashboard/org_my_events.blade.php --}}
{{-- /my/events — упрощённый карточный список мероприятий организатора (см. задачу 1 обмена /my/events <-> /my/bookings) --}}
@php
$fmtDate = function ($startsAt, $tz) {
	if (!$startsAt) return '—';
	$tz = $tz ?: 'UTC';
	$dt = \Carbon\Carbon::parse($startsAt, 'UTC')->setTimezone($tz);
	$days = ['Mon' => 'Пн', 'Tue' => 'Вт', 'Wed' => 'Ср', 'Thu' => 'Чт', 'Fri' => 'Пт', 'Sat' => 'Сб', 'Sun' => 'Вс'];
	$dow = $days[$dt->format('D')] ?? $dt->format('D');
	return $dt->translatedFormat('j M Y') . ', ' . $dow . ' · ' . $dt->format('H:i');
};

$fmtAddress = function ($row) {
	return trim((string)($row->loc_address ?? '')) ?: trim((string)($row->loc_name ?? '')) ?: '—';
};
@endphp

<x-voll-layout body_class="organizer-my-events-page">

	<x-slot name="title">{{ __('profile.menu_my_events') }}</x-slot>
	<x-slot name="h1">{{ __('profile.menu_my_events') }}</x-slot>

	<x-slot name="breadcrumbs">
		<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
			<a href="{{ route('profile.show') }}" itemprop="item">
				<span itemprop="name">Мой профиль</span>
			</a>
			<meta itemprop="position" content="2">
		</li>
		<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
			<span itemprop="name">{{ __('profile.menu_my_events') }}</span>
			<meta itemprop="position" content="3">
		</li>
	</x-slot>

	<x-slot name="d_description">
		<div class="d-flex flex-wrap gap-1 m-center">
			<div class="mt-2" data-aos-delay="250" data-aos="fade-up">
				<a href="{{ route('organizer.my-events', ['filter' => 'current']) }}"
				class="btn {{ $filter === 'current' ? 'btn-primary' : 'btn-secondary' }}">
					Текущие
				</a>
			</div>
			<div class="mt-2" data-aos-delay="350" data-aos="fade-up">
				<a href="{{ route('organizer.my-events', ['filter' => 'archive']) }}"
				class="btn {{ $filter === 'archive' ? 'btn-primary' : 'btn-secondary' }}">
					Архивные
				</a>
			</div>
		</div>
	</x-slot>

	<div class="container">
		<div class="row row2">
			<div class="col-lg-4 col-xl-3 order-2 d-none d-lg-block">
				<div class="sticky">
					<div class="card-ramka">
						@include('profile._menu', ['activeMenu' => 'organizer_my_events'])
					</div>
				</div>
			</div>
			<div class="col-lg-8 col-xl-9 order-1">
				<div class="ramka pb-1 mb-2">
					<h2 class="-mt-05">
						{{ $filter === 'current' ? 'Текущие' : 'Архивные' }} мероприятия
					</h2>
				</div>

				@if($occurrences->isEmpty())
				<div class="ramka">
					<p class="text-muted f-15 mb-0">{{ __('events.overview_empty') }}</p>
				</div>
				@else

				@foreach($occurrences as $row)
				@php
				$isTournament = ($row->format ?? '') === 'tournament';
				$tournamentUrl = $isTournament
					? url('/events/' . (int)$row->event_id . '/tournament/setup') . ($row->is_recurring ? '?occurrence_id=' . (int)$row->occurrence_id : '')
					: null;
				$registrationsUrl = route('events.registrations.index', ['event' => (int)$row->event_id]) . '?occurrence=' . (int)$row->occurrence_id;
				@endphp
				<div class="card mb-2" style="height:auto;border:0.15rem solid #E7612F;">
					<div class="d-flex between gap-1 fvc">
						<div>
							<a href="{{ url('/events/' . (int)$row->event_id) . '?occurrence=' . (int)$row->occurrence_id }}"
							class="blink b-600 mb-1">
								{{ $row->title }}
							</a>
							<div>🗓 {{ $fmtDate($row->starts_at, $row->timezone) }}</div>
							@if($fmtAddress($row) !== '—')
							<div>📍 {{ $fmtAddress($row) }}</div>
							@endif
						</div>
						<div class="d-flex flex-column gap-1" style="flex-shrink:0;">
							@if($isTournament)
							<a href="{{ $tournamentUrl }}" class="btn btn-secondary btn-small">{{ __('events.occ_tournament_btn') }}</a>
							@endif
							<a href="{{ $registrationsUrl }}" class="btn btn-secondary btn-small">{{ __('events.registrations_manage') }}</a>
						</div>
					</div>
				</div>
				@endforeach

				{{-- Пагинация --}}
				@if($occurrences->hasPages())
				<div class="mt-2">
					{{ $occurrences->links() }}
				</div>
				@endif
				@endif
			</div>
		</div>
	</div>
</x-voll-layout>
