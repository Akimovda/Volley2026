{{-- resources/views/events/_card.blade.php --}}
@php $event = $occ->event; @endphp

@if(!$event)
@else

@php
$joinedOccurrenceIds     = $joinedOccurrenceIds ?? [];
$restrictedOccurrenceIds = $restrictedOccurrenceIds ?? [];

$isJoined     = in_array((int)$occ->id, $joinedOccurrenceIds, true);
$joinDisabled = in_array((int)$occ->id, $restrictedOccurrenceIds, true);

$dir = $event?->direction ?? 'classic';
$userLevel = null;
if ($dir === 'beach') {
    $userLevel = auth()->user()?->beach_level;
} else {
    $userLevel = auth()->user()?->classic_level;
}

$hasLevelRestriction =
    !is_null($event?->beach_level_min) || !is_null($event?->beach_level_max)
    || !is_null($event?->classic_level_min) || !is_null($event?->classic_level_max);

$levelRestrictionActive = false;
if ($userLevel !== null) {
    if ($dir === 'beach') {
        if (!is_null($event?->beach_level_min) && $userLevel < (int)$event->beach_level_min) $levelRestrictionActive = true;
        if (!is_null($event?->beach_level_max) && $userLevel > (int)$event->beach_level_max) $levelRestrictionActive = true;
    } else {
        if (!is_null($event?->classic_level_min) && $userLevel < (int)$event->classic_level_min) $levelRestrictionActive = true;
        if (!is_null($event?->classic_level_max) && $userLevel > (int)$event->classic_level_max) $levelRestrictionActive = true;
    }
} else {
    $levelRestrictionActive = $hasLevelRestriction;
}

$dt = $fmtDate($occ);

$addressParts = array_filter([
    $event?->location?->name,
    $event?->location?->city?->name,
    $event?->location?->address,
]);
$address = $addressParts ? implode(', ', $addressParts) : '—';

$coverUrl = $event ? $event->getFirstMediaUrl('cover') : '';
$gs = $event?->gameSettings ?? null;
$regEnabled = (bool) data_get($event, 'allow_registration', false);

$maxPlayersCard = (int) (data_get($occ, 'max_players') ?? 0);
if ($maxPlayersCard <= 0) $maxPlayersCard = (int) (data_get($gs, 'max_players') ?? 0);
if ($maxPlayersCard <= 0) $maxPlayersCard = (int) (data_get($event, 'max_players') ?? 0);

$showSeatLine = $maxPlayersCard > 0;

$positions = $gs?->positions;
if (is_string($positions)) {
    $positions = json_decode($positions, true);
}

$isClassicDirection = ((string)($event?->direction ?? 'classic') === 'classic');
$isBeachDirection   = ((string)($event?->direction ?? '') === 'beach');
$requiresPositionChoice = $isClassicDirection;

$trainerLabel = null;
if ($trainerColumn && $event) {
    $tid = (int)($event->{$trainerColumn} ?? 0);
    if ($tid > 0 && isset($trainersById[$tid])) {
        $tu = $trainersById[$tid];
        $trainerLabel = trim(($tu->name ?? '') ?: ($tu->email ?? '')) . ' (#' . (int)$tid . ')';
    }
}

$isTrainingFmt = in_array((string)($event?->format ?? ''), ['training','training_game'], true);

$dir  = (string)($event?->direction ?? '');
$fmt  = (string)($event?->format ?? '');
$clMin = is_null($event?->classic_level_min) ? '' : (int)$event->classic_level_min;
$clMax = is_null($event?->classic_level_max) ? '' : (int)$event->classic_level_max;
$bMin  = is_null($event?->beach_level_min) ? '' : (int)$event->beach_level_min;
$bMax  = is_null($event?->beach_level_max) ? '' : (int)$event->beach_level_max;

$tzEvent = (string)($occ->timezone ?: ($event?->timezone ?: 'UTC'));

$startsAtUtc = $occ->starts_at
    ? \Illuminate\Support\Carbon::parse($occ->starts_at, 'UTC')
    : null;

$regStartsUtc   = $occ->effectiveRegistrationStartsAt();
$regEndsUtc     = $occ->effectiveRegistrationEndsAt();
$cancelUntilUtc = $occ->effectiveCancelSelfUntil();

