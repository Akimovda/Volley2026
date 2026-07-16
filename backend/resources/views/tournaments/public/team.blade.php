<x-voll-layout body_class="tournament-team-public-page">
@php
$roleLabels = ['captain' => __('tournaments.team_role_captain'), 'player' => __('tournaments.team_role_player'), 'reserve' => __('tournaments.team_role_reserve')];
$posLabels = __('profile.pos_long');
$positionOrder = ['setter', 'outside', 'opposite', 'middle', 'libero'];

$activeMembers = $team->members->where('confirmation_status', 'confirmed');
$captainMember = $activeMembers->firstWhere('user_id', $team->captain_user_id);

$reserveMembers = $activeMembers->filter(fn($m) => (int) $m->user_id !== (int) $team->captain_user_id && $m->effective_team_role === 'reserve');

$squadMembers = $activeMembers->reject(fn($m) => (int) $m->user_id === (int) $team->captain_user_id)
    ->reject(fn($m) => $m->effective_team_role === 'reserve');

// Уровень по направлению турнира — тот же паттерн, что и на странице мероприятия
// (events/show/players.blade.php, 2026-07-16): classic_level приоритетно для
// классики, beach_level для пляжки, с фолбэком на другое поле.
$isBeachPair = $team->team_kind === 'beach_pair';
$levelOf = function ($user) use ($isBeachPair) {
    if (!$user) return null;
    return $isBeachPair
        ? (int) ($user->beach_level ?? $user->classic_level ?? 0)
        : (int) ($user->classic_level ?? $user->beach_level ?? 0);
};
$levelColorOf = function ($user) use ($levelOf) {
    $lvl = $levelOf($user);
    return $lvl > 0 ? level_color($lvl) : '#aaaaaa';
};

if ($team->team_kind === 'classic_team') {
    $squadMembers = $squadMembers->sortBy(function ($m) use ($positionOrder) {
        $idx = array_search($m->effective_position_code, $positionOrder, true);
        return $idx === false ? 99 : $idx;
    })->values();
}
@endphp
<x-slot name="title">{{ $team->name }}</x-slot>
<x-slot name="h1">{{ $team->name }}</x-slot>
<x-slot name="h2">{{ $team->team_kind === 'classic_team' ? __('tournaments.invite_fmt_team_classic') : __('tournaments.invite_fmt_team_beach') }}</x-slot>

<x-slot name="breadcrumbs">
    <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
        <a href="{{ route('tournament.public.show', $event) }}" itemprop="item"><span itemprop="name">{{ $event->title }}</span></a>
        <meta itemprop="position" content="2">
    </li>
    <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
        <span itemprop="name">{{ $team->name }}</span>
        <meta itemprop="position" content="3">
    </li>
</x-slot>

<x-slot name="d_description">
    <div class="d-flex flex-wrap gap-1 m-center">
        <div class="mt-2" data-aos-delay="250" data-aos="fade-up">
            <a href="{{ route('tournament.public.show', $event) }}" class="btn btn-secondary">← {{ __('tournaments.pub_back_to_tournament') }}</a>
        </div>
    </div>
</x-slot>

