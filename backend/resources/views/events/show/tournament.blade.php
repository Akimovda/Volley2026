@php
    $tournamentUi = $tournamentUi ?? [
        'enabled' => false,
        'setting' => null,
        'myTeams' => collect(),
    ];

    $isTournament = (bool) ($tournamentUi['enabled'] ?? false);
@endphp

@if($isTournament)
    {{-- Ссылка на публичные результаты (если турнир настроен) --}}
    @php
        $hasStages = \App\Models\TournamentStage::where('event_id', $event->id)->exists();
    @endphp
    @if($hasStages)
        <div class="ramka" style="text-align:center">
            <a href="{{ route('tournament.public.show', $event) }}" class="btn btn-primary">
                {{ __('events.show_tournament_results_btn') }}
            </a>
        </div>
    @endif
@endif
