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
	
	$canShowContactButtons = $isAuthed && !$isSelf && $allowContact && $hasAnyContact;
	
	
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
											{{ $user->city->name }}@if($user->city->region) ({{ $user->city->region }})@endif
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
                    <div class="row row2">
						
                        {{-- КЛАССИКА --}}
                        <div class="col-md-6">
                            <div class="card">
                                <p class="b-600 mb-1">Классический волейбол</p>
								
                                @if(auth()->check() && !$isSelf)
                                <form method="POST" action="{{ route('user.vote', $user->id) }}" class="mb-2">
                                    @csrf
                                    <input type="hidden" name="direction" value="classic">
                                    <div class="d-flex flex-wrap gap-1 mb-1">
                                        @foreach($levelEmojis as $lvl => $emoji)
                                        <button type="submit" name="level" value="{{ $lvl }}"
										class="btn btn-small {{ $myClassicVote == $lvl ? '' : 'btn-secondary' }}"
										title="{{ $lvl }} — {{ level_name($lvl) }}"
										style="font-size:2rem; padding: 0.2rem 0.6rem; {{ $myClassicVote == $lvl ? 'outline: 2px solid var(--cd)' : '' }}">
                                            {{ $emoji }}
										</button>
                                        @endforeach
									</div>
                                    <div>
                                        @if($myClassicVote) Ваша оценка: <strong>{{ $myClassicVote }}</strong> {{ $levelEmojis[$myClassicVote] }}
                                        @else Выберите уровень @endif
									</div>
								</form>
                                @elseif($isSelf)
                                <div class="mb-2">Нельзя оценивать себя</div>
                                @else
                                <div class="mb-2"><a href="{{ route('login') }}">Войдите</a> чтобы оценить</div>
                                @endif
								
                                {{-- Полоска с указателем --}}
                                @if($classicAvg !== null)
                                @php
								$pct = (($classicAvg - 1) / 6) * 100;
								$colorIdx = (int)round($classicAvg);
								$colorIdx = max(1, min(7, $colorIdx));
                                @endphp
                                <div class="mt-2">
                                    <div class="d-flex between mb-05">
                                        <span>{{ $classicVotes->count() }} {{ trans_choice('оценка|оценки|оценок', $classicVotes->count()) }}</span>
                                        <span class="b-600 cd">Средняя: {{ $classicAvg }}</span>
									</div>
                                    {{-- Указатель --}}
                                    <div style="position:relative; padding-bottom: 0.4rem;">
                                        <div style="position:absolute; left:{{ $pct }}%; transform:translateX(-50%); font-size:1.2rem; bottom:0.2rem; line-height:1">✔️</div>
									</div>
                                    {{-- Полоска --}}
                                    <div style="display:flex; border-radius:0.6rem; overflow:hidden; height:1.2rem;">
                                        @foreach($levelColors as $lvl => $color)
                                        <div style="flex:1; background:{{ $color }};"></div>
                                        @endforeach
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
                                <form method="POST" action="{{ route('user.vote', $user->id) }}" class="mb-2">
                                    @csrf
                                    <input type="hidden" name="direction" value="beach">
                                    <div class="d-flex flex-wrap gap-1 mb-1">
                                        @foreach($levelEmojis as $lvl => $emoji)
                                        <button type="submit" name="level" value="{{ $lvl }}"
										class="btn btn-small {{ $myBeachVote == $lvl ? '' : 'btn-secondary' }}"
										title="{{ $lvl }} — {{ level_name($lvl) }}"
										style="font-size:2rem; padding: 0.2rem 0.6rem; {{ $myBeachVote == $lvl ? 'outline: 2px solid var(--cd)' : '' }}">
                                            {{ $emoji }}
										</button>
                                        @endforeach
									</div>
                                    <div class="f-14">
                                        @if($myBeachVote) Ваша оценка: <strong>{{ $myBeachVote }}</strong> {{ $levelEmojis[$myBeachVote] }}
                                        @else Выберите уровень @endif
									</div>
								</form>
                                @elseif($isSelf)
                                <div class="mb-2">Нельзя оценивать себя</div>
                                @else
                                <div class="mb-2"><a href="{{ route('login') }}">Войдите</a> чтобы оценить</div>
                                @endif
								
                                @if($beachAvg !== null)
                                @php
								$pctB = (($beachAvg - 1) / 6) * 100;
                                @endphp
                                <div class="mt-2">
                                    <div class="d-flex between f-14 mb-05">
                                        <span>{{ $beachVotes->count() }} {{ trans_choice('оценка|оценки|оценок', $beachVotes->count()) }}</span>
                                        <span class="b-600 cd">Средняя: {{ $beachAvg }}</span>
									</div>
                                    <div style="position:relative; padding-bottom: 0.4rem;">
                                        <div style="position:absolute; left:{{ $pctB }}%; transform:translateX(-50%); font-size:1.2rem; bottom:0.2rem; line-height:1">✔️</div>
									</div>
                                    <div style="display:flex; border-radius:0.6rem; overflow:hidden; height:1.2rem;">
                                        @foreach($levelColors as $lvl => $color)
                                        <div style="flex:1; background:{{ $color }};"></div>
                                        @endforeach
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
                        ->with(['event', 'team'])
                        ->get();
                    $careerClassic = \App\Models\PlayerCareerStats::where('user_id', $user->id)->where('direction', 'classic')->first();
                    $careerBeach = \App\Models\PlayerCareerStats::where('user_id', $user->id)->where('direction', 'beach')->first();
                    $hasTournaments = $tStats->isNotEmpty() || $careerClassic || $careerBeach;
                @endphp

                @if($hasTournaments)
                <div class="ramka">
                    <h2 class="-mt-05">Турнирная статистика</h2>

                    <div class="d-flex mb-3" style="flex-wrap:wrap;gap:12px">
                        @if($careerClassic && $careerClassic->total_matches > 0)
                            <div class="card p-3" style="flex:1;min-width:200px">
                                <div class="f-13 b-600 mb-1" style="opacity:.6">🏐 Классика</div>
                                <div class="d-flex" style="gap:16px;flex-wrap:wrap">
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

                        @if($careerBeach && $careerBeach->total_matches > 0)
                            <div class="card p-3" style="flex:1;min-width:200px">
                                <div class="f-13 b-600 mb-1" style="opacity:.6">🏖 Пляж</div>
                                <div class="d-flex" style="gap:16px;flex-wrap:wrap">
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
                    </div>

                    @if($tStats->isNotEmpty())
                        <div class="b-600 f-14 mb-2">История турниров</div>
                        @foreach($tStats->groupBy('event_id') as $eventId => $stats)
                            @php $firstStat = $stats->first(); @endphp
                            <div class="d-flex f-13" style="padding:6px 0;border-bottom:1px solid rgba(128,128,128,.08);gap:8px;align-items:center;flex-wrap:wrap">
                                <a href="{{ route('tournament.public.show', $eventId) }}" class="blink b-600" style="flex:1;min-width:120px">
                                    {{ $firstStat->event->title ?? 'Турнир' }}
                                </a>
                                <span style="opacity:.5">{{ $firstStat->team->name ?? '—' }}</span>
                                <span class="b-700" style="color:#E7612F">{{ $firstStat->match_win_rate }}%</span>
                                <span>{{ $firstStat->matches_won }}В {{ $firstStat->matches_played - $firstStat->matches_won }}П</span>
                                <span style="opacity:.4">сеты {{ $firstStat->sets_won }}:{{ $firstStat->sets_lost }}</span>
                            </div>
                        @endforeach
                    @endif
                </div>
                @endif

                {{-- ===== С ВАМИ УДОБНО ИГРАТЬ ===== --}}
                <div class="ramka">
                    <div class="d-flex between fvc">
                        <h2 class="-mt-05">С 
							@if($user->gender === 'm') ним
							@elseif($user->gender === 'f') ней
							@else — @endif	
						удобно играть</h2>
                        <span class="f-16 b-600 cd">{{ $likes->count() }}</span>
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
                        <a href="{{ $lkUrl }}" title="{{ $lkName }}">
                            <img src="{{ $lkPhoto }}" alt="{{ $lkName }}"
							style="width:4rem;height:4rem;border-radius:50%;object-fit:cover;border:2px solid var(--bg2)">
						</a>
                        @endif
                        @endforeach
					</div>
                    @else
                    <div class="alert alert-info">Пока никто не отметил</div>
                    @endif
					
                    {{-- Кнопка лайка --}}
                    @if(auth()->check() && !$isSelf)
                    <form method="POST" action="{{ route('user.like', $user->id) }}">
                        @csrf
                        <button type="submit" class="btn {{ $iLiked ? '' : 'btn-secondary' }}">
                            {{ $iLiked ? '💔 Не нравится играть вместе' : '🤍 Нравится играть вместе' }}
						</button>
					</form>
                    @elseif(!auth()->check())
                    <a href="{{ route('login') }}" class="btn btn-secondary">Войдите чтобы отметить</a>
                    @endif
					
                    {{-- Кнопка друга --}}
                    @if(auth()->check() && !$isSelf)
                    <div style="margin-top:1rem;">
                        @if(auth()->user()->isFriendWith($user->id))
                        <form method="POST" action="{{ route('friends.destroy', $user->id) }}">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-secondary">✅ В друзьях</button>
						</form>
                        @else
                        <form method="POST" action="{{ route('friends.store', $user->id) }}">
                            @csrf
                            <button class="btn">👥 Добавить в друзья</button>
						</form>
                        @endif
					</div>
                    @endif
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
				
				
			</div>
		</div>
	</div>
    
</x-voll-layout>