$eventStarted  = $startsAtUtc ? $nowUtc->gte($startsAtUtc) : false;
$regNotStarted = $regStartsUtc ? $nowUtc->lt($regStartsUtc) : false;
$regClosed     = $regEndsUtc   ? $nowUtc->gte($regEndsUtc)   : false;

$canRegister   = $regEnabled && !$eventStarted && !$regNotStarted && !$regClosed;
$canCancelSelf = $regEnabled && !$eventStarted && (!$cancelUntilUtc || $nowUtc->lt($cancelUntilUtc));

$dirLabel = ($dir === 'beach') ? 'Пляжка' : (($dir === 'classic') ? 'Классика' : '—');
$tzUser   = $userTz ?? 'UTC';
$tzEvent  = (string)($occ->timezone ?: ($event?->timezone ?: 'UTC'));

$sLocal = $occ->starts_at
    ? \Illuminate\Support\Carbon::parse($occ->starts_at, 'UTC')->setTimezone($tzUser)
    : null;

$eLocal = null;
if ($sLocal && !empty($occ->duration_sec)) {
    $eLocal = $sLocal->copy()->addSeconds((int)$occ->duration_sec);
}

$dateLong  = $sLocal ? $sLocal->locale('ru')->translatedFormat('d F') : '—';
$timeRange = $sLocal
    ? $sLocal->format('H:i') . ($eLocal ? '-' . $eLocal->format('H:i') : '')
    : '—';
$tzLabel = $sLocal ? ($sLocal->format('T') . ' (UTC' . $sLocal->format('P') . ')') : ($tzUser);

$durLabel = null;
if (!empty($occ->duration_sec)) {
    $mins = intdiv((int)$occ->duration_sec, 60);
    $h = intdiv($mins, 60);
    $m = $mins % 60;
    $durLabel = sprintf('%d:%02d', $h, $m);
}

if ($dir === 'beach') {
    $lvMin = is_null($event?->beach_level_min) ? null : (int)$event->beach_level_min;
    $lvMax = is_null($event?->beach_level_max) ? null : (int)$event->beach_level_max;
} else {
    $lvMin = is_null($event?->classic_level_min) ? null : (int)$event->classic_level_min;
    $lvMax = is_null($event?->classic_level_max) ? null : (int)$event->classic_level_max;
}
$levelLabel = ($lvMin !== null || $lvMax !== null)
    ? (($lvMin !== null ? $lvMin : '—') . ' - ' . ($lvMax !== null ? $lvMax : '—'))
    : null;

$priceLabel = null;
if (!empty($event?->is_paid)) {
    if (!is_null($event?->price_minor)) {
        $priceLabel = money_human((int) $event->price_minor, (string) ($event->price_currency ?? 'RUB'));
    } elseif (trim((string) ($event?->price_text ?? '')) !== '') {
        $priceLabel = trim((string) $event->price_text);
    }
}

$trainerUrl = null;
if ($isTrainingFmt && $trainerColumn && $event) {
    $tid = (int)($event->{$trainerColumn} ?? 0);
    if ($tid > 0) {
        $trainerUrl = url('/user/' . $tid);
    }
}

$join   = $occ->join   ?? null;
$cancel = $occ->cancel ?? null;

$eventStarted2 = $startsAtUtc ? $nowUtc->gte($startsAtUtc) : false;
$regClosed2    = $regEndsUtc  ? $nowUtc->gte($regEndsUtc)  : false;
$regMode       = (string)($event->registration_mode ?? 'single');
$isGroupMode   = in_array($regMode, ['mixed_group','team_beach','team_classic','team'], true);
$eventPageUrl  = url('/events/'.(int)$event->id).'?occurrence='.(int)$occ->id;
$joinCode      = $join?->code ?? null;
@endphp

