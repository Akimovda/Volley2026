<x-voll-layout body_class="player-dashboard-page">
	@php
	$user = auth()->user();
	@endphp	

    <x-slot name="title">Моя статистика</x-slot>
    <x-slot name="h1">Моя статистика</x-slot>
    <x-slot name="h2">
        @if(!empty($user->first_name) || !empty($user->last_name))
        {{ trim($user->first_name . ' ' . $user->last_name) }}
        @else
        Пользователь #{{ $user->id }}
        @endif
	</x-slot>	

    <x-slot name="t_description">Ваша активность на площадке</x-slot>

    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('profile.show') }}" itemprop="item"><span itemprop="name">Мой профиль</span></a>
            <meta itemprop="position" content="2">
        </li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <span itemprop="name">Моя статистика</span>
            <meta itemprop="position" content="3">
        </li>
    </x-slot>

    <div class="container">

        <div class="row row2">
            <div class="col-lg-4 col-xl-3 order-2 d-none d-lg-block">
                <div class="sticky">
                    <div class="card-ramka">
                        @include('profile._menu', [
						'activeMenu'    => 'player_dashboard',
                        ])
					</div>
				</div>
			</div>
            <div class="col-lg-8 col-xl-9 order-1">    


        {{-- СВОДКА --}}
        <div class="ramka">
            <h2 class="-mt-05">Активность</h2>
            <div class="row">
                <div class="col-6 col-md-3">
                    <div class="card text-center">
                        <div class="">Всего игр</div>
                        <div style="font-size: 3rem" class="b-600 cd">{{ $totalVisits }}</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card text-center">
                        <div class="">В этом месяце</div>
                        <div style="font-size: 3rem" class="f-36 b-600 cd">{{ $visitsThisMonth }}</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card text-center">
                        <div class="">Отмен</div>
                        <div style="font-size: 3rem" class="f-36 b-600 cd">{{ $totalCancellations }}</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card text-center">
                        <div class="">Серия недель</div>
                        <div style="font-size: 3rem" class="f-36 b-600 cd">{{ $streak }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- РЕЙТИНГ И ОЦЕНКИ --}}
        <div class="ramka">
            <h2 class="-mt-05">Рейтинг и оценки</h2>
            <div class="row">
                <div class="col-6 col-md-3">
                    <div class="card text-center">
                        <div>Оценок уровня</div>
                        <div style="font-size: 3rem" class="f-36 b-600 cd">{{ $totalVotes }}</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card text-center">
                        <div>Средний уровень</div>
                        <div style="font-size: 3rem" class="f-36 b-600 cd">{{ $avgLevel ? round($avgLevel, 1) : '—' }}</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card text-center">
                        <div>Нравится</div>
                        <div style="font-size: 3rem" class="f-36 b-600 cd">{{ $likesCount }}</div>
						<div class="f-16">c вами играть</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card text-center">
                        <div>Топ активности</div>
                        <div style="font-size: 3rem" class="f-36 b-600 cd">{{ $percentile }}%</div>
                        <div class="f-16">игроков</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ПРОСМОТРЫ ПРОФИЛЯ --}}
        <div class="ramka">
            <h2 class="-mt-05">Просмотры профиля</h2>
            <div class="row">
                <div class="col-6">
                    <div class="card text-center">
                        <div>За все время</div>
                        <div style="font-size: 3rem" class="f-36 b-600 cd">{{ $profileViews }}</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="card text-center">
                        <div>За последние 30 дней</div>
                        <div style="font-size: 3rem" class="f-36 b-600 cd">{{ $profileViews30d }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ДИНАМИКА --}}
        <div class="ramka">
            <h2 class="-mt-05">Активность по месяцам</h2>
            <div class="card">
                <canvas id="playerMonthlyChart" height="80"></canvas>
            </div>
        </div>

        {{-- ПОЗИЦИИ + ЛОКАЦИИ + ОРГАНИЗАТОРЫ --}}
        
            <div class="row row2">
                {{-- Позиции --}}
                <div class="col-sm-4">
				<div class="ramka">
                    <h2 class="-mt-05">Позиции</h2>
                        @forelse($positions as $pos)
                        <div class="d-flex between fvc py-1">
                            <span>{{ position_name($pos->position) }}</span>
                            <span class="f-16 b-600">{{ $pos->cnt }}</span>
                        </div>
                        @empty
						<div class="alert alert-info">Нет данных</div>
                        @endforelse
                    </div>
                </div>

                {{-- Локации --}}
                <div class="col-sm-4">
				<div class="ramka">
                    <h2 class="-mt-05">Площадки</h2>
                        @forelse($topLocations as $i => $loc)
                        <div class="d-flex between fvc py-1 {{ $i > 0 ? 'border-top' : '' }}">
                            <a href="{{ route('locations.show', [$loc->id, \Illuminate\Support\Str::slug($loc->name)]) }}" class="blink">
                                {{ $loc->name }}
                            </a>
                            <span class="f-16 b-600">{{ $loc->visits }}</span>
                        </div>
                        @empty
                        <div class="alert alert-info">Нет данных</div>
                        @endforelse
                    </div>
                </div>

                {{-- Организаторы --}}
                <div class="col-sm-4">
				<div class="ramka">
                    <h2 class="-mt-05">Организаторы</h2>
                        @forelse($topOrganizers as $i => $org)
                        <div class="d-flex between fvc py-1 {{ $i > 0 ? 'border-top' : '' }}">
                            <a href="{{ route('users.show', $org->id) }}" class="blink">
                                {{ trim($org->first_name . ' ' . $org->last_name) ?: '#'.$org->id }}
                            </a>
                            <span class="f-16 b-600">{{ $org->visits }}</span>
                        </div>
                        @empty
                        <div class="alert alert-info">Нет данных</div>
                        @endforelse
                    </div>
                </div>
            </div>




    <x-slot name="script">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const data = @json($monthlyVisits);
        const ctx = document.getElementById('playerMonthlyChart');
        if (ctx && data.length) {
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.map(r => r.month),
                    datasets: [{
                        label: 'Игр',
                        data: data.map(r => r.visits),
                        borderColor: 'rgba(13, 110, 253, 0.8)',
                        backgroundColor: 'rgba(13, 110, 253, 0.1)',
                        fill: true,
                        tension: 0.4,
                        pointRadius: 4,
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true } }
                }
            });
        }
    });
    </script>
    </x-slot>

    {{-- PREMIUM РАЗДЕЛ --}}
    @if($isPremium)
    <div class="ramka">
        <h2 class="-mt-05">👑 Premium</h2>
        <div class="f-15 mb-2" style="opacity:.6">
            Подписка активна до <strong>{{ $activePremium->expires_at->format('d.m.Y') }}</strong>
        </div>

        {{-- Табы --}}
        <div class="tabs-content">
            <div class="tabs">
                <div class="tab active" data-tab="premium-friends">👥 Друзья ({{ $friendsCount }})</div>
                <div class="tab" data-tab="premium-visitors">👀 Гости профиля</div>
                <div class="tab" data-tab="premium-history">📊 История игр</div>
                <div class="tab-highlight"></div>
            </div>
            <div class="tab-panes">

                {{-- Друзья --}}
                <div class="tab-pane active" id="premium-friends">
                    @if($friends->isEmpty())
                    <div class="text-center py-3" style="opacity:.5;">
                        <div class="f-24 mb-1">👥</div>
                        <div class="f-16">Друзей пока нет</div>
                        <a href="{{ route('users.index') }}" class="btn btn-secondary mt-1">Найти игроков</a>
                    </div>
                    @else
                    <div class="row row2 mt-1">
                        @foreach($friends as $friend)
                        <div class="col-6 col-md-4" style="margin-bottom:1.5rem;">
                            <div class="card">
                                <div style="display:flex;align-items:center;gap:1.2rem;padding:1.2rem;">
                                    <a href="{{ route('users.show', $friend->id) }}">
                                        <span class="{{ $friend->isPremium() ? 'avatar-premium' : '' }}" style="display:inline-block;position:relative;">
                                            <img src="{{ $friend->profile_photo_url ?? asset('img/no-avatar.png') }}"
                                                 style="width:4rem;height:4rem;border-radius:50%;object-fit:cover;">
                                        </span>
                                    </a>
                                    <div>
                                        <div class="f-15 b-600">
                                            <a href="{{ route('users.show', $friend->id) }}">
                                                {{ $friend->first_name }} {{ $friend->last_name }}
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @if($friendsCount > 6)
                    <div class="text-center mt-1">
                        <a href="{{ route('friends.index') }}" class="btn btn-secondary">
                            Все друзья ({{ $friendsCount }})
                        </a>
                    </div>
                    @endif
                    @endif
                </div>

                {{-- Гости --}}
                <div class="tab-pane" id="premium-visitors">
                    @if($recentVisitors->isEmpty())
                    <div class="text-center py-3" style="opacity:.5;">
                        <div class="f-24 mb-1">👀</div>
                        <div class="f-16">За последние 7 дней никто не заходил</div>
                    </div>
                    @else
                    <div class="row row2 mt-1">
                        @foreach($recentVisitors as $visit)
                        @php $visitor = $visit->visitor; @endphp
                        @if(!$visitor) @continue @endif
                        <div class="col-6 col-md-4" style="margin-bottom:1.5rem;">
                            <div class="card">
                                <div style="display:flex;align-items:center;gap:1.2rem;padding:1.2rem;">
                                    <a href="{{ route('users.show', $visitor->id) }}">
                                        <span class="{{ $visitor->isPremium() ? 'avatar-premium' : '' }}" style="display:inline-block;position:relative;">
                                            <img src="{{ $visitor->profile_photo_url ?? asset('img/no-avatar.png') }}"
                                                 style="width:4rem;height:4rem;border-radius:50%;object-fit:cover;">
                                        </span>
                                    </a>
                                    <div>
                                        <div class="f-15 b-600">
                                            <a href="{{ route('users.show', $visitor->id) }}">
                                                {{ $visitor->first_name }} {{ $visitor->last_name }}
                                            </a>
                                        </div>
                                        <div class="f-13" style="opacity:.5;">
                                            {{ $visit->visited_at->diffForHumans() }}
                                        </div>
                                    </div>
                                </div>
                                @if(!auth()->user()->isFriendWith($visitor->id))
                                <div style="padding:0 1.2rem 1.2rem;">
                                    <form method="POST" action="{{ route('friends.store', $visitor->id) }}">
                                        @csrf
                                        <button class="btn btn-secondary" style="width:100%;font-size:1.4rem;">+ Друг</button>
                                    </form>
                                </div>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                    <div class="text-center mt-1">
                        <a href="{{ route('profile.visitors') }}" class="btn btn-secondary">Все гости</a>
                    </div>
                    @endif
                </div>

                {{-- История игр --}}
                <div class="tab-pane" id="premium-history">
                    {{-- Фильтры --}}
                    <form method="GET" action="{{ route('player.dashboard') }}#premium-history" class="form mb-2">
                        <div class="row row2">
                            <div class="col-md-4">
                                <select name="filter[position]">
                                    <option value="">Все позиции</option>
                                    @foreach(['setter','outside','opposite','middle','libero','player'] as $pos)
                                    <option value="{{ $pos }}" {{ ($historyFilter['position'] ?? '') === $pos ? 'selected' : '' }}>
                                        {{ position_name($pos) }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <select name="filter[location_id]">
                                    <option value="">Все площадки</option>
                                    @foreach($topLocations as $loc)
                                    <option value="{{ $loc->id }}" {{ ($historyFilter['location_id'] ?? '') == $loc->id ? 'selected' : '' }}>
                                        {{ $loc->name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button class="btn btn-secondary" style="width:100%;">Применить</button>
                            </div>
                        </div>
                    </form>

                    @if($gameHistory->isEmpty())
                    <div class="text-center py-3" style="opacity:.5;">
                        <div class="f-16">Нет игр по выбранным фильтрам</div>
                    </div>
                    @else
                    <div class="card">
                        @foreach($gameHistory as $i => $game)
                        <div class="d-flex between fvc py-1 {{ $i > 0 ? 'border-top' : '' }}">
                            <div>
                                <div class="f-16 b-600">
                                    <a href="{{ route('events.show', $game->event_id) }}">{{ $game->title }}</a>
                                </div>
                                <div class="f-13" style="opacity:.5;">
                                    {{ \Carbon\Carbon::parse($game->starts_at)->format('d.m.Y H:i') }}
                                    @if($game->location_name) · {{ $game->location_name }} @endif
                                    @if($game->position) · {{ position_name($game->position) }} @endif
                                </div>
                            </div>
                            @if($game->organizer_name)
                            <div class="f-14" style="opacity:.6;white-space:nowrap;">
                                {{ $game->organizer_name }}
                            </div>
                            @endif
                        </div>
                        @endforeach
                    </div>
                    <div class="mt-2">
                        {{ $gameHistory->appends(['filter' => $historyFilter])->links() }}
                    </div>
                    @endif
                </div>

            </div>
        </div>
    </div>

    @else
    {{-- Не Premium --}}
    <div class="ramka">
        <div class="text-center">
            <div style="font-size:3.6rem; margin-bottom:1.5rem;">👑</div>
            <div class="f-22 b-700 mb-1">Premium возможности</div>
            <div class="mb-2">
                Друзья, гости профиля и детальная история игр доступны в Premium
            </div>
            <a href="{{ route('premium.index') }}" class="btn">Подключить Premium</a>
        </div>
    </div>
    @endif
 </div>
  </div>
    </div>{{-- /container --}}

</x-voll-layout>
