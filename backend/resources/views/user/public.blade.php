{{-- resources/views/user/public.blade.php --}}
<x-voll-layout body_class="user-public-page">
	
	
    @php
	/** @var \App\Models\User $user */
	$age = method_exists($user, 'ageYears') ? $user->ageYears() : null;
	$isSelf = auth()->check() && auth()->id() === $user->id;
	
	$posMap = [
	'setter'   => 'Связующий',
	'outside'  => 'Доигровщик',
	'opposite' => 'Диагональный',
	'middle'   => 'Центральный блокирующий',
	'libero'   => 'Либеро',
	];
	
	$classicPrimary = optional($user->classicPositions)->firstWhere('is_primary', true)?->position;
	$classicExtras  = optional($user->classicPositions)->where('is_primary', false)->pluck('position')->values()->all() ?? [];
	
	$beachPrimary = optional($user->beachZones)->firstWhere('is_primary', true)?->zone;
	$beachExtras  = optional($user->beachZones)->where('is_primary', false)->pluck('zone')->values()->all() ?? [];
	
	// --- Contacts logic ---
	$allowContact = (bool)($user->allow_user_contact ?? true);
	$isAuthed = auth()->check();
	$isSelf = $isAuthed && auth()->id() === $user->id;
	
	$tgUrl = null;
	$tgUsername = trim((string)($user->telegram_username ?? ''));
	if ($tgUsername !== '') {
	$tgUsername = ltrim($tgUsername, '@');
	if ($tgUsername !== '') {
	$tgUrl = 'https://t.me/' . $tgUsername;
	}
	}
	
	$vkUrl = null;
	$vkId = trim((string)($user->vk_id ?? ''));
	if ($vkId !== '') {
	$vkUrl = 'https://vk.com/id' . $vkId;
	}
	
	$hasAnyContact = (bool)($tgUrl || $vkUrl);
	
	$canShowContactButtons = $isAuthed  && $allowContact && $hasAnyContact;
	
	
	$age = '—';
	if (method_exists($user, 'ageYears') && $years = $user->ageYears()) {
    $ending = match($years % 10) {
	1 => 'год',
	2,3,4 => 'года',
	default => 'лет'
    };
    $age = $years . ' ' . $ending;
	}
    
	$birth = $user->birth_date 
    ? $user->birth_date->isoFormat('D MMMM YYYY') . ' г.'
    : '—';
	
    @endphp	
	
    <x-slot name="title">
        @if($isSelf)
		Ваш публичный профиль
        @else
		Профиль игрока: {{ $user->name }}
        @endif
	</x-slot>
	
	<x-slot name="description">
        @if($isSelf)
		Ваш публичный профиль на платформе
        @else
		Профиль игрока {{ $user->name }}
        @endif
	</x-slot>
	
    <x-slot name="canonical">
        {{ route('users.show', $user->id) }}
	</x-slot>
	
    <x-slot name="style">
        <style>
/* Базовые классы для индикатора */
.gradient-indicator {
	position: relative;
	margin-top: 3rem;
}

.gradient-bar {
	display: flex;
	border-radius: 0.6rem;
	overflow: hidden;
	height: 1.2rem;
}

/* Затемняющий слой (светлая тема) */
.gradient-overlay-light {
	position: absolute;
	top: 0;
	bottom: 0;
	right: 0;
	background: rgba(255, 255, 255, 0.8);
	border-radius: 0 0.6rem 0.6rem 0;
	pointer-events: none;
}
body.dark .gradient-overlay-light {
	background: rgba(0, 0, 0, 0.8);
}

/* Вертикальная черта-указатель */
.gradient-marker {
	position: absolute;
	bottom: 1.2rem;
	transform: translateX(-50%);
}

.gradient-marker-line {
	position: absolute;
	bottom: 100%;
	height: 2rem;
	width: 2rem;
	left: -1.8rem;
	background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg version='1.1' xmlns='http://www.w3.org/2000/svg' xmlns:xlink='http://www.w3.org/1999/xlink' x='0px' y='0px' viewBox='0 0 471.098 471.098' style='enable-background:new 0 0 471.098 471.098;' xml:space='preserve'%3e%3cpath d='M403.288,153.454L111.732,45.741v-21.27C111.732,10.961,100.779,0,87.261,0C73.743,0,62.79,10.961,62.79,24.471v422.156 c0,13.51,10.952,24.471,24.471,24.471c13.518,0,24.471-10.961,24.471-24.471V275.555l291.557-107.699 c3.012-1.113,5.019-3.981,5.019-7.2C408.307,157.436,406.3,154.57,403.288,153.454z' fill='%232967BA'/%3e%3c/svg%3e");
	background-repeat: no-repeat;
	background-size: contain;
	background-position: left center;
	transform: scaleX(-1);
}

