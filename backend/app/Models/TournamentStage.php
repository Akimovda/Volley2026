<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TournamentStage extends Model
{
    protected $fillable = [
        'event_id',
        'occurrence_id',
        'type',
        'name',
        'division_tier',
        'sort_order',
        'config',
        'status',
    ];

    protected $casts = [
        'config' => 'array',
    ];

    /* ---------- constants: stage types ---------- */

    public const TYPE_ROUND_ROBIN    = 'round_robin';
    public const TYPE_GROUPS_PLAYOFF = 'groups_playoff';
    public const TYPE_SINGLE_ELIM    = 'single_elim';
    public const TYPE_DOUBLE_ELIM    = 'double_elim';
    public const TYPE_SWISS          = 'swiss';
    public const TYPE_KING_OF_COURT  = 'king_of_court';
    public const TYPE_THAI           = 'thai';
    public const TYPE_KING_BEACH     = 'king_beach';

    public const STATUS_PENDING     = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED   = 'completed';

    // Режимы жеребьёвки
    public const DRAW_SEEDED        = 'seeded';
    public const DRAW_STAGE_ADVANCE = 'stage_advance';
    public const DRAW_LEAGUE_CARRY  = 'league_carry';
    public const DRAW_MANUAL        = 'manual';

    public const DRAW_MODES = [
        self::DRAW_SEEDED,
        self::DRAW_STAGE_ADVANCE,
        self::DRAW_LEAGUE_CARRY,
        self::DRAW_MANUAL,
    ];

    public const SEED_BY_ELO            = 'elo';
    public const SEED_BY_MATCH_WIN_RATE = 'match_win_rate';
    public const SEED_BY_RATING_POINTS  = 'rating_points';

    public const SEED_BY_OPTIONS = [
        self::SEED_BY_ELO,
        self::SEED_BY_MATCH_WIN_RATE,
        self::SEED_BY_RATING_POINTS,
    ];

    public const TYPES = [
        self::TYPE_ROUND_ROBIN,
        self::TYPE_GROUPS_PLAYOFF,
        self::TYPE_SINGLE_ELIM,
        self::TYPE_DOUBLE_ELIM,
        self::TYPE_SWISS,
        self::TYPE_KING_OF_COURT,
        self::TYPE_THAI,
        self::TYPE_KING_BEACH,
    ];

    /* ---------- type traits: единая точка правды вместо разбросанных списков типов ----------
     * См. report_stage_type_branching_audit.md — до этого рефакторинга >20 мест в
     * TournamentController.php/сервисах/setup.blade.php независимо перечисляли типы
     * (['round_robin','groups_playoff'] и т.п.), и один из списков (auto-pairing
     * companion-стадии плей-офф) отстал от остальных — round_robin не получал
     * авто-созданную стадию плей-офф, турнир закрывался сразу после группового
     * этапа (диагностика report_diagnosis_round_robin_finals_557.md, event 557).
     *
     * Трейты:
     *  - group_stage          — групповой этап (раунд-робин внутри групп)
     *  - can_have_followup    — при создании стадии этого типа ДОЛЖНА сразу создаваться
     *                           парная стадия-продолжение (иначе checkStageCompletion()
     *                           закрывает турнир сразу после этой стадии)
     *  - batch_matches        — все матчи создаются сразу жеребьёвкой, не по ходу турнира
     *  - bracket_stage        — сетка на выбывание, даёт финальную классификацию 1-4 места
     *  - incremental_matches  — матчи создаются по ходу (раунд/матч за раз), не все сразу;
     *                           checkStageCompletion() для них ненадёжен (не трогается в
     *                           этом рефакторинге — отдельный Проход 2)
     *  - player_based_matches — матчи хранят участников в meta (по игрокам), без
     *                           team_home_id/team_away_id
     *
     * thai помечен ТОЛЬКО group_stage, БЕЗ can_have_followup: TournamentThaiService
     * (initialize()/createDivisions()) не вызывается ни в одном контроллере — стадия
     * этого типа сегодня нефункциональна (createStage()/draw() не генерируют для неё
     * ни группы, ни матчи). Включение can_have_followup для thai до реализации вызова
     * сервиса создало бы регресс "наоборот" — auto-pairing начал бы создавать
     * companion-стадию плей-офф для формата, который сам ничего не генерирует.
     */
    private const TYPE_TRAITS = [
        self::TYPE_ROUND_ROBIN    => ['group_stage', 'can_have_followup', 'batch_matches'],
        self::TYPE_GROUPS_PLAYOFF => ['group_stage', 'can_have_followup', 'batch_matches'],
        self::TYPE_THAI           => ['group_stage'],
        self::TYPE_SINGLE_ELIM    => ['bracket_stage', 'batch_matches'],
        self::TYPE_DOUBLE_ELIM    => ['bracket_stage', 'batch_matches'],
        self::TYPE_SWISS          => ['incremental_matches'],
        self::TYPE_KING_OF_COURT  => ['incremental_matches'],
        self::TYPE_KING_BEACH     => ['incremental_matches', 'player_based_matches'],
    ];

    private function hasTrait(string $trait): bool
    {
        return in_array($trait, self::TYPE_TRAITS[$this->type] ?? [], true);
    }

    /**
     * Групповой этап (раунд-робин внутри групп) — round_robin, groups_playoff, thai.
     * ВНИМАНИЕ: включает thai (у него пока нет подключённого сервиса) — не использовать
     * этот предикат там, где thai должен оставаться неактивным (там нужна
     * canHaveFollowupStage(), которая thai не включает).
     */
    public function isGroupStage(): bool
    {
        return $this->hasTrait('group_stage');
    }

    /**
     * Сетка на выбывание (single_elim, double_elim) — даёт финальную классификацию мест
     * турнира (замена разрозненных whereIn('type', ['single_elim','double_elim'])).
     */
    public function isBracketStage(): bool
    {
        return $this->hasTrait('bracket_stage');
    }

    /**
     * Человекочитаемое название раунда сетки на выбывание (Финал/Полуфинал/
     * Четвертьфинал) — вынесено из ранее приватной inline-closure
     * `$roundLabel` в resources/views/tournaments/public/_bracket.blade.php,
     * чтобы не дублировать ту же логику на /score (score.blade.php,
     * score_rally.blade.php). null — если для этого матча человекочитаемое
     * название неприменимо, вызывающий код должен фолбэкнуться на обычное
     * "Раунд N" / "Тур N".
     *
     * double_elim НЕ покрыт (кроме Гранд-финала по $match->court) — раунды
     * double_elim идут по отдельным upper/lower секциям (см. _bracket.blade.php),
     * единой оси "N раундов до финала" для них нет.
     */
    public function roundLabelFor(TournamentMatch $match): ?string
    {
        if ($match->court === 'Grand Final Reset') {
            return 'Решающий матч';
        }
        if ($match->court === 'Grand Final') {
            return 'Финал';
        }
        if ($this->type !== self::TYPE_SINGLE_ELIM) {
            return null;
        }

        $totalRounds = $this->matches->max('round') ?? $match->round;

        return match ($totalRounds - $match->round) {
            0 => 'Финал',
            1 => 'Полуфинал',
            2 => 'Четвертьфинал',
            default => null,
        };
    }

    /**
     * При создании стадии этого типа нужно сразу создать парную стадию-продолжение —
     * иначе checkStageCompletion() закроет турнир сразу после этой стадии
     * (round_robin, groups_playoff; НЕ thai — см. докстринг TYPE_TRAITS выше).
     */
    public function canHaveFollowupStage(): bool
    {
        return $this->hasTrait('can_have_followup');
    }

    /**
     * Матчи создаются по ходу турнира (раунд/матч за раз), не все сразу жеребьёвкой —
     * swiss, king_of_court, king_beach. checkStageCompletion() для них ненадёжен
     * (см. report_stage_type_branching_audit.md §3) — предикат не меняет его поведение
     * в этом рефакторинге, только называет факт, который раньше был неявным.
     */
    public function hasIncrementalMatchGeneration(): bool
    {
        return $this->hasTrait('incremental_matches');
    }

    /**
     * Матчи хранят участников в meta (по игрокам), без team_home_id/team_away_id —
     * сегодня только king_beach.
     */
    public function isPlayerBasedMatches(): bool
    {
        return $this->hasTrait('player_based_matches');
    }

    /**
     * Доступные переходы к стадии-продолжению для ТЕКУЩЕГО состояния этой
     * стадии — чистая функция, без обращений к БД (не проверяет реально
     * существующие companion-стадии/матчи, только структурные факты
     * тип+конфиг). Справочный источник для будущего диспетчера переходов
     * (см. report/kusok2_transitions_dump_2026-08-18.md) — сегодня этот
     * метод никем не вызывается, ничего в существующем поведении не меняет.
     *
     * Правила:
     * - Инкрементальные форматы (swiss/king_of_court/king_beach) — у них
     *   своя логика "следующий раунд/матч" (generateNextRound()/
     *   generateNextMatch()/advanceToNextRound()), не через finals_mode —
     *   переходов в этом смысле нет.
     * - Bracket-стадии (single_elim/double_elim) — сами являются финалом,
     *   продолжения не бывает.
     * - Групповые (round_robin/groups_playoff/thai, трейт group_stage) —
     *   набор зависит от числа групп: 1 группа → финал не нужен (мини-турнир,
     *   см. коммит f3ac0463 "одногрупповой турнир без финала"); 2 группы →
     *   доступны все 3 режима (placement требует РОВНО 2 группы —
     *   TournamentBracketService::generateGroupCrossover() кидает
     *   InvalidArgumentException при другом числе); 3+ групп — без placement.
     *
     * @return array<int, array{type: string, label: string}>
     */
    public function getAvailableTransitions(): array
    {
        if ($this->hasIncrementalMatchGeneration()) {
            return [];
        }

        if ($this->isBracketStage()) {
            return [];
        }

        if ($this->canHaveFollowupStage()) {
            $groupsCount = (int) $this->cfg('groups_count', 0);

            if ($groupsCount === 1) {
                return [
                    ['type' => 'none', 'label' => 'Финал не нужен — единственная группа сама определяет итоговые места'],
                ];
            }

            if ($groupsCount === 2) {
                return [
                    ['type' => 'bracket', 'label' => 'Плей-офф с финалами (полуфиналы кросс-посевом → финал + матч за 3-4)'],
                    ['type' => 'placement', 'label' => 'Прямые матчи за места (1-е места за 1-2, 2-е за 3-4, без полуфиналов)'],
                    ['type' => 'divisions', 'label' => 'Финальные группы по уровням'],
                ];
            }

            if ($groupsCount >= 3) {
                return [
                    ['type' => 'bracket', 'label' => 'Плей-офф с финалами (полуфиналы кросс-посевом → финал + матч за 3-4)'],
                    ['type' => 'divisions', 'label' => 'Финальные группы по уровням'],
                ];
            }

            return [];
        }

        return [];
    }

    /**
     * Значения типов с трейтом group_stage — единый источник для JS-списка в
     * setup.blade.php (форма "Добавить стадию", поля групп), чтобы не держать
     * независимую копию списка в JS.
     *
     * @return array<int, string>
     */
    public static function groupTypeValues(): array
    {
        return array_values(array_filter(
            self::TYPES,
            fn (string $type) => in_array('group_stage', self::TYPE_TRAITS[$type] ?? [], true)
        ));
    }

    /**
     * Значения типов с трейтом can_have_followup — единый источник для JS-списка
     * в setup.blade.php (блок "Режим финалов" в форме "Добавить стадию"), чтобы
     * не дублировать список типов отдельной хардкод-проверкой (t==='groups_playoff')
     * в JS, расходящейся с canHaveFollowupStage() на бэкенде (round_robin тоже
     * имеет этот трейт — см. докстринг canHaveFollowupStage() выше).
     *
     * @return array<int, string>
     */
    public static function followupTypeValues(): array
    {
        return array_values(array_filter(
            self::TYPES,
            fn (string $type) => in_array('can_have_followup', self::TYPE_TRAITS[$type] ?? [], true)
        ));
    }

    /**
     * Названия финальных групп по уровням (Hard/Medium/Lite) для заданного числа
     * исходных групп — единый источник для TournamentController::formDivisions(),
     * TournamentKingBeachService::formDivisions() и обоих blade-блоков (пульт +
     * мастер, выбор finals_mode=divisions). При изменении формулы — менять только тут.
     *
     * Правило (баг "1,4,1" вместо "2,2,2" на N=6, исправлено 2026-08-18): раньше
     * default-ветка всегда давала ровно 1 Hard + (N-2) Medium + 1 Lite — при
     * росте N почти все дивизионы становились Medium. Теперь уровни делятся
     * пропорционально: base=⌊N/3⌋, Hard=base+(есть остаток 1 или 2), Medium=base+
     * (остаток 2), Lite=base — т.е. остаток 1 уходит в Hard, остаток 2 — в Hard
     * И Medium. N=2 — спецслучай (Hard, Lite, без Medium). N<2 не используется
     * реальным флоу (formDivisionsCore() требует >=2 групп), но сохранено прежнее
     * поведение — не трогать, чтобы не менять window.__divisionNamesByGroupsCount
     * для несуществующего N=1 в мастере.
     *
     * @return array<int, string>
     */
    public static function divisionNamesFor(int $groupsCount): array
    {
        if ($groupsCount < 2) {
            return array_merge(
                ['Hard'],
                array_map(fn ($i) => 'Medium-' . $i, range(1, max(1, $groupsCount - 2))),
                ['Lite']
            );
        }

        if ($groupsCount === 2) {
            return ['Hard', 'Lite'];
        }

        $base = intdiv($groupsCount, 3);
        $rem = $groupsCount % 3;
        $levelCounts = [
            'Hard' => $base + ($rem >= 1 ? 1 : 0),
            'Medium' => $base + ($rem >= 2 ? 1 : 0),
            'Lite' => $base,
        ];

        $names = [];
        foreach ($levelCounts as $level => $count) {
            for ($i = 0; $i < $count; $i++) {
                $names[] = $i === 0 ? $level : $level . '-' . $i;
            }
        }

        return $names;
    }

    /* ---------- relations ---------- */

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(TournamentGroup::class, 'stage_id')->orderBy('sort_order');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(TournamentMatch::class, 'stage_id');
    }

    public function standings(): HasMany
    {
        return $this->hasMany(TournamentStanding::class, 'stage_id');
    }

    /* ---------- helpers ---------- */

    /**
     * Алиас для configValue (используется в контроллере).
     */
    public function cfg(string $key, mixed $default = null): mixed
    {
        return $this->configValue($key, $default);
    }

    public function configValue(string $key, mixed $default = null): mixed
    {
        return data_get($this->config, $key, $default);
    }

    public function matchFormat(): string
    {
        return $this->configValue('match_format', 'bo3');
    }

    public function setPoints(): int
    {
        return (int) $this->configValue('set_points', 25);
    }

    public function decidingSetPoints(): int
    {
        return (int) $this->configValue('deciding_set_points', 15);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Финал за места напрямую (crossover, см. TournamentBracketService::generateGroupCrossover) —
     * в отличие от обычной сетки (bracket), это два равноправных матча первого
     * раунда без next_match_id-связей: "за 1-2 место" и "за 3-4 место".
     *
     * Источник правды — finals_mode в config. Для стадий, созданных ДО того,
     * как finals_mode стал частью конфига плей-офф стадии (инцидент event 402,
     * см. report_402_finals_bug.md) — мост по факту: ровно 2 матча первого
     * раунда с русским названием корта "Матч за N-M место".
     */
    public function isPlacementFinal(): bool
    {
        if ($this->type !== self::TYPE_SINGLE_ELIM) {
            return false;
        }

        $mode = $this->configValue('finals_mode');
        if ($mode === 'placement') {
            return true;
        }
        if ($mode === 'bracket') {
            return false;
        }

        $roundOneMatches = $this->matches()->where('round', 1)->get(['court']);
        if ($roundOneMatches->count() !== 2) {
            return false;
        }

        return $roundOneMatches->every(
            fn ($m) => $m->court && preg_match('/за\s+\d+-\d+\s+место/u', $m->court) === 1
        );
    }

    /**
     * Матч placement-финала, начинающий диапазон мест с $placeFrom (1 = "за 1-2
     * место", 3 = "за 3-4 место" и т.д.) — различаем по тексту корта, а не по
     * round/match_number (у обоих матчей round=1, порядок match_number не
     * гарантирует, какой из них "за 1-2", а какой "за 3-4").
     */
    public function placementMatch(int $placeFrom): ?TournamentMatch
    {
        return $this->matches()
            ->where('round', 1)
            ->get()
            ->first(function (TournamentMatch $m) use ($placeFrom) {
                if (!$m->court || !preg_match('/за\s+(\d+)-\d+\s+место/u', $m->court, $groups)) {
                    return false;
                }
                return (int) $groups[1] === $placeFrom;
            });
    }

    public function drawMode(): string
    {
        return $this->configValue('draw_mode', self::DRAW_SEEDED);
    }

    public function drawSeedBy(): string
    {
        return $this->configValue('draw_seed_by', self::SEED_BY_ELO);
    }

    public function drawModeLabel(): string
    {
        return match ($this->drawMode()) {
            self::DRAW_SEEDED        => 'Посев по рейтингу',
            self::DRAW_STAGE_ADVANCE => 'По результатам предыдущей стадии',
            self::DRAW_LEAGUE_CARRY  => 'По составу лиги',
            self::DRAW_MANUAL        => 'Ручное распределение',
            default                  => 'Посев по рейтингу',
        };
    }
}
