@once
@include('tournaments._partials.flip_score_css')
<style>
/* ───── Tournament Bracket ───── */
.bk-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch;padding:4px 2px 16px}
.bk-inner{position:relative;display:inline-block;min-width:max-content}
.bk-rounds{display:flex;align-items:flex-start}
.bk-col{flex-shrink:0;width:244px}
.bk-col-gap{width:56px;flex-shrink:0}
.bk-col-label{
    text-align:center;font-size:.85rem;font-weight:700;
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
    font-size:.8rem;font-weight:700;color:#9ca3af;
    text-transform:uppercase;letter-spacing:.07em;margin-bottom:12px;
}
/* double_elim: бронза без дискретного матча — просто имя команды, без карточки счёта */
.bk-third-place-name{font-size:.95rem;font-weight:700;color:#111827}

/* ── Заголовок секции double_elim (Верхняя/Нижняя сетка, Гранд-финал) ── */
.bk-section-title{
    font-size:.85rem;font-weight:700;color:#9ca3af;
    padding:.75rem 0 .25rem;letter-spacing:.07em;text-transform:uppercase;
}
.bk-section-title:first-child{padding-top:0}

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
body.dark .bk-third-place-name{color:#e2e8f0}

/* ───── Оформление 2026-11: крупнее, мягче, акценты победителя ───── */
.bk-wrap{text-align:center}
.bk-inner{text-align:left}
.bk-col{width:264px}
.bk-match{
    width:264px;border-radius:16px;border:1px solid rgba(15,23,42,.08);border-left:4px solid #2967BA;
    background:linear-gradient(#fff,#fbfcff);box-shadow:0 2px 10px rgba(15,23,42,.08);
    transition:box-shadow .2s,transform .2s;
}
.bk-match:hover{box-shadow:0 10px 24px rgba(15,23,42,.15);transform:translateY(-1px)}
.bk-match--completed{border-color:rgba(16,185,129,.3);border-left-color:#10b981;box-shadow:0 3px 12px rgba(16,185,129,.12)}
.bk-match--live{border-color:rgba(220,38,38,.35);border-left-color:#dc2626;animation:bk-livepulse 2s infinite}
@keyframes bk-livepulse{0%,100%{box-shadow:0 2px 10px rgba(220,38,38,.15)}50%{box-shadow:0 0 0 4px rgba(220,38,38,.12),0 3px 16px rgba(220,38,38,.3)}}
.bk-header{height:34px;background:linear-gradient(90deg,rgba(41,103,186,.07),rgba(41,103,186,0));font-size:.76rem;color:#475569}
.bk-live-pill{display:inline-flex;align-items:center;gap:5px;background:#dc2626;color:#fff;border-radius:999px;padding:1px 9px;font-size:.66rem;font-weight:800;letter-spacing:.04em;flex-shrink:0;margin-left:auto}
.bk-live-pill i{width:6px;height:6px;border-radius:50%;background:#fff;animation:bk-livepulse-dot 1.2s infinite}
@keyframes bk-livepulse-dot{0%,100%{opacity:1}50%{opacity:.35}}
.bk-team{min-height:58px;padding:9px 12px;position:relative}
.bk-team--win{background:linear-gradient(90deg,rgba(16,185,129,.16),rgba(16,185,129,.03))}
.bk-team--win::before{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;background:#10b981}
.bk-player-name,.bk-team-name-link{font-size:.9rem}
.bk-flip{display:inline-flex;font-size:30px;flex-shrink:0;line-height:1}
.bk-team:not(.bk-team--win) .bk-flip{opacity:.5;filter:saturate(.35)}
.bk-col-label{display:flex;align-items:center;justify-content:center;gap:6px}
.bk-col-label--final{color:#b45309}
.bk-ribbon{display:flex;align-items:center;justify-content:center;gap:8px;padding:9px 12px;font-weight:800;font-size:.85rem;letter-spacing:.01em}
.bk-ribbon--champ{background:linear-gradient(90deg,#f59e0b,#fcd34d,#f59e0b);color:#3b2a05}
.bk-ribbon--bronze{background:linear-gradient(90deg,#c2763a,#e6a574,#c2763a);color:#2b1606}
.bk-ribbon span{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.bk-third-section{text-align:center}
.bk-third-section .bk-match{margin:0 auto;text-align:left}
.bk-third-section-label{display:flex;align-items:center;justify-content:center;gap:6px}
@media (max-width:600px){.bk-wrap{scroll-snap-type:x proximity}.bk-col{scroll-snap-align:center}}
body.dark .bk-match{background:linear-gradient(#1e293b,#18222f)}
body.dark .bk-header{background:linear-gradient(90deg,rgba(59,130,246,.14),rgba(59,130,246,0));color:#94a3b8}
body.dark .bk-team--win{background:linear-gradient(90deg,rgba(16,185,129,.22),rgba(16,185,129,.04))}
body.dark .bk-col-label--final{color:#fbbf24}

/* подписи источника: «Гр. A · 1 место» цветом группы; «Победитель матча N» в пустых слотах */
.bk-nameline{display:flex;align-items:center;gap:8px;min-width:0}
.bk-nameline .bk-team-name-link{flex:1;min-width:0}
.bk-src{display:inline-flex;align-items:center;flex-shrink:0;background:var(--gc,#64748b);color:#fff;border-radius:6px;padding:1px 7px;font-size:.62rem;font-weight:700;letter-spacing:.02em;line-height:1.5;white-space:nowrap}
.bk-src--line{align-self:flex-start;margin-bottom:1px}

/* ── Симметричная сетка: ФИНАЛ по центру ── */
.bk-rounds--sym{margin-top:96px}
.bk-col--final{width:300px}
.bk-col--final .bk-match{width:300px;box-shadow:0 8px 28px rgba(245,158,11,.22);border-left-color:#f59e0b}
.bk-col--final .bk-match--completed{border-left-color:#f59e0b}
.bk-hero{position:absolute;left:0;right:0;bottom:calc(100% + 14px);text-align:center;color:#b45309}
.bk-hero svg{display:block;margin:0 auto 2px}
.bk-hero-title{font-size:2rem;font-weight:900;letter-spacing:.18em;line-height:1;background:linear-gradient(90deg,#2563eb,#7c3aed 45%,#e7612f);-webkit-background-clip:text;background-clip:text;color:transparent;text-transform:uppercase}
.bk-hero-date{font-size:.9rem;font-weight:600;color:#64748b;margin-top:4px}
.bk-hero-dots{display:flex;align-items:center;justify-content:center;gap:7px;margin-top:8px}
.bk-hero-dots i{display:block;background:linear-gradient(135deg,#38bdf8,#6366f1);transform:rotate(45deg);border-radius:2px}
body.dark .bk-hero-date{color:#94a3b8}
body.dark .bk-hero{color:#fbbf24}
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

// Матч за 3-е место — bracket_position='third_place' никто живой не проставляет
// (единственный писатель — неиспользуемый TournamentSetupService::generateSingleElimBracket()).
// Реальная бронза single_elim лежит в: crossover (generateGroupCrossover()) пишет
// meta.placement_from=3 (источник правды, та же логика что TournamentStage::
// placementMatch()) + текст "Матч за 3-4 место" в court как фоллбэк для матчей
// без meta — court перезаписывается TournamentScheduleService::generateSchedule()
// при назначении реальной площадки, метка по тексту терялась (баг события 422);
// сеточный путь (generateSingleElimination()) пишет литерал 'court' === '3rd place'.
$isThirdPlaceMatch = function ($m) {
    $metaFrom = $m->meta['placement_from'] ?? null;
    if ($metaFrom !== null) {
        return (int) $metaFrom === 3;
    }
    if ($m->court && preg_match('/за\s+(\d+)-\d+\s+место/u', $m->court, $groups)) {
        return (int) $groups[1] === 3;
    }
    return $m->court === '3rd place';
};
$thirdPlace  = $matches->first($isThirdPlaceMatch);
$mainMatches = $matches->reject(fn($m) => $thirdPlace && $m->id === $thirdPlace->id);

// Откуда команда: место в группе (из группового этапа того же тура) — подпись «Гр. A · 1 место» цветом группы,
// как «Winners Group A» в футбольных сетках. Работает и для уже созданных турниров (берём из таблиц групп).
$groupPalette = ['#7c3aed', '#0d9488', '#dc2626', '#d97706', '#16a34a', '#2563eb', '#db2777', '#0891b2'];
$teamSource = [];
$__groupStageIds = \App\Models\TournamentStage::query()
    ->where('event_id', $stage->event_id)
    ->when($stage->occurrence_id, fn ($q) => $q->where('occurrence_id', $stage->occurrence_id), fn ($q) => $q->whereNull('occurrence_id'))
    ->where('id', '!=', $stage->id)
    ->whereHas('groups')
    ->pluck('id');
if ($__groupStageIds->isNotEmpty()) {
    $__groups = \App\Models\TournamentGroup::whereIn('stage_id', $__groupStageIds)->orderBy('sort_order')->get()->keyBy('id');
    $__groupIdx = $__groups->keys()->flip();
    \App\Models\TournamentStanding::whereIn('stage_id', $__groupStageIds)->whereNotNull('group_id')->get(['team_id', 'group_id', 'rank'])
        ->each(function ($st) use (&$teamSource, $__groups, $__groupIdx, $groupPalette) {
            $g = $__groups->get($st->group_id);
            if ($g && $st->rank) {
                $teamSource[$st->team_id] = ['group' => trim((string) preg_replace('/^\s*(Группа|Group)\s+/iu', '', (string) $g->name)) ?: (string) $g->name, 'rank' => (int) $st->rank, 'color' => $groupPalette[($__groupIdx[$st->group_id] ?? 0) % count($groupPalette)]];
            }
        });
}
// Для пустых слотов (команда ещё не известна): «Победитель матча N» / «Проигравший матча N» по связям next_match / loser_next_match
$slotFrom = [];
foreach ($matches as $__f) {
    if ($__f->next_match_id && $__f->next_match_slot) {
        $slotFrom[$__f->next_match_id][$__f->next_match_slot] = ['win', $__f->match_number];
    }
    if ($__f->loser_next_match_id && $__f->loser_next_match_slot) {
        $slotFrom[$__f->loser_next_match_id][$__f->loser_next_match_slot] = ['lose', $__f->match_number];
    }
}

// double_elim: раскладка в одну линию колонок по глобальному round (как для
// single_elim ниже) визуально разъезжается — upper/lower идут вперемешку одна
// колонка за другой, хотя это два параллельных трека + отдельная секция
// Гранд-финала. Секции по рецептуре из генератора (TournamentBracketService::
// generateDoubleElimination()): GF/GF-reset формально bracket_position='upper',
// поэтому фильтруем по court ПЕРЕД bracket_position (см. report_recon_bracket.md).
$isDoubleElim = $stage->type === \App\Models\TournamentStage::TYPE_DOUBLE_ELIM;

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

// Захвачены по use() в $renderMatchCard ниже — обязаны быть определены ДО неё
// (баг: раньше объявлялись после closure, PHP резолвит use() при определении
// функции, не при вызове → ErrorException "Undefined variable $tz").
$bracketId = 'bk-' . $stage->id;
$tz = $stage->event->timezone ?? 'Europe/Moscow';

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
$renderTeam = function(?object $team, bool $isWinner, ?int $sets, bool $isCompleted, string $side = 'home', ?string $tbdLabel = null) use ($avatarColor, $initials, $teamSource): string {
    $html  = '<div class="bk-team' . ($isWinner ? ' bk-team--win' : '') . '">';
    $src = $team ? ($teamSource[$team->id] ?? null) : null;

    if ($team && $team->team_kind === 'classic_team') {
        // Классика: название команды (ссылка на состав) + капитан отдельной строкой
        $teamUrl = route('tournament.public.team', [$team->event_id, $team->id]);
        $captain = $team->captain;

        $html .= '<div class="bk-team-classic">';
        $html .= '<div class="bk-nameline"><a href="' . e($teamUrl) . '" class="bk-team-name-link">' . e($team->name ?: '?') . '</a>';
        if ($src) {
            $html .= '<span class="bk-src" style="--gc:' . $src['color'] . '">' . e(__('tournaments.bk_src', ['group' => $src['group'], 'place' => $src['rank']])) . '</span>';
        }
        $html .= '</div>';

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
        if ($src) {
            $html .= '<span class="bk-src bk-src--line" style="--gc:' . $src['color'] . '">' . e(__('tournaments.bk_src', ['group' => $src['group'], 'place' => $src['rank']])) . '</span>';
        }

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
            $html .= '<span class="bk-player-name bk-player-name--tbd">' . e($tbdLabel ?: 'TBD') . '</span>';
            $html .= '</div>';
        }

        $html .= '</div>'; // .bk-players
    }

    if ($isCompleted && $sets !== null) {
        // счёт по сетам — плитка-табло в цвете стороны (домашняя красная, гостевая синяя); у проигравшего приглушена
        $d = (string) max(0, (int) $sets);
        $html .= '<span class="rw-flipgroup bk-flip" data-side="' . $side . '" aria-label="' . e($d) . '"><span class="rw-fd" data-d="' . $d . '"><span class="rw-fd-t"><i>' . $d . '</i></span><span class="rw-fd-b"><i>' . $d . '</i></span></span></span>';
    }

    $html .= '</div>'; // .bk-team
    return $html;
};

// Отрисовка одной карточки матча (шапка + обе команды/счёт) — вынесено из
// прежнего инлайн-@foreach, чтобы переиспользовать без дублирования разметки
// и для single_elim (одна секция), и для double_elim (upper/lower/GF секции).
$renderMatchCard = function (object $m) use ($courtLabel, $tz, $renderTeam, $isThirdPlaceMatch, $totalRounds, $slotFrom): string {
    $tbdFor = function (string $slot) use ($m, $slotFrom): ?string {
        $f = $slotFrom[$m->id][$slot] ?? null;
        return $f ? __('tournaments.' . ($f[0] === 'win' ? 'bk_winner_of' : 'bk_loser_of'), ['n' => $f[1]]) : null;
    };
    $when = $m->scheduled_at ? $m->scheduled_at->setTimezone($tz)->format('d.m H:i') : '';
    $isCompleted = $m->status === 'completed';
    // Bracket reset: GF2 cancelled = reset не потребовался (upper-представитель
    // выиграл GF1 напрямую) — это не "матч ожидает команды" (bk-match--empty),
    // а осознанно неигранный матч.
    $isCancelled = $m->status === 'cancelled';
    $homeWin = $m->winner_team_id && $m->winner_team_id === $m->team_home_id;
    $awayWin = $m->winner_team_id && $m->winner_team_id === $m->team_away_id;
    $cls = 'bk-match';
    if ($isCompleted)                                $cls .= ' bk-match--completed';
    elseif ($isCancelled)                            $cls .= ' bk-match--cancelled';
    elseif (!$m->team_home_id || !$m->team_away_id)  $cls .= ' bk-match--empty';
    if ($m->status === 'live')                       $cls .= ' bk-match--live';

    $html = '<div class="' . $cls . '" data-match-id="' . $m->id . '" data-next-match-id="' . e($m->next_match_id) . '" data-when="' . e($when) . '">';
    $html .= '<div class="bk-header">';
    $html .= '<span>Матч&thinsp;' . e($m->match_number) . '</span>';
    if ($m->court)         $html .= '<span class="bk-court">' . e($courtLabel($m->court)) . '</span>';
    if ($m->scheduled_at)  $html .= '<span class="bk-time">' . e($m->scheduled_at->setTimezone($tz)->format('H:i')) . '</span>';
    if ($isCompleted)      $html .= '<span class="bk-check">' . ico_svg('check', '1em') . '</span>';
    if ($m->status === 'live') $html .= '<span class="bk-live-pill"><i></i>LIVE</span>';
    $html .= '</div>';

    if ($isCancelled) {
        $html .= '<div class="bk-cancelled-note">Решающий матч не потребовался</div>';
    } else {
        $html .= $renderTeam($m->teamHome, $homeWin, $isCompleted ? $m->sets_home : null, $isCompleted, 'home', $tbdFor('home'));
        $html .= $renderTeam($m->teamAway, $awayWin, $isCompleted ? $m->sets_away : null, $isCompleted, 'away', $tbdFor('away'));

        // лента победителя: финал — «Чемпион», матч за 3-е место — «Бронза»
        if ($isCompleted && ($homeWin || $awayWin)) {
            $winTeam = $homeWin ? $m->teamHome : $m->teamAway;
            $winName = $winTeam?->name ?: '';
            if ($isThirdPlaceMatch($m)) {
                $html .= '<div class="bk-ribbon bk-ribbon--bronze">' . ico_svg('medal-bronze', '1.15em') . '<span>' . e(__('tournaments.bk_bronze')) . ' · ' . e($winName) . '</span></div>';
            } elseif ($m->next_match_id === null && (int) $m->round === (int) $totalRounds) {
                $html .= '<div class="bk-ribbon bk-ribbon--champ">' . ico_svg('trophy', '1.15em') . '<span>' . e(__('tournaments.bk_champion')) . ' · ' . e($winName) . '</span></div>';
            }
        }
    }

    $html .= '</div>'; // .bk-match
    return $html;
};

// Отрисовка одной колонки (раунд): вертикальное выравнивание по той же
// экспоненциальной формуле, что и раньше (padTop/gap от stepH) — для upper-
// секции double_elim она структурно верна (полная бинарная сетка, как
// single_elim), для lower-секции — приближение (реальная форма нижней сетки
// сложнее степени двойки), SVG-коннекторы всё равно рисуются по фактическим
// координатам DOM, а не по этой формуле, так что визуально это не критично.
$renderColumn = function ($roundMatches, string $label, int $relR) use ($stepH, $baseGap, $cardH, $renderMatchCard): string {
    $padTop = (int) round($stepH * (pow(2, $relR - 1) - 1));
    $gap    = $relR === 1 ? $baseGap : (int) round($stepH * pow(2, $relR) - $cardH);

    $html  = '<div class="bk-col">';
    $isFinalLabel = ($label === 'Финал');
    $html .= '<div class="bk-col-label' . ($isFinalLabel ? ' bk-col-label--final' : '') . '">' . ($isFinalLabel ? ico_svg('trophy', '1.1em') : '') . e($label) . '</div>';
    $html .= '<div class="bk-matches" style="gap:' . $gap . 'px;padding-top:' . $padTop . 'px">';
    foreach ($roundMatches as $m) {
        $html .= $renderMatchCard($m);
    }
    $html .= '</div></div>';
    return $html;
};

// Симметричная сетка (как футбольные): левая половина → ФИНАЛ по центру ← правая половина (зеркально). Включается для single_elim с ≥2 раундами.
$useSymmetric = !$isDoubleElim && $totalRounds >= 2;
$renderFinalColumn = function ($m) use ($stepH, $totalRounds, $renderMatchCard, $tz): string {
    // карточка финала — на той же высоте, что и полуфиналы (они сходятся к ней с двух сторон)
    $padTop = (int) round($stepH * (pow(2, max(0, $totalRounds - 2)) - 1));
    $when = $m->scheduled_at
        ? $m->scheduled_at->copy()->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('j F · H:i')
        : '';
    $diamonds = '';
    foreach ([6, 8, 10, 13, 10, 8, 6] as $i => $sz) {
        $diamonds .= '<i style="width:' . $sz . 'px;height:' . $sz . 'px"></i>';
    }
    $html  = '<div class="bk-col bk-col--final">';
    $html .= '<div class="bk-col-label"></div>';
    $html .= '<div class="bk-matches" style="padding-top:' . $padTop . 'px;position:relative">';
    $html .= '<div class="bk-hero">' . ico_svg('trophy', '1.6em')
        . '<div class="bk-hero-title">' . e(__('tournaments.bk_final_title')) . '</div>'
        . ($when ? '<div class="bk-hero-date">' . e($when) . '</div>' : '')
        . '<div class="bk-hero-dots">' . $diamonds . '</div></div>';
    $html .= $renderMatchCard($m);
    $html .= '</div></div>';
    return $html;
};

if ($isDoubleElim) {
    // Группировка по секциям (рецептура из генератора, report_recon_bracket.md
    // Блок 2 п.4): GF/GF-reset формально bracket_position='upper' — фильтруем
    // по court ПЕРВЫМ, иначе они попадут в $upperMatches.
    $upperMatches = $mainMatches->filter(fn($m) =>
        $m->bracket_position === 'upper'
        && $m->court !== 'Grand Final'
        && $m->court !== 'Grand Final Reset'
    );
    $lowerMatches = $mainMatches->filter(fn($m) => $m->bracket_position === 'lower');
    $gfMatch      = $mainMatches->first(fn($m) => $m->court === 'Grand Final');
    $gfReset      = $mainMatches->first(fn($m) => $m->court === 'Grand Final Reset');

    // round → коллекция матчей этого раунда, отсортированная по match_number;
    // ключи (реальные round) отсортированы по возрастанию, но НОМЕРА КОЛОНОК
    // считаются относительно секции (см. $renderColumn($ms, $label, $relR)
    // ниже — $relR = порядковый индекс среди раундов ЭТОЙ секции, не глобальный
    // round=4,5... как было бы при обычной группировке).
    $upperByRound = $upperMatches->groupBy('round')->sortKeys()
        ->map(fn($ms) => $ms->sortBy('match_number')->values());
    $lowerByRound = $lowerMatches->groupBy('round')->sortKeys()
        ->map(fn($ms) => $ms->sortBy('match_number')->values());
} else {
    $matchesByRound = [];
    for ($r = 1; $r <= $totalRounds; $r++) {
        $matchesByRound[$r] = $mainMatches
            ->filter(fn($m) => $m->round == $r)
            ->sortBy('match_number')
            ->values();
    }

}
@endphp

@if($totalRounds === 0)
    <div style="text-align:center;padding:36px 0;opacity:.4;font-size:.95rem">
        Сетка появится после жеребьёвки плей-офф
    </div>
@else
@if($isDoubleElim)

    {{-- ═══ Верхняя сетка ═══ --}}
    <div class="bk-section-title">Верхняя сетка</div>
    <div class="bk-wrap">
    <div class="bk-inner" id="{{ $bracketId }}-upper">
    <div class="bk-rounds">
        @foreach($upperByRound as $round => $roundMatches)
        @if(!$loop->first)<div class="bk-col-gap"></div>@endif
        {!! $renderColumn($roundMatches, 'Раунд ' . ($loop->index + 1), $loop->index + 1) !!}
        @endforeach
    </div>{{-- .bk-rounds --}}
    <svg id="{{ $bracketId }}-upper-svg" style="position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;overflow:visible"></svg>
    </div>{{-- .bk-inner --}}
    </div>{{-- .bk-wrap --}}

    {{-- ═══ Нижняя сетка ═══ --}}
    <div class="bk-section-title">Нижняя сетка</div>
    <div class="bk-wrap">
    <div class="bk-inner" id="{{ $bracketId }}-lower">
    <div class="bk-rounds">
        @foreach($lowerByRound as $round => $roundMatches)
        @if(!$loop->first)<div class="bk-col-gap"></div>@endif
        {!! $renderColumn($roundMatches, 'Раунд ' . ($loop->index + 1), $loop->index + 1) !!}
        @endforeach
    </div>{{-- .bk-rounds --}}
    <svg id="{{ $bracketId }}-lower-svg" style="position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;overflow:visible"></svg>
    </div>{{-- .bk-inner --}}
    </div>{{-- .bk-wrap --}}

    {{-- ═══ Гранд-финал (+ Переигровка, если существует) ═══ --}}
    @if($gfMatch)
    <div class="bk-section-title">Гранд-финал</div>
    <div class="bk-wrap">
    <div class="bk-inner">
    <div class="bk-rounds">
        <div class="bk-col">
            <div class="bk-col-label">Гранд-финал</div>
            <div class="bk-matches" style="gap:{{ $baseGap }}px">
                {!! $renderMatchCard($gfMatch) !!}
                @if($gfReset)
                {!! $renderMatchCard($gfReset) !!}
                @endif
            </div>
        </div>
    </div>{{-- .bk-rounds --}}
    </div>{{-- .bk-inner --}}
    </div>{{-- .bk-wrap --}}
    @endif{{-- $gfMatch --}}

@else{{-- !$isDoubleElim --}}
<div class="bk-wrap">
<div class="bk-inner" id="{{ $bracketId }}">

@if($useSymmetric)
<div class="bk-rounds bk-rounds--sym">
@for($r = 1; $r < $totalRounds; $r++)
    @php
        $__all = $matchesByRound[$r] ?? collect();
        $__left = $__all->slice(0, (int) ceil($__all->count() / 2))->values();
        $__rep = $__left->first();
        $__label = $__rep ? ($stage->roundLabelFor($__rep) ?? 'Раунд ' . $r) : 'Раунд ' . $r;
    @endphp
    @if($r > 1)<div class="bk-col-gap"></div>@endif
    {!! $renderColumn($__left, $__label, $r) !!}
@endfor
<div class="bk-col-gap"></div>
{!! $renderFinalColumn(($matchesByRound[$totalRounds] ?? collect())->first()) !!}
@for($r = $totalRounds - 1; $r >= 1; $r--)
    @php
        $__all = $matchesByRound[$r] ?? collect();
        $__right = $__all->slice((int) ceil($__all->count() / 2))->values();
        $__rep = $__right->first();
        $__label = $__rep ? ($stage->roundLabelFor($__rep) ?? 'Раунд ' . $r) : 'Раунд ' . $r;
    @endphp
    <div class="bk-col-gap"></div>
    {!! $renderColumn($__right, $__label, $r) !!}
@endfor
</div>{{-- .bk-rounds--sym --}}
@else
<div class="bk-rounds">
@for($r = 1; $r <= $totalRounds; $r++)
    @php
        $roundMatches = $matchesByRound[$r] ?? collect();
        // Наименование раунда — единая точка правды TournamentStage::roundLabelFor()
        // (GF/GF-reset по court, Финал/Полуфинал/Четвертьфинал по номеру раунда от
        // конца сетки), не дублируется здесь inline-closure'ом.
        $repMatch = $roundMatches->first();
        $label = $repMatch ? ($stage->roundLabelFor($repMatch) ?? 'Раунд ' . $r) : 'Раунд ' . $r;
    @endphp

    @if($r > 1)<div class="bk-col-gap"></div>@endif

    {!! $renderColumn($roundMatches, $label, $r) !!}
@endfor
</div>{{-- .bk-rounds --}}
@endif

{{-- SVG коннекторы (bezier, через JS) --}}
<svg id="{{ $bracketId }}-svg"
     style="position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;overflow:visible">
</svg>

</div>{{-- .bk-inner --}}
</div>{{-- .bk-wrap --}}
@endif

{{-- Матч за 3-е место --}}
@if($thirdPlace)
@php
    $m3 = $thirdPlace;
    $c3 = $m3->status === 'completed';
    $h3 = $m3->winner_team_id && $m3->winner_team_id === $m3->team_home_id;
    $a3 = $m3->winner_team_id && $m3->winner_team_id === $m3->team_away_id;
    $tbd3 = fn (string $slot) => isset($slotFrom[$m3->id][$slot])
        ? __('tournaments.' . ($slotFrom[$m3->id][$slot][0] === 'win' ? 'bk_winner_of' : 'bk_loser_of'), ['n' => $slotFrom[$m3->id][$slot][1]])
        : null;
    $cls3 = 'bk-match bk-match--third' . ($c3 ? ' bk-match--completed' : (!$m3->team_home_id || !$m3->team_away_id ? ' bk-match--empty' : ''));
@endphp
<div class="bk-third-section">
    <div class="bk-third-section-label"><x-ico name="medal-bronze" size="1.1em" /> Матч за 3-е место</div>
    <div class="{{ $cls3 }}" style="max-width:264px">
        <div class="bk-header">
            <span>Матч&thinsp;{{ $m3->match_number }}</span>
            @if($m3->court)<span class="bk-court">{{ $courtLabel($m3->court) }}</span>@endif
            @if($m3->scheduled_at)<span class="bk-time">{{ $m3->scheduled_at->setTimezone($tz)->format('H:i') }}</span>@endif
            @if($c3)<span class="bk-check"><x-ico name="check" size="1em" /></span>@endif
        </div>
        {!! $renderTeam($m3->teamHome, $h3, $c3 ? $m3->sets_home : null, $c3, 'home', $tbd3('home')) !!}
        {!! $renderTeam($m3->teamAway, $a3, $c3 ? $m3->sets_away : null, $c3, 'away', $tbd3('away')) !!}
        @if($c3 && ($h3 || $a3))
        <div class="bk-ribbon bk-ribbon--bronze"><x-ico name="medal-bronze" size="1.15em" /><span>{{ __('tournaments.bk_bronze') }} · {{ ($h3 ? $m3->teamHome : $m3->teamAway)?->name }}</span></div>
        @endif
    </div>
</div>
@endif

{{-- double_elim: дискретного матча за 3-е нет (канонически — бронза это
     проигравший LB-финала, уже определяется TournamentStatsService::
     calculateFinalClassification(), см. report/recon_third_place_de.md).
     Гейт строго по типу стадии — для остальных типов (single_elim/crossover
     уже отрисован выше через $thirdPlace) эта ветка не вызывается вовсе,
     дублирования витрины нет. $event виден из скоупа родителя show.blade.php
     (тот же паттерн вызова, что show.blade.php:368, вкладка «Результаты»). --}}
@if($stage->type === \App\Models\TournamentStage::TYPE_DOUBLE_ELIM)
@php
    $deClassification = app(\App\Services\TournamentStatsService::class)
        ->calculateFinalClassification($event, $selectedOccurrence?->id ?? null);
    $deThirdPlaceEntry = collect($deClassification)->firstWhere('place', 3);
@endphp
@if($deThirdPlaceEntry)
<div class="bk-third-section">
    <div class="bk-third-section-label"><x-ico name="medal-bronze" size="1.1em" /> 3-е место</div>
    <div class="bk-third-place-name">{{ $deThirdPlaceEntry['team_name'] }}</div>
</div>
@endif
@endif

{{-- JS: SVG bezier-линии по реальным DOM-координатам. double_elim рисует
     ДВЕ независимые секции (upper/lower) каждая в своём bk-inner/svg — линии
     upper-финал→ГФ и lower-финал→ГФ НЕ рисуются (ГФ физически в другом
     bk-wrap/скролл-контейнере, координаты между секциями не связаны) —
     ограничение этого захода, см. отчёт. --}}
<script>
(function(){
    function drawOne(containerId, svgId){
        var inner = document.getElementById(containerId);
        if(!inner) return;
        var svg = document.getElementById(svgId);
        if(!svg) return;
        svg.innerHTML = '';
        inner.querySelectorAll('[data-match-id]').forEach(function(e){ e.__whenDrawn = false; });

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
            // правая половина симметричной сетки идёт справа налево: линия из левого края карточки в правый край следующей
            var rtl = (b.left + b.width/2) < (a.left + a.width/2);
            var x1 = (rtl ? a.left  : a.right) - iRect.left,  y1 = a.top + a.height/2 - iRect.top;
            var x2 = (rtl ? b.right : b.left)  - iRect.left,  y2 = b.top + b.height/2 - iRect.top;
            var cx = (x2 - x1) * 0.46;

            var done = el.classList.contains('bk-match--completed');
            // ортогональные линии с закруглёнными углами: от карточки вправо → вертикаль на середине зазора → в следующую карточку
            var mx = x1 + (x2 - x1) / 2, r = Math.min(10, Math.abs(y2 - y1) / 2, Math.abs(x2 - x1) / 4);
            var dir = y2 >= y1 ? 1 : -1, sx = x2 >= x1 ? 1 : -1;
            var d = (Math.abs(y2 - y1) < 2)
                ? 'M ' + x1 + ' ' + y1 + ' L ' + x2 + ' ' + y2
                : 'M ' + x1 + ' ' + y1 + ' L ' + (mx - sx * r) + ' ' + y1 +
                  ' Q ' + mx + ' ' + y1 + ' ' + mx + ' ' + (y1 + dir * r) +
                  ' L ' + mx + ' ' + (y2 - dir * r) +
                  ' Q ' + mx + ' ' + y2 + ' ' + (mx + sx * r) + ' ' + y2 +
                  ' L ' + x2 + ' ' + y2;
            var p = document.createElementNS('http://www.w3.org/2000/svg','path');
            p.setAttribute('d', d);
            p.setAttribute('fill','none');
            p.setAttribute('stroke', done ? (dark ? 'rgba(52,211,153,.7)' : 'rgba(16,185,129,.6)') : color);
            p.setAttribute('stroke-width', done ? '3' : '2');
            p.setAttribute('stroke-linecap','round');
            p.setAttribute('stroke-linejoin','round');
            svg.appendChild(p);
            // кружок-«узел» у выхода из карточки
            var dot = document.createElementNS('http://www.w3.org/2000/svg','circle');
            dot.setAttribute('cx', x1); dot.setAttribute('cy', y1); dot.setAttribute('r', done ? '4' : '3');
            dot.setAttribute('fill', done ? (dark ? 'rgba(52,211,153,.9)' : '#10b981') : (dark ? 'rgba(100,116,139,.7)' : 'rgba(0,0,0,.2)'));
            svg.appendChild(dot);
            // дата/время следующего матча — над входом в карточку (как дата на стыке линий в футбольных сетках), один раз на матч
            var nextEl = map[nid], when = nextEl.getAttribute('data-when');
            if (when && !nextEl.__whenDrawn) {
                nextEl.__whenDrawn = true;
                var ty = y2 - 16;
                var t = document.createElementNS('http://www.w3.org/2000/svg','text');
                t.setAttribute('x', mx); t.setAttribute('y', ty + 4);
                t.setAttribute('text-anchor','middle');
                t.setAttribute('font-size','10.5'); t.setAttribute('font-weight','700');
                t.setAttribute('fill', dark ? '#cbd5e1' : '#475569');
                t.textContent = when;
                var bg = document.createElementNS('http://www.w3.org/2000/svg','rect');
                var wpx = when.length * 6.2 + 12;
                bg.setAttribute('x', mx - wpx / 2); bg.setAttribute('y', ty - 9);
                bg.setAttribute('width', wpx); bg.setAttribute('height', 18); bg.setAttribute('rx', 9);
                bg.setAttribute('fill', dark ? '#1e293b' : '#ffffff');
                bg.setAttribute('stroke', dark ? 'rgba(148,163,184,.35)' : 'rgba(15,23,42,.14)');
                svg.appendChild(bg);
                svg.appendChild(t);
            }
        });
    }
    function draw(){
        @if($useSymmetric)
        // симметричная сетка шире экрана — открываем по центру (на ФИНАЛЕ)
        var sym = document.getElementById('{{ $bracketId }}');
        if (sym && sym.parentElement) { sym.parentElement.scrollLeft = Math.max(0, (sym.parentElement.scrollWidth - sym.parentElement.clientWidth) / 2); }
        @endif
        @if($isDoubleElim)
        drawOne('{{ $bracketId }}-upper', '{{ $bracketId }}-upper-svg');
        drawOne('{{ $bracketId }}-lower', '{{ $bracketId }}-lower-svg');
        @else
        drawOne('{{ $bracketId }}', '{{ $bracketId }}-svg');
        @endif
    }
    if(document.readyState==='loading'){
        document.addEventListener('DOMContentLoaded', draw);
    } else {
        setTimeout(draw, 0);
    }
})();
</script>
@endif