/* Для тёмной темы */
body.dark .gradient-marker-line,
.theme-dark .gradient-marker-line {
	background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg version='1.1' xmlns='http://www.w3.org/2000/svg' xmlns:xlink='http://www.w3.org/1999/xlink' x='0px' y='0px' viewBox='0 0 471.098 471.098' style='enable-background:new 0 0 471.098 471.098;' xml:space='preserve'%3e%3cpath d='M403.288,153.454L111.732,45.741v-21.27C111.732,10.961,100.779,0,87.261,0C73.743,0,62.79,10.961,62.79,24.471v422.156 c0,13.51,10.952,24.471,24.471,24.471c13.518,0,24.471-10.961,24.471-24.471V275.555l291.557-107.699 c3.012-1.113,5.019-3.981,5.019-7.2C408.307,157.436,406.3,154.57,403.288,153.454z' fill='%23E7612F'/%3e%3c/svg%3e");
	transform: scaleX(-1);
}
	
		</style>
	</x-slot>
	
    <x-slot name="script">
        <script src="/assets/fas.js"></script>   	
        <script>
            // === Инициализация Swiper ===
            const swiper = new Swiper('.photo-swiper', {
                slidesPerView: 2,
                spaceBetween: 20,
                pagination: {
                    el: '.swiper-pagination',
                    clickable: true,
				},
                breakpoints: {
                    640: {
                        slidesPerView: 3, 
					},
                    768: {
                        slidesPerView: 3,
					},
                    992: {
                        slidesPerView: 3,
					},
                    1024: {
                        slidesPerView: 3,
					},
                    1280: {
                        slidesPerView: 4,
					}
				}   
			});     
		</script>	
	</x-slot>
    <x-slot name="h1">
        @if($isSelf)
		Ваш публичный профиль
        @else
		Профиль игрока
        @endif
	</x-slot>
	
    <x-slot name="h2">
		{{ $user->name }}
	</x-slot>
	
    <x-slot name="t_description">
		Информация об игроке
	</x-slot>
	
    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope="" itemtype="http://schema.org/ListItem">
            <a href="{{ route('users.index') }}" itemprop="item">
                <span itemprop="name">Игроки</span>
			</a>
            <meta itemprop="position" content="2">
		</li>
        <li itemprop="itemListElement" itemscope="" itemtype="http://schema.org/ListItem">
            <span itemprop="name">
                @if($isSelf)
				Ваш публичный профиль
                @else
				{{ $user->name }}
                @endif
			</span>
            <meta itemprop="position" content="2">
		</li>
	</x-slot>
	
	

	
	
    <div class="container">
		
		{{-- FLASH --}}
		@if (session('status'))
		<div class="ramka">
			<div class="alert alert-success mb-4">
				{{ session('status') }}
			</div>
		</div>
		@endif
		@if (session('error'))
		<div class="ramka">
			<div class="alert alert-danger mb-4">
				{{ session('error') }}
			</div>
		</div>
		@endif	
		
		
        <div class="row row2">
            {{-- SIDEBAR (левая колонка) --}}
            <div class="col-lg-4 col-xl-4 order-1 order-lg-1">
                <div class="sticky">
                    <div class="ramka">
                        <div class="card-body">
                            <div class="row row2">
                                <div class="col-12 text-center">
									<div class="profile-avatar {{ $user->isPremium() ? 'avatar-premium' : '' }}">
										<img
										src="{{ $user->profile_photo_url }}"
										alt="avatar"
										class="avatar"
										/>			
									</div>
								</div>
								<div class="col-12">    
									<ul class="list mb-0 mt-2 -ml-1">	
										<li><span class="b-600">Фамилия:</span> {{ $user->last_name ?? '—' }}</li>
										<li><span class="b-600">Имя:</span> {{ $user->first_name ?? '—' }}</li>
										@if(auth()->check() && (auth()->user()->isAdmin() || auth()->user()->isOrganizer()))		
										<li><span class="b-600">Отчество:</span> {{ $user->patronymic ?? '—' }}</li>
										@endif	
										<li><span class="b-600">Пол:</span>
											@if($user->gender === 'm') Мужчина
											@elseif($user->gender === 'f') Женщина
											@else — @endif										
										</li>
										<li><span class="b-600">Рост:</span> {{ !empty($user->height_cm) ? ($user->height_cm.' см') : '—' }}</li>
										<li><span class="b-600">Город:</span>
											@if($user->city)
											{{ $user->city->name }}@if($user->city->region) (<span class="f-16">{{ $user->city->region }}</span>)@endif
											@else
											—
										@endif</li>
										<li><span class="b-600">Дата рождения:</span> {{ $birth }} 	
										</li>
										<li><span class="b-600">Возраст:</span>	{{ $age }}								
										</li>										
									</ul>
								</div>
                                <div class="col-12">          
									@auth
									@if(!$isSelf)
									@if(auth()->check() && (auth()->user()->isAdmin() || auth()->user()->isOrganizer()))		
									<hr class="mt-1 mb-1">
									<nav class="menu-nav">   		
										<a href="{{ url('/profile/complete?user_id=' . $user->id) }}" class="menu-item">
											<span class="menu-text">
												@if(auth()->check() && auth()->user()->isAdmin())
												Редактировать пользователя
												@else
												Настроить уровни
												@endif
											</span>
										</a>
										@if(auth()->check() && auth()->user()->isAdmin())       
										<a href="{{ url('/user/photos?user_id=' . $user->id) }}" class="menu-item">
											<span class="menu-text">
												Редактировать фото пользователя
											</span>
										</a>											
										@endif	
									</nav>
									@endif
									@endif
									
									
									@endauth	
								</div>  
							</div>
						</div>
					</div>
				</div>   
			</div>
			
            {{-- MAIN CONTENT (правая колонка) --}}
            <div class="col-lg-8 col-xl-8 order-2 order-lg-2">
				
                {{-- Skills --}}
				<div class="ramka">  	
					<h2 class="-mt-05">Навыки в волейболе</h2>
					<div class="row">
						<div class="col-md-6 col-lg-12 col-xl-6">
							<div class="card">
								<p class="b-600 mb-1">Классический волейбол</p>
								
								<div class="level-wrap">
									<div class="level-levelmark levelmark level-{{ $user->classic_level ?? '—' }}">
										<div class="f-11">Уровень: </div>
										<div class="f-22 l-13">{{ $user->classic_level ?? '—' }}</div>
										<div class="f-11">{{ level_name($user->classic_level) ?? '—' }}</div>
									</div>	
									<div class="level-level">	
										<ul class="list">
											<li>
												<span class="b-600">Амплуа игрока:</span><br>
												
												@if($classicPrimary)
												{{ $posMap[$classicPrimary] ?? $classicPrimary }}
												@if(!empty($classicExtras))
												</li><li><span class="b-600">Дополнительно:</span><br>{{ collect($classicExtras)->map(fn($p) => $posMap[$p] ?? $p)->join(', ') }}
												@endif
												@else
												—
												@endif
											</li>
										</ul>
									</div>
								</div>
							</div>
						</div>
						<div class="col-md-6 col-lg-12 col-xl-6">
							<div class="card">
								<p class="b-600 mb-1">Пляжный волейбол</p>
								
								
								<div class="level-wrap">
									<div class="level-levelmark levelmark level-{{ $user->beach_level ?? '—' }}">
										<div class="f-11">Уровень: </div>
										<div class="f-22 l-13">{{ $user->beach_level ?? '—' }}</div>
										<div class="f-11">{{ level_name($user->beach_level) ?? '—' }}</div>
									</div>	
									<div class="level-level">	
										<ul class="list">
											<li>
												<span class="b-600">Зона игры:</span><br>
												
												
												@if(!empty($user->beach_universal))
												Универсал (2 и 4)
												@elseif(!is_null($beachPrimary))
												Основная: {{ $beachPrimary }}
												@if(!empty($beachExtras))
												; Доп.: {{ collect($beachExtras)->join(', ') }}
												@endif
												@else
												—
												@endif
												
											</li>
										</ul>
									</div>
								</div>								
							</div>
						</div>
					</div>
				</div>	
				
                {{-- ===== ОЦЕНКА УРОВНЯ ===== --}}
                @php
				$levelEmojis = [1=>"⚪️",2=>"🟡",3=>"🟠",4=>"🔵",5=>"🟣",6=>"🔴",7=>"⚫️"];
				$levelColors = [1=>"#e5e7eb",2=>"#fbbf24",3=>"#f97316",4=>"#3b82f6",5=>"#a855f7",6=>"#ef4444",7=>"#1f2937"];
                @endphp
				
                <div class="ramka">
                    <h2 class="-mt-05">Оценка уровня игроками</h2>
                    <div class="row">
						
                        {{-- КЛАССИКА --}}
                        <div class="col-md-6">
                            <div class="card">
                                <p class="b-600 mb-1">Классический волейбол</p>
								
                                @if(auth()->check() && !$isSelf)
                                <form method="POST" action="{{ route('user.vote', $user->id) }}">
                                    @csrf
                                    <input type="hidden" name="direction" value="classic">
                                    <div class="f-16">
                                        @if($myClassicVote) Ваша оценка: <strong>{{ $myClassicVote }}</strong> {{ $levelEmojis[$myClassicVote] }}
                                        @else Выберите уровень: @endif
									</div>									
                                    <div class="text-center d-flex flex-wrap gap-1 mb-2 mt-2">
                                        @foreach($levelEmojis as $lvl => $emoji)
                                        <button type="submit" name="level" value="{{ $lvl }}"
										class="btn btn-small {{ $myClassicVote == $lvl ? '' : 'btn-secondary' }}"
										title="{{ $lvl }} — {{ level_name($lvl) }}"
										style="font-size:2rem; padding: 0.6rem; {{ $myClassicVote == $lvl ? 'outline: 2px solid var(--cd)' : '' }}">
                                            {{ $emoji }}
										</button>
                                        @endforeach
									</div>

								</form>
                                @elseif($isSelf)
                                <div class="mb-1">Нельзя оценивать себя</div>
                                @else
                                <div class="mb-1"><a href="{{ route('login') }}">Войдите</a> чтобы оценить</div>
                                @endif
								
                                {{-- Полоска с указателем --}}
                                @if($classicAvg !== null)
                                @php
								$pct = (($classicAvg - 1) / 6) * 100;
								$colorIdx = (int)round($classicAvg);
								$colorIdx = max(1, min(7, $colorIdx));
                                @endphp

