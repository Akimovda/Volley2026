<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventGameSetting;
use App\Models\EventRoleSlot;
use App\Models\EventTournamentSetting;
use Illuminate\Support\Facades\Schema;
use App\Services\GameCalculator;
use App\Services\EventRoleSlotService;


class EventGameSettingsService
{

    private EventRoleSlotService $roleSlotService;

    public function __construct(EventRoleSlotService $roleSlotService)
    {
        $this->roleSlotService = $roleSlotService;
    }
    public function isTournament(string $format): bool
    {
        return in_array($format, ['tournament', 'tournament_classic', 'tournament_beach'], true);
    }

    /*
    |--------------------------------------------------------------------------
    | NORMALIZE GAME DEFAULTS
    |--------------------------------------------------------------------------
    */
    public function normalizeTournamentDefaults(array $data, string $direction, string $format): array
    {
        $errors = [];
    
        $isTournamentClassic = ($direction === 'classic' && in_array($format, ['tournament', 'tournament_classic'], true));
        $isTournamentBeach = ($direction === 'beach' && in_array($format, ['tournament', 'tournament_beach'], true));
        $needTournamentSettings = $isTournamentClassic || $isTournamentBeach;
    
        if (!$needTournamentSettings) {
            return [
                'data' => $data,
                'errors' => [],
                'needTournamentSettings' => false,
                'registrationMode' => null,
            ];
        }
    
        $isIndividual = !empty($data['tournament_individual_reg']);
        $registrationMode = $isIndividual
            ? 'tournament_individual'
            : ($isTournamentBeach ? 'team_beach' : 'team_classic');
        $data['tournament_registration_mode'] = $registrationMode;
    
        $scheme = (string)($data['tournament_game_scheme'] ?? '');
        if ($scheme === '') {
            $scheme = $isTournamentBeach ? '2x2' : '5x1';
            $data['tournament_game_scheme'] = $scheme;
        }
    
        $data['tournament_teams_count'] = isset($data['tournament_teams_count']) && $data['tournament_teams_count'] !== ''
            ? (int)$data['tournament_teams_count']
            : 4;
    
        if ($data['tournament_teams_count'] < 3 || $data['tournament_teams_count'] > 100) {
            $errors['tournament_teams_count'] = [
                'Количество команд должно быть от 3 до 100.'
            ];
        }
    
        $defaults = $this->getTournamentDefaults($direction, $scheme);
    
        if (!$defaults) {
            $errors['tournament_game_scheme'] = ['Неверная схема турнира.'];
    
            return [
                'data' => $data,
                'errors' => $errors,
                'needTournamentSettings' => true,
                'registrationMode' => $registrationMode,
            ];
        }
    
        $reserveMax = isset($data['tournament_reserve_players_max']) && $data['tournament_reserve_players_max'] !== ''
            ? (int)$data['tournament_reserve_players_max']
            : (int)$defaults['reserve_max'];
    
        if ($reserveMax < 0) {
            $errors['tournament_reserve_players_max'] = [
                'Максимум запасных не может быть отрицательным.'
            ];
            $reserveMax = 0;
        }
    
        $data['tournament_team_size_min'] = (int)$defaults['min'];
        $data['tournament_reserve_players_max'] = $reserveMax;
        $data['tournament_total_players_max'] = (int)$defaults['min'] + $reserveMax;
        $data['tournament_require_libero'] = (bool)$defaults['require_libero'];
    
        $data['tournament_max_rating_sum'] = isset($data['tournament_max_rating_sum']) && $data['tournament_max_rating_sum'] !== ''
            ? (int)$data['tournament_max_rating_sum']
            : null;
    
        return [
            'data' => $data,
            'errors' => $errors,
            'needTournamentSettings' => true,
            'registrationMode' => $registrationMode,
        ];
    }
    
    /**
     * Получить дефолтные лимиты для турнира
     */
    private function getTournamentDefaults(string $direction, string $scheme): ?array
    {
        if ($direction === 'classic') {
            return match ($scheme) {
                '4x4' => ['min' => 4, 'reserve_max' => 3, 'total_max' => 7, 'require_libero' => false],
                '4x2' => ['min' => 6, 'reserve_max' => 4, 'total_max' => 10, 'require_libero' => false],
                '5x1' => ['min' => 6, 'reserve_max' => 4, 'total_max' => 10, 'require_libero' => false],
                '5x1_libero' => ['min' => 7, 'reserve_max' => 4, 'total_max' => 11, 'require_libero' => true],
                default => null,
            };
        }
    
        if ($direction === 'beach') {
            return match ($scheme) {
                '2x2' => ['min' => 2, 'reserve_max' => 0, 'total_max' => 2, 'require_libero' => false],
                '3x3' => ['min' => 3, 'reserve_max' => 2, 'total_max' => 5, 'require_libero' => false],
                '4x4' => ['min' => 4, 'reserve_max' => 3, 'total_max' => 7, 'require_libero' => false],
                default => null,
            };
        }
    
        return null;
    }
    