<div class="container">
    <div class="ramka">

        {{-- Капитан — крупная карточка сверху --}}
        <div class="card mb-3 p-3 ttp-captain-card" @if($captainMember) id="member-{{ $captainMember->user_id }}" @endif>
            <div class="f-13 mb-1" style="opacity:.6">👑 {{ __('tournaments.apps_captain') }}</div>
            @if($team->captain)
            <img src="{{ $captainMember->user->profile_photo_url ?? '' }}" alt="" class="ttp-captain-photo">
            <div class="b-700 f-20">
                <span class="level-dot" style="background:{{ $levelColorOf($team->captain) }}"></span>
                <a href="{{ route('users.show', $team->captain_user_id) }}" class="blink">{{ trim(($team->captain->last_name ?? '') . ' ' . ($team->captain->first_name ?? '')) ?: $team->captain->name }}</a>
                @if($team->captain->gender === 'm')<span class="ttp-gender ttp-gender--m">♂</span>@elseif($team->captain->gender === 'f')<span class="ttp-gender ttp-gender--f">♀</span>@endif
            </div>
            @if($team->team_kind === 'classic_team' && $captainMember?->effective_position_code)
            <div class="f-14" style="opacity:.7">{{ $posLabels[$captainMember->effective_position_code] ?? $captainMember->effective_position_code }}</div>
            @endif
            @else
            <div class="b-700 f-18">#{{ $team->captain_user_id }}</div>
            @endif
        </div>

        {{-- Основной состав --}}
        <h2 class="ttp-section-h2">👥 {{ __('tournaments.team_roster_title') }}</h2>

        @if($squadMembers->isNotEmpty())
        <div class="ttp-squad-grid">
            @php $lastPosition = null; @endphp
            @foreach($squadMembers as $member)
                @if($team->team_kind === 'classic_team' && $member->effective_position_code !== $lastPosition)
                    @php $lastPosition = $member->effective_position_code; @endphp
                    <div class="ttp-position-h3">{{ $posLabels[$lastPosition] ?? $lastPosition ?? __('tournaments.team_role_player') }}</div>
                @endif
                <div class="card ttp-player-card" id="member-{{ $member->user_id }}">
                    <div class="d-flex fvc" style="gap:.8rem;flex-wrap:wrap">
                        <img src="{{ $member->user->profile_photo_url ?? '' }}" alt="" class="ttp-player-photo">
                        <div>
                            <div class="d-flex fvc" style="gap:.4rem;flex-wrap:wrap;row-gap:.4rem">
                                <span class="level-dot" style="background:{{ $levelColorOf($member->user) }}"></span>
                                <a href="{{ route('users.show', $member->user_id) }}" class="blink b-600 f-16">{{ $member->user->name ?? ('#'.$member->user_id) }}</a>
                                @if($member->user?->gender === 'm')<span class="ttp-gender ttp-gender--m">♂</span>@elseif($member->user?->gender === 'f')<span class="ttp-gender ttp-gender--f">♀</span>@endif
                            </div>
                            @if($team->team_kind !== 'classic_team')
                            <span class="f-14" style="opacity:.7">{{ $roleLabels[$member->effective_team_role] ?? $member->effective_team_role }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        @elseif(!$captainMember)
        <div class="alert alert-info">{{ __('tournaments.team_roster_empty') }}</div>
        @endif

        {{-- Запасные --}}
        @if($reserveMembers->isNotEmpty())
        <h2 class="ttp-section-h2 ttp-reserve-h2">🔄 {{ __('tournaments.team_reserve_title') }}</h2>
        <div class="ttp-squad-grid">
            @foreach($reserveMembers as $member)
            <div class="card ttp-player-card ttp-player-card--reserve" id="member-{{ $member->user_id }}">
                <div class="d-flex fvc" style="gap:.8rem;flex-wrap:wrap">
                    <img src="{{ $member->user->profile_photo_url ?? '' }}" alt="" class="ttp-player-photo">
                    <div>
                        <div class="d-flex fvc" style="gap:.4rem;flex-wrap:wrap;row-gap:.4rem">
                            <span class="level-dot" style="background:{{ $levelColorOf($member->user) }}"></span>
                            <a href="{{ route('users.show', $member->user_id) }}" class="blink b-600 f-16">{{ $member->user->name ?? ('#'.$member->user_id) }}</a>
                            @if($member->user?->gender === 'm')<span class="ttp-gender ttp-gender--m">♂</span>@elseif($member->user?->gender === 'f')<span class="ttp-gender ttp-gender--f">♀</span>@endif
                        </div>
                        @if($team->team_kind === 'classic_team' && $member->effective_position_code)
                        <span class="f-14" style="opacity:.7">{{ $posLabels[$member->effective_position_code] ?? $member->effective_position_code }}</span>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @endif

        {{-- Средний уровень / рейтинг команды --}}
        @if(!is_null($teamLevel) || !is_null($teamRating))
        <div class="card p-3 mt-3 ttp-rating-card">
            @if($team->team_kind === 'classic_team' && !is_null($teamLevel))
            <div class="f-14" style="opacity:.7">{{ __('tournaments.team_avg_level_label') }}</div>
            <div class="b-700 f-24">{{ number_format($teamLevel, 2, '.', '') }} / 7</div>
            @elseif(!is_null($teamRating))
            <div class="f-14" style="opacity:.7">{{ __('tournaments.team_rating_label') }}</div>
            <div class="b-700 f-24" style="color:#E7612F">{{ number_format($teamRating, 1, '.', '') }}</div>
            @endif
        </div>
        @endif

    </div>
</div>

<style>
.ttp-captain-card { text-align: center; }
.ttp-captain-photo {
    width: 72px; height: 72px; border-radius: 50%; object-fit: cover;
    margin: 0 auto .6rem; display: block;
}
.ttp-section-h2 { margin-top: 1.5rem; }
.ttp-reserve-h2 { opacity: .75; }
.ttp-squad-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: .75rem;
}
.ttp-position-h3 {
    grid-column: 1 / -1;
    font-weight: 700;
    font-size: .8rem;
    text-transform: uppercase;
    letter-spacing: .03em;
    opacity: .5;
    margin: .5rem 0 -.25rem;
}
.ttp-position-h3:first-child { margin-top: 0; }
.ttp-player-photo {
    width: 40px; height: 40px; border-radius: 50%; object-fit: cover; flex-shrink: 0;
}
.ttp-player-card--reserve {
    border: 1px dashed rgba(128,128,128,.4);
    opacity: .8;
}
.ttp-gender { font-size: .9rem; line-height: 1; }
.ttp-gender--m { color: #2967BA; }
.ttp-gender--f { color: #E7612F; }
.ttp-rating-card { text-align: center; }
.ttp-highlight { outline: 2px solid #E7612F; outline-offset: 2px; }

@media (min-width: 992px) {
    .ttp-captain-photo { width: 96px; height: 96px; }
}
@media (max-width: 480px) {
    .ttp-squad-grid { grid-template-columns: 1fr; }
}
</style>

<script>
(function () {
    var hash = window.location.hash;
    if (!hash || hash.indexOf('#member-') !== 0) return;
    var el = document.getElementById(hash.slice(1));
    if (!el) return;
    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    el.classList.add('ttp-highlight');
    setTimeout(function () { el.classList.remove('ttp-highlight'); }, 2500);
})();
</script>
</x-voll-layout>