<div class="">
    <div class="d-flex between mb-1 f-16">
        <span><span class="b-600 cd">{{ $classicVotes->count() }}</span> {{ trans_choice('оценка|оценки|оценок', $classicVotes->count()) }}</span>
        <span>Средняя: <span class="b-600 cd">{{ $classicAvg }}</span></span>
    </div>
    
    <div class="gradient-indicator">
        {{-- Полоска --}}
        <div class="gradient-bar">
            @foreach($levelColors as $lvl => $color)
                <div style="flex:1; background:{{ $color }};"></div>
            @endforeach
        </div>
        
        {{-- Затемняющий слой (кинь один из классов в зависимости от темы) --}}
        <div class="gradient-overlay-light" style="left: {{ $pct }}%;"></div>
        {{-- Или для тёмной темы: <div class="gradient-overlay-dark" style="left: {{ $pct }}%;"></div> --}}
        
        {{-- Вертикальная черта --}}
        <div class="gradient-marker" style="left: {{ $pct }}%;">
            <div class="gradient-marker-line"></div>
        </div>
    </div>
</div>
                                @else
                                <div class="mt-1">Оценок пока нет</div>
                                @endif
							</div>
						</div>
						
                        {{-- ПЛЯЖ --}}
<div class="col-md-6">
    <div class="card">
        <p class="b-600 mb-1">Пляжный волейбол</p>
        
        @if(auth()->check() && !$isSelf)
        <form method="POST" action="{{ route('user.vote', $user->id) }}">
            @csrf
            <input type="hidden" name="direction" value="beach">
            <div class="f-16">
                @if($myBeachVote) Ваша оценка: <strong>{{ $myBeachVote }}</strong> {{ $levelEmojis[$myBeachVote] }}
                @else Выберите уровень @endif
            </div>                                    
            <div class="text-center d-flex flex-wrap gap-1 mb-2 mt-2">
                @foreach($levelEmojis as $lvl => $emoji)
                <button type="submit" name="level" value="{{ $lvl }}"
                class="btn btn-small {{ $myBeachVote == $lvl ? '' : 'btn-secondary' }}"
                title="{{ $lvl }} — {{ level_name($lvl) }}"
                style="font-size:2rem; padding: 0.6rem; {{ $myBeachVote == $lvl ? 'outline: 2px solid var(--cd)' : '' }}">
                    {{ $emoji }}
                </button>
                @endforeach
            </div>
        </form>
        @elseif($isSelf)
        <div class="mb-1">Нельзя оценивать себя</div>
        @else
        <div class="mb-1"><a href="{{ route('login') }}">Войдите</a> чтобы оценить</div>
        @endif
        
        @if($beachAvg !== null)
        @php
        $pctB = (($beachAvg - 1) / 6) * 100;
        @endphp
        
        <div class="">
            <div class="d-flex between f-16 mb-1">
                <span><span class="b-600 cd">{{ $beachVotes->count() }}</span> {{ trans_choice('оценка|оценки|оценок', $beachVotes->count()) }}</span>
                <span>Средняя: <span class="b-600 cd">{{ $beachAvg }}</span></span>
            </div>
            
            <div class="gradient-indicator">
                {{-- Полоска --}}
                <div class="gradient-bar">
                    @foreach($levelColors as $lvl => $color)
                        <div style="flex:1; background:{{ $color }};"></div>
                    @endforeach
                </div>
                
                {{-- Затемняющий слой --}}
                <div class="gradient-overlay-light" style="left: {{ $pctB }}%;"></div>
                
                {{-- Флажок-указатель --}}
                <div class="gradient-marker" style="left: {{ $pctB }}%;">
                    <div class="gradient-marker-line"></div>
                </div>
            </div>
        </div>
        @else
        <div class="mt-1">Оценок пока нет</div>
        @endif
    </div>