   public function createOrUpdateTournamentSettings(
        Event $event,
        array $data,
        string $direction,
        string $format,
        ?string $registrationMode = null
    ): void {
        $isTournamentClassic = ($direction === 'classic' && in_array($format, ['tournament', 'tournament_classic'], true));
        $isTournamentBeach = ($direction === 'beach' && in_array($format, ['tournament', 'tournament_beach'], true));
    
        if (!$isTournamentClassic && !$isTournamentBeach) {
            return;
        }
    
        // Используем переданный registrationMode или определяем сами
        $registrationMode = $registrationMode ?? ($isTournamentBeach ? 'team_beach' : 'team_classic');
    
        EventTournamentSetting::updateOrCreate(
            ['event_id' => $event->id],
            [
                'registration_mode' => $registrationMode,
                'game_scheme' => $data['tournament_game_scheme'] ?? null,
        
                'team_size_min' => $data['tournament_team_size_min'] ?? null,
                'team_size_max' => $data['tournament_team_size_min'] ?? null,
                'reserve_players_max' => $data['tournament_reserve_players_max'] ?? null,
                'total_players_max' => $data['tournament_total_players_max'] ?? null,
                'teams_count' => $data['tournament_teams_count'] ?? 4,
        
                'require_libero' => (bool)($data['tournament_require_libero'] ?? false),
                'max_rating_sum' => $data['tournament_max_rating_sum'] ?? null,
                'allow_reserves' => ((int)($data['tournament_reserve_players_max'] ?? 0) > 0),
                'captain_confirms_members' => (bool)($data['tournament_captain_confirms_members'] ?? true),
                'auto_submit_when_ready' => (bool)($data['tournament_auto_submit_when_ready'] ?? false),
                'allow_incomplete_application' => (bool)($data['tournament_allow_incomplete_application'] ?? false),
                'seeding_mode' => $data['tournament_seeding_mode'] ?? null,
        
                'meta' => [
                    'source' => 'EventGameSettingsService',
                    'team_size_min' => $data['tournament_team_size_min'] ?? null,
                    'reserve_players_max' => $data['tournament_reserve_players_max'] ?? null,
                    'total_players_max' => $data['tournament_total_players_max'] ?? null,
                    'teams_count' => $data['tournament_teams_count'] ?? 4,
                ],
            ]
        );
    }
    
