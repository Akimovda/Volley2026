{{-- resources/views/volleyball_school/show.blade.php --}}
<x-voll-layout body_class="volleyball-school-show-page">
	
    <x-slot name="title">{{ $school->name }}</x-slot>
    <x-slot name="description">{{ Str::limit(strip_tags($school->description ?? $school->name), 160) }}</x-slot>
    <x-slot name="canonical">{{ route('volleyball_school.show', $school->slug) }}</x-slot>
    <x-slot name="h1">{{ $school->name }}</x-slot>
	
    @php
	$dirLabel = match($school->direction) {
	'classic' => 'Классический волейбол',
	'beach'   => 'Пляжный волейбол',
	'both'    => 'Классика и пляж',
	default   => ''
	};
	$organizer    = $school->organizer;
	$allCovers    = $organizer?->getMedia('school_cover')->sortBy(function($m) use ($school) {
	return $m->id == $school->cover_media_id ? 0 : 1;
	}) ?? collect();
	$coverMedia   = $allCovers->first();
	$logoMedia    = $organizer?->getMedia('school_logo')->firstWhere('id', $school->logo_media_id)
	?? $organizer?->getMedia('school_logo')->sortByDesc('created_at')->first();
	
	$logo = $logoMedia
	? ($logoMedia->hasGeneratedConversion('school_logo_thumb') ? $logoMedia->getUrl('school_logo_thumb') : $logoMedia->getUrl())
	: ($school->getFirstMediaUrl('logo', 'thumb') ?: $school->getFirstMediaUrl('logo'));
    @endphp
	
    <x-slot name="h2">{{ $dirLabel }}</x-slot>
    <x-slot name="t_description">@if($school->cityModel)г. {{ $school->cityModel->name }}@if($school->cityModel->region_display), {{ $school->cityModel->region_display }}@endif@elseif($school->city){{ $school->city }}@endif</x-slot>
	
    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('volleyball_school.index') }}" itemprop="item"><span itemprop="name">Школы волейбола</span></a>
            <meta itemprop="position" content="2">
		</li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <span itemprop="name">{{ $school->name }}</span>
            <meta itemprop="position" content="3">
		</li>
	</x-slot>
	
    <x-slot name="d_description">
        @if(auth()->check() && auth()->id() === $school->organizer_id)
		
		<div data-aos-delay="250" data-aos="fade-up">
			<a href="{{ route('volleyball_school.edit') }}" class="btn mt-2">Редактировать</a>
		</div> 		
		
        @endif
	</x-slot>
	
    <x-slot name="style">
		<style>
			.school-cover { width:100%; max-height:36rem; object-fit:cover; border-radius:1rem; display:block; }
			.school-cover-placeholder { width:100%; height:24rem; border-radius:1rem; background:linear-gradient(135deg,var(--bg2,#f0f0f0),var(--bg3,#e0e0e0)); display:flex; align-items:center; justify-content:center; flex-direction:column; gap:1rem; }
			.school-logo-big { width:8rem; height:8rem; border-radius:50%; object-fit:cover; border:0.3rem solid var(--bg2); flex-shrink:0; }
			.organizer-avatar { border-radius:50%; object-fit:cover; flex-shrink:0; }
			.live-dot { display:inline-block; width:0.8rem; height:0.8rem; border-radius:50%; background:#10b981; margin-right:0.5rem; vertical-align:middle; animation: liveDotPulse 1.5s infinite; }
			@keyframes liveDotPulse {
				0%   { box-shadow: 0 0 0 0 rgba(16,185,129,.6); }
				70%  { box-shadow: 0 0 0 0.6rem rgba(16,185,129,0); }
				100% { box-shadow: 0 0 0 0 rgba(16,185,129,0); }
			}
			/* Фильтр по дням — визуально как .day-chip на /events, но без привязки к её JS/скоупу */
			.school-days-sticky { position: sticky; top: 0; z-index: 50; padding: 0.8rem 1rem 0.2rem; margin-bottom: 1.5rem; }
			.school-days-topbar { display:flex; align-items:center; gap:0.8rem; margin-bottom:0.3rem; }
			.school-days-filter-btn {
				position: relative; width:4rem; height:4rem; flex-shrink:0; display:inline-flex;
				align-items:center; justify-content:center; border-radius:50%;
				border:0.2rem solid rgba(41,103,186,.25); background:rgba(255,255,255,.7);
				color:#2967BA; cursor:pointer; transition:all .2s ease;
			}
			.school-days-filter-btn.has-active { background:#2967BA; color:#fff; border-color:#2967BA; }
			body.dark .school-days-filter-btn { background:rgba(0,0,0,.2); }
			.school-days-strip { display:flex; gap:0.6rem; overflow-x:auto; padding:0.5rem 0 1rem; -webkit-overflow-scrolling:touch; flex:1; }
			@media (min-width: 768px) {
				.school-days-strip { justify-content:center; }
			}
			.school-day-chip {
				display:flex; flex-direction:column; align-items:center;
				padding:0.9rem 1.5rem 1rem; min-width:5.2rem; text-align:center;
				border-radius:1.2rem; text-decoration:none; color:inherit; cursor:pointer;
				user-select:none; line-height:1.15; flex-shrink:0; transition:background .2s ease;
			}
			.school-day-chip .dc-dow  { font-size:12px; font-weight:700; text-transform:uppercase; opacity:.7; }
			.school-day-chip .dc-date { font-weight:700; font-size:2.1rem; margin-top:0.1rem; }
			.school-day-chip .dc-dot  { display:block; width:0.6rem; height:0.6rem; border-radius:50%; background:#10b981; margin:0.5rem auto 0; }
			.school-day-chip .dc-dot.dc-dot--empty { background:transparent; }
			.school-day-chip.is-weekend .dc-dow { color:#ef4444; opacity:1; }
			.school-day-chip.active { background:transparent; box-shadow:inset 0 -0.3rem 0 0 #2967BA; }
			.school-day-chip.active .dc-dow, .school-day-chip.active .dc-date { color:#2967BA; opacity:1; }
			body.dark .school-day-chip.active { box-shadow:inset 0 -0.3rem 0 0 #E7612F; }
			body.dark .school-day-chip.active .dc-dow, body.dark .school-day-chip.active .dc-date { color:#E7612F; }
			.school-day-section-title {
				display:flex; align-items:center; gap:1.2rem;
				font-size:1.5rem; font-weight:700; text-transform:uppercase; letter-spacing:.02em;
				opacity:.75; margin:1.5rem 0 1rem; padding-left:0.2rem; white-space:nowrap;
			}
			.school-day-section-title::after { content:''; flex:1 1 auto; height:1px; background:currentColor; opacity:.3; }
			.school-day-section:first-of-type .school-day-section-title { margin-top:0; }
			.school-day-section-title.is-weekend { color:#ef4444; opacity:1; }
			.school-day-chip.school-day-nav { opacity:.75; }
			.school-day-chip.school-day-nav .dc-date { font-size:1.3rem; font-weight:600; margin-top:0.3rem; }
			.school-day-chip.school-day-nav .dc-dow { text-transform:none; }
		</style>
	</x-slot>
	
    <div class="container">		
        @if(session('status'))
        <div class="ramka"><div class="alert alert-success">{{ session('status') }}</div></div>
        @endif
		
		
		<div class="row">
			<div class="col-lg-4 col-xl-3 order-1 order-lg-1">
				<div class="sticky">
					<div class="ramka">
						<div class="text-center">
							@if($logo)
							<div class="profile-avatar mb-3">
								<img src="{{ $logo }}" alt="logo">
							</div>        
							@endif
						</div>
						
						<div class="row">
							<div class="col-sm-6 col-md-6 col-lg-12">
								<h2 class="-mt-05">Контакты</h2>
								@if($school->phone)    
								<div class="provider-card__header icon-light">
									<span class="provider-card__icon icon-tel"></span>
									<span class="provider-card__title"><a href="tel:{{ $school->phone }}">{{ preg_replace('/(\+7)(\d{3})(\d{3})(\d{2})(\d{2})/', '$1 ($2) $3-$4-$5', $school->phone) }}</a></span>
								</div>        
								@endif                        
								@if($school->email)    
								<div class="provider-card__header icon-light">
									<span class="provider-card__icon icon-mail"></span>
									<span class="provider-card__title"><a style="word-break: break-word;" href="mailto:{{ $school->email }}">{{ $school->email }}</a></span>
								</div>        
								@endif        
								
								@if($school->website)    
								<div class="provider-card__header icon-light">
									<span class="provider-card__icon icon-site"></span>
									<span class="provider-card__title"><a href="{{ $school->website }}" target="_blank" rel="nofollow">{{ preg_replace('#^https?://(www\.)?|/.*$#', '', $school->website) }}</a></span>
								</div>        
								@endif    
							</div>
							
							<div class="col-sm-6 col-md-6 col-lg-12 text-center">
								<div class="social-btns">
									@if($school->vk_url)    
									<a href="{{ $school->vk_url }}" target="_blank">
										<span class="provider-card__header">
											<span class="provider-card__icon icon-vk"></span>
										</span>  
									</a>
									@endif            
									
									@if($school->tg_url)  
									<a href="{{ $school->tg_url }}" target="_blank">
										<span class="provider-card__header">
											<span class="provider-card__icon icon-tg"></span>
										</span> 
									</a>	
									@endif        
									
									@if($school->max_url)  
									<a href="{{ $school->max_url }}" target="_blank">
										<span class="provider-card__header">
											<span class="provider-card__icon icon-max"></span>
										</span>  
									</a>    
									@endif            
									
									@if(!$school->phone && !$school->email && !$school->website && !$school->vk_url && !$school->tg_url && !$school->max_url)
									<div class="alert alert-error">Не указаны</div>
									@endif    
								</div>	
							</div>
							@if($organizer)
							<div class="col-12">
								<h2 class="mt-1">Организатор</h2>
								
								<div class="provider-card__header">
									<span class="provider-card__icon"><img src="{{ $organizer->profile_photo_url }}" alt="{{ $organizer->first_name }}" class="organizer-avatar"></span>
									<span class="provider-card__title"><a href="{{ route('users.show', $organizer->id) }}">{{ trim($organizer->first_name . ' ' . $organizer->last_name) }}</a></span>
								</div>                                        
							</div>
							@endif
						</div> 
					</div>
				</div> 
			</div> 
			
			<div class="col-lg-8 col-xl-9 order-2 order-lg-2">
				{{-- ОБЛОЖКА / СЛАЙДЕР --}}
				@if($allCovers->count() > 1)
				<div class="ramka">  
					<div class="swiper school-show-swiper">
						<div class="swiper-wrapper">
							@foreach($allCovers as $cm)
							@php
							$cmUrl = $cm->hasGeneratedConversion('school_cover_thumb')
							? $cm->getUrl('school_cover_thumb')
							: $cm->getUrl();
							@endphp
							<div class="swiper-slide">
								
								<div class="hover-image">
									<a href="{{ $cm->getUrl() }}" class="fancybox" data-fancybox="school-cover-gallery">
										<img src="{{ $cmUrl }}" alt="{{ $school->name }}" loading="lazy">
										<span></span>
										<div class="hover-image-circle"></div>
									</a>
								</div>							
								
							</div>
							@endforeach
						</div>
						<div class="swiper-pagination"></div>
					</div>
				</div>  <!-- ЗАКРЫВАЕМ ramka для слайдера -->
				@elseif($coverMedia)
				@php
				$coverUrl = $coverMedia->hasGeneratedConversion('school_cover_thumb')
				? $coverMedia->getUrl('school_cover_thumb')
				: $coverMedia->getUrl();
				@endphp
				<div class="ramka">
					<img src="{{ $coverUrl }}" alt="{{ $school->name }}" class="school-cover">
				</div>
				@else
				<div class="ramka">
						<div class="alert alert-info">
						Фотографий нет
						@if(auth()->check() && auth()->id() === $school->organizer_id)
							<div class="text-center mt-1">
						<a href="{{ route('user.photos') }}" class="btn">Добавить фото</a>
						</div>
						@endif						
						</div>

				</div>
				@endif

				@if($school->description)
				<div class="ramka">
					{!! $school->description !!}
				</div>
				@endif
			</div>  <!-- ЗАКРЫВАЕМ col-lg-8 -->
		</div>  <!-- ЗАКРЫВАЕМ row -->	
		
		
		
		
		
		
{{-- ТУРНИРЫ ШКОЛЫ --}}
@if($schoolTournaments->isNotEmpty())
<div class="ramka">
    <h2 class="-mt-05">Турниры</h2>

    {{-- Топ-5 игроков (OpenSkill Conservative Rating, см. /players/rating) --}}
    @if($schoolTopPlayers->isNotEmpty())
        <div class="card p-3 mb-3">
            <div class="b-600 f-14 mb-2">{{ __('tournaments.school_top_players_title') }}</div>
            @foreach($schoolTopPlayers as $i => $tp)
                <div class="d-flex f-13" style="padding:5px 0;border-bottom:1px solid rgba(128,128,128,.08);gap:8px;align-items:center">
                    <span class="b-700" style="width:20px">{{ $i + 1 }}</span>
                    <a href="{{ route('users.show', $tp->user_id) }}" class="blink" style="flex:1">
                        {{ $tp->user?->displayName() }}
                    </a>
                    <span class="b-700" style="color:#E7612F">{{ round($tp->cr, 1) }}</span>
                    <span style="opacity:.5">{{ $tp->won }}В/{{ $tp->played }}М</span>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Список турниров --}}
    @foreach($schoolTournaments as $tourn)
        @php
            $matchesCount = $tourn->tournamentStages->sum('matches_count');
            $stagesCount = $tourn->tournamentStages->count();
            $isActive = $tourn->tournamentStages->where('status', 'in_progress')->isNotEmpty();
        @endphp
        <div class="d-flex f-14" style="padding:8px 0;border-bottom:1px solid rgba(128,128,128,.08);gap:10px;align-items:center;flex-wrap:wrap">
            <div style="flex:1;min-width:150px">
                <a href="{{ route('tournament.public.show', $tourn->id) }}" class="blink b-600">
                    {{ $tourn->title }}
                </a>
                <div class="f-12" style="opacity:.5">
                    {{ $tourn->starts_at ? $tourn->starts_at->format('d.m.Y') : '' }}
                    @if($tourn->location) · {{ $tourn->location->name }} @endif
                </div>
            </div>
            @if($isActive)
                <span class="f-12 b-600" style="color:#10b981"><span class="live-dot"></span>LIVE</span>
            @endif
            <span class="f-12">
                {{ $tourn->direction === 'beach' ? '🏖' : '🏐' }} {{ $matchesCount }} матчей
            </span>
        </div>
    @endforeach

    @if($schoolTournaments->count() >= 10)
        <div class="mt-2" style="text-align:center">
            <span class="f-13" style="opacity:.5">Показаны последние 10 турниров</span>
        </div>
    @endif
</div>
@endif

{{-- АБОНЕМЕНТЫ --}}
		@if(isset($subscriptionTemplates) && $subscriptionTemplates->isNotEmpty())
		<div class="ramka">
			<h2 class="-mt-05">Абонементы</h2>
			<div class="row">
				@foreach($subscriptionTemplates as $t)
				@php
				$durationLabel = null;
				$dm = (int)($t->duration_months ?? 0);
				$dd = (int)($t->duration_days ?? 0);
				if ($dm > 0 || $dd > 0) {
				$parts = [];
				if ($dm > 0) $parts[] = $dm . ' ' . trans_choice('мес.|мес.|мес.', $dm);
				if ($dd > 0) $parts[] = $dd . ' ' . trans_choice('день|дня|дней', $dd);
				$durationLabel = implode(' ', $parts);
				}
				@endphp
				<div class="col-md-4">
					<div class="sub-gold-card" style="border-radius:1.6rem;overflow:hidden;position:relative;color:#1a1100;box-shadow:0 0.8rem 3rem rgba(180,140,0,.35);">
						<style>
							.sub-gold-card {
							background: linear-gradient(135deg, #bf953f, #fcf6ba, #b38728, #fbf5b7, #aa771c);
							}
							.sub-buy-shimmer {
							background: linear-gradient(90deg, #f5d78e 0%, #fff8e1 20%, #f5d78e 40%, #fff8e1 60%, #f5d78e 100%);
							background-size: 200% auto;
							-webkit-background-clip: text;
							background-clip: text;
							color: transparent;
							animation: goldShimmer 2.5s linear infinite;
							}
							@keyframes goldShimmer {
							0%   { background-position: 0% 50%; }
							100% { background-position: -200% 50%; }
							}
							.sub-gold-card .sub-buy-btn {
							background: linear-gradient(115deg,#1a1a2e,#0f3460,#1a4a8a,#0f3460,#1a1a2e);
							background-size: 300% 300%;
							animation: buyBtnShimmer 3s ease infinite;
							color: #f5d78e;
							width: 100%;
							padding: 1.2rem;
							border: none;
							border-radius: 1rem;
							font-size: 1.6rem;
							font-weight: 700;
							cursor: pointer;
							letter-spacing: .02em;
							transition: opacity .2s;
							text-transform: uppercase;
							}
							@keyframes buyBtnShimmer {
							0%   { background-position: 0% 50%; }
							50%  { background-position: 100% 50%; }
							100% { background-position: 0% 50%; }
							}
							.sub-gold-card .sub-buy-btn:hover { opacity: .85; }
							.sub-gold-card .sub-badge {
							background: rgba(0,0,0,.12);
							border-radius: 2rem;
							padding: .3rem .9rem;
							font-size: 1.3rem;
							color: #1a1100;
							}
						</style>
						{{-- Блик --}}
						<div style="position:absolute;top:-3rem;right:-3rem;width:12rem;height:12rem;border-radius:50%;background:rgba(255,255,255,.2);pointer-events:none;"></div>
						<div style="position:absolute;bottom:-2rem;left:-2rem;width:8rem;height:8rem;border-radius:50%;background:rgba(255,255,255,.1);pointer-events:none;"></div>
						
						<div style="padding:2rem 2rem 1.5rem;">
							{{-- Название --}}
							<div style="font-size:2rem;font-weight:700;margin-bottom:.5rem;letter-spacing:.02em;color:#1a1100;">{{ $t->name }}</div>
							
							{{-- Посещения --}}
							<div style="font-size:3.6rem;font-weight:800;line-height:1;margin-bottom:.3rem;color:#1a1100;">
								{{ $t->visits_total }}
								<span style="font-size:1.6rem;font-weight:400;opacity:.7;">посещений</span>
							</div>
							
							{{-- Срок --}}
							<div style="font-size:1.4rem;opacity:.7;margin-bottom:1.5rem;color:#3a2800;">
								@if($durationLabel)
								⏱ Действует {{ $durationLabel }} с момента покупки
								@else
								♾ Бессрочный абонемент
								@endif
							</div>
							
							{{-- Фичи --}}
							<div style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:1.5rem;">
								@if($t->freeze_enabled)
								<span class="sub-badge">❄️ Заморозка</span>
								@endif
								@if($t->transfer_enabled)
								<span class="sub-badge">🔄 Передача</span>
								@endif
								@if($t->sale_limit)
								<span class="sub-badge">
									🎟 Осталось: {{ max(0, $t->sale_limit - $t->sold_count) }}
								</span>
								@endif
							</div>
							
							{{-- Цена --}}
							<div style="font-size:2.8rem;font-weight:800;color:#1a1100;">
								{{ $t->price_minor > 0 ? number_format($t->price_minor/100, 0, '.'  , ' ').' ₽' : 'Бесплатно' }}
							</div>
						</div>
						
						{{-- Кнопка --}}
						<div style="padding:0 2rem 2rem;">
							@auth
							@if(!$t->isSoldOut())
							<form method="POST" action="{{ route('subscriptions.buy', $t->id) }}">
								@csrf
								{{--
								<button type="submit" class="w-100 btn">
									{{ $t->price_minor > 0 ? 'Купить абонемент' : 'Получить абонемент' }}
								</button>
								--}}
								<button type="submit" class="sub-buy-btn">
									@if($t->price_minor > 0)
								<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#f5d78e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-3px;margin-right:0.4rem">
									<rect x="2" y="5" width="20" height="14" rx="2"></rect>
									<line x1="2" y1="10" x2="22" y2="10"></line>
								</svg>
								@else
								🎫
								@endif
								<span class="sub-buy-shimmer">{{ $t->price_minor > 0 ? 'Купить абонемент' : 'Получить абонемент' }}</span>
								</button>
								
							</form>
							@else
							<button disabled style="width:100%;padding:1.2rem;border:none;border-radius:1rem;background:rgba(255,255,255,.1);color:rgba(255,255,255,.4);font-size:1.6rem;font-weight:700;cursor:not-allowed;">
								Продано
							</button>
							@endif
							@else
							<a href="{{ route('login') }}" style="display:block;width:100%;padding:1.2rem;border:none;border-radius:1rem;background:linear-gradient(135deg,#e2b96f,#f5d78e);color:#1a1a2e;font-size:1.6rem;font-weight:700;text-align:center;text-decoration:none;">
								Войти для покупки
							</a>
							@endauth
						</div>
					</div>
				</div>
				@endforeach
			</div>
		</div>
		@endif
		
		{{-- МЕРОПРИЯТИЯ --}}
		@if(!$schoolHasAnyUpcomingEvents)
		<div class="ramka">
			<div class="alert alert-info">Предстоящих мероприятий пока нет.</div>
		</div>
		@else
		@php
		$fmtDate = function($occ) {
		$tz = $occ->timezone ?: 'Europe/Moscow';
		if (!$occ->starts_at) return ['date'=>'—','time'=>'—','tz'=>$tz,'tzLabel'=>$tz,'raw'=>null];
		$dt = \Carbon\Carbon::parse($occ->starts_at)->setTimezone($tz);
		return ['date'=>$dt->translatedFormat('j M Y'),'time'=>$dt->format('H:i'),'tz'=>$tz,'tzLabel'=>'MSK (UTC+03:00)','raw'=>$dt];
		};
		$joinedOccurrenceIds     = [];
		$restrictedOccurrenceIds = [];
		$trainerColumn           = null;
		$trainersById            = [];
		$guard                   = app(\App\Services\EventRegistrationGuard::class);
		$nowUtc                  = \Carbon\Carbon::now('UTC');
		$userTz                  = auth()->user()?->timezone ?? 'Europe/Moscow';
		$authUser                = auth()->user();
		@endphp
		
		@php
			// Мероприятия по датам — как на /events: непрерывная шкала дней от
			// первого до последнего тура, дни без мероприятий тоже есть в чипах
			// (пустая точка), но собственную секцию в списке не занимают.
			$occByDate = $occurrences->groupBy(function($occ) {
				$tz = $occ->timezone ?: 'Europe/Moscow';
				return $occ->starts_at ? \Carbon\Carbon::parse($occ->starts_at)->setTimezone($tz)->format('Y-m-d') : 'unknown';
			});
			$daysOfWeek = __('events.dow_short');

			$fFormat = trim((string) request('format', ''));
			$levelRaw = request('level');
			$fLevel = ($levelRaw === null || $levelRaw === '') ? '' : (int) $levelRaw;
			$levelOptions = [1, 2, 3, 4, 5, 6, 7];
			$formatLabels = [
				'game'               => __('events.fmt_game'),
				'training'           => __('events.fmt_training'),
				'training_game'      => __('events.fmt_training_game'),
				'coach_student'      => __('events.fmt_coach_student'),
				'tournament'         => __('events.fmt_tournament'),
				'tournament_classic' => __('events.fmt_tournament_classic'),
				'tournament_beach'   => __('events.fmt_tournament_beach'),
				'camp'               => __('events.fmt_camp'),
				'master_class'       => __('events.fmt_master_class'),
			];
			$formatDirections = [
				'coach_student'      => ['beach'],
				'tournament_classic' => ['classic'],
				'tournament_beach'   => ['beach'],
			];
			$formatLabelsFiltered = $school->direction === 'both'
				? $formatLabels
				: array_filter($formatLabels, fn($k) => in_array($school->direction, $formatDirections[$k] ?? ['classic', 'beach'], true), ARRAY_FILTER_USE_KEY);
			$hasActiveSecondaryFilters = $fFormat !== '' || $fLevel !== '';

			// Окно — ровно 10 календарных дней от $windowStartDate (вычислен в
			// контроллере — как на /events), а не диапазон "от первого до
			// последнего тура" — иначе пустая точка "нет событий" была бы
			// бессмысленна (при коротком диапазоне почти все дни заполнены).
			$today = \Carbon\Carbon::now($windowTz)->startOfDay();
			$windowStart = \Carbon\Carbon::createFromFormat('Y-m-d', $windowStartDate, $windowTz)->startOfDay();
			$windowDays = [];
			for ($i = 0; $i < 10; $i++) {
				$windowDays[] = $windowStart->copy()->addDays($i);
			}

			$prevWindowStart = $windowStart->copy()->subDays(10);
			if ($prevWindowStart->lt($today)) $prevWindowStart = $today->copy();
			$showPrevNav = $windowStart->gt($today);
			$nextDateParam = $windowStart->copy()->addDays(10)->format('Y-m-d');
			$prevDateParam = $prevWindowStart->format('Y-m-d');
			$baseParams = array_filter([
				'format' => $fFormat,
				'level'  => $fLevel,
			], fn($v) => $v !== '' && $v !== null);
		@endphp

		{{-- Фильтр по дням + фильтр по типу/уровню (как на /events) --}}
		<div class="card-ramka school-days-sticky" id="schoolDaysSticky">
			<div class="school-days-topbar">
				<button type="button" id="schoolBtnOpenFilters"
					class="school-days-filter-btn{{ $hasActiveSecondaryFilters ? ' has-active' : '' }}"
					title="{{ __('events.btn_filter') }}" aria-label="{{ __('events.btn_filter') }}">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
						<line x1="4" y1="6" x2="20" y2="6"></line><circle cx="9" cy="6" r="2" fill="currentColor" stroke="none"></circle>
						<line x1="4" y1="12" x2="20" y2="12"></line><circle cx="16" cy="12" r="2" fill="currentColor" stroke="none"></circle>
						<line x1="4" y1="18" x2="20" y2="18"></line><circle cx="11" cy="18" r="2" fill="currentColor" stroke="none"></circle>
					</svg>
				</button>
				<div class="school-days-strip" id="schoolDaysStrip">
					@if($showPrevNav)
					<a href="{{ route('volleyball_school.show', array_merge(['slug' => $school->slug], $baseParams, ['date' => $prevDateParam])) }}#schoolDaysSticky"
					   class="school-day-chip school-day-nav">
						<span class="dc-dow">{{ __('events.days_prev') }}</span>
						<span class="dc-date">{{ __('events.days_n_days') }}</span>
					</a>
					@endif
					@foreach($windowDays as $d)
						@php
							$dateKey = $d->format('Y-m-d');
							$isWeekend = in_array($d->dayOfWeekIso, [6, 7], true);
							$hasEvents = $occByDate->has($dateKey);
						@endphp
						<a href="#day-{{ $dateKey }}"
						   class="school-day-chip js-school-day-chip {{ $loop->first ? 'active' : '' }} {{ $isWeekend ? 'is-weekend' : '' }}"
						   data-target="day-{{ $dateKey }}">
							<span class="dc-dow">{{ $daysOfWeek[$d->dayOfWeekIso] ?? '' }}</span>
							<span class="dc-date">{{ $d->format('j') }}</span>
							<span class="dc-dot {{ $hasEvents ? '' : 'dc-dot--empty' }}"></span>
						</a>
					@endforeach
					<a href="{{ route('volleyball_school.show', array_merge(['slug' => $school->slug], $baseParams, ['date' => $nextDateParam])) }}#schoolDaysSticky"
					   class="school-day-chip school-day-nav">
						<span class="dc-dow">{{ __('events.days_next') }}</span>
						<span class="dc-date">{{ __('events.days_n_days') }}</span>
					</a>
				</div>
			</div>
		</div>

		{{-- Поп-ап "Фильтры" (fancybox inline) — тип/уровень, как на /events --}}
		<div id="schoolFilterModal" style="display:none;max-width:48rem">
			<h2 class="title-h -mt-05">{{ __('events.btn_filter') }}</h2>
			<div class="form" style="overflow:visible">
				<form method="GET" action="{{ route('volleyball_school.show', $school->slug) }}">
					<div class="row g-2">
						<div class="col-12">
							<label class="form-label mb-1">{{ __('events.filter_event_type') }}</label>
							<select name="format" class="form-select">
								<option value="" {{ $fFormat === '' ? 'selected' : '' }}>{{ __('events.filter_any') }}</option>
								@foreach($formatLabelsFiltered as $k => $lbl)
								<option value="{{ $k }}" {{ $fFormat === $k ? 'selected' : '' }}>{{ $lbl }}</option>
								@endforeach
							</select>
						</div>
						<div class="col-12">
							<label class="form-label mb-1">{{ __('events.filter_level') }}</label>
							<select name="level" class="form-select">
								<option value="" {{ $fLevel === '' ? 'selected' : '' }}>{{ __('events.filter_any_level') }}</option>
								@foreach($levelOptions as $lv)
								<option value="{{ $lv }}" {{ (string) $fLevel === (string) $lv ? 'selected' : '' }}>{{ level_filter_label($lv, level_terminology_scope_for_user(auth()->user())) }}</option>
								@endforeach
							</select>
						</div>
						<div class="col-12 d-flex flex-wrap gap-2 align-items-center mt-1">
							<button type="submit" class="btn">{{ __('events.filter_apply') }}</button>
							<a href="{{ route('volleyball_school.show', $school->slug) }}#schoolDaysSticky" class="btn btn-secondary">{{ __('events.filter_reset') }}</a>
						</div>
					</div>
				</form>
			</div>
		</div>

		<div id="schoolDaysFeed">
			@if(count($windowDays) > 1)
				@foreach($windowDays as $d)
				@php
					$dateKey = $d->format('Y-m-d');
					$dayOccs = $occByDate->get($dateKey, collect());
					$isWeekendSection = in_array($d->dayOfWeekIso, [6, 7], true);
					$isToday = $d->isSameDay($today);
					$isTomorrow = $d->isSameDay($today->copy()->addDay());
					$dayPrefix = $isToday ? __('events.day_header_today') : ($isTomorrow ? __('events.day_header_tomorrow') : null);
					$dayHeaderLabel = ($dayPrefix ? $dayPrefix . ' · ' : '') . ($daysOfWeek[$d->dayOfWeekIso] ?? '') . ', ' . $d->translatedFormat('j F');
				@endphp
				<section class="school-day-section" id="day-{{ $dateKey }}">
					<div class="school-day-section-title {{ $isWeekendSection ? 'is-weekend' : '' }}">{{ $dayHeaderLabel }}</div>
					@if($dayOccs->isEmpty())
					<div class="ramka">
						<div class="alert alert-info">
							<div>{{ __('events.empty_list_day') }}</div>
							<div class="f-13 text-muted mt-05">{{ __('events.empty_list_day_notify') }}</div>
						</div>
					</div>
					@else
					<div class="row">
						@foreach($dayOccs as $occ)
						@php
						$event = $occ->event;
						if (!$event) continue;
						if (!isset($occ->join)) {
						$occ->join   = $authUser ? $guard->quickCheck($authUser, $occ) : null;
						$occ->cancel = null;
						}
						@endphp
						@include('events._card', ['occ' => $occ, 'join' => $occ->join, 'cancel' => $occ->cancel])
						@endforeach
					</div>
					@endif
				</section>
				@endforeach
			@else
				@foreach($occByDate as $dateKey => $dayOccs)
				@php
					$d = $dateKey !== 'unknown' ? \Carbon\Carbon::createFromFormat('Y-m-d', $dateKey) : null;
					$isWeekendSection = $d && in_array($d->dayOfWeekIso, [6, 7], true);
				@endphp
				<section class="school-day-section" id="day-{{ $dateKey }}">
					<div class="school-day-section-title {{ $isWeekendSection ? 'is-weekend' : '' }}">{{ $d ? $d->translatedFormat('l, j F') : 'Дата не указана' }}</div>
					<div class="row">
						@foreach($dayOccs as $occ)
						@php
						$event = $occ->event;
						if (!$event) continue;
						if (!isset($occ->join)) {
						$occ->join   = $authUser ? $guard->quickCheck($authUser, $occ) : null;
						$occ->cancel = null;
						}
						@endphp
						@include('events._card', ['occ' => $occ, 'join' => $occ->join, 'cancel' => $occ->cancel])
						@endforeach
					</div>
				</section>
				@endforeach
			@endif

			@if($occByDate->has('unknown') && count($windowDays) > 1)
			<section class="school-day-section" id="day-unknown">
				<div class="school-day-section-title">Дата не указана</div>
				<div class="row">
					@foreach($occByDate->get('unknown') as $occ)
					@php
					$event = $occ->event;
					if (!$event) continue;
					if (!isset($occ->join)) {
					$occ->join   = $authUser ? $guard->quickCheck($authUser, $occ) : null;
					$occ->cancel = null;
					}
					@endphp
					@include('events._card', ['occ' => $occ, 'join' => $occ->join, 'cancel' => $occ->cancel])
					@endforeach
				</div>
			</section>
			@endif
		</div>

		@endif
		
	</div>
	
	{{-- JOIN MODAL (Fancybox inline) --}}
	<div id="joinModalContent" style="display:none;max-width:420px;width:100%;padding:1.5rem">
		<h2 id="jmTitle" class="-mt-05 f-20 b-600">Запись на мероприятие</h2>
		<div id="jmMeta" class="f-14 mb-05" style="opacity:.6"></div>
		<div id="jmAddr" class="f-14 mb-2" style="opacity:.6"></div>
		<div id="jmError" class="alert alert-error" style="display:none"></div>
		<div id="jmLoading" class="f-14 mb-1" style="display:none;opacity:.6">Загружаю позиции…</div>
		<div id="jmPositions"></div>
		<div class="f-13 mt-2" style="opacity:.5">После выбора позиции вы сразу будете записаны.</div>
	</div>
	<form id="joinForm" method="POST" action="" style="display:none">
		@csrf
		<input type="hidden" name="position" id="joinPosition" value="">
	</form>

	@guest
	@include('auth._login_popup')
	@endguest

	<x-slot name="script">
		<script src="/assets/fas.js"></script>
		<script>
			// Swiper для обложек школы
			if (document.querySelector('.school-show-swiper')) {
				new Swiper('.school-show-swiper', {
					loop: true,
					slidesPerView: 1,
					spaceBetween: 20,
					autoplay: { delay: 4000, disableOnInteraction: false },
					pagination: { el: '.swiper-pagination', clickable: true },
					breakpoints: {
						540: { slidesPerView: 2 },
						
					}
				});
			}

			// Sticky-лента дат — отступ от реальной высоты фикс-шапки (не хардкод, см. getFixedHeaderBottom)
			var schoolDaysSticky = document.getElementById('schoolDaysSticky');
			function positionSchoolDaysSticky() {
				if (!schoolDaysSticky || !window.getFixedHeaderBottom) return;
				schoolDaysSticky.style.top = window.getFixedHeaderBottom() + 'px';
			}
			positionSchoolDaysSticky();
			window.addEventListener('resize', positionSchoolDaysSticky);
			window.addEventListener('orientationchange', positionSchoolDaysSticky);
			window.addEventListener('load', positionSchoolDaysSticky);
			document.addEventListener('vp:header-resize', positionSchoolDaysSticky);

			// После применения фильтра/навигации prev-next (полная перезагрузка страницы
			// через GET) браузер по умолчанию открывает страницу сверху — не полагаемся
			// только на #schoolDaysSticky в href (GET-формы не все браузеры одинаково
			// надёжно сохраняют fragment при сабмите), подстраховываем явным скроллом,
			// если в URL есть признак применённого фильтра/навигации по датам.
			if (location.search && /(?:^|[?&])(format|level|date)=/.test(location.search) && schoolDaysSticky) {
				schoolDaysSticky.scrollIntoView({ block: 'start' });
			}

			// ===== Лента дней: чипы ↔ скролл (двусторонняя связь, как на /events) =====
			try {
				function schoolStickyBottom() {
					return schoolDaysSticky ? schoolDaysSticky.getBoundingClientRect().bottom : 0;
				}

				var suppressSchoolObserverUntil = 0;

				function centerSchoolChipInStrip(chip) {
					var strip = document.getElementById('schoolDaysStrip');
					if (!strip || !chip) return;
					var stripRect = strip.getBoundingClientRect();
					var chipRect = chip.getBoundingClientRect();
					var delta = (chipRect.left + chipRect.right) / 2 - (stripRect.left + stripRect.right) / 2;
					strip.scrollLeft += delta;
				}

				function setActiveSchoolChip(dateKey, centerChip) {
					document.querySelectorAll('.js-school-day-chip[data-target]').forEach(function (c) {
						c.classList.toggle('active', c.dataset.target === 'day-' + dateKey);
					});
					if (centerChip) {
						var chip = document.querySelector('.js-school-day-chip[data-target="day-' + dateKey + '"]');
						if (chip) centerSchoolChipInStrip(chip);
					}
				}

				function scrollToSchoolDaySection(dateKey) {
					suppressSchoolObserverUntil = performance.now() + 1500;
					var target = document.getElementById('day-' + dateKey);
					if (!target) return;

					function alignedTop() {
						return Math.max(0, target.getBoundingClientRect().top + window.pageYOffset - schoolStickyBottom() - 12);
					}

					window.scrollTo({ top: alignedTop(), behavior: 'smooth' });

					function finalizeActiveChip() {
						setActiveSchoolChip(dateKey, true);
						requestAnimationFrame(function () { setActiveSchoolChip(dateKey, true); });
					}

					if ('onscrollend' in window) {
						var onEnd = function () {
							window.removeEventListener('scrollend', onEnd);
							window.scrollTo({ top: alignedTop(), behavior: 'auto' });
							finalizeActiveChip();
						};
						window.addEventListener('scrollend', onEnd);
					} else {
						var lastY = window.scrollY, stableFrames = 0, ticks = 0, maxTicks = 180;
						function poll() {
							ticks++;
							var y = window.scrollY;
							if (y === lastY) { stableFrames++; } else { stableFrames = 0; lastY = y; }
							if (stableFrames >= 3 || ticks >= maxTicks) {
								window.scrollTo({ top: alignedTop(), behavior: 'auto' });
								finalizeActiveChip();
								return;
							}
							requestAnimationFrame(poll);
						}
						requestAnimationFrame(poll);
					}
				}

				document.querySelectorAll('.js-school-day-chip[data-target]').forEach(function (chip) {
					chip.addEventListener('click', function (e) {
						e.preventDefault();
						var dateKey = chip.dataset.target.replace('day-', '');
						setActiveSchoolChip(dateKey, true);
						scrollToSchoolDaySection(dateKey);
					});
				});

				if ('IntersectionObserver' in window) {
					var schoolSections = Array.prototype.slice.call(document.querySelectorAll('.school-day-section[id^="day-"]'));
					if (schoolSections.length) {
						function recomputeActiveSchoolDay() {
							if (performance.now() < suppressSchoolObserverUntil) return;
							var boundary = schoolStickyBottom() + 12;
							var bestDate = null, bestTop = -Infinity;
							schoolSections.forEach(function (s) {
								var top = s.getBoundingClientRect().top;
								if (top <= boundary + 1 && top > bestTop) {
									bestTop = top;
									bestDate = s.id.replace('day-', '');
								}
							});
							if (!bestDate) bestDate = schoolSections[0].id.replace('day-', '');
							setActiveSchoolChip(bestDate, true);
						}

						var schoolStickyH = schoolDaysSticky ? schoolDaysSticky.getBoundingClientRect().height : 100;
						var schoolObserver = new IntersectionObserver(recomputeActiveSchoolDay, {
							root: null,
							rootMargin: '-' + Math.ceil(schoolStickyH + 20) + 'px 0px -70% 0px',
							threshold: 0,
						});
						schoolSections.forEach(function (s) { schoolObserver.observe(s); });
					}
				}
			} catch (e) {
				console.error('school day-feed scroll sync error', e);
			}

			var schoolBtnOpenFilters = document.getElementById('schoolBtnOpenFilters');
			if (schoolBtnOpenFilters) {
				schoolBtnOpenFilters.addEventListener('click', function () {
					jQuery.fancybox.open({
						src: '#schoolFilterModal',
						type: 'inline',
						opts: { hideScrollbar: false, touch: false, toolbar: false, smallBtn: true, animationEffect: 'zoom-in-out', transitionEffect: 'zoom-in-out', baseClass: 'school-filter-fancybox' }
					});
				});
			}
		</script>
		<script>
			const positionNames = {
				outside: 'Доигровщик', opposite: 'Диагональный',
				middle: 'ЦБ', setter: 'Связующий', libero: 'Либеро', player: 'Игрок',
			};
			const titleEl   = document.getElementById('jmTitle');
			const metaEl    = document.getElementById('jmMeta');
			const addrEl    = document.getElementById('jmAddr');
			const posWrap   = document.getElementById('jmPositions');
			const errBox    = document.getElementById('jmError');
			const loadingEl = document.getElementById('jmLoading');
			const joinForm  = document.getElementById('joinForm');
			const joinPos   = document.getElementById('joinPosition');
			
			function showError(msg) { if(errBox){errBox.textContent=msg;errBox.style.display='';} }
			function clearError()   { if(errBox){errBox.textContent='';errBox.style.display='none';} }
			function setLoading(v)  { if(loadingEl) loadingEl.style.display = v ? '' : 'none'; }
			
			function openJoinModal(payload) {
				clearError(); setLoading(true);
				posWrap.innerHTML = '';
				titleEl.textContent = payload.title || 'Запись на мероприятие';
				metaEl.textContent  = [payload.date, payload.time, payload.tz ? '('+payload.tz+')' : ''].filter(Boolean).join(' ');
				addrEl.textContent  = payload.address || '';
				jQuery.fancybox.open({
					src: '#joinModalContent', type: 'inline',
					opts: { touch: false, animationEffect: false, toolbar: false, smallBtn: true }
				});
			}
			
			function renderPositions(occurrenceId, freePositions) {
				posWrap.innerHTML = '';
				setLoading(false);
				if (!Array.isArray(freePositions) || !freePositions.length) {
					showError('Свободных мест нет или нет доступных позиций.'); return;
				}
				freePositions.forEach(p => {
					const label = positionNames[p.key] || p.key;
					const free  = p.free ?? 0;
					const btn   = document.createElement('button');
					btn.type = 'button';
					btn.className = 'btn w-100 mb-1';
					btn.innerHTML = label + ' <span style="opacity:.6;font-size:1.4rem;">(' + free + ')</span>';
					btn.addEventListener('click', () => {
						joinForm.action = '/occurrences/' + occurrenceId + '/join';
						joinPos.value = p.key;
						joinForm.submit();
					});
					posWrap.appendChild(btn);
				});
			}
			
			async function fetchAvailability(occurrenceId) {
				const res  = await fetch('/occurrences/' + occurrenceId + '/availability', {
					headers: { 'Accept': 'application/json' }, credentials: 'same-origin'
				});
				const data = await res.json().catch(() => null);
				if (!res.ok || !data) { showError('Не удалось получить данные.'); return null; }
				return data;
			}
			
			document.querySelectorAll('.js-open-join').forEach(btn => {
				btn.addEventListener('click', async () => {
					const occurrenceId = btn.dataset.occurrenceId;
					openJoinModal({ title: btn.dataset.title, date: btn.dataset.date, time: btn.dataset.time, tz: btn.dataset.tz, address: btn.dataset.address });
					const data = await fetchAvailability(occurrenceId);
					setLoading(false);
					if (!data) return;
					renderPositions(occurrenceId, data.free_positions || data.data?.free_positions || []);
				});
			});

			@include('events._partials.seatline_script')
		</script>
	</x-slot>

</x-voll-layout>
