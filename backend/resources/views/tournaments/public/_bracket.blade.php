@once
<style>
/* ───── Tournament Bracket ───── */
.bk-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch;padding:4px 2px 16px}
.bk-inner{position:relative;display:inline-block;min-width:max-content}
.bk-rounds{display:flex;align-items:flex-start}
.bk-col{flex-shrink:0;width:244px}
.bk-col-gap{width:56px;flex-shrink:0}
.bk-col-label{
    text-align:center;font-size:.72rem;font-weight:700;
    letter-spacing:.07em;text-transform:uppercase;
    color:#9ca3af;margin-bottom:12px;height:22px;line-height:22px;
}
.bk-matches{display:flex;flex-direction:column}

/* ── Карточка матча ── */
.bk-match{
    width:244px;background:#fff;
    border:1.5px solid rgba(0,0,0,.09);
    border-left:3px solid #2967BA;
    border-radius:12px;overflow:hidden;
    box-shadow:0 1px 5px rgba(0,0,0,.07);
}
.bk-match--completed{
    border-color:rgba(16,185,129,.28);
    border-left-color:#10b981;
    box-shadow:0 2px 8px rgba(16,185,129,.1);
}
.bk-match--empty{border-color:rgba(0,0,0,.07);border-left-color:#d1d5db}
.bk-match--cancelled{border-color:rgba(0,0,0,.07);border-left-color:#d1d5db;opacity:.65}
.bk-match--third{border-left-color:#f59e0b}
.bk-match--third.bk-match--completed{border-left-color:#10b981}

/* ── Bracket reset: GF2 отменён (reset не потребовался) ── */
.bk-cancelled-note{
    padding:16px 10px;text-align:center;
    font-size:.78rem;color:#9ca3af;font-style:italic;
}

/* ── Шапка матча ── */
.bk-header{
    display:flex;align-items:center;gap:6px;
    height:30px;padding:0 10px;
    background:rgba(0,0,0,.025);
    border-bottom:1px solid rgba(0,0,0,.07);
    font-size:.73rem;color:#6b7280;font-weight:700;
    white-space:nowrap;overflow:hidden;
}
.bk-court{
    background:rgba(41,103,186,.11);color:#2967BA;
    border-radius:5px;padding:2px 6px;
    font-size:.68rem;font-weight:700;flex-shrink:0;
}
.bk-time{margin-left:auto;font-size:.68rem;opacity:.6;flex-shrink:0}
.bk-check{color:#10b981;font-size:.85rem;flex-shrink:0;margin-left:auto}

/* ── Секция команды ── */
.bk-team{
    display:flex;align-items:center;
    padding:8px 10px;gap:10px;
    border-bottom:1px solid rgba(0,0,0,.06);
    min-height:52px;
}
.bk-team:last-child{border-bottom:none}
.bk-team--win{background:rgba(16,185,129,.07)}

/* ── Список игроков ── */
.bk-players{flex:1;display:flex;flex-direction:column;gap:5px;min-width:0}

.bk-player{
    display:flex;align-items:center;gap:8px;
    text-decoration:none;color:inherit;
    transition:opacity .15s;
}
.bk-player:hover{opacity:.75}

.bk-player-avatar{
    width:28px;height:28px;border-radius:50%;
    object-fit:cover;flex-shrink:0;
    border:1.5px solid rgba(255,255,255,.9);
    box-shadow:0 1px 3px rgba(0,0,0,.12);
}
.bk-player-avatar--initials{
    display:flex;align-items:center;justify-content:center;
    font-size:.62rem;font-weight:800;color:#fff;
    text-transform:uppercase;letter-spacing:-.02em;
}

.bk-player-name{
    font-size:.85rem;font-weight:600;
    white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
    color:#1f2937;flex:1;min-width:0;
}
.bk-player-name--tbd{color:#9ca3af;font-style:italic;font-weight:500}
.bk-team--win .bk-player-name{color:#065f46;font-weight:700}

/* ── Название команды (классика) + капитан ── */
.bk-team-classic{flex:1;display:flex;flex-direction:column;gap:6px;min-width:0}
.bk-team-name-link{
    font-size:.85rem;font-weight:700;color:#2967BA;text-decoration:none;
    white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block;
}
.bk-team-name-link:hover{text-decoration:underline}
.bk-team--win .bk-team-name-link{color:#065f46}

/* ── Счёт ── */
.bk-score{
    width:32px;height:32px;border-radius:8px;
    display:flex;align-items:center;justify-content:center;
    font-size:.9rem;font-weight:800;flex-shrink:0;
}
.bk-score--win{background:#10b981;color:#fff}
.bk-score--lose{background:rgba(0,0,0,.08);color:#6b7280}

/* ── Матч за 3-е ── */
.bk-third-section{margin-top:24px;padding-top:20px;border-top:1px dashed rgba(0,0,0,.1)}
.bk-third-section-label{
    font-size:.72rem;font-weight:700;color:#9ca3af;
    text-transform:uppercase;letter-spacing:.07em;margin-bottom:12px;
}

/* ── Dark mode ── */
body.dark .bk-match{background:#1e293b;border-color:rgba(255,255,255,.1);border-left-color:#3b82f6}
body.dark .bk-match--completed{border-color:rgba(16,185,129,.3);border-left-color:#10b981}
body.dark .bk-match--empty{border-color:rgba(255,255,255,.07);border-left-color:#475569}
body.dark .bk-match--cancelled{border-color:rgba(255,255,255,.07);border-left-color:#475569}
body.dark .bk-cancelled-note{color:#64748b}
body.dark .bk-header{background:rgba(255,255,255,.03);color:#94a3b8;border-color:rgba(255,255,255,.06)}
body.dark .bk-court{background:rgba(59,130,246,.15);color:#93c5fd}
body.dark .bk-team{border-color:rgba(255,255,255,.05)}
body.dark .bk-team--win{background:rgba(16,185,129,.12)}
body.dark .bk-player-name{color:#e2e8f0}
body.dark .bk-team--win .bk-player-name{color:#6ee7b7;font-weight:700}
body.dark .bk-score--lose{background:rgba(255,255,255,.1);color:#94a3b8}
body.dark .bk-third-section{border-color:rgba(255,255,255,.08)}
body.dark .bk-team-name-link{color:#93c5fd}
body.dark .bk-team--win .bk-team-name-link{color:#6ee7b7}
</style>
@endonce

@php
/**
 * Bracket-сетка (single elimination).
 *
 * @var \App\Models\TournamentStage $stage
 * @var \Illuminate\Support\Collection $matches
 */

$totalRounds = $matches->max('round') ?? 0;
$thirdPlace  = $matches->first(fn($m) => $m->bracket_position === 'third_place');
$mainMatches = $matches->filter(fn($m) => $m->bracket_position !== 'third_place');

$matchesByRound = [];
for ($r = 1; $r <= $totalRounds; $r++) {
    $matchesByRound[$r] = $mainMatches
        ->filter(fn($m) => $m->round == $r)
        ->sortBy('match_number')
        ->values();
}

// Bracket reset (double elimination): GF1 (court='Grand Final') и GF2
// (court='Grand Final Reset') — их раунды всегда старше остальных, определяем
// по court, а не по позиции в $totalRounds (тот теперь указывает на GF2, если
// он есть, из-за чего наивная арифметика "$total-$r" ниже съезжает). Для
// single_elim (нет такого court вообще) — не влияет, работает как раньше.
$gf1Round = $mainMatches->first(fn($m) => $m->court === 'Grand Final')?->round;
$gf2Round = $mainMatches->first(fn($m) => $m->court === 'Grand Final Reset')?->round;
// Раунд-"точка отсчёта" для generic-лейблов (Финал/Полуфинал/...) — GF1, если он
// есть (тот же ориентир, что был до появления GF2), иначе $totalRounds как раньше.
$labelReferenceRound = $gf1Round ?? $totalRounds;

$courtLabel = function (?string $court) {
    return match ($court) {
        'Grand Final'       => 'Финал',
        'Grand Final Reset' => 'Решающий матч',
        default             => $court,
    };
};

// Вертикальное выравнивание раундов:
// cardH ≈ 184px (header 30 + 2 команды × 2 игрока × 30px + padding)
// baseGap = 20, stepH = (cardH + baseGap) / 2
// padTop[r] = stepH * (2^(r-1) - 1)
// gapBetween[r] = stepH * 2^r - cardH
$cardH   = 184;
$baseGap = 20;
$stepH   = ($cardH + $baseGap) / 2; // 102

$roundLabel = function(int $r, int $total): string {
    return match ($total - $r) {
        0 => 'Финал',
        1 => 'Полуфинал',
        2 => 'Четвертьфинал',
        default => 'Раунд ' . $r,
    };
};

// Цвета аватаров (когда нет фото)
$palette = ['#2967BA','#10b981','#f59e0b','#8b5cf6','#ec4899','#06b6d4','#84cc16','#ef4444'];
$avatarColor = function(?string $seed) use ($palette): string {
    if (!$seed) return '#9ca3af';
    $h = 0;
    foreach (mb_str_split($seed) as $ch) {
        $h = ($h * 31 + mb_ord($ch)) & 0x7FFFFFFF;
    }
    return $palette[$h % count($palette)];
};
$initials = function(?string $first, ?string $last): string {
    $a = $first ? mb_strtoupper(mb_substr($first, 0, 1)) : '';
    $b = $last  ? mb_strtoupper(mb_substr($last,  0, 1)) : '';
    return $a . $b ?: '?';
};

// Отрисовка секции команды (home или away)
$renderTeam = function(?object $team, bool $isWinner, ?int $sets, bool $isCompleted) use ($avatarColor, $initials): string {
    $html  = '<div class="bk-team' . ($isWinner ? ' bk-team--win' : '') . '">';

    if ($team && $team->team_kind === 'classic_team') {
        // Классика: название команды (ссылка на состав) + капитан отдельной строкой
        $teamUrl = route('tournament.public.team', [$team->event_id, $team->id]);
        $captain = $team->captain;

        $html .= '<div class="bk-team-classic">';
        $html .= '<a href="' . e($teamUrl) . '" class="bk-team-name-link">' . e($team->name ?: '?') . '</a>';

        if ($captain) {
            $first    = $captain->first_name ?? '';
            $last     = $captain->last_name  ?? '';
            $display  = trim("{$last} {$first}") ?: ($captain->name ?? 'Игрок');
            $url      = route('users.show', $captain->id);
            $photoUrl = $captain->profile_photo_url;

            $html .= '<a href="' . e($url) . '" class="bk-player">';
            if ($photoUrl) {
                $html .= '<img src="' . e($photoUrl) . '" class="bk-player-avatar" alt="' . e($display) . '" loading="lazy">';
            } else {
                $color = $avatarColor($captain->name ?? $display);
                $ini   = $initials($first, $last);
                $html .= '<div class="bk-player-avatar bk-player-avatar--initials" style="background:' . $color . '">' . e($ini) . '</div>';
            }
            $html .= '<span class="bk-player-name">' . e($display) . '</span>';
            $html .= '</a>';
        }

        $html .= '</div>'; // .bk-team-classic
    } else {
        $html .= '<div class="bk-players">';

        if ($team) {
            // Пляжка: до 2 участников пары, капитан первым
            $members = $team->members
                ->sortByDesc(fn($m) => $m->role_code === 'captain' ? 1 : 0)
                ->take(2);

            foreach ($members as $member) {
                $u       = $member->user;
                $first   = $u?->first_name ?? '';
                $last    = $u?->last_name  ?? '';
                $display = trim("{$last} {$first}") ?: ($u?->name ?? 'Игрок');
                $url      = $u ? route('users.show', $u->id) : '#';
                // profile_photo_url: фото из коллекции photos (avatar_media_id)
                //   либо автоматический ui-avatars.com — всегда возвращает URL
                $photoUrl = $u ? $u->profile_photo_url : '';

                $html .= '<a href="' . e($url) . '" class="bk-player">';
                if ($photoUrl) {
                    $html .= '<img src="' . e($photoUrl) . '" class="bk-player-avatar" alt="' . e($display) . '" loading="lazy">';
                } else {
                    $color = $avatarColor($u?->name ?? $display);
                    $ini   = $initials($first, $last);
                    $html .= '<div class="bk-player-avatar bk-player-avatar--initials" style="background:' . $color . '">' . e($ini) . '</div>';
                }
                $html .= '<span class="bk-player-name">' . e($display) . '</span>';
                $html .= '</a>';
            }
        } else {
            // TBD
            $html .= '<div class="bk-player">';
            $html .= '<div class="bk-player-avatar bk-player-avatar--initials" style="background:#e5e7eb;color:#9ca3af">?</div>';
            $html .= '<span class="bk-player-name bk-player-name--tbd">TBD</span>';
            $html .= '</div>';
        }

        $html .= '</div>'; // .bk-players
    }

    if ($isCompleted && $sets !== null) {
        $cls   = $isWinner ? 'bk-score--win' : 'bk-score--lose';
        $html .= '<div class="bk-score ' . $cls . '">' . $sets . '</div>';
    }

    $html .= '</div>'; // .bk-team
    return $html;
};

$bracketId = 'bk-' . $stage->id;
$tz = $stage->event->timezone ?? 'Europe/Moscow';
@endphp

@if($totalRounds === 0)
    <div style="text-align:center;padding:36px 0;opacity:.4;font-size:.95rem">
        Сетка появится после жеребьёвки плей-офф
    </div>
@else
<div class="bk-wrap">
<div class="bk-inner" id="{{ $bracketId }}">

<div class="bk-rounds">
@for($r = 1; $r <= $totalRounds; $r++)
    @php
        $roundMatches = $matchesByRound[$r] ?? collect();
        $padTop = (int) round($stepH * (pow(2, $r - 1) - 1));
        $gap    = $r === 1 ? $baseGap : (int) round($stepH * pow(2, $r) - $cardH);
        // Bracket reset: GF1/GF2-раунды получают явную подпись по court, а не
        // из арифметики $total-$r (см. $gf1Round/$gf2Round выше) — иначе после
        // добавления GF2 (round=max) подписи "Финал"/"Полуфинал" съезжают местами.
        if ($gf2Round !== null && $r === $gf2Round) {
            $label = 'Решающий матч';
        } elseif ($gf1Round !== null && $r === $gf1Round) {
            $label = 'Финал';
        } else {
            $label = $roundLabel($r, $labelReferenceRound);
        }
    @endphp

    @if($r > 1)<div class="bk-col-gap"></div>@endif

    <div class="bk-col">
        <div class="bk-col-label">{{ $label }}</div>
        <div class="bk-matches" style="gap:{{ $gap }}px;padding-top:{{ $padTop }}px">
            @foreach($roundMatches as $m)
            @php
                $isCompleted = $m->status === 'completed';
                // Bracket reset: GF2 cancelled = reset не потребовался (upper-
                // представитель выиграл GF1 напрямую) — это не "матч ожидает
                // команды" (bk-match--empty), а осознанно неигранный матч.
                $isCancelled = $m->status === 'cancelled';
                $homeWin = $m->winner_team_id && $m->winner_team_id === $m->team_home_id;
                $awayWin = $m->winner_team_id && $m->winner_team_id === $m->team_away_id;
                $cls = 'bk-match';
                if ($isCompleted)                                $cls .= ' bk-match--completed';
                elseif ($isCancelled)                            $cls .= ' bk-match--cancelled';
                elseif (!$m->team_home_id || !$m->team_away_id)  $cls .= ' bk-match--empty';
            @endphp
            <div class="{{ $cls }}" data-match-id="{{ $m->id }}" data-next-match-id="{{ $m->next_match_id }}">
                {{-- Шапка --}}
                <div class="bk-header">
                    <span>Матч&thinsp;{{ $m->match_number }}</span>
                    @if($m->court)<span class="bk-court">{{ $courtLabel($m->court) }}</span>@endif
                    @if($m->scheduled_at)<span class="bk-time">{{ $m->scheduled_at->setTimezone($tz)->format('H:i') }}</span>@endif
                    @if($isCompleted)<span class="bk-check">✓</span>@endif
                </div>
                @if($isCancelled)
                <div class="bk-cancelled-note">Решающий матч не потребовался</div>
                @else
                {{-- Команды --}}
                {!! $renderTeam($m->teamHome, $homeWin, $isCompleted ? $m->sets_home : null, $isCompleted) !!}
                {!! $renderTeam($m->teamAway, $awayWin, $isCompleted ? $m->sets_away : null, $isCompleted) !!}
                @endif
            </div>
            @endforeach
        </div>
    </div>
@endfor
</div>{{-- .bk-rounds --}}

{{-- SVG коннекторы (bezier, через JS) --}}
<svg id="{{ $bracketId }}-svg"
     style="position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;overflow:visible">
</svg>

</div>{{-- .bk-inner --}}
</div>{{-- .bk-wrap --}}

{{-- Матч за 3-е место --}}
@if($thirdPlace)
@php
    $m3 = $thirdPlace;
    $c3 = $m3->status === 'completed';
    $h3 = $m3->winner_team_id && $m3->winner_team_id === $m3->team_home_id;
    $a3 = $m3->winner_team_id && $m3->winner_team_id === $m3->team_away_id;
    $cls3 = 'bk-match bk-match--third' . ($c3 ? ' bk-match--completed' : (!$m3->team_home_id || !$m3->team_away_id ? ' bk-match--empty' : ''));
@endphp
<div class="bk-third-section">
    <div class="bk-third-section-label">🥉 Матч за 3-е место</div>
    <div class="{{ $cls3 }}" style="max-width:244px">
        <div class="bk-header">
            <span>Матч&thinsp;{{ $m3->match_number }}</span>
            @if($m3->court)<span class="bk-court">{{ $courtLabel($m3->court) }}</span>@endif
            @if($m3->scheduled_at)<span class="bk-time">{{ $m3->scheduled_at->setTimezone($tz)->format('H:i') }}</span>@endif
            @if($c3)<span class="bk-check">✓</span>@endif
        </div>
        {!! $renderTeam($m3->teamHome, $h3, $c3 ? $m3->sets_home : null, $c3) !!}
        {!! $renderTeam($m3->teamAway, $a3, $c3 ? $m3->sets_away : null, $c3) !!}
    </div>
</div>
@endif

{{-- JS: SVG bezier-линии по реальным DOM-координатам --}}
<script>
(function(){
    function draw(){
        var inner = document.getElementById('{{ $bracketId }}');
        if(!inner) return;
        var svg = document.getElementById('{{ $bracketId }}-svg');
        if(!svg) return;
        svg.innerHTML = '';

        var iRect = inner.getBoundingClientRect();
        var dark  = document.body.classList.contains('dark');
        var color = dark ? 'rgba(100,116,139,.5)' : 'rgba(0,0,0,.12)';

        var els = inner.querySelectorAll('[data-match-id]');
        var map = {};
        els.forEach(function(el){ map[el.dataset.matchId] = el; });

        els.forEach(function(el){
            var nid = el.dataset.nextMatchId;
            if(!nid || !map[nid]) return;

            var a = el.getBoundingClientRect();
            var b = map[nid].getBoundingClientRect();
            var x1 = a.right  - iRect.left,  y1 = a.top + a.height/2 - iRect.top;
            var x2 = b.left   - iRect.left,  y2 = b.top + b.height/2 - iRect.top;
            var cx = (x2 - x1) * 0.46;

            var p = document.createElementNS('http://www.w3.org/2000/svg','path');
            p.setAttribute('d','M '+x1+' '+y1+' C '+(x1+cx)+' '+y1+' '+(x2-cx)+' '+y2+' '+x2+' '+y2);
            p.setAttribute('fill','none');
            p.setAttribute('stroke', color);
            p.setAttribute('stroke-width','2');
            p.setAttribute('stroke-linecap','round');
            svg.appendChild(p);
        });
    }
    if(document.readyState==='loading'){
        document.addEventListener('DOMContentLoaded', draw);
    } else {
        setTimeout(draw, 0);
    }
})();
</script>
@endif