    public function normalizeGameDefaults(array $data, string $direction, string $format): array
    {
        $errors = [];

        $isGameClassic = ($direction === 'classic' && $format === 'game');
        $isGameBeach = ($direction === 'beach' && $format === 'game');
        $isTrainingLike = in_array($format, ['training','training_game','training_pro_am','camp','coach_student'], true);

        $needGameSettings = ($isGameClassic || $isGameBeach || $isTrainingLike);

       if ($isGameClassic) {

            $subtype = (string)($data['game_subtype'] ?? '');

            if ($subtype === '') {
                $subtype = '4x2';
                $data['game_subtype'] = $subtype;
            }

            $defaults = [
                '4x4' => [8,16],
                '4x2' => [6,12],
                '5x1' => [6,12],
            ];

            if (!isset($defaults[$subtype])) {
                return [
                    'data'=>$data,
                    'errors'=>['game_subtype'=>['Неверный подтип игры.']],
                    'isGameClassic'=>true,
                    'isGameBeach'=>false,
                    'isTrainingLike'=>false,
                    'needGameSettings'=>true,
                    'minPlayers'=>null,
                    'maxPlayers'=>null
                ];
            }

            [$defMin,$defMax] = $defaults[$subtype];

            if (empty($data['game_min_players'])) $data['game_min_players'] = $defMin;
            if (empty($data['game_max_players'])) $data['game_max_players'] = $defMax;

        }
        elseif ($isGameBeach) {

            $subtype = (string)($data['game_subtype'] ?? '');

            if ($subtype === '') {
                $subtype = '2x2';
                $data['game_subtype'] = $subtype;
            }

            if (!in_array($subtype,['2x2','3x3','4x4'],true)) {
                return [
                    'data'=>$data,
                    'errors'=>['game_subtype'=>['Неверный подтип игры.']],
                    'isGameClassic'=>false,
                    'isGameBeach'=>true,
                    'isTrainingLike'=>false,
                    'needGameSettings'=>true,
                    'minPlayers'=>null,
                    'maxPlayers'=>null
                ];
            }

            $defaults = [
                '2x2'=>[4,6],
                '3x3'=>[6,12],
                '4x4'=>[8,16],
            ];

            [$defMin,$defMax] = $defaults[$subtype];

            if (empty($data['game_min_players'])) $data['game_min_players']=$defMin;
            if (empty($data['game_max_players'])) $data['game_max_players']=$defMax;

        }
        elseif ($isTrainingLike) {

            $subtype = (string)($data['game_subtype'] ?? '');

            if ($subtype === '') {
                $subtype = ($direction === 'beach') ? '2x2' : '4x2';
            }

            $data['game_subtype'] = $subtype;

            if (empty($data['game_min_players']))
                $data['game_min_players'] = ($direction === 'beach') ? 4 : 6;

            if (empty($data['game_max_players']))
                $data['game_max_players'] = ($direction === 'beach') ? 6 : 12;

        }
        else {

            $data['game_subtype'] = null;
            $data['game_min_players'] = null;
            $data['game_max_players'] = null;
            $data['game_allow_girls'] = 0;
            $data['game_girls_max'] = null;
            $data['game_has_libero'] = 0;
            $data['game_libero_mode'] = null;
            $data['game_positions'] = [];
            $data['game_gender_policy'] = null;
            $data['game_gender_limited_side'] = null;
            $data['game_gender_limited_max'] = null;
            $data['game_gender_limited_positions'] = null;

        }

        $minPlayers = isset($data['game_min_players']) && $data['game_min_players'] !== ''
            ? (int)$data['game_min_players']
            : null;

        $maxPlayers = isset($data['game_max_players']) && $data['game_max_players'] !== ''
            ? (int)$data['game_max_players']
            : null;

        if (!is_null($minPlayers) && !is_null($maxPlayers) && $maxPlayers < $minPlayers) {
            $errors['game_max_players'] = ['Макс. участников не может быть меньше Мин. участников.'];
        }

        return [
            'data'=>$data,
            'errors'=>$errors,
            'isGameClassic'=>$isGameClassic,
            'isGameBeach'=>$isGameBeach,
            'isTrainingLike'=>$isTrainingLike,
            'needGameSettings'=>$needGameSettings,
            'minPlayers'=>$minPlayers,
            'maxPlayers'=>$maxPlayers
        ];
    }


