<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\EventTeam;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Services\EventVisibilityService;
use App\Services\EventRegistrationGuard;
use App\Services\EventCancellationGuard;
use App\Services\EventRegistrationGroupService;

class EventShowService
{
    protected EventVisibilityService $visibility;
    protected EventRegistrationGroupService $groupService;

    public function __construct(
        EventVisibilityService $visibility,
        EventRegistrationGroupService $groupService
    ) {
        $this->visibility = $visibility;
        $this->groupService = $groupService;
    }

    public function handle(Request $request, Event $event): array
    {
        $occurrenceId = (int) $request->query('occurrence');

        if (!$occurrenceId) {
            $occurrenceId = EventOccurrence::query()
                ->where('event_id', $event->id)
                ->whereNull('cancelled_at')
                ->where(function ($q) {
                    $q->whereNull('is_cancelled')->orWhere('is_cancelled', false);
                })
                ->orderBy('starts_at')
                ->value('id');
        }

        if (!$occurrenceId) {
            abort(404);
        }

        $userId = auth()->id() ?? 0;
        $cacheKey = "event_page:{$occurrenceId}:u{$userId}";

        $page = Cache::remember(
            $cacheKey,
            now()->addSeconds(5),
            fn () => $this->buildEventPage($request, $event, $occurrenceId)
        );

        $user = auth()->user();

        $join = app(EventRegistrationGuard::class)
            ->check($user, $page['occurrence']);

        $cancel = app(EventCancellationGuard::class)
            ->check($user, $page['occurrence']);

        $page['join'] = $join;
        $page['cancel'] = $cancel;
        $page['freePositions'] = $join->data['free_positions'] ?? [];

        // ===== Occurrence overrides on event (in-memory, after cache) =====
        $occ = $page['occurrence'];
        $evt = $page['event'];

        // 1. Скалярные поля occurrence → event
        foreach ([
            'title', 'description_html',
            'classic_level_min', 'classic_level_max',
            'beach_level_min', 'beach_level_max',
            'age_policy', 'child_age_min', 'child_age_max',
            'is_paid', 'price_minor', 'price_currency', 'price_text',
            'payment_method', 'payment_link',
            'trainer_user_id',
            'requires_personal_data', 'show_participants',
        ] as $field) {
            if (!is_null($occ->$field)) {
                $evt->$field = $occ->$field;
            }
        }

        // 2. Trainers override (event_occurrence_trainers → event.trainers relation)
        $occ->loadMissing('trainers');
        if ($occ->trainers->isNotEmpty()) {
            $evt->setRelation('trainers', $occ->trainers);
        }

        // 3. Game settings override (event_occurrence_game_settings → event.gameSettings)
        $occGs = $occ->gameSettingsOverride;
        $evtGs = $evt->gameSettings;
        if ($occGs && $evtGs) {
            foreach ([
                'subtype', 'teams_count', 'min_players', 'max_players',
                'gender_policy', 'gender_limited_side', 'gender_limited_max',
                'gender_limited_positions',
                'libero_mode', 'positions',
            ] as $gsField) {
                if (!is_null($occGs->$gsField)) {
                    $evtGs->$gsField = $occGs->$gsField;
                }
            }
        }

        return $page;
    }

