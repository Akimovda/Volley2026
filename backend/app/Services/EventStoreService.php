<?php

namespace App\Services;

use App\Models\Event;
use App\Models\User;
use App\Jobs\ExpandEventOccurrencesJob;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use App\Services\Validation\EventCreateValidator;

class EventStoreService
{
    public function __construct(
        private EventOccurrenceService $occurrenceService,
        private EventTrainerService $trainerService,
        private EventLocationService $locationService,
        private EventRulesService $rulesService,
        private EventAccessService $accessService,
        private EventDescriptionService $descriptionService,
        private EventCoverService $coverService,
        private EventGameSettingsService $gameSettingsService,
        private EventRoleSlotService $roleSlotService,
        private EventNotificationChannelService $channelService  // <-- ДОБАВЛЕНА НОВАЯ ЗАВИСИМОСТЬ
    ) {}

    /*
        |--------------------------------------------------------------------------
        | MAIN STORE
        |--------------------------------------------------------------------------
    */

    public function store(Request $request, User $user): array
    {

        /*
            |--------------------------------------------------------------------------
            | 1 VALIDATION
            |--------------------------------------------------------------------------
        */

        $validator = app(EventCreateValidator::class)->make($request);
        $data = $validator->validate();
		
    // 👇 НОВЫЙ КОД: Обработка фото для галереи мероприятия
    if ($request->has('event_photos')) {
        $eventPhotos = json_decode($request->event_photos, true);
        $data['event_photos'] = $eventPhotos;
    } else {
        $data['event_photos'] = [];
    }		
		
		
		
        \Log::info('EVENT STORE VALIDATED', [
    'is_paid' => $data['is_paid'] ?? null,
    'price_amount' => $data['price_amount'] ?? null,
    'price_minor' => $data['price_minor'] ?? null,
    'price_currency' => $data['price_currency'] ?? null,
]);
       
        /*
        |--------------------------------------------------------------------------
        | 1.1 TOURNAMENT NORMALIZATION EARLY
        |--------------------------------------------------------------------------
        |
        | Для турниров:
        | - число команд по умолчанию = 4
        | - min игроков в команде берём из схемы
        | - total игроков = базовый состав + запасные
        | - отдельный checkbox "обязателен либеро" больше не нужен:
        |   либеро определяется самой схемой 5x1_libero
        |
        */
        if (($data['format'] ?? null) === 'tournament') {
            $scheme = (string)($data['tournament_game_scheme'] ?? '');
            $reserve = (int)($data['tournament_reserve_players_max'] ?? 0);

            if ($reserve < 0) {
                $reserve = 0;
            }

            if (empty($data['tournament_teams_count'])) {
                $data['tournament_teams_count'] = 4;
            }

            $basePlayers = match ($scheme) {
                '2x2' => 2,
                '3x3' => 3,
                '4x4' => 4,
                '4x2' => 6,
                '5x1' => 6,
                '5x1_libero' => 7,
                default => null,
            };

            if ($basePlayers !== null) {
                $data['tournament_team_size_min'] = $basePlayers;
                $data['tournament_total_players_max'] = $basePlayers + $reserve;
            }

            // Больше не используем отдельный флаг либеро для турнира
            $data['tournament_require_libero'] = ($scheme === '5x1_libero');
        }

        $cover = $this->coverService->resolveCover($request);


        /*
            |--------------------------------------------------------------------------
            | 2 NORMALIZE TITLE
            |--------------------------------------------------------------------------
        */

        $title = trim((string)($data['title'] ?? ''));

        if ($title === '') {

            $dir = (string)($data['direction'] ?? '');
            $fmt = (string)($data['format'] ?? '');

            $dirLabel = match ($dir) {
                'classic' => 'Классика',
                'beach' => 'Пляжка',
                default => 'Волейбол'
            };

            $fmtMap = [
                'game' => 'игра',
                'training' => 'тренировка',
                'training_game' => 'тренировка+игра',
                'training_pro_am' => 'про‑ам тренировка',
                'coach_student' => 'тренер+ученик',
                'tournament' => 'турнир',
                'camp' => 'кемп'
            ];

            $data['title'] = trim($dirLabel.' '.($fmtMap[$fmt] ?? $fmt));
        }


        /*
            |--------------------------------------------------------------------------
            | 3 DESCRIPTION SANITIZE
            |--------------------------------------------------------------------------
        */

        $data = $this->descriptionService->normalizeDescriptionHtml($data);


        /*
            |--------------------------------------------------------------------------
            | 4 ACCESS RULES
            |--------------------------------------------------------------------------
        */

        $this->accessService->assertCreatorCanUseOrganizer(
            $user,
            $data['organizer_id'] ?? null
        );

        $role = (string)($user->role ?? 'user');


        /*
            |--------------------------------------------------------------------------
            | 5 BUSINESS RULES
            |--------------------------------------------------------------------------
        */

        $data['age_policy'] = $this->rulesService->normalizeAgePolicy($data);

        $formatErrors = $this->rulesService->assertFormatRules($data);
        if (!empty($formatErrors)) {
            throw ValidationException::withMessages($formatErrors);
        }


        /*
            |--------------------------------------------------------------------------
            | 6 NORMALIZE LEVELS
            |--------------------------------------------------------------------------
        */

        $data = $this->rulesService->normalizeLevelsByDirection($data);

        $beachErrors = $this->rulesService->assertBeachLevelsIfNeeded(
            $data,
            $data['direction']
        );

        if (!empty($beachErrors)) {
            throw ValidationException::withMessages($beachErrors);
        }


        /*
            |--------------------------------------------------------------------------
            | 7 PAID RULES
            |--------------------------------------------------------------------------
        */

        $data = $this->rulesService->normalizePrice($data);

        $paidErrors = $this->rulesService->assertPaidRules($data);
      
       
        if (!empty($paidErrors)) {
            throw ValidationException::withMessages($paidErrors);
        }
        /*
            |--------------------------------------------------------------------------
            | 8 TRAINERS
            |--------------------------------------------------------------------------
        */

        $needTrainers = in_array(
            $data['format'],
            ['training','training_game','training_pro_am','camp','coach_student'],
            true
        );

        $trainers = $this->trainerService->normalizeTrainerIds(
            $data,
            $needTrainers
        );

        if (!empty($trainers['errors'])) {
            throw ValidationException::withMessages($trainers['errors']);
        }

        $trainerIds = $trainers['trainerIds'];


        /*
            |--------------------------------------------------------------------------
            | 9 LOCATION
            |--------------------------------------------------------------------------
        */

        $location = $this->locationService->resolveAndAssertLocation(
            $data,
            $role
        );


        /*
            |--------------------------------------------------------------------------
            | 10 TIMEZONE
            |--------------------------------------------------------------------------
        */

        $tz = $data['timezone']
            ?? $location->timezone
            ?? config('event_timezones.default','Europe/Moscow');


        /*
            |--------------------------------------------------------------------------
            | 11 DATES
            |--------------------------------------------------------------------------
        */

        $dates = $this->occurrenceService->parseAndAssertDates($data,$tz);

        if (!empty($dates['errors'])) {
            throw ValidationException::withMessages($dates['errors']);
        }

        $startsUtc = $dates['startsUtc'];
        $durationSec = $dates['durationSec'];


        /*
            |--------------------------------------------------------------------------
            | 12 REGISTRATION WINDOWS
            |--------------------------------------------------------------------------
        */

        $allowReg = (bool)($data['allow_registration'] ?? false);

        $windows = $this->occurrenceService->buildRegistrationWindows(
            $startsUtc,
            $allowReg,
            $data
        );

        $data['registration_starts_at'] = $windows['regStartsUtc'];
        $data['registration_ends_at'] = $windows['regEndsUtc'];
        $data['cancel_self_until'] = $windows['cancelUntilUtc'];


        /*
            |--------------------------------------------------------------------------
            | 13 RECURRENCE
            |--------------------------------------------------------------------------
        */

        $rec = $this->occurrenceService->normalizeRecurrenceRule(
            $data,
            $allowReg,
            $tz
        );

        if (!empty($rec['errors'])) {
            throw ValidationException::withMessages($rec['errors']);
        }


        /*
            |--------------------------------------------------------------------------
            | 14 CREATE EVENT
            |--------------------------------------------------------------------------
        */

        DB::beginTransaction();

        try {

            $format = (string) ($data['format'] ?? 'game');
            $isTournament = $this->gameSettingsService->isTournament($format);

            $event = new Event();

            $event->title = $data['title'];
            $event->direction = $data['direction'];
            $event->format = $format;
            $event->registration_mode = $isTournament
                ? ($data['direction'] === 'beach' ? 'team_beach' : 'team_classic')
                : ($data['registration_mode'] ?? 'single');

            $event->organizer_id =
                $data['organizer_id']
                ?? ($this->accessService->resolveOrganizerIdForCreator($user) ?: $user->id);

            $event->location_id = $location->id;

            $event->timezone = $tz;

            $event->starts_at = $startsUtc;
            $event->duration_sec = $durationSec;

            $event->allow_registration = $allowReg;

            $event->classic_level_min = $data['classic_level_min'] ?? null;
            $event->classic_level_max = $data['classic_level_max'] ?? null;
            $event->beach_level_min = $data['beach_level_min'] ?? null;
            $event->beach_level_max = $data['beach_level_max'] ?? null;
            $event->age_policy = $data['age_policy'] ?? 'any';
            $event->child_age_min = ($data['age_policy'] ?? 'any') === 'child'
                ? (isset($data['child_age_min']) ? (int)$data['child_age_min'] : null)
                : null;
            
            $event->child_age_max = ($data['age_policy'] ?? 'any') === 'child'
                ? (isset($data['child_age_max']) ? (int)$data['child_age_max'] : null)
                : null;
            $event->is_private = (bool)($data['is_private'] ?? false);
            
            $event->is_private = (bool)($data['is_private'] ?? false);
            $event->is_paid = (bool)($data['is_paid'] ?? false);
            $event->price_minor = $data['price_minor'] ?? null;
            $event->price_currency = $data['price_currency'] ?? ($event->is_paid ? 'RUB' : null);
            $event->payment_method = $event->is_paid ? ($data['payment_method'] ?? 'cash') : null;
            $event->payment_link   = $event->is_paid ? ($data['payment_link'] ?? null) : null;
            $event->refund_hours_full    = $event->is_paid ? ($data['refund_hours_full'] ?? null) : null;
            $event->refund_hours_partial = $event->is_paid ? ($data['refund_hours_partial'] ?? null) : null;
            $event->refund_partial_pct   = $event->is_paid ? ($data['refund_partial_pct'] ?? null) : null;

            $event->show_participants = (bool)($data['show_participants'] ?? false);
            $event->requires_personal_data = (bool)($data['requires_personal_data'] ?? false);

            $event->is_snow = (bool)($data['is_snow'] ?? false);

            $event->remind_registration_enabled =
                (bool)($data['remind_registration_enabled'] ?? false);

            $event->remind_registration_minutes_before =
                $data['remind_registration_minutes_before'] ?? null;

            $event->is_recurring = (bool)$rec['isRecurring'];
            $event->recurrence_rule = $rec['recRule'];
            
            $event->bot_assistant_enabled      = (bool) ($data['bot_assistant_enabled'] ?? false);
            $event->bot_assistant_threshold    = max(5, min(30, (int) ($data['bot_assistant_threshold'] ?? 10)));
            $event->bot_assistant_max_fill_pct = max(10, min(60, (int) ($data['bot_assistant_max_fill_pct'] ?? 40)));
            
            $event->public_token = $event->public_token
                ?? bin2hex(random_bytes(16));

            $firstTrainerId = (int)($trainerIds[0] ?? 0);

            if ($firstTrainerId > 0) {
                $event->trainer_user_id = $firstTrainerId;
            }
			$event->event_photos = $data['event_photos'] ?? [];
            $event->save();

            /*
                |--------------------------------------------------------------------------
                || TOURNAMENT SETTINGS (если это турнир)
                |--------------------------------------------------------------------------
            */

            if ($isTournament) {
                // Нормализуем турнирные настройки
                $tournamentNormalized = $this->gameSettingsService->normalizeTournamentDefaults(
                    $data,
                    $data['direction'],
                    $format
                );

                if (!empty($tournamentNormalized['errors'])) {
                    throw ValidationException::withMessages($tournamentNormalized['errors']);
                }

                $data = $tournamentNormalized['data'];

                // Создаём или обновляем турнирные настройки
                $this->gameSettingsService->createOrUpdateTournamentSettings(
                    $event,
                    $data,
                    $data['direction'],
                    $format,
                    $tournamentNormalized['registrationMode']
                );
            }

            /*
                |--------------------------------------------------------------------------
                | DESCRIPTION STORE
                |--------------------------------------------------------------------------
            */

            $this->descriptionService->store($event,$data);


            /*
            |--------------------------------------------------------------------------
            | GAME SETTINGS
            |--------------------------------------------------------------------------
            |
            | Для турнира:
            | - normalizeGameDefaults не вызываем
            | - gender policy валидируем по tournament лимитам
            | - event_game_settings не создаём
            |
            | Для обычной игры/тренировки:
            | - работаем через event_game_settings как раньше
            |
            */

            $game = [
                'data' => $data,
                'errors' => [],
                'isGameClassic' => false,
                'isGameBeach' => false,
                'isTrainingLike' => false,
                'needGameSettings' => false,
                'minPlayers' => null,
                'maxPlayers' => null,
            ];

            if (!$isTournament) {
                $game = $this->gameSettingsService->normalizeGameDefaults(
                    $data,
                    $data['direction'],
                    $format
                );

                if (!empty($game['errors'])) {
                    throw ValidationException::withMessages($game['errors']);
                }

                $data = $game['data'];
            }

            /*
            |--------------------------------------------------------------------------
            | GENDER POLICY LIMITS SOURCE
            |--------------------------------------------------------------------------
            |
            | Для обычных форматов лимиты берём из game settings.
            | Для турнира — из tournament settings.
            |
            */

            $genderMinPlayers = $isTournament
                ? (
                    isset($data['tournament_team_size_min']) &&
                    $data['tournament_team_size_min'] !== ''
                        ? (int) $data['tournament_team_size_min']
                        : null
                )
                : ($game['minPlayers'] ?? null);

            $genderMaxPlayers = $isTournament
                ? (
                    isset($data['tournament_total_players_max']) &&
                    $data['tournament_total_players_max'] !== ''
                        ? (int) $data['tournament_total_players_max']
                        : null
                )
                : ($game['maxPlayers'] ?? null);

            $gender = $this->gameSettingsService->normalizeGenderPolicy(
                $data,
                $data['direction'],
                $format,
                $genderMinPlayers,
                $genderMaxPlayers
            );

            if (!empty($gender['errors'])) {
                throw ValidationException::withMessages($gender['errors']);
            }

            $data = $gender['data'];

            /*
            |--------------------------------------------------------------------------
            | CREATE EVENT GAME SETTINGS
            |--------------------------------------------------------------------------
            */

            if (!$isTournament) {
                $this->gameSettingsService->createGameSettings(
                    $event,
                    $data,
                    $game,
                    $gender,
                    $data['direction']
                );
            }

            /*
                |--------------------------------------------------------------------------
                | COVER
                |--------------------------------------------------------------------------
            */

            if (!empty($cover['file'])) {

                $event
                    ->addMedia($cover['file'])
                    ->toMediaCollection('cover');

            }


            /*
                |--------------------------------------------------------------------------
                | TRAINERS
                |--------------------------------------------------------------------------
            */

            $this->trainerService->sync(
                $event,
                $needTrainers,
                $trainerIds
            );


            /*
                |--------------------------------------------------------------------------
                | FIRST OCCURRENCE
                |--------------------------------------------------------------------------
            */

            $this->occurrenceService->createFirstOccurrence(
                $event,
                $durationSec,
                $data['age_policy'],
                (bool)$rec['isRecurring'],
                $data
            );
            
            $this->channelService->storeChannels($event, $request);
            
            if ($event->is_recurring) {
                ExpandEventOccurrencesJob::dispatch($event->id);
            }
    
                DB::commit();
                // Отправляем анонс если регистрация уже открыта
                try {
                    $firstOcc = $event->occurrences()
                        ->where('registration_starts_at', '<=', now())
                        ->whereNull('cancelled_at')
                        ->orderBy('starts_at')
                        ->first();
                
                    if ($firstOcc && $event->notificationChannels()->exists()) {
                        app(\App\Services\PublishOccurrenceAnnouncementService::class)
                            ->publish($firstOcc->load([
                                'event.notificationChannels.channel',
                                'event.media',
                                'event.location.city',
                                'event.organizer',
                                'event.gameSettings',
                            ]));
                    }
                } catch (\Throwable $e) {
                    \Log::warning('Auto-announce failed after event create: ' . $e->getMessage());
                    // Не бросаем — мероприятие уже создано
                }
            } catch (\Throwable $e) {
    
                DB::rollBack();
                throw $e;
            }


        /*
            |--------------------------------------------------------------------------
            | RESULT
            |--------------------------------------------------------------------------
        */

        return [
            'event' => $event
        ];
    }
}