<div class="col-12 col-xl-4 col-lg-4 col-md-6 col-sm-6">
    <div class="card-ramka">
        <div
            class="event-card"
            data-direction="{{ e($dir) }}"
            data-format="{{ e($fmt) }}"
            data-classic-min="{{ e($clMin) }}"
            data-classic-max="{{ e($clMax) }}"
            data-beach-min="{{ e($bMin) }}"
            data-beach-max="{{ e($bMax) }}"
        >
            {{-- Фото --}}
            <div class="border f-0 mb-1">
                @if(!empty($event->event_photos) && count($event->event_photos) > 0)
                @php $firstPhoto = \Spatie\MediaLibrary\MediaCollections\Models\Media::find($event->event_photos[0]); @endphp
                @if($firstPhoto)
                    <img src="{{ $firstPhoto->getUrl('event_thumb') }}" alt="" class="card-img-top">
                @else
                    <img src="/img/{{ $event->direction === 'beach' ? 'beach.webp' : 'classic.webp' }}" alt="" class="card-img-top">
                @endif
                @elseif(!empty($coverUrl))
                    <img src="{{ $coverUrl }}" alt="" class="card-img-top">
                @else
                    <img src="/img/{{ $event->direction === 'beach' ? 'beach.webp' : 'classic.webp' }}" alt="" class="card-img-top">
                @endif
            </div>

            <div class="card-body">
                <div class="d-flex gap-3 justify-content-between align-items-start">
                    <div class="flex-grow-1" style="min-width:0;">

                        <a href="{{ url('/events/' . (int)$event->id) . '?occurrence=' . (int)$occ->id }}" class="card-title mb-1">
                            {{ $event?->title ?? '—' }}
                            @if(!empty($event?->is_private))
                            <span class="ms-2 text-muted" title="Приватное мероприятие">🙈</span>
                            @endif
                        </a>

                        <div class="d-flex flex-wrap gap-2 mt-1">
                            <span class="badge text-bg-secondary">{{ $dirLabel }}</span>
                        </div>

                        <div class="mt-2 text-muted small">
                            🗓 <span class="fw-semibold text-body">{{ $dateLong }}</span>
                        </div>

                        <div class="text-muted small mt-1">
                            ⏰ <span class="fw-semibold text-body">{{ $timeRange }}</span>
                            <span class="ms-2 text-muted">({{ $tzLabel }})</span>
                            @if($durLabel)
                            <span class="ms-2">⏳ <span class="fw-semibold text-body">{{ $durLabel }}</span></span>
                            @endif
                        </div>

                        <div class="text-muted small mt-1">📍 {{ $address }}</div>

                        @if($isTrainingFmt && !empty($trainerLabel))
                        <div class="text-muted small mt-1 d-flex align-items-center gap-2 flex-wrap">
                            <img src="{{ $trainerIconUrl }}" alt="trainer" style="width:18px;height:18px;opacity:.85;">
                            <span>Тренер:</span>
                            @if($trainerUrl)
                            <a class="fw-semibold text-decoration-underline" href="{{ $trainerUrl }}">{{ $trainerLabel }}</a>
                            @else
                            <span class="fw-semibold text-body">{{ $trainerLabel }}</span>
                            @endif
                        </div>
                        @endif

                        @if(!empty($event?->organizer_id))
                        @php
                            $orgId = (int)$event->organizer_id;
                            $org = $event?->organizer_user ?? $event?->organizer ?? null;
                            if (!$org && isset($usersById[$orgId])) $org = $usersById[$orgId];
                            if (!$org && isset($trainersById[$orgId])) $org = $trainersById[$orgId];
                          $organizerLabel = null;
                            if ($org) {
                                $full = trim(($org->first_name ?? '') . ' ' . ($org->last_name ?? ''));
                                $organizerLabel = $full !== '' ? $full : trim((string)($org->name ?? ''));
                                if ($organizerLabel === '') $organizerLabel = (string)($org->nickname ?? '');
                                if ($organizerLabel === '') $organizerLabel = (string)($org->email ?? '');
                            }
                            if (!$organizerLabel && $orgId > 0) $organizerLabel = 'Пользователь #' . $orgId;
                            $organizerUrl = $orgId > 0 ? url('/user/' . $orgId) : null;
                        @endphp
                        @if($organizerLabel)
                        <div class="text-muted small mt-1 d-flex align-items-center gap-2 flex-wrap">
                            <span>Организатор:</span>
                            <a class="fw-semibold text-decoration-underline" href="{{ $organizerUrl }}">{{ $organizerLabel }}</a>
                        </div>
                        @endif
                        @endif

                        @if($levelLabel)
                        <div class="text-muted small mt-1">
                            🎚 Уровень: <span class="fw-semibold text-body">{{ $levelLabel }}</span>
                        </div>
                        @endif

                        @if($priceLabel)
                        <div class="text-muted small mt-1">
                            💸 <span class="fw-semibold text-body">{{ $priceLabel }}</span>
                        </div>
                        @endif

                        @if($showSeatLine)
                        <div
                            class="mt-2 small"
                            data-seatline
                            data-occurrence-id="{{ (int)$occ->id }}"
                            data-registration-enabled="{{ $regEnabled ? '1' : '0' }}"
                            data-reg-not-started="{{ $regNotStarted ? '1' : '0' }}"
                            data-reg-closed="{{ $regClosed ? '1' : '0' }}"
                            data-max-players="{{ (int)$maxPlayersCard }}"
                            style="display:flex;align-items:center;gap:.4rem;"
                        >
                            <span class="text-muted">🧑‍🧑‍🧒</span>
                            <span class="text-muted">Осталось мест:</span>
                            <span class="fw-semibold" data-left>{{ (int)$maxPlayersCard }}</span>
                            <span class="text-muted">из</span>
                            <span class="fw-semibold" data-total>{{ (int)$maxPlayersCard }}</span>
                            <span class="text-muted">!</span>
                        </div>
                        @elseif($regEnabled)
                        <div class="mt-2 small text-muted">🧑‍🧑‍🧒 Лимит мест не задан</div>
                        @endif
                    </div>
                </div>

                {{-- Кнопки записи --}}
                <div class="mt-3 d-flex flex-wrap gap-2 align-items-center">

                    @if ($eventStarted2)
                        <div class="small fw-semibold text-warning">🎉 Мероприятие уже началось</div>

                    @elseif (!auth()->check())
                        <div class="small fw-semibold text-muted">🔐 Вам нужно войти на сайт!</div>

                    @elseif ($joinCode === 'age_blocked')
                        <div class="small fw-semibold text-danger">{{ $join->message }}</div>

                    @elseif ($joinCode === 'level_too_high')
                        <div class="small fw-semibold text-info">{{ $join->message }}</div>

                    @elseif ($joinCode === 'level_too_low')
                        <div class="small fw-semibold text-warning">{{ $join->message }}</div>

                    @elseif ($isJoined)
                        @if ($cancel?->allowed)
                        <form method="POST" action="{{ route('occurrences.leave', ['occurrence' => $occ->id]) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-secondary">Отменить запись</button>
                        </form>
                        @else
                        <div class="small text-danger fw-semibold">{{ $cancel?->message ?? 'Отмена недоступна' }}</div>
                        @endif

                    @elseif ($regClosed2)
                        <div class="small fw-semibold text-danger">❗️ Для записи Вам необходима помощь организатора</div>

                    @elseif ($isGroupMode)
                        <a href="{{ $eventPageUrl }}" class="btn btn-primary">Записаться</a>

                    @elseif ($join === null)
                        {{-- occurrences не обогащены (страница локации) — ведём на страницу события --}}
                        <a href="{{ $eventPageUrl }}" class="btn btn-primary">Записаться</a>

                    @elseif (!$join->allowed)
                        <button class="btn btn-primary" disabled style="opacity:.55;cursor:not-allowed;">Записаться</button>
                        @if ($join->message)
                        <div class="w-100 small text-muted mt-1">{{ $join->message }}</div>
                        @endif

                    @else
                        @if (!$requiresPositionChoice)
                        <form method="POST" action="{{ route('occurrences.join', ['occurrence' => $occ->id]) }}">
                            @csrf
                            <button type="submit" class="btn btn-primary">Записаться</button>
                        </form>
                        @else
                        <button
                            type="button"
                            class="btn btn-primary js-open-join"
                            data-occurrence-id="{{ (int)$occ->id }}"
                            data-title="{{ e($event?->title ?? '') }}"
                            data-date="{{ e($dt['date']) }}"
                            data-time="{{ e($dt['time']) }}"
                            data-tz="{{ e($dt['tzLabel'] ?? $dt['tz']) }}"
                            data-address="{{ e($address) }}"
                        >Записаться</button>
                        @endif
                        @if ($join->message)
                        <div class="w-100 small text-muted mt-1">{{ $join->message }}</div>
                        @endif

                    @endif
                </div>{{-- /кнопки --}}

            </div>
        </div>
    </div>
</div>

@endif
{{-- end _card.blade.php --}}