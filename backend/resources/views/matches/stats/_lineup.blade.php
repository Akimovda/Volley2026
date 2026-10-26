@php
    $teams = $stats['teams'];
    $lu = $stats['lineup'];
    $zoneTxt = fn ($z) => $z ? __('match_stats.lu_zone', ['n' => $z]) : '';
    $posTxt = fn ($p) => $p ? __('tournaments.pos_abbr_' . $p) : '';
@endphp
@foreach($lu['sets'] as $sn => $ls)
<div class="ramka">
    <h2 class="-mt-05">{{ __('match_stats.set_n', ['n' => $sn]) }}</h2>
    <div class="mx-lu-grid">
        @foreach(['home', 'away'] as $side)
        <div class="mx-lu-team">
            <div class="mx-team-title mx-team-title--{{ $side }}">{{ $teams[$side]['name'] }}</div>
            <h4>{{ __('match_stats.lu_start') }}</h4>
            @if($ls['teams'][$side]['court'] || $ls['teams'][$side]['libero'])
            <ul class="mx-lu-list">
                @foreach($ls['teams'][$side]['court'] as $p)
                <li><span class="z">{{ $zoneTxt($p['zone']) }}</span>{{ $p['name'] }} <small style="opacity:.55">{{ $posTxt($p['position']) }}</small></li>
                @endforeach
                @foreach($ls['teams'][$side]['libero'] as $p)
                <li><span class="z">{{ __('match_stats.lu_libero') }}</span>{{ $p['name'] }}</li>
                @endforeach
            </ul>
            @else
            <div class="mx-note">{{ __('match_stats.lu_no_start') }}</div>
            @endif
        </div>
        @endforeach
    </div>
    <h4 style="margin:1rem 0 .4rem">{{ __('match_stats.lu_subs') }}</h4>
    @forelse($ls['events'] as $e)
    @php
        $text = match ($e['type']) {
            'substitution' => __('match_stats.lu_sub', ['in' => $e['in'] ?? '—', 'out' => $e['out'] ?? '—']),
            'libero_in'    => __('match_stats.lu_libero_in', ['in' => $e['in'] ?? '—', 'out' => $e['out'] ?? '—']),
            default        => __('match_stats.lu_libero_out', ['in' => $e['in'] ?? '—', 'out' => $e['out'] ?? '—']),
        };
    @endphp
    <div class="mx-ev mx-ev--lineup" style="grid-template-columns:1fr">
        <div>{{ __('match_stats.lu_event_line', ['set' => $sn, 'score' => $e['score_home'] . ':' . $e['score_away'], 'text' => $text]) }}
            <span class="t t--{{ $e['side'] }}" style="font-weight:700">({{ $teams[$e['side']]['name'] }})</span></div>
    </div>
    @empty
    <div class="mx-note">{{ __('match_stats.lu_no_events') }}</div>
    @endforelse
</div>
@endforeach

<div class="ramka">
    <h2 class="-mt-05">{{ __('match_stats.lu_sets_played') }}</h2>
    <div class="mx-lu-grid">
        @foreach(['home', 'away'] as $side)
        <div class="mx-lu-team">
            <div class="mx-team-title mx-team-title--{{ $side }}">{{ $teams[$side]['name'] }}</div>
            <ul class="mx-lu-list">
                @foreach($lu['sets_played'][$side] as $p)
                <li><span class="z">{{ $p['sets'] }}</span>{{ $p['name'] }}</li>
                @endforeach
            </ul>
        </div>
        @endforeach
    </div>
</div>