</div>
						
					</div>
				</div>
				
                {{-- Турнирная статистика --}}
                @php
                    $tStats = \App\Models\PlayerTournamentStats::where('user_id', $user->id)
                        ->where('matches_played', '>', 0)
                        ->with(['event', 'team.members.user'])
                        ->get();
                    $careerClassic = \App\Models\PlayerCareerStats::where('user_id', $user->id)->where('direction', 'classic')->first();
                    $careerBeach   = \App\Models\PlayerCareerStats::where('user_id', $user->id)->where('direction', 'beach')->first();
                    $hasTournaments = $tStats->isNotEmpty() || ($careerClassic && $careerClassic->total_matches > 0) || ($careerBeach && $careerBeach->total_matches > 0);

                    $occByEvent = \App\Models\TournamentStage::whereIn('event_id', $tStats->pluck('event_id')->unique())
                        ->whereNotNull('occurrence_id')
                        ->pluck('occurrence_id', 'event_id');

                    $teamIds = $tStats->pluck('team_id')->unique()->filter();
                    $rankByTeam = $teamIds->isNotEmpty()
                        ? \Illuminate\Support\Facades\DB::table('tournament_standings')
                            ->whereIn('team_id', $teamIds)
                            ->selectRaw('team_id, MIN(rank) as best_rank')
                            ->groupBy('team_id')
                            ->pluck('best_rank', 'team_id')
                        : collect();

                    $partnerStats = $teamIds->isNotEmpty()
                        ? \App\Models\PlayerTournamentStats::whereIn('team_id', $teamIds)
                            ->where('user_id', '!=', $user->id)
                            ->with('user:id,first_name,last_name,avatar_media_id')
                            ->get()
                        : collect();
                    // Загружаем медиа для аватарок партнёров
                    (new \Illuminate\Database\Eloquent\Collection(
                        $partnerStats->pluck('user')->filter()->unique('id')->values()->all()
                    ))->load('media');
                    $partnerStatsByTeam = $partnerStats->groupBy('team_id');

                    $tStatsBeach   = $tStats->filter(fn($s) => $s->event?->direction === 'beach');
                    $tStatsClassic = $tStats->filter(fn($s) => $s->event?->direction === 'classic');
                    $hasBeach   = $tStatsBeach->isNotEmpty() || ($careerBeach   && $careerBeach->total_matches   > 0);
                    $hasClassic = $tStatsClassic->isNotEmpty() || ($careerClassic && $careerClassic->total_matches > 0);
                    $medals = [1 => '🥇', 2 => '🥈', 3 => '🥉'];
                @endphp

                @if($hasTournaments)
                <div class="ramka">
                    <h2 class="-mt-05">Турнирная статистика</h2>

                    {{-- Вкладки (только если есть оба направления) --}}
                    @if($hasBeach && $hasClassic)
                    <div class="d-flex mb-3 gap-1" id="ts-tabs">
                        <button class="btn ts-tab-btn" data-tab="beach">🏖 Пляжка</button>
                        <button class="btn btn-secondary ts-tab-btn" data-tab="classic">🏐 Классика</button>
                    </div>
                    @endif

                    {{-- ===== ПЛЯЖКА ===== --}}
                    @if($hasBeach)
                    <div class="ts-tab-pane" id="ts-pane-beach">
                        @if($careerBeach && $careerBeach->total_matches > 0)
                        <div class="card p-3 mb-3">
                            <div class="f-13 b-600 mb-2" style="opacity:.6">🏖 Итого</div>
                            <div class="d-flex" style="gap:16px;flex-wrap:wrap">
                                @php $crBeach = max(0, ($careerBeach->mu ?? 25) - 3 * ($careerBeach->sigma ?? 8.333)); @endphp
                                @if($careerBeach->total_matches >= 3)
                                <div style="text-align:center">
                                    <div class="f-24 b-800" style="color:#E7612F">{{ number_format($crBeach, 1) }}</div>
                                    <div class="f-11" style="opacity:.5">{{ __('tournaments.conservative_rating') }}</div>
                                </div>
                                @endif
                                <div style="text-align:center">
                                    <div class="f-24 b-800" style="color:#E7612F">{{ $careerBeach->match_win_rate }}%</div>
                                    <div class="f-11" style="opacity:.5">WinRate</div>
                                </div>
                                <div style="text-align:center">
                                    <div class="f-18 b-700">{{ $careerBeach->total_wins }}/{{ $careerBeach->total_matches }}</div>
                                    <div class="f-11" style="opacity:.5">Побед/Матчей</div>
                                </div>
                                <div style="text-align:center">
                                    <div class="f-18 b-700">{{ $careerBeach->total_tournaments }}</div>
                                    <div class="f-11" style="opacity:.5">Турниров</div>
                                </div>
                                @if($careerBeach->best_placement)
                                <div style="text-align:center">
                                    <div class="f-18 b-700">🏆 {{ $careerBeach->best_placement }}</div>
                                    <div class="f-11" style="opacity:.5">Лучшее место</div>
                                </div>
                                @endif
                                @if($careerBeach->elo_rating && $careerBeach->elo_rating != 1500)
                                <div style="text-align:center">
                                    <div class="f-18 b-700">{{ $careerBeach->elo_rating }}</div>
                                    <div class="f-11" style="opacity:.5">Elo</div>
                                </div>
                                @endif
                            </div>
                        </div>
                        @endif

                        @if($tStatsBeach->isNotEmpty())
                        <div class="b-600 f-14 mb-2">История турниров</div>
                        <div class="card mb-3">
                            @foreach($tStatsBeach->groupBy('event_id') as $eventId => $stats)
                            @php
                                $s = $stats->first();
                                $occId = $s->occurrence_id ?? ($occByEvent[$eventId] ?? null);
                                $url = route('tournament.public.show', $eventId) . ($occId ? '?tab=overview&occurrence_id='.$occId : '');
                                $rank = $rankByTeam[$s->team_id] ?? null;
                            @endphp
                            <div class="d-flex f-13" style="padding:8px 0;border-bottom:1px solid rgba(128,128,128,.08);gap:8px;align-items:center;flex-wrap:wrap">
                                <a href="{{ $url }}" class="blink b-600" style="flex:1;min-width:140px">{{ $s->event->title ?? 'Турнир' }}</a>
                                @if($rank)
                                <span class="b-700" style="font-size:{{ $rank <= 3 ? '16px' : '13px' }}">{{ $medals[$rank] ?? $rank.'.' }}</span>
                                @endif
                                <span class="b-700" style="color:#E7612F">{{ $s->match_win_rate }}%</span>
                                <span style="opacity:.5">{{ $s->matches_won }}В&nbsp;{{ $s->matches_played - $s->matches_won }}П</span>
                            </div>
                            @endforeach
                        </div>

                        {{-- Партнёры --}}
                        @php
                            $beachPartners = collect();
                            foreach($tStatsBeach->groupBy('event_id') as $stats) {
                                $tid = $stats->first()->team_id;
                                if($tid && isset($partnerStatsByTeam[$tid])) {
                                    foreach($partnerStatsByTeam[$tid] as $ps) { $beachPartners->push($ps); }
                                }
                            }
                            $beachPartners = $beachPartners->unique('user_id');
                        @endphp
                        @if($beachPartners->isNotEmpty())
                        <div class="b-600 f-14 mb-2">Партнёры</div>
                        <div class="d-flex flex-wrap" style="gap:10px">
                            @foreach($beachPartners as $ps)
                            @if($ps->user)
                            <a href="{{ route('users.show', $ps->user) }}" class="card" style="text-decoration:none;display:flex;align-items:center;gap:10px;padding:10px 14px;flex:1;min-width:180px;max-width:320px">
                                <div style="width:42px;height:42px;border-radius:50%;overflow:hidden;flex-shrink:0;background:rgba(128,128,128,.1)">
                                    <img src="{{ $ps->user->profile_photo_url }}" style="width:100%;height:100%;object-fit:cover" alt="" loading="lazy">
                                </div>
                                <div>
                                    <div class="b-600 f-14">{{ $ps->user->last_name }} {{ $ps->user->first_name }}</div>
                                    <div class="f-12" style="color:#E7612F">{{ $ps->match_win_rate }}% WinRate</div>
                                </div>
                            </a>
                            @endif
                            @endforeach
                        </div>
                        @endif
                        @endif
                    </div>
                    @endif

                    {{-- ===== КЛАССИКА ===== --}}
                    @if($hasClassic)
                    <div class="ts-tab-pane" id="ts-pane-classic"@if($hasBeach) style="display:none"@endif>
                        @if($careerClassic && $careerClassic->total_matches > 0)
                        <div class="card p-3 mb-3">
                            <div class="f-13 b-600 mb-2" style="opacity:.6">🏐 Итого</div>
                            <div class="d-flex" style="gap:16px;flex-wrap:wrap">
                                @php $crClassic = max(0, ($careerClassic->mu ?? 25) - 3 * ($careerClassic->sigma ?? 8.333)); @endphp
                                @if($careerClassic->total_matches >= 3)
                                <div style="text-align:center">
                                    <div class="f-24 b-800" style="color:#E7612F">{{ number_format($crClassic, 1) }}</div>
                                    <div class="f-11" style="opacity:.5">{{ __('tournaments.conservative_rating') }}</div>
                                </div>
                                @endif
                                <div style="text-align:center">
                                    <div class="f-24 b-800" style="color:#E7612F">{{ $careerClassic->match_win_rate }}%</div>
                                    <div class="f-11" style="opacity:.5">WinRate</div>
                                </div>
                                <div style="text-align:center">
                                    <div class="f-18 b-700">{{ $careerClassic->total_wins }}/{{ $careerClassic->total_matches }}</div>
                                    <div class="f-11" style="opacity:.5">Побед/Матчей</div>
                                </div>
                                <div style="text-align:center">
                                    <div class="f-18 b-700">{{ $careerClassic->total_tournaments }}</div>
                                    <div class="f-11" style="opacity:.5">Турниров</div>
                                </div>
                                @if($careerClassic->best_placement)
                                <div style="text-align:center">
                                    <div class="f-18 b-700">🏆 {{ $careerClassic->best_placement }}</div>
                                    <div class="f-11" style="opacity:.5">Лучшее место</div>
                                </div>
                                @endif
                                @if($careerClassic->elo_rating && $careerClassic->elo_rating != 1500)
                                <div style="text-align:center">
                                    <div class="f-18 b-700">{{ $careerClassic->elo_rating }}</div>
                                    <div class="f-11" style="opacity:.5">Elo</div>
                                </div>
                                @endif
                            </div>
                        </div>
                        @endif

                        @if($tStatsClassic->isNotEmpty())
                        <div class="b-600 f-14 mb-2">История турниров</div>
                        <div class="card mb-3">
                            @foreach($tStatsClassic->groupBy('event_id') as $eventId => $stats)
                            @php
                                $s = $stats->first();
                                $occId = $s->occurrence_id ?? ($occByEvent[$eventId] ?? null);
                                $url = route('tournament.public.show', $eventId) . ($occId ? '?tab=overview&occurrence_id='.$occId : '');
                                $rank = $rankByTeam[$s->team_id] ?? null;
                            @endphp
                            <div class="d-flex f-13" style="padding:8px 0;border-bottom:1px solid rgba(128,128,128,.08);gap:8px;align-items:center;flex-wrap:wrap">
                                <a href="{{ $url }}" class="blink b-600" style="flex:1;min-width:140px">{{ $s->event->title ?? 'Турнир' }}</a>
                                @if($rank)
                                <span class="b-700" style="font-size:{{ $rank <= 3 ? '16px' : '13px' }}">{{ $medals[$rank] ?? $rank.'.' }}</span>
                                @endif
                                <span class="b-700" style="color:#E7612F">{{ $s->match_win_rate }}%</span>
                                <span style="opacity:.5">{{ $s->matches_won }}В&nbsp;{{ $s->matches_played - $s->matches_won }}П</span>
                            </div>
                            @endforeach
                        </div>

                        {{-- Команды --}}
                        <div class="b-600 f-14 mb-2">Команды</div>
                        <div class="card">
                            @foreach($tStatsClassic->unique('team_id') as $s)
                            <div class="d-flex f-13" style="padding:8px 0;border-bottom:1px solid rgba(128,128,128,.08);gap:8px;align-items:center">
                                <span class="b-600" style="flex:1">{{ $s->team->name ?? '—' }}</span>
                                <span class="b-700" style="color:#E7612F">{{ $s->match_win_rate }}%</span>
                                <span style="opacity:.5">{{ $s->matches_won }}В&nbsp;{{ $s->matches_played - $s->matches_won }}П</span>
                            </div>
                            @endforeach
                        </div>
                        @endif
                    </div>
                    @endif
                </div>

                <script>
                (function(){
                    var btns = document.querySelectorAll('.ts-tab-btn');
                    btns.forEach(function(btn){ btn.addEventListener('click', function(){
                        document.querySelectorAll('.ts-tab-pane').forEach(function(p){ p.style.display = 'none'; });
                        document.getElementById('ts-pane-' + btn.dataset.tab).style.display = '';
                        btns.forEach(function(b){ b.classList.remove('active'); b.classList.add('btn-secondary'); });
                        btn.classList.add('active');
                        btn.classList.remove('btn-secondary');
                    }); });
                })();
                </script>
                @endif

                {{-- ===== OPENSKILL: История, партнёры, соперники ===== --}}
                @php
                    $hasRatingHistory = isset($ratingHistory) && $ratingHistory->isNotEmpty();
                    $hasRatingPartners = isset($ratingPartners) && (
                        collect($ratingPartners['beach'] ?? [])->isNotEmpty() ||
                        collect($ratingPartners['classic'] ?? [])->isNotEmpty()
                    );
                    $hasRatingOpponents = isset($ratingOpponents) && $ratingOpponents->isNotEmpty();
                @endphp

                @if($hasRatingHistory || $hasRatingPartners || $hasRatingOpponents)
                <div class="ramka">
                    <h2 class="-mt-05">📈 Рейтинговая статистика</h2>

                    {{-- Позиции в рейтинге --}}
                    @if(isset($ratingPositions) && count($ratingPositions) > 0)
                    <div class="d-flex gap-2 mb-3 flex-wrap">
                        @foreach($ratingPositions as $dir => $pos)
                        <div class="card text-center" style="min-width:120px;padding:10px 16px">
                            <div class="f-11" style="opacity:.5">{{ $dir === 'beach' ? '🏖 Пляж' : '🏐 Классика' }}</div>
                            <div class="f-22 b-800" style="color:#E7612F">#{{ $pos['pos'] }}</div>
                            <div class="f-11" style="opacity:.5">из {{ $pos['total'] }}</div>
                        </div>
                        @endforeach
                        <div style="align-self:center">
                            <a href="{{ route('players.rating', ['direction' => array_key_first($ratingPositions ?? ['beach'=>null])]) }}" class="btn btn-secondary btn-small">Полный рейтинг →</a>
                        </div>
                    </div>
                    @endif

                    {{-- График динамики mu --}}
                    @if($hasRatingHistory)
                    <div class="card mb-3">
                        <div class="f-14 b-600 mb-2">{{ __('players.rating_dynamics') }}</div>
                        <canvas id="profileRatingChart" height="120"></canvas>
                    </div>
                    @endif

                    {{-- Форма и серии --}}
                    @foreach(['beach','classic'] as $dir)
                    @php
                        $dirStats = null;
                        if ($dir === 'beach' && isset($careerBeach) && $careerBeach) $dirStats = $careerBeach;
                        if ($dir === 'classic' && isset($careerClassic) && $careerClassic) $dirStats = $careerClassic;
                        $f5  = $dirStats?->last_5_form ?? '';
                        $f10 = $dirStats?->last_10_form ?? '';
                    @endphp
                    @if($dirStats && $dirStats->total_matches > 0 && ($f5 || $f10))
                    <div class="f-13 b-600 mb-1" style="opacity:.6">{{ $dir === 'beach' ? '🏖 Пляж' : '🏐 Классика' }}</div>
                    <div class="d-flex gap-2 mb-3 flex-wrap">
                        @if($f5)
                        <div class="card text-center" style="padding:8px 14px">
                            <div class="f-11" style="opacity:.5">{{ __('players.last_5') }}</div>
                            <div class="f-15 b-700">
                                @foreach(mb_str_split($f5) as $ch)
                                    <span class="{{ in_array($ch,['В','W']) ? 'cs' : 'red' }}">{{ $ch }}</span>
                                @endforeach
                            </div>
                        </div>
                        @endif
                        @if($f10)
                        <div class="card text-center" style="padding:8px 14px">
                            <div class="f-11" style="opacity:.5">{{ __('players.last_10') }}</div>
                            <div class="f-14 b-700">
                                @foreach(mb_str_split($f10) as $ch)
                                    <span class="{{ in_array($ch,['В','W']) ? 'cs' : 'red' }}">{{ $ch }}</span>
                                @endforeach
                            </div>
                        </div>
                        @endif
                        @if(($dirStats->pair_stability ?? 0) > 0 && $dirStats->main_partner_id)
                        @php $mp = \App\Models\User::select('id','first_name','last_name')->find($dirStats->main_partner_id); @endphp
                        @if($mp)
                        <div class="card text-center" style="padding:8px 14px">
                            <div class="f-11" style="opacity:.5">{{ __('players.pair_stability') }}</div>
                            <div class="f-15 b-700">{{ round($dirStats->pair_stability) }}%</div>
                            <div class="f-11" style="opacity:.5">{{ trim($mp->last_name . ' ' . mb_substr($mp->first_name,0,1)) }}.</div>
                        </div>
                        @endif
                        @endif
                    </div>
                    @endif
                    @endforeach

                    {{-- Партнёры --}}
                    @foreach(['beach','classic'] as $dir)
                    @php $dirPairs = collect($ratingPartners[$dir] ?? []); @endphp
                    @if($dirPairs->isNotEmpty())
                    <div class="f-14 b-600 mb-1">{{ __('players.teammates') }} ({{ $dir === 'beach' ? '🏖 Пляж' : '🏐 Классика' }})</div>
                    <div class="card mb-3">
                        @foreach($dirPairs->take(5) as $i => $pair)
                        @php $wr = $pair->matches_together > 0 ? round($pair->wins_together / $pair->matches_together * 100) : 0; @endphp
                        <div class="d-flex between fvc py-1 f-14 {{ $i > 0 ? 'border-top' : '' }}">
                            <div class="d-flex fvc gap-2">
                                <span class="f-13" style="width:20px;opacity:.4">{{ $i+1 }}</span>
                                <a href="{{ route('users.show', $pair->partner->id) }}" class="blink">
                                    {{ trim($pair->partner->last_name . ' ' . $pair->partner->first_name) }}
                                </a>
                                @if($pair->game_scheme)
                                <span class="f-12" style="opacity:.4">{{ $pair->game_scheme }}</span>
                                @endif
                            </div>
                            <div class="text-right">
                                <span class="b-600">{{ $pair->matches_together }}</span>
                                <span style="opacity:.5"> игр</span>
                                <span class="{{ $wr >= 50 ? 'cs' : 'red' }} b-600 f-13 ml-1">{{ $wr }}%</span>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @endif
                    @endforeach

                    {{-- Соперники --}}
                    @if($hasRatingOpponents)
                    <div class="f-14 b-600 mb-1">{{ __('players.opponents') }}</div>
                    <div class="card mb-3">
                        @foreach($ratingOpponents->take(5) as $i => $opp)
                        @php $wr = $opp->matches_against > 0 ? round($opp->wins_against / $opp->matches_against * 100) : 0; @endphp
                        <div class="d-flex between fvc py-1 f-14 {{ $i > 0 ? 'border-top' : '' }}">
                            <div class="d-flex fvc gap-2">
                                <span class="f-13" style="width:20px;opacity:.4">{{ $i+1 }}</span>
                                <a href="{{ route('users.show', $opp->opponent_id) }}" class="blink">
                                    {{ trim($opp->last_name . ' ' . $opp->first_name) }}
                                </a>
                            </div>
                            <div class="text-right">
                                <span class="b-600">{{ $opp->matches_against }}</span>
                                <span style="opacity:.5"> встреч</span>
                                <span class="{{ $wr >= 50 ? 'cs' : 'red' }} b-600 f-13 ml-1">{{ $wr }}%В</span>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @endif

                </div>
                @endif

                {{-- ===== С ВАМИ УДОБНО ИГРАТЬ ===== --}}
                <div class="ramka">
                    <div class="d-flex between">
                        <h2 class="-mt-05">С 
							@if($user->gender === 'm') ним
							@elseif($user->gender === 'f') ней
							@else ним(ней) @endif	
						удобно играть</h2>
                        <span class="f-16 b-600 cd">{{ $likes->count() }} 💔</span></span>
					</div>
					
                    {{-- Аватары лайкнувших --}}
                    @if($likes->isNotEmpty())
                    <div class="d-flex flex-wrap gap-1">
                        @foreach($likes as $like)
                        @php
						$lk = $like->liker;
						$lkUrl = $lk ? route('users.show', $lk->id) : null;
						$lkPhoto = $lk?->profile_photo_url;
						$lkName = trim(($lk->first_name ?? '') . ' ' . ($lk->last_name ?? '')) ?: 'Игрок';
                        @endphp
                        @if($lk)
                        <a class="user-avatar-img-wrapper f-0" href="{{ $lkUrl }}" title="{{ $lkName }}">
                            <img src="{{ $lkPhoto }}" alt="{{ $lkName }}"
							class="user-card-avatar-img">
						</a>
                        @endif
                        @endforeach
					</div>
                    @else
                    <div class="alert alert-info">Пока никто не отметил</div>
                    @endif
					 <div class="d-flex flex-wrap gap-1 text-center">
                    {{-- Кнопка лайка --}}
                    @if(auth()->check() && !$isSelf)
                    <form class="d-inline-block mt-1" method="POST" action="{{ route('user.like', $user->id) }}">
                        @csrf
                        <button type="submit" class="btn {{ $iLiked ? '' : 'btn-secondary' }}">
                            {{ $iLiked ? '✅ Нравится играть вместе' : 'Нравится играть вместе' }}
						</button>
					</form>
                    @elseif(!auth()->check())
                    <a href="{{ route('login') }}" class="btn btn-secondary">Войдите чтобы отметить</a>
                    @endif
					
                    {{-- Кнопка друга --}}
                    @if(auth()->check() && !$isSelf)
                        @if(auth()->user()->isFriendWith($user->id))
                        <form class="d-inline-block mt-1" method="POST" action="{{ route('friends.destroy', $user->id) }}">
                            @csrf
                            @method('DELETE')
                            <button class="btn">✅ В друзьях</button>
						</form>
                        @else
                        <form class="d-inline-block mt-1" method="POST" action="{{ route('friends.store', $user->id) }}">
                            @csrf
                            <button class="btn btn-secondary">Добавить в друзья</button>
						</form>
                        @endif

                        {{-- Кнопка слежки за записями (только Premium + друг) --}}
                        @if($canFollow)
                            @if($isFollowing)
                            <form class="d-inline-block mt-1" method="POST" action="{{ route('premium.follows.destroy', $user->id) }}">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-small" style="font-size:13px" title="Вы следите за записями этого игрока">⭐ Слежу за записями</button>
                            </form>
                            @else
                            <form class="d-inline-block mt-1" method="POST" action="{{ route('premium.follows.store', $user->id) }}">
                                @csrf
                                <button class="btn btn-secondary btn-small" style="font-size:13px">⭐ Следить за записями</button>
                            </form>
                            @endif
                        @endif
                    @endif
				</div>
				</div>
                {{-- Contacts --}}
                <div class="ramka">
                    <div class="card-body">
						<h2 class="-mt-05">Контакты</h2>
						
                        @if(!$allowContact)
						<div class="alert alert-info">
							Пользователь запретил связываться с ним через Telegram/VK.
						</div>
                        @elseif(!$hasAnyContact)
						<div class="alert alert-info">
							Пользователь не указал Telegram/VK для связи.
						</div>
                        @elseif(!$isAuthed)
						<div class="alert alert-info">
							Чтобы написать пользователю в Telegram/VK, нужно войти в аккаунт.
						</div>
                        @elseif($canShowContactButtons)
						<div class="d-flex flex-wrap gap-1 fc">
							
							@auth
							@if(auth()->check() && (auth()->user()->isAdmin() || auth()->user()->isOrganizer()) && $user->phone)
							<a class="btn" href="tel:{{ $user->phone }}">
								{{ $user->formatted_phone }}
							</a>
							@endif
							@endauth	
							
							
							@if($tgUrl)
							<a class="btn"
							href="{{ $tgUrl }}" target="_blank" rel="noopener noreferrer">
								Написать в Telegram
							</a>
							@endif
							
							@if($vkUrl)
							<a class="btn"
							href="{{ $vkUrl }}" target="_blank" rel="noopener noreferrer">
								Написать в VK
							</a>
							@endif
						</div>
                        @endif
					</div>
				</div>
				
				
				@auth
                <div class="ramka">        
                    {{-- Gallery --}}
                    <h2 class="-mt-05">Фотографии</h2>
					
                    <div class="mt-2">
                        @if($photos->isEmpty())
                        <div class="alert alert-info">
							У пользователя нет загруженных фотографий
						</div>
                        @else
                        <div class="swiper photo-swiper">
                            <div class="swiper-wrapper">
                                @foreach($photos as $m)
                                @php
                                $thumbUrl = method_exists($m, 'hasGeneratedConversion') && $m->hasGeneratedConversion('thumb')
                                ? $m->getUrl('thumb')
                                : $m->getUrl();
                                @endphp
                                <div class="swiper-slide">
                                    
                                    <div class="hover-image">
                                        <a href="{{ $m->getUrl() }}" class="fancybox" data-fancybox="gallery">
                                            <img
                                            src="{{ $thumbUrl }}"
                                            alt="photo"
                                            loading="lazy"
                                            />
                                            <span></span>
                                            <div class="hover-image-circle"></div>
										</a>
									</div>                                                          
								</div>
                                @endforeach
							</div>
                            <div class="swiper-pagination"></div>
						</div>
                        @endif
					</div>
					{{-- 
					<div class="text-right">
					    <p>Всего: <strong class="cd">{{ $photos->count() }}</strong> фото</p>
					</div> 
					--}}
				</div>  				
				@endauth	
				
			</div>
		</div>
	</div>
    