        /*
        |--------------------------------------------------------------------------
        | GENDER POLICY
        |--------------------------------------------------------------------------
        */
        public function normalizeGenderPolicy(
            array $data,
            string $direction,
            string $format,
            ?int $minPlayers,
            ?int $maxPlayers
        ): array {
            $errors = [];
        
            $isClassicPlayableFormat = (
                $direction === 'classic'
                && in_array($format, ['game', 'training', 'training_game', 'camp', 'tournament', 'tournament_classic'], true)
            );
        
            $isBeachPlayableFormat = (
                $direction === 'beach'
                && in_array($format, ['game', 'training', 'training_game', 'camp', 'tournament', 'tournament_beach'], true)
            );
        
            $genderPolicy = (string) ($data['game_gender_policy'] ?? '');
            $genderLimitedSide = $data['game_gender_limited_side'] ?? null;
        
            $genderLimitedMax = isset($data['game_gender_limited_max'])
                ? (int) $data['game_gender_limited_max']
                : null;
        
            $genderLimitedPositions = $data['game_gender_limited_positions'] ?? null;
        
            if (is_string($genderLimitedPositions)) {
                $genderLimitedPositions = [$genderLimitedPositions];
            }
        
            if (is_array($genderLimitedPositions)) {
                $genderLimitedPositions = array_values(
                    array_unique(array_map('strval', $genderLimitedPositions))
                );
        
                if (count($genderLimitedPositions) === 0) {
                    $genderLimitedPositions = null;
                }
            } else {
                $genderLimitedPositions = null;
            }
        
            $legacyAllowGirls = (bool) ($data['game_allow_girls'] ?? true);
        
            $legacyGirlsMax = (
                isset($data['game_girls_max']) &&
                (string) $data['game_girls_max'] !== ''
            )
                ? (int) $data['game_girls_max']
                : null;
        
            if (!$isClassicPlayableFormat && !$isBeachPlayableFormat) {
                return [
                    'data' => $data,
                    'genderPolicy' => '',
                    'genderLimitedSide' => null,
                    'genderLimitedMax' => null,
                    'genderLimitedPositions' => null,
                    'errors' => [],
                ];
            }
        
            if ($isClassicPlayableFormat) {
                if (
                    $genderPolicy === '' &&
                    $genderLimitedSide &&
                    !is_null($genderLimitedMax)
                ) {
                    $genderPolicy = 'mixed_limited';
                }
        
                if ($genderPolicy === 'mixed_5050') {
                    $errors['game_gender_policy'] = [
                        '50/50 доступно только для пляжного волейбола.',
                    ];
                }
        
                if ($genderPolicy === '') {
                    if (!$legacyAllowGirls) {
                        $genderPolicy = 'only_male';
                    } elseif (!is_null($legacyGirlsMax)) {
                        $genderPolicy = 'mixed_limited';
                        $genderLimitedSide = 'female';
                        $genderLimitedMax = $legacyGirlsMax;
                    } else {
                        $genderPolicy = 'mixed_open';
                    }
                }
        
                if ($genderPolicy === 'mixed_limited') {
                    if (!$genderLimitedSide) {
                        $errors['game_gender_limited_side'] = [
                            'Укажи, кого ограничиваем (М или Ж).',
                        ];
                    }
        
                    if (is_null($genderLimitedMax)) {
                        $errors['game_gender_limited_max'] = [
                            'Укажи максимум мест для ограничиваемых.',
                        ];
                    }
                } else {
                    $genderLimitedSide = null;
                    $genderLimitedMax = null;
                    $genderLimitedPositions = null;
                }
            }
        
            if ($isBeachPlayableFormat) {
                if ($genderPolicy === '') {
                    $genderPolicy = 'mixed_open';
                }
        
                $allowed = [
                    'mixed_open',
                    'mixed_5050',
                    'only_male',
                    'only_female',
                ];
        
                if (!in_array($genderPolicy, $allowed, true)) {
                    $errors['game_gender_policy'] = [
                        'Для пляжа доступны: без ограничений / только мужчины / только девушки / микс 50/50.',
                    ];
                }
        
                $genderLimitedSide = null;
                $genderLimitedMax = null;
                $genderLimitedPositions = null;
        
                $data['game_allow_girls'] = 0;
                $data['game_girls_max'] = null;
        
                if ($genderPolicy === 'mixed_5050') {
                    $errorField = $this->isTournament($format)
                        ? 'tournament_total_players_max'
                        : 'game_max_players';
        
                    $mp = (int) ($maxPlayers ?? 0);
        
                    if ($mp < 2) {
                        $errors[$errorField] = [
                            'Для 50/50 минимум 2 участника.',
                        ];
                    } elseif ($mp % 2 !== 0) {
                        $errors[$errorField] = [
                            'Для 50/50 макс. участников должен быть чётным.',
                        ];
                    }
                }
            }
        
            return [
                'data' => $data,
                'genderPolicy' => $genderPolicy,
                'genderLimitedSide' => $genderLimitedSide,
                'genderLimitedMax' => $genderLimitedMax,
                'genderLimitedPositions' => $genderLimitedPositions,
                'errors' => $errors,
            ];
        }


    /*
    |--------------------------------------------------------------------------
    | CLASSIC POSITIONS
    |--------------------------------------------------------------------------
    */

    public function autoPositionsForClassic(string $subtype, ?string $liberoMode): array
    {
        $subtype = trim($subtype);
        $liberoMode = $liberoMode ? trim($liberoMode) : null;

        if ($subtype === '4x4') return ['setter','outside','opposite'];
        if ($subtype === '4x2') return ['setter','outside'];

        if ($subtype === '5x1') {

            if ($liberoMode === 'with_libero')
                return ['setter','outside','opposite','middle','libero'];

            return ['setter','outside','opposite','middle'];

        }

        return [];
    }


    /*
    |--------------------------------------------------------------------------
    | CREATE GAME SETTINGS
    |--------------------------------------------------------------------------
    */

