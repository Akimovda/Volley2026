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
