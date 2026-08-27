{{-- resources/views/events/_card.blade.php --}}
@php $event = $occ->event; @endphp

@if(!$event)
@else

@php
$joinedOccurrenceIds     = $joinedOccurrenceIds ?? [];
$restrictedOccurrenceIds = $restrictedOccurrenceIds ?? [];
$eventLikeCounts         = $eventLikeCounts ?? [];
$likedEventIds           = $likedEventIds ?? [];

$isJoined     = in_array((int)$occ->id, $joinedOccurrenceIds, true);
$joinDisabled = in_array((int)$occ->id, $restrictedOccurrenceIds, true);

$eventLikeCount = (int) ($eventLikeCounts[(int) $event->id] ?? 0);
$eventLiked     = in_array((int) $event->id, $likedEventIds, true);

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
		$maxPlayersCard += (int) (data_get($gs, 'reserve_players_max') ?? 0);

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

			// Для турнира — используем кол-во команд вместо max_players
			$isTournamentFmt    = ($fmt === 'tournament');
			$tournamentTeamsMax = $isTournamentFmt ? (int)($event->tournament_teams_count ?? 0) : 0;
			$gsSubtype          = (string)($gs?->subtype ?? '');
			$teamSizeForTmnt    = ($isTournamentFmt && preg_match('/^(\d+)x\d+$/i', $gsSubtype, $m)) ? (int)$m[1] : 2;
			if ($isTournamentFmt && $tournamentTeamsMax > 0) {
				$maxPlayersCard = $tournamentTeamsMax;
				$showSeatLine   = true;
			}

			// Бейджи "подтип игры" + "гендерная политика" (после строки организатора,
			// см. events/_card.blade.php ниже) — только если реально заданы.
			$subtypeDirKey  = $dir === 'beach' ? 'beach' : 'classic';
			$subtypeTipText = $gsSubtype !== '' ? __('events.subtype_tooltip.' . $subtypeDirKey . '.' . $gsSubtype) : null;
			if ($subtypeTipText === 'events.subtype_tooltip.' . $subtypeDirKey . '.' . $gsSubtype) {
				$subtypeTipText = null; // нет описания для этой схемы — бейдж без тултипа не показываем
			}

			$genderPolicy = (string) ($gs?->gender_policy ?? '');
			$genderBadgeLabel = null;
			$genderTipText = null;
			if (in_array($genderPolicy, ['only_male', 'only_female', 'mixed_5050', 'mixed_limited'], true)) {
				$genderBadgeLabel = __('events.gender_' . ($genderPolicy === 'mixed_5050' ? '5050' : $genderPolicy));
				if ($genderPolicy === 'mixed_limited') {
					$gLimitSide = $gs?->gender_limited_side;
					$gLimitMax  = $gs?->gender_limited_max;
					if ($gLimitSide && $gLimitMax) {
						$sideLabel = __('events.gender_limit_side_' . $gLimitSide . '_gen');
						$genderTipText = __('events.gender_tooltip_mixed_limited_with_limit', ['side' => $sideLabel, 'limit' => $gLimitMax]);
					} else {
						$genderTipText = __('events.gender_tooltip_mixed_limited');
					}
				} else {
					$genderTipText = __('events.gender_tooltip_' . ($genderPolicy === 'mixed_5050' ? 'mixed_5050' : $genderPolicy));
				}
			}

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

			// Статус мероприятия на карточке (регистрация/идёт сейчас/завершено) —
			// остальные случаи (рег. не началась/закрыта) бейджа не показывают.
			$endsAtUtc = ($startsAtUtc && !empty($occ->duration_sec))
			? $startsAtUtc->copy()->addSeconds((int) $occ->duration_sec)
			: null;
			$eventFinished = $endsAtUtc ? $nowUtc->gte($endsAtUtc) : false;
			$eventLive     = $eventStarted && !$eventFinished;

			if ($eventFinished) {
			$cardStatus = 'finished';
			$cardStatusLabel = __('events.card_status_finished');
			} elseif ($eventLive) {
			$cardStatus = 'live';
			$cardStatusLabel = __('events.card_status_live');
			} elseif ($canRegister) {
			$cardStatus = 'open';
			$cardStatusLabel = __('events.card_status_open');
			} else {
			$cardStatus = null;
			$cardStatusLabel = null;
			}

			// Бейдж возрастного ограничения — показываем ТОЛЬКО когда реально есть
			// ограничение (adult/child). Для "any" (без ограничений) бейдж не
			// рисуем вовсе — пустая пилюля "0+"/"Все" не несёт информации.
			$agePolicy = (string)($event?->age_policy ?? 'any');
			$ageBadgeLabel = null;
			if ($agePolicy === 'adult') {
				$ageBadgeLabel = __('events.card_age_adult');
			} elseif ($agePolicy === 'child') {
				$childMin = $event?->child_age_min;
				$childMax = $event?->child_age_max;
				if (!is_null($childMin) && !is_null($childMax)) {
					$ageBadgeLabel = __('events.card_age_child_range', ['min' => (int)$childMin, 'max' => (int)$childMax]);
				} elseif (!is_null($childMax)) {
					$ageBadgeLabel = __('events.card_age_child_max', ['max' => (int)$childMax]);
				} else {
					$ageBadgeLabel = __('events.card_age_child');
				}
			}

			$dirLabel = ($dir === 'beach') ? __('events.card_dir_beach') : (($dir === 'classic') ? __('events.card_dir_classic') : __('events.card_dir_dash'));
			$tzEvent  = (string)($occ->timezone ?: ($event?->timezone ?: 'UTC'));
			$tzUser   = ($userHasCityTz ?? false) ? ($userTz ?? $tzEvent) : $tzEvent;
			
			$sLocal = $occ->starts_at
			? \Illuminate\Support\Carbon::parse($occ->starts_at, 'UTC')->setTimezone($tzUser)
			: null;
			
			$eLocal = null;
			if ($sLocal && !empty($occ->duration_sec)) {
			$eLocal = $sLocal->copy()->addSeconds((int)$occ->duration_sec);
			}
			
			$dateLong  = $sLocal ? $sLocal->locale(app()->getLocale())->translatedFormat('d F') : '—';
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
			
			
			$levelScope = level_terminology_scope_for_event($event);

			$levelLabel = null;
			$levelTooltipHtml = null;
			if ($lvMin !== null || $lvMax !== null) {
			$minText = $lvMin !== null ? level_name_short($lvMin, $levelScope) : '—';
			$maxText = $lvMax !== null ? level_name_short($lvMax, $levelScope) : '—';
			$minSpan = $lvMin !== null
			? "<span class=\"levelmark levelmark--event level-{$lvMin}\">" . e($minText) . "</span>"
			: '<span class="levelmark levelmark--event level-minus">—</span>';

			$maxSpan = $lvMax !== null
			? "<span class=\"levelmark levelmark--event level-{$lvMax}\">" . e($maxText) . "</span>"
			: '<span class="levelmark levelmark--event level-minus">—</span>';

			$levelLabel = '<div class="level-range">' . $minSpan . '<span class="level-range-sep">—</span>' . $maxSpan . '</div>';

			// Тултип: ВСЕ уровни диапазона (включая промежуточные, не только края!),
			// полное название на бейдже .levelmark.level-N (та же плашка, что на
			// /level_players — гарантированный контраст текста в обеих темах, в
			// отличие от голого level_color() текстом на фоне тултипа), + краткое
			// описание (терминология — по scope события, как и сами бейджи).
			$levelTooltipDir = $dir === 'beach' ? 'beach' : 'classic';
			$rangeStart = $lvMin ?? $lvMax;
			$rangeEnd   = $lvMax ?? $lvMin;
			$levelsForTooltip = ($rangeStart !== null && $rangeEnd !== null)
			? range(min($rangeStart, $rangeEnd), max($rangeStart, $rangeEnd))
			: [];
			$tooltipRows = [];
			foreach ($levelsForTooltip as $lv) {
			$fullName = level_name((int)$lv, $levelScope);
			$desc = level_tooltip_description((int)$lv, $levelTooltipDir, $levelScope);
			$row = '<div class="level-tip-row"><span class="levelmark level-' . (int)$lv . '">' . e($fullName) . '</span>';
			if ($desc) {
			$row .= '<div class="level-tip-desc">' . e($desc) . '</div>';
			}
			$row .= '</div>';
			$tooltipRows[] = $row;
			}
			$levelTooltipHtml = implode('', $tooltipRows);
			}
			
			
			
			
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
				<div
				class="event-card card-ramka"
				data-direction="{{ e($dir) }}"
				data-format="{{ e($fmt) }}"
				data-classic-min="{{ e($clMin) }}"
				data-classic-max="{{ e($clMax) }}"
				data-beach-min="{{ e($bMin) }}"
				data-beach-max="{{ e($bMax) }}"
				>
					{{-- Фото --}}
					
					<div class="event-card-body">
						<div class="mb-1 -mt-05">
							<a href="{{ url('/events/' . (int)$event->id) . '?occurrence=' . (int)$occ->id }}" class="blink cd b-600 card-title ">
								@if(!empty($event?->is_private))
								<x-menu-icon name="eye-off" class="cd" title="{{ __('events.card_private_title') }}" />
								@endif
								@if($isTournamentFmt)
								<x-menu-icon name="trophy" class="cd" />
								@endif
								{{ $event?->title ?? '—' }}
							</a>
						</div>	
						<div class="d-flex event-address">
							<div class="emo f-16"><x-menu-icon name="pin" class="cd" /></div>
							<div class="f-16">{{ $address }}</div>
						</div>
						
						
						<div class="border f-0 mb-1 card-img-top">
							<a href="{{ $eventPageUrl }}">
								@if(!empty($event->event_photos) && count($event->event_photos) > 0)
								@php $firstPhoto = \Spatie\MediaLibrary\MediaCollections\Models\Media::find($event->event_photos[0]); @endphp
								@if($firstPhoto)
								<img src="/img/pixel.png" data-src="{{ $firstPhoto->getUrl('event_thumb') }}" alt="{{ $event?->title ?? '—' }}">
								@else
								<img src="/img/pixel.png" data-src="/img/{{ $event->direction === 'beach' ? 'beach.webp' : 'classic.webp' }}" alt="{{ $event?->title ?? '—' }}">
								@endif
								@elseif(!empty($coverUrl))
								<img src="/img/pixel.png" data-src="{{ $coverUrl }}" alt="{{ $event?->title ?? '—' }}">
								@else
								<img src="/img/pixel.png" data-src="/img/{{ $event->direction === 'beach' ? 'beach.webp' : 'classic.webp' }}" alt="{{ $event?->title ?? '—' }}">
								@endif

								<div class="event-direction {{ $event->direction === 'beach' ? 'beach-direction' : 'classic-direction' }}">{{ $dirLabel }}</div>
								<div class="event-price">{{ $priceLabel }}</div>
							</a>
						</div>
						
						
						
						
						<div class="event-column">
							<div class="event-col">
								<div class="event-col-icon icon-calendar"></div>
								<div class="event-col-data">
									<span class="d-inline-block">{{ $dateLong }}</span><span class="d-inline-block">{{ $timeRange }}</span>
								</div>
							</div>
							@if($levelLabel)
							<div class="event-col">
								<div class="event-col-icon icon-level"></div>
								<div class="event-col-data">
									<button type="button" class="level-badge-trigger js-open-level-info" data-target="level-info-{{ (int)$occ->id }}">
										{!! $levelLabel !!}
									</button>
								</div>
							</div>
							{{-- Поп-ап (fancybox) с полным описанием диапазона уровня — было
							тултипом-подсказкой, но при 3+ уровнях в диапазоне контент не
							помещался и не скроллился на экране. --}}
							<div id="level-info-{{ (int)$occ->id }}" style="display:none; max-width: 44rem">
								<h2 class="title-h -mt-05">{{ __('events.level_info_title') }}</h2>
								<div class="level-tip-modal-body">
									{!! $levelTooltipHtml !!}
								</div>
							</div>
							@endif
							<div class="event-col">
								<div class="event-col-icon icon-men"></div>
								@if($showSeatLine)
								<div
								class="event-col-data"
								data-seatline
								data-occurrence-id="{{ (int)$occ->id }}"
								data-registration-enabled="{{ $regEnabled ? '1' : '0' }}"
								data-reg-not-started="{{ $regNotStarted ? '1' : '0' }}"
								data-reg-closed="{{ $regClosed ? '1' : '0' }}"
								data-max-players="{{ (int)$maxPlayersCard }}"
								data-is-tournament="{{ $isTournamentFmt ? '1' : '0' }}"
								data-tournament-team-size="{{ $teamSizeForTmnt }}"
								>
									{{-- Слово-юнит ("команд"/"игроков") и подпись "Мест нет" убраны с карточки
									     по просьбе — оставлены только числа "X из Y" и полоска прогресса.
									     seatline_script.blade.php по-прежнему ищет [data-seat-unit] и
									     [data-seat-progress-full] через querySelector — оба null-guarded
									     (if(unitEl)/if(fullLbl)), удаление разметки ничего не ломает. --}}
									<div>
										<span class="b-600" data-left>0</span>
										<span class="text-muted">{{ __('events.card_seats_of') }}</span>
										<span class="b-600" data-total>{{ (int)$maxPlayersCard }}</span>
									</div>
									<div class="progress mt-1 mb-0" data-seat-progress-wrap>
										<div class="progress-bar bg-success" data-seat-progress-bar style="width:0%"></div>
									</div>
								</div>
								@elseif($regEnabled)
								{{ __('events.card_no_limit') }}
								@endif
							</div>
						</div>				
						
						
						{{--
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
						--}}
						
						{{--
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
						--}}
						
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
						if (!$organizerLabel && $orgId > 0) $organizerLabel = __('events.card_user_n', ['id' => $orgId]);
						$organizerUrl = $orgId > 0 ? url('/user/' . $orgId) : null;
                        @endphp
                        @if($organizerLabel)
						
						<div class="d-flex mb-05">
							<div class="emo f-16"><x-menu-icon name="organizer" class="cd" /></div>
							<div class="f-16">{{ __('events.card_organizer') }}  <a href="{{ $organizerUrl }}">{{ $organizerLabel }}</a></div>
						</div>
                        @endif
                        @endif

						@if($gsSubtype !== '' || $genderBadgeLabel || $ageBadgeLabel || $cardStatus)
						<div class="event-badges-row d-flex flex-wrap align-items-center gap-1 mb-05">
							@if($gsSubtype !== '')
								@if($subtypeTipText)
								<span class="info-tip js-info-tip">
									<span class="info-tip-trigger badge badge-sm">{{ $gsSubtype }}</span>
									<span class="info-tip-content">{{ $subtypeTipText }}</span>
								</span>
								@else
								<span class="badge-holder"><span class="badge badge-sm">{{ $gsSubtype }}</span></span>
								@endif
							@endif
							@if($genderBadgeLabel)
							<span class="info-tip js-info-tip">
								<span class="info-tip-trigger badge badge-sm">{{ $genderBadgeLabel }}</span>
								<span class="info-tip-content">{{ $genderTipText }}</span>
							</span>
							@endif
							@if($ageBadgeLabel)
							<span class="badge-holder"><span class="badge badge-sm">{{ $ageBadgeLabel }}</span></span>
							@endif
							@if($cardStatus)
							<span class="badge-holder"><span class="badge badge-sm status-{{ $cardStatus }}">{{ $cardStatusLabel }}</span></span>
							@endif
							@include('events._partials.like_badge')
						</div>
						@endif

						{{--
                        @if($levelLabel)
                        <div class="text-muted small mt-1">
                            🎚 Уровень: <span class="fw-semibold text-body">{{ $levelLabel }}</span>
						</div>
                        @endif
						--}}
						
						
						
						
                        @if($priceLabel)
						
						
						
                        @endif
						{{--
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
                            <span class="text-muted">{{ __('events.card_seats_of') }}</span>
                            <span class="fw-semibold" data-total>{{ (int)$maxPlayersCard }}</span>
                            <span class="text-muted">!</span>
						</div>
                        @elseif($regEnabled)
                        <div class="mt-2 small text-muted">🧑‍🧑‍🧒 Лимит мест не задан</div>
                        @endif
						--}}
					</div>
					
					{{-- Кнопки записи --}}
					<div class="mt-1">
						
						@if (!$regEnabled)
						<a href="{{ $eventPageUrl }}" class="btn w-100">{{ __('events.btn_details') }}</a>
						@else
						@if ($eventStarted2)
                        <div class="alert alert-info">{{ __('events.msg_event_started') }}</div>
						
						@elseif (!auth()->check())
                        <button type="button" class="btn w-100 js-open-login-popup" data-return-url="{{ $eventPageUrl }}">{{ __('events.btn_join') }}</button>

						@elseif ($joinCode === 'age_blocked')
                        <div class="alert alert-info">{{ $join->message }}</div>
						
						@elseif ($joinCode === 'level_too_high')
                        <div class="alert alert-info">{{ $join->message }}</div>
						
						@elseif ($joinCode === 'level_too_low')
                        <div class="alert alert-info">{{ $join->message }}</div>
						
						@elseif ($joinCode === 'team_only')
                        @if ($isJoined)
                        <a href="{{ $eventPageUrl }}" class="btn w-100">{{ __('events.btn_cancel_join') }}</a>
                        @else
                        <a href="{{ $eventPageUrl }}" class="btn w-100">{{ __('events.btn_join') }}</a>
                        @endif

						@elseif ($isJoined)
                        @if ($cancel?->allowed)
                        <a href="{{ $eventPageUrl }}" class="btn w-100">{{ __('events.msg_you_joined') }}</a>
                        @else
                        <div class="alert alert-info">{{ $cancel?->message ?? __('events.msg_cancel_blocked') }}</div>
                        @endif
						
						@elseif ($regNotStarted)
                        @php
						$regStartsLocal = $regStartsUtc ? $regStartsUtc->setTimezone($tzUser) : null;
                        @endphp
						
                        <div class="w-100">
                           {{-- <button class="btn w-100" disabled style="opacity:.55;cursor:not-allowed;">{{ __('events.btn_join') }}</button> --}}
                            <div class="alert alert-info">
                                {{ __('events.msg_reg_opens') }}
                                @if($regStartsLocal)
                                {{ $regStartsLocal->locale(app()->getLocale())->translatedFormat(__('events.dt_format_date_at_time')) }}
                                @endif
							</div>
						</div>
						
						@elseif ($regClosed2)
                        <div class="alert alert-info">{{ __('events.msg_reg_closed') }}</div>
						
						@elseif ($isGroupMode)
                        @if ($isBeachDirection)
                        <form class="w-100" method="POST" action="{{ route('occurrences.join', ['occurrence' => $occ->id]) }}">
                            @csrf
                            <button type="submit" class="btn w-100">{{ __('events.btn_join') }}</button>
						</form>
                        @else
                        <a href="{{ $eventPageUrl }}" class="btn w-100">{{ __('events.btn_join') }}</a>
                        @endif
						
						@elseif ($join === null)
                        {{-- occurrences не обогащены --}}
                        @if ($isBeachDirection)
                        <form class="w-100" method="POST" action="{{ route('occurrences.join', ['occurrence' => $occ->id]) }}">
                            @csrf
                            <button type="submit" class="btn w-100">{{ __('events.btn_join') }}</button>
						</form>
                        @else
                        <a href="{{ $eventPageUrl }}" class="btn w-100">{{ __('events.btn_join') }}</a>
                        @endif
						
						@elseif (!$join->allowed)
                        {{-- <button class="btn btn-primary" disabled style="opacity:.55;cursor:not-allowed;">{{ __('events.btn_join') }}</button> --}}
                        @if ($join->message)
                        <div class="alert alert-info">{{ $join->message }}</div>
                        @endif
						
						@else
                        @if (!$requiresPositionChoice)
                        <form class="w-100" method="POST" action="{{ route('occurrences.join', ['occurrence' => $occ->id]) }}">
                            @csrf
                            <button type="submit" class="btn w-100">{{ __('events.btn_join') }}</button>
						</form>
                        @else
                        <button
						type="button"
						class="btn w-100 js-open-join"
						data-occurrence-id="{{ (int)$occ->id }}"
						data-title="{{ e($event?->title ?? '') }}"
						data-date="{{ e($dt['date']) }}"
						data-time="{{ e($dt['time']) }}"
						data-tz="{{ e($dt['tzLabel'] ?? $dt['tz']) }}"
						data-address="{{ e($address) }}"
                        >{{ __('events.btn_join') }}</button>
                        @endif
                        @if ($join->message)
                        <div class="alert alert-info">{{ $join->message }}</div>
                        @endif
						
						@endif
					@endif{{-- /regEnabled --}}
					</div>{{-- /кнопки --}}
					
					
				</div>
			</div>
			
			
			@endif
		{{-- end _card.blade.php --}}										