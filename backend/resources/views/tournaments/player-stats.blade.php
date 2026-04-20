<x-voll-layout body_class="tournament-stats-page">
<x-slot name="title">Статистика — {{ $event->title }}</x-slot>
<x-slot name="h1">📊 Статистика игроков</x-slot>

<x-slot name="breadcrumbs">
    <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
        <a href="{{ route('tournament.setup', $event) }}" itemprop="item"><span itemprop="name">{{ $event->title }}</span></a>
        <meta itemprop="position" content="2">
    </li>
    <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
        <span itemprop="name">Статистика матча #{{ $match->match_number }}</span>
        <meta itemprop="position" content="3">
    </li>
</x-slot>

<div class="container">
<div class="ramka" style="max-width:720px;margin:0 auto">

    @if(session('error'))
        <div class="p-3 mb-3" style="background:rgba(220,38,38,.1);border:1px solid rgba(220,38,38,.3);border-radius:10px;color:#dc2626">
            {{ session('error') }}
        </div>
    @endif
    @if(session('success'))
        <div class="p-3 mb-3" style="background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);border-radius:10px;color:#16a34a">
            {{ session('success') }}
        </div>
    @endif

    {{-- Шапка матча --}}
    <div class="card p-3 mb-3" style="text-align:center">
        <div class="f-13 mb-1" style="opacity:.6">
            Матч #{{ $match->match_number }} · {{ $match->setsScore() }} ({{ $match->detailedScore() }})
        </div>
        <div class="d-flex between fvc">
            <div style="flex:1"><span class="b-700 f-16">{{ $match->teamHome->name ?? 'TBD' }}</span></div>
            <div class="px-2 f-18 b-700" style="opacity:.4">VS</div>
            <div style="flex:1"><span class="b-700 f-16">{{ $match->teamAway->name ?? 'TBD' }}</span></div>
        </div>
    </div>

    {{-- Табы: по сетам + итого --}}
    <div class="d-flex mb-3" style="gap:6px;flex-wrap:wrap" id="setTabs">
        @for($s = 1; $s <= $setsCount; $s++)
            <button type="button" class="btn {{ $s === 1 ? 'btn-primary' : 'btn-secondary' }} set-tab f-13 px-3 py-2"
                    data-set="{{ $s }}" onclick="switchSet({{ $s }})">
                Сет {{ $s }}
            </button>
        @endfor
        <button type="button" class="btn btn-secondary set-tab f-13 px-3 py-2"
                data-set="0" onclick="switchSet(0)">
            Итого
        </button>
    </div>

    <form method="POST" action="{{ route('tournament.matches.player_stats.save', $match) }}" id="statsForm">
        @csrf

        @php
            $fieldLabels = [
                'serves_total'    => ['label' => 'Подачи', 'short' => 'Пдч'],
                'aces'            => ['label' => 'Эйсы', 'short' => 'Эйс'],
                'serve_errors'    => ['label' => 'Ош. подачи', 'short' => 'Ош.П'],
                'attacks_total'   => ['label' => 'Атаки', 'short' => 'Атк'],
                'kills'           => ['label' => 'Результ.', 'short' => 'Рез'],
                'attack_errors'   => ['label' => 'Ош. атаки', 'short' => 'Ош.А'],
                'blocks'          => ['label' => 'Блоки', 'short' => 'Блк'],
                'block_errors'    => ['label' => 'Ош. блока', 'short' => 'Ош.Б'],
                'digs'            => ['label' => 'Приём', 'short' => 'Прм'],
                'reception_errors'=> ['label' => 'Ош. приёма', 'short' => 'Ош.Р'],
                'assists'         => ['label' => 'Передачи', 'short' => 'Пер'],
            ];
            $teams = [
                ['id' => $match->team_home_id, 'name' => $match->teamHome->name ?? 'Home', 'players' => $players['home']],
                ['id' => $match->team_away_id, 'name' => $match->teamAway->name ?? 'Away', 'players' => $players['away']],
            ];
        @endphp

        {{-- Контент по сетам --}}
        @for($s = 0; $s <= $setsCount; $s++)
            <div class="set-content" data-set="{{ $s }}" style="{{ $s !== 1 && $s !== 0 ? 'display:none;' : '' }}{{ $s === 0 ? 'display:none;' : '' }}">
                @foreach($teams as $team)
                    <div class="card p-3 mb-3">
                        <div class="b-700 f-15 mb-2">{{ $team['name'] }}</div>

                        @foreach($team['players'] as $player)
                            <div class="mb-3 pb-3" style="border-bottom:1px solid rgba(128,128,128,.15)">
                                <div class="b-600 f-14 mb-2">{{ $player['name'] }}</div>
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
                                        <div class="stat-cell">
                                            <label class="f-11" style="opacity:.7;display:block;text-align:center;margin-bottom:2px"
                                                   title="{{ $fieldLabels[$field]['label'] }}">
                                                {{ $fieldLabels[$field]['short'] }}
                                            </label>
                                            <input type="number" name="{{ $inputName }}"
                                                   value="{{ $existVal }}" min="0" max="999"
                                                   class="stat-input"
                                                   inputmode="numeric">
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endfor

        <button type="submit" class="btn btn-primary w-100 p-3 f-16 mb-2">
            💾 Сохранить статистику
        </button>

        <a href="{{ route('tournament.setup', $event) }}" class="btn btn-secondary w-100 p-2 f-14" style="text-align:center;display:block">
            ← Назад
        </a>
    </form>

</div>
</div>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 4px;
}
@media (max-width: 576px) {
    .stats-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}
.stat-cell {
    text-align: center;
}
.stat-input {
    width: 100%;
    text-align: center;
    font-size: 1rem;
    font-weight: 600;
    padding: 6px 2px;
    border: 1px solid rgba(128,128,128,.3);
    border-radius: 8px;
    background: var(--bg-secondary, #f9fafb);
    color: var(--text-primary, #111);
    -moz-appearance: textfield;
}
.stat-input::-webkit-outer-spin-button,
.stat-input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
.stat-input:focus {
    outline: none;
    border-color: var(--primary, #2563eb);
    box-shadow: 0 0 0 2px rgba(37,99,235,.2);
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
