<x-voll-layout body_class="tournament-stats-page">
<x-slot name="title">{{ __('tournaments.stats_title') }} — {{ $event->title }}</x-slot>
<x-slot name="h1">📊 {{ __('tournaments.stats_h1') }}</x-slot>

<x-slot name="breadcrumbs">
    <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
        <a href="{{ route('tournament.setup', $event) }}" itemprop="item"><span itemprop="name">{{ $event->title }}</span></a>
        <meta itemprop="position" content="2">
    </li>
    <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
        <span itemprop="name">{{ __('tournaments.stats_match_n', ['n' => $match->match_number]) }}</span>
        <meta itemprop="position" content="3">
    </li>
</x-slot>

<div class="container">
	
    @if(session('error'))
         <div class="alert alert-error">
            {{ session('error') }}
        </div>
    @endif
    @if(session('success'))
         <div class="alert alert-error">
            {{ session('success') }}
        </div>
    @endif	
	
	
<div class="ramka">

    {{-- Шапка матча --}}
    <div class="card mb-3" style="text-align:center">
        <div class="mb-1">
            {{ __('tournaments.stats_match_header', ['n' => $match->match_number]) }} · {{ $match->setsScore() }} ({{ $match->detailedScore() }})
        </div>
        <div class="d-flex between fvc">
            <div style="flex:1"><span class="b-600">{{ $match->teamHome->name ?? 'TBD' }}</span></div>
            <div class="px-2 f-20 b-600">VS</div>
            <div style="flex:1"><span class="b-600">{{ $match->teamAway->name ?? 'TBD' }}</span></div>
        </div>
    </div>

    {{-- Табы: по сетам + итого --}}
    <div class="d-flex gap-1 text-center" style="flex-wrap:wrap" id="setTabs">
        @for($s = 1; $s <= $setsCount; $s++)
            <button type="button" class="btn {{ $s === 1 ? 'btn-primary' : 'btn-secondary' }} set-tab"
                    data-set="{{ $s }}" onclick="switchSet({{ $s }})">
                {{ __('tournaments.score_set_n', ['n' => $s]) }}
            </button>
        @endfor
        <button type="button" class="btn btn-secondary"
                data-set="0" onclick="switchSet(0)">
            {{ __('tournaments.stats_total') }}
        </button>
    </div>

    <form method="POST" action="{{ route('tournament.matches.player_stats.save', $match) }}" id="statsForm">
        @csrf

        @php
            $fieldLabels = [
                'serves_total'    => ['label' => __('tournaments.stat_serves'), 'short' => __('tournaments.stat_serves_short')],
                'aces'            => ['label' => __('tournaments.stat_aces'), 'short' => __('tournaments.stat_aces_short')],
                'serve_errors'    => ['label' => __('tournaments.stat_serve_err'), 'short' => __('tournaments.stat_serve_err_short')],
                'attacks_total'   => ['label' => __('tournaments.stat_attacks'), 'short' => __('tournaments.stat_attacks_short')],
                'kills'           => ['label' => __('tournaments.stat_kills'), 'short' => __('tournaments.stat_kills_short')],
                'attack_errors'   => ['label' => __('tournaments.stat_attack_err'), 'short' => __('tournaments.stat_attack_err_short')],
                'blocks'          => ['label' => __('tournaments.stat_blocks'), 'short' => __('tournaments.stat_blocks_short')],
                'block_errors'    => ['label' => __('tournaments.stat_block_err'), 'short' => __('tournaments.stat_block_err_short')],
                'digs'            => ['label' => __('tournaments.stat_digs'), 'short' => __('tournaments.stat_digs_short')],
                'reception_errors'=> ['label' => __('tournaments.stat_dig_err'), 'short' => __('tournaments.stat_dig_err_short')],
                'assists'         => ['label' => __('tournaments.stat_assists'), 'short' => __('tournaments.stat_assists_short')],
            ];
            $teams = [
                ['id' => $match->team_home_id, 'name' => $match->teamHome->name ?? 'Home', 'players' => $players['home']],
                ['id' => $match->team_away_id, 'name' => $match->teamAway->name ?? 'Away', 'players' => $players['away']],
            ];
        @endphp

</div>

        {{-- Контент по сетам --}}
        @for($s = 0; $s <= $setsCount; $s++)
            <div class="set-content" data-set="{{ $s }}" style="{{ $s !== 1 && $s !== 0 ? 'display:none;' : '' }}{{ $s === 0 ? 'display:none;' : '' }}">
                @foreach($teams as $team)
                    <div class="ramka">
                        <h2 class="-mt-05">{{ $team['name'] }}</h2>

                        @foreach($team['players'] as $player)
                            <div class="mb-3">
                                <div class="b-600 mb-2">{{ $player['name'] }}</div>
                                <div class="stats-grid">
								 
                                    @foreach($statFields as $field)
                                        @php
                                            $inputName = "stats[{$team['id']}][{$player['id']}][{$s}][{$field}]";
                                            $existVal = 0;
                                            if (!empty($existingStats['has_stats'])) {
                                                $side = $team['id'] == $match->team_home_id ? 'home' : 'away';
                                                foreach ($existingStats[$side] as $ps) {
                                                    if ($ps['user_id'] == $player['id']) {
                                                        if ($s === 0 && isset($ps['totals'])) {
                                                            $existVal = is_array($ps['totals']) ? ($ps['totals'][$field] ?? 0) : ($ps['totals']->$field ?? 0);
                                                        } elseif (isset($ps['sets'][$s])) {
                                                            $existVal = $ps['sets'][$s]->$field ?? 0;
                                                        }
                                                    }
                                                }
                                            }
                                        @endphp
										<div class="card">
                                        <div class="form stat-cell">
                                            <label class="f-16 b-300"
                                                   title="{{ $fieldLabels[$field]['label'] }}">
                                                {{ $fieldLabels[$field]['short'] }}
                                            </label>
                                            <input type="number" name="{{ $inputName }}"
                                                   value="{{ $existVal }}" min="0" max="999"
                                                   class="stat-input"
                                                   inputmode="numeric">
                                        </div>
										 </div>
                                    @endforeach
                                </div>
								</div>
                           
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endfor
 <div class="ramka text-center">
        <button type="submit" class="btn btn-primary">
            💾 {{ __('tournaments.stats_btn_save') }}
        </button>

        <a href="{{ route('tournament.setup', $event) }}" class="btn btn-secondary">
            {{ __('tournaments.btn_back') }}
        </a>
</div>		
    </form>


</div>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 1rem;
}
@media (max-width: 991px) {
    .stats-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}
@media (max-width: 576px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
.stat-cell {
    text-align: center;
}
.stat-input {
    text-align: center;
}

.set-tab.active, .set-tab.btn-primary {
    font-weight: 700;
}
</style>

<script>
function switchSet(setNum) {
    document.querySelectorAll('.set-content').forEach(function(el) {
        el.style.display = 'none';
    });
    var target = document.querySelector('.set-content[data-set="' + setNum + '"]');
    if (target) target.style.display = '';

    document.querySelectorAll('.set-tab').forEach(function(btn) {
        btn.classList.remove('btn-primary');
        btn.classList.add('btn-secondary');
    });
    var activeBtn = document.querySelector('.set-tab[data-set="' + setNum + '"]');
    if (activeBtn) {
        activeBtn.classList.remove('btn-secondary');
        activeBtn.classList.add('btn-primary');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Автоматически показать первый сет
    switchSet(1);
});
</script>

</x-voll-layout>
