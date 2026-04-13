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
	$logoMedia    = $organizer?->getMedia('school_logo')->sortByDesc('created_at')->first();
	
	$logo = $logoMedia
	? ($logoMedia->hasGeneratedConversion('school_logo_thumb') ? $logoMedia->getUrl('school_logo_thumb') : $logoMedia->getUrl())
	: ($school->getFirstMediaUrl('logo', 'thumb') ?: $school->getFirstMediaUrl('logo'));
    @endphp
	
    <x-slot name="h2">{{ $dirLabel }}</x-slot>
    <x-slot name="t_description">@if($school->cityModel)г. {{ $school->cityModel->name }}@if($school->cityModel->region), {{ $school->cityModel->region }}@endif@elseif($school->city){{ $school->city }}@endif</x-slot>
	
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
		</style>
	</x-slot>
	
    <div class="container">		
        @if(session('status'))
        <div class="ramka"><div class="alert alert-success">{{ session('status') }}</div></div>
        @endif
		
		
		<div class="row row2">
			<div class="col-lg-4 col-xl-4 order-1 order-lg-1">
				<div class="sticky">
					<div class="ramka">
						<div class="text-center">
							@if($logo)
							<div class="profile-avatar">
								<img src="{{ $logo }}" alt="logo">
							</div>        
							@endif
						</div>
						
						<div class="row">
							<div class="col-sm-12">
								@if($school->phone)    
								<div class="provider-card__header icon-light">
									<span class="provider-card__icon icon-tel"></span>
									<span class="provider-card__title"><a href="tel:{{ $school->phone }}">{{ preg_replace('/(\+7)(\d{3})(\d{3})(\d{2})(\d{2})/', '$1 ($2) $3-$4-$5', $school->phone) }}</a></span>
								</div>        
								@endif                        
								@if($school->email)    
								<div class="provider-card__header">
									<span class="provider-card__icon icon-mail"></span>
									<span class="provider-card__title"><a href="mailto:{{ $school->email }}">{{ $school->email }}</a></span>
								</div>        
								@endif        
								
								@if($school->website)    
								<div class="provider-card__header">
									<span class="provider-card__icon icon-site"></span>
									<span class="provider-card__title"><a href="{{ $school->website }}" target="_blank" rel="nofollow">{{ preg_replace('#^https?://(www\.)?|/.*$#', '', $school->website) }}</a></span>
								</div>        
								@endif    
							</div>
							
							<div class="col-sm-12 text-center">
							<div class="d-flex fvc">
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
								<h2 class="-mt-05">Организатор</h2>
								
								<div class="provider-card__header">
									<span class="provider-card__icon"><img src="{{ $organizer->profile_photo_url }}" alt="{{ $organizer->first_name }}" class="organizer-avatar"></span>
									<span class="provider-card__title"><a href="{{ route('users.show', $organizer->id) }}">{{ trim($organizer->first_name . ' ' . $organizer->last_name) }}</a></span>
								</div>                                        
							</div>
							@endif
						</div>  <!-- ЗАКРЫВАЕМ row -->
					</div>  <!-- ЗАКРЫВАЕМ ramka -->
				</div>  <!-- ЗАКРЫВАЕМ sticky -->
			</div>  <!-- ЗАКРЫВАЕМ col-lg-4 -->
			
			<div class="col-lg-8 col-xl-8 order-2 order-lg-2">
				@if($school->description)
				<div class="ramka">
					{!! $school->description !!}
				</div>
				@endif
				
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
								<img src="{{ $cmUrl }}" alt="{{ $school->name }}" class="school-cover">
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
					<div class="school-cover-placeholder">
						<div style="font-size:5rem;">🏐</div>
						<div class="f-16" style="opacity:.4;">Обложка не добавлена</div>
						@if(auth()->check() && auth()->id() === $school->organizer_id)
						<a href="{{ route('user.photos') }}" class="btn btn-secondary">+ Добавить обложку</a>
						@endif
					</div>
				</div>    
				@endif
			</div>  <!-- ЗАКРЫВАЕМ col-lg-8 -->
		</div>  <!-- ЗАКРЫВАЕМ row2 -->	
		
		
		
		
		
		{{-- АБОНЕМЕНТЫ --}}
		@if(isset($subscriptionTemplates) && $subscriptionTemplates->isNotEmpty())
		<div class="ramka">
			<h2 class="-mt-05">Абонементы</h2>
			<div class="row row2">
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
							background-size: 300% 300%;
							animation: goldShimmer 4s ease infinite;
							}
							@keyframes goldShimmer {
							0%   { background-position: 0% 50%; }
							50%  { background-position: 100% 50%; }
							100% { background-position: 0% 50%; }
							}
							.sub-gold-card .sub-buy-btn {
							background: linear-gradient(135deg,#1a1a2e,#0f3460);
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
									{{ $t->price_minor > 0 ? '💳 Купить абонемент' : '🎫 Получить абонемент' }}
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
		@if($occurrences->isEmpty())
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
		
		<div class="row">
			@foreach($occurrences as $occ)
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
	
	<x-slot name="script">
		<script src="/assets/fas.js"></script>
		<script>
			// Swiper для обложек школы
			if (document.querySelector('.school-show-swiper')) {
				new Swiper('.school-show-swiper', {
					loop: true,
					autoplay: false,
					pagination: { el: '.swiper-pagination', clickable: true },
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
		</script>
	</x-slot>
	
</x-voll-layout>