    private function buildEventPage(
        Request $request,
        Event $event,
        int $occurrenceId
    ): array {
        $user = auth()->user();

        /*
        |--------------------------------------------------------------------------
        | VISIBILITY
        |--------------------------------------------------------------------------
        */
        if (
            $this->visibility->isPrivateEventRow($event)
            && !$this->visibility->canViewPrivateEvent($event, $user)
        ) {
            abort(404);
        }

        /*
        |--------------------------------------------------------------------------
        | LOCATION COLUMNS
        |--------------------------------------------------------------------------
        */
        $locCols = config('event.location_columns', [
            'id',
            'name',
            'city_id',
            'address',
            'lat',
            'lng',
        ]);

        /*
        |--------------------------------------------------------------------------
        | OCCURRENCE QUERY
        |--------------------------------------------------------------------------
        */
        $occurrence = EventOccurrence::query()
            ->with([
                'registrations.user:id,gender',
                'event.gameSettings',
            ])
            ->withCount([
                'registrations as registrations_count' => function ($q) {
                    $q->whereNull('cancelled_at')
                    ->where(function ($qq) {
                        $qq->whereNull('is_cancelled')->orWhere('is_cancelled', false);
                    })
                    ->where(function ($qq) {
                        $qq->whereNull('status')->orWhere('status', '!=', 'cancelled');
                    });
                },
            ])
            ->where('id', $occurrenceId)
            ->where('event_id', $event->id)
            ->whereNull('cancelled_at')
            ->where(function ($q) {
                $q->whereNull('is_cancelled')->orWhere('is_cancelled', false);
            })
            ->first();

        if (!$occurrence) {
            abort(404);
        }

        /*
        |--------------------------------------------------------------------------
        | EVENT RELATIONS
        |--------------------------------------------------------------------------
        */
        $relations = [
            'location:' . implode(',', $locCols),
            'location.city:id,name,region,timezone',
            'gameSettings:event_id,subtype,libero_mode,max_players,reserve_players_max,positions,gender_policy,gender_limited_side,gender_limited_max,gender_limited_positions,gender_limited_reg_starts_days_before,allow_girls,girls_max',
            'media',
            'tournamentSetting',
            'occurrences',
        ];

        if (method_exists($event, 'trainer_user')) {
            $relations[] = 'trainer_user:id,name,email,nickname,username,phone';
        }

        $event->loadMissing($relations);

        if (method_exists($occurrence, 'location')) {
            $occurrence->loadMissing([
                'location:' . implode(',', $locCols),
                'location.city:id,name,region,timezone',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | REGISTERED COUNT
        |--------------------------------------------------------------------------
        */
        $registeredCount = $occurrence->registrations_count ?? 0;

        /*
        |--------------------------------------------------------------------------
        | BEACH GROUP UI
        |--------------------------------------------------------------------------
        */
        $groupUi = [
            'enabled' => ((string) ($event->direction ?? '') === 'beach')
                && ((string) ($event->registration_mode ?? 'single') === 'mixed_group'),
            'registration' => null,
            'group_key' => null,
            'group_members' => collect(),
            'pending_invites' => collect(),
            'invite_candidates' => collect(),
        ];

        if ($groupUi['enabled'] && $user) {
            $registration = DB::table('event_registrations')
                ->where('event_id', (int) $event->id)
                ->where('user_id', (int) $user->id)
                ->where(function ($q) {
                    $q->whereNull('is_cancelled')->orWhere('is_cancelled', false);
                })
                ->where(function ($q) {
                    $q->whereNull('status')->orWhere('status', '<>', 'cancelled');
                })
                ->orderByDesc('id')
                ->first();

            $groupUi['registration'] = $registration;
            $groupUi['group_key'] = $registration->group_key ?? null;

            if (!empty($groupUi['group_key'])) {
                $groupUi['group_members'] = $this->groupService->getGroupMembers(
                    (int) $event->id,
                    (string) $groupUi['group_key']
                );
            }

            $groupUi['pending_invites'] = $this->groupService->listPendingInvitesForUser(
                (int) $event->id,
                (int) $user->id
            );

            $inviteCandidates = DB::table('users')
                ->where('id', '<>', (int) $user->id)
                ->orderBy('name')
                ->limit(100)
                ->get(['id', 'name', 'email']);

            $groupedUserIds = DB::table('event_registrations')
                ->where('event_id', (int) $event->id)
                ->whereNotNull('group_key')
                ->where(function ($q) {
                    $q->whereNull('is_cancelled')->orWhere('is_cancelled', false);
                })
                ->where(function ($q) {
                    $q->whereNull('status')->orWhere('status', '<>', 'cancelled');
                })
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $groupUi['invite_candidates'] = $inviteCandidates
                ->reject(function ($u) use ($groupedUserIds) {
                    return in_array((int) $u->id, $groupedUserIds, true);
                })
                ->values();
        }

        /*
        |--------------------------------------------------------------------------
        | TOURNAMENT UI
        |--------------------------------------------------------------------------
        */
        $tournamentSetting = $event->tournamentSetting;

        $isTournament = $tournamentSetting
            && in_array((string) $tournamentSetting->registration_mode, ['team_classic', 'team_beach'], true);

        $myTournamentTeams = collect();

        if ($isTournament && $user) {
            $myTournamentTeams = EventTeam::query()
                ->where('event_id', $event->id)
                ->where(function ($q) use ($user) {
                    $q->where('captain_user_id', (int) $user->id)
                        ->orWhereHas('members', function ($mq) use ($user) {
                            $mq->where('user_id', (int) $user->id);
                        });
                })
                ->with([
                    'occurrence',
                    'application',
                ])
                ->orderByDesc('id')
                ->get();
        }

        /*
        |--------------------------------------------------------------------------
        | RETURN PAGE DATA
        |--------------------------------------------------------------------------
        */
        $registrationMode = (string) ($event->registration_mode ?? 'single');

        // Настройки оплаты рекламных мероприятий
        $platPaySettings = \App\Models\PlatformPaymentSetting::first();

        return [
            'event' => $event,
            'platPaySettings' => $platPaySettings,
            'occurrence' => $occurrence,
            'registered_total' => $registeredCount,
            'participants' => collect(),
            'groupUi' => $groupUi,
            'tournamentUi' => [
                'enabled' => $isTournament,
                'setting' => $tournamentSetting,
                'myTeams' => $myTournamentTeams,
            ],
            'myTournamentTeams' => $myTournamentTeams,
            'registrationMode' => $registrationMode,
        ];
    }
}