    public function createGameSettings(
        Event $event,
        array $data,
        array $game,
        array $gender,
        string $direction
    ): void {
    
        if (empty($game['needGameSettings'])) {
            return;
        }
    
        $isGameClassic = (bool)($game['isGameClassic'] ?? false);
    
        $subtype = $data['game_subtype'] ?? null;
        $positions = [];
        $liberoMode = null;
    
        if ($isGameClassic) {
    
            $subtypeStr = (string)$subtype;
    
            if ($subtypeStr === '5x1') {
    
                $liberoMode = $data['game_libero_mode'] ?? null;
    
                if (!$liberoMode) {
                    $hasLiberoLegacy = (bool)($data['game_has_libero'] ?? false);
                    $liberoMode = $hasLiberoLegacy ? 'with_libero' : 'without_libero';
                }
    
                $liberoMode = $liberoMode ?: 'with_libero';
            }
    
            $positions = $this->autoPositionsForClassic($subtypeStr, $liberoMode);
        }
    
        $allowGirls = $isGameClassic ? (bool)($data['game_allow_girls'] ?? false) : false;
        $girlsMax   = $isGameClassic ? ($data['game_girls_max'] ?? null) : null;
    
             /*
            |--------------------------------------------------------------------------
            | CALCULATE ROLES
            |--------------------------------------------------------------------------
            */
            
            $teams = (int)($data['teams_count'] ?? 2);
            $teams = max(2, min($teams, 200));
            
            $subtype = $subtype ?: (($direction === 'beach') ? '2x2' : '4x2');
            
            $calc = GameCalculator::calculate(
                (string)$subtype,
                $liberoMode,
                1
            );
            
            /*
            |--------------------------------------------------------------------------
            | MAX PLAYERS
            |--------------------------------------------------------------------------
            */
            
            $maxPlayersCalculated = $calc['team_size'] * $teams;
            
            /*
            |--------------------------------------------------------------------------
            | ROLES
            |--------------------------------------------------------------------------
            */
            
            $roles = [];
            
            foreach (($calc['roles'] ?? []) as $role => $count) {
                $roles[$role] = $count * $teams;
            }
            
            /*
            |--------------------------------------------------------------------------
            | SAFETY CHECK
            |--------------------------------------------------------------------------
            */
            
            if (empty($roles)) {
                throw new \RuntimeException('No roles calculated');
            }
            
            $expectedPlayers = array_sum($roles);
            
            if ($expectedPlayers !== $maxPlayersCalculated) {
                throw new \RuntimeException('Role calculation mismatch');
            }
        /*
        |--------------------------------------------------------------------------
        | SYNC ROLE SLOTS (через сервис)
        |--------------------------------------------------------------------------
        */
        if (empty($roles)) {
            return;
        }
    
        $this->roleSlotService->syncRoleSlots(
            $event,
            $roles
        );
    
        /*
        |--------------------------------------------------------------------------
        | GAME SETTINGS PAYLOAD
        |--------------------------------------------------------------------------
        */
    
        $rawReserve = $data['game_reserve_players_max'] ?? null;
        $reservePlayersMax = ($rawReserve !== null && $rawReserve !== '') ? (int) $rawReserve : null;

        $egsPayload = [
            'subtype'              => $subtype,
            'libero_mode'          => $liberoMode,
            'min_players'          => $data['game_min_players'] ?? null,
            'max_players'          => $maxPlayersCalculated,
            'teams_count'          => $teams,
            'allow_girls'          => $allowGirls,
            'girls_max'            => $girlsMax,
            'positions'            => $positions,
            'reserve_players_max'  => $reservePlayersMax,
        ];
    
        if (empty($egsPayload['subtype'])) {
            $egsPayload['subtype'] = ($direction === 'beach') ? '2x2' : '4x2';
        }
    
        if (Schema::hasColumn('event_game_settings', 'gender_policy')) {
            $egsPayload['gender_policy'] =
                $gender['genderPolicy'] ?: ($data['game_gender_policy'] ?? null);
         }
        
        if (Schema::hasColumn('event_game_settings', 'gender_limited_side')) {
            $egsPayload['gender_limited_side'] =
                $gender['genderLimitedSide']
                ?? ($data['game_gender_limited_side'] ?? null);
        }
        
        if (Schema::hasColumn('event_game_settings', 'gender_limited_max')) {
            $egsPayload['gender_limited_max'] =
                $gender['genderLimitedMax']
                ?? ($data['game_gender_limited_max'] ?? null);
        }
        
        if (Schema::hasColumn('event_game_settings', 'gender_limited_positions')) {
            $egsPayload['gender_limited_positions'] =
                $gender['genderLimitedPositions']
                ?? ($data['game_gender_limited_positions'] ?? null);
        }

        if (Schema::hasColumn('event_game_settings', 'gender_limited_reg_starts_days_before')) {
            $rawDays = $data['game_gender_limited_reg_starts_days_before'] ?? null;
            $egsPayload['gender_limited_reg_starts_days_before'] =
                ($rawDays === null || $rawDays === '') ? null : (int) $rawDays;
        }
    
        EventGameSetting::updateOrCreate(
            ['event_id' => $event->id],
            $egsPayload
        );
    }

}