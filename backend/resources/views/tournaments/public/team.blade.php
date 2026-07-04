<x-voll-layout body_class="tournament-team-public-page">
@php
$roleLabels = ['captain' => __('tournaments.team_role_captain'), 'player' => __('tournaments.team_role_player'), 'reserve' => __('tournaments.team_role_reserve')];
$posLabels = __('profile.pos_long');
$activeMembers = $team->members->where('confirmation_status', 'confirmed');
$captainMember = $activeMembers->firstWhere('user_id', $team->captain_user_id);
$restMembers = $activeMembers->reject(fn($m) => (int) $m->user_id === (int) $team->captain_user_id);
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
        <div class="card mb-3 p-3 team-captain-card">
            <div class="f-13 mb-1" style="opacity:.6">👑 {{ __('tournaments.apps_captain') }}</div>
            @if($team->captain)
            <img src="{{ $captainMember->user->profile_photo_url ?? '' }}" alt="" class="team-captain-photo">
            <div class="b-700 f-20">
                <a href="{{ route('users.show', $team->captain_user_id) }}" class="blink">{{ trim(($team->captain->last_name ?? '') . ' ' . ($team->captain->first_name ?? '')) ?: $team->captain->name }}</a>
            </div>
            @if($team->team_kind === 'classic_team' && $captainMember?->position_code)
            <div class="f-14" style="opacity:.7">{{ $posLabels[$captainMember->position_code] ?? $captainMember->position_code }}</div>
            @endif
            @else
            <div class="b-700 f-18">#{{ $team->captain_user_id }}</div>
            @endif
        </div>

        <h2 class="-mt-05">👥 {{ __('tournaments.team_roster_title') }}</h2>

        @if($restMembers->isNotEmpty())
        <div class="team-roster-grid">
            @foreach($restMembers as $member)
            <div class="card">
                <div class="d-flex fvc" style="gap:.8rem;flex-wrap:wrap">
                    <img src="{{ $member->user->profile_photo_url ?? '' }}" alt="" style="width:36px;height:36px;border-radius:50%;object-fit:cover;flex-shrink:0;">
                    <div>
                        <div class="d-flex fvc" style="gap:.6rem;flex-wrap:wrap;row-gap:.4rem">
                            <a href="{{ route('users.show', $member->user_id) }}" class="blink b-600 f-16">{{ $member->user->name ?? ('#'.$member->user_id) }}</a>
                            <span class="f-14" style="opacity:.7">
                                {{ $roleLabels[$member->team_role] ?? $member->team_role }}
                                @if($team->team_kind === 'classic_team' && $member->position_code)
                                    · {{ $posLabels[$member->position_code] ?? $member->position_code }}
                                @endif
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @elseif(!$captainMember)
        <div class="alert alert-info">{{ __('tournaments.team_roster_empty') }}</div>
        @endif
    </div>
</div>

<style>
.team-captain-card { text-align: center; }
.team-captain-photo {
    width: 72px; height: 72px; border-radius: 50%; object-fit: cover;
    margin: 0 auto .6rem; display: block;
}
.team-roster-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: .75rem;
}
@media (min-width: 992px) {
    .team-captain-photo { width: 96px; height: 96px; }
}
</style>
</x-voll-layout>