@if(isset($ratingHistory) && $ratingHistory->isNotEmpty())
@php
    $ratingChartData = $ratingHistory->map(function($h) {
        $date = $h->match_scored_at ?? $h->match_scheduled_at ?? $h->recorded_at;
        if ($date && is_string($date)) {
            $date = \Carbon\Carbon::parse($date);
        }
        return [
            'label' => $date ? $date->format('d.m') : '',
            'mu'    => round((float)$h->mu_after, 2),
            'cr'    => round(max(0, (float)$h->mu_after - 3 * (float)$h->sigma_after), 2),
        ];
    })->values()->toArray();
@endphp
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function(){
    const ctx = document.getElementById('profileRatingChart');
    if (!ctx) return;
    const raw = @json($ratingChartData);
    if (!raw.length) return;
    const peak = Math.max(...raw.map(r => r.mu));
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: raw.map(r => r.label),
            datasets: [
                { label: 'μ — скрытый потенциал', data: raw.map(r => r.mu), borderColor: '#E7612F', borderWidth: 2, pointRadius: 2, tension: 0.3, fill: false },
                { label: 'CR — публичный рейтинг (μ−3σ)', data: raw.map(r => r.cr), borderColor: '#28a745', borderDash: [4,4], borderWidth: 1.5, pointRadius: 0, fill: false },
                { label: 'Пик μ: '+peak.toFixed(1), data: raw.map(() => peak), borderColor: 'rgba(220,53,69,.3)', borderDash:[2,4], borderWidth:1, pointRadius:0, fill:false }
            ]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'top', labels: { usePointStyle: true, boxWidth: 8, font: { size: 11 } } } },
            scales: { y: { beginAtZero: false }, x: { ticks: { maxTicksLimit: 12, font: { size: 10 } } } }
        }
    });
})();
</script>
@endif
</x-voll-layout>