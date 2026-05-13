<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\User;
use App\Services\EventRegistrationGroupService;
use App\Services\UserNotificationService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\EventOccurrenceStatsService;
use App\Services\StaffLogService;

class EventRegistrationsManagementController extends Controller
{
    public function __construct(
        private EventRegistrationGroupService $groupService,
        private UserNotificationService $userNotificationService
    ) {}

    /**
     * GET /events/{event}/registrations
     */
    public function index(Request $request, Event $event)
    {
        $user = $request->user();
        if (!$user) return redirect()->route('login');

        $this->ensureCanCreateEvents($user);
        $this->ensureCanManageEvent($user, $event);

        if (!Schema::hasColumn('event_registrations', 'cancelled_at')) {
            return back()->with('error', 'Нет колонки cancelled_at в event_registrations.');
        }

        $hasPosition    = Schema::hasColumn('event_registrations', 'position');
        $hasGroupKey    = Schema::hasColumn('event_registrations', 'group_key');
        $hasCancelledAt = true; // уже проверили выше
        $hasIsCancelled = Schema::hasColumn('event_registrations', 'is_cancelled');
        $hasStatus      = Schema::hasColumn('event_registrations', 'status');
        $hasOrgNote     = Schema::hasColumn('event_registrations', 'organizer_note');
 
        // Game context для позиций и группировки
        $event->loadMissing('gameSettings');
        $direction          = (string) ($event->direction ?? 'classic');
        $gameSubtype        = (string) ($event->gameSettings?->subtype ?? '');
        $liberoMode         = (string) ($event->gameSettings?->libero_mode ?? 'with_libero');
        $availablePositions = $this->resolvePositions($direction, $gameSubtype, $liberoMode);

        $occurrenceId = (int) $request->query('occurrence', 0);
        $occurrence   = null;
        if ($occurrenceId) {
            $occurrence = DB::table('event_occurrences')
                ->where('id', $occurrenceId)
                ->where('event_id', (int) $event->id)
                ->first(['id', 'max_players', 'starts_at', 'timezone']);
        }

        // Свободные слоты по позициям (только для классики с конкретной датой)
        $freePositionSlots = []; // role => free_count
        $eventSlots = null;
        if ($direction === 'classic') {
            $eventSlots = app(\App\Services\EventRoleSlotService::class)->getSlots($event);
            if ($occurrenceId && $eventSlots->isNotEmpty()) {
                $takenByRole = DB::table('event_registrations')
                    ->where('occurrence_id', $occurrenceId)
                    ->whereNull('cancelled_at')
                    ->whereIn('position', $eventSlots->pluck('role')->all())
                    ->selectRaw('position, count(*) as cnt')
                    ->groupBy('position')
                    ->pluck('cnt', 'position')
                    ->toArray();
                foreach ($eventSlots as $slot) {
                    $taken = (int) ($takenByRole[$slot->role] ?? 0);
                    $freePositionSlots[$slot->role] = max(0, $slot->max_slots - $taken);
                }
            }
        }

        // Запасные игроки: добавляем позицию 'reserve' если настроена или уже используется
        if ($direction === 'classic') {
            $reserveSlot = $eventSlots?->firstWhere('role', 'reserve');
            $reserveMax  = $reserveSlot
                ? (int) $reserveSlot->max_slots
                : (int) ($event->gameSettings?->reserve_players_max ?? 0);

            // Есть ли уже записанные запасные (для корректного отображения legacy-данных)
            $hasReserveRegs = $occurrenceId && DB::table('event_registrations')
                ->where('occurrence_id', $occurrenceId)
                ->whereNull('cancelled_at')
                ->where('position', 'reserve')
                ->exists();

            if ($reserveMax > 0 || $hasReserveRegs) {
                $availablePositions['reserve'] = __('events.positions.reserve');

                if ($occurrenceId && !array_key_exists('reserve', $freePositionSlots)) {
                    $takenReserve = (int) DB::table('event_registrations')
                        ->where('occurrence_id', $occurrenceId)
                        ->whereNull('cancelled_at')
                        ->where('position', 'reserve')
                        ->count();
                    if ($reserveMax > 0) {
                        $freePositionSlots['reserve'] = max(0, $reserveMax - $takenReserve);
                    }
                    // reserveMax=0 но regs есть → не добавляем в freeSlots → показ без ограничения
                }
            }
        }

        // max_players: из occurrence если открыта конкретная дата, иначе из event_game_settings
        if ($occurrence && $occurrence->max_players > 0) {
            $maxPlayers = (int) $occurrence->max_players;
        } else {
            $maxPlayers = (int) DB::table('event_game_settings')
                ->where('event_id', (int) $event->id)
                ->value('max_players');
        }

        $activeRegsQuery = DB::table('event_registrations')
            ->where('event_id', (int) $event->id)
            ->whereNull('cancelled_at');
        if ($occurrenceId) {
            $activeRegsQuery->where('occurrence_id', $occurrenceId);
        }
        $activeRegs = (int) $activeRegsQuery->count();

        $freeSeats = null;
        if ($maxPlayers > 0) {
            $freeSeats = max(0, $maxPlayers - $activeRegs);
        }

        $registrations = DB::table('event_registrations as er')
            ->join('users as u', 'u.id', '=', 'er.user_id')
            ->where('er.event_id', (int) $event->id)
            ->when($occurrenceId, fn ($q) => $q->where('er.occurrence_id', $occurrenceId))
            ->select([
                'er.id',
                'er.user_id',
                $hasGroupKey    ? 'er.group_key'    : DB::raw('NULL::text as group_key'),
                $hasPosition    ? 'er.position'     : DB::raw('NULL::text as position'),
                'er.cancelled_at',
                $hasIsCancelled ? 'er.is_cancelled' : DB::raw('NULL::boolean as is_cancelled'),
                $hasStatus      ? 'er.status'       : DB::raw('NULL::text as status'),
                'er.created_at',
                $hasOrgNote ? 'er.organizer_note' : DB::raw("NULL::text as organizer_note"),
                'u.first_name',
                'u.last_name',
                'u.patronymic',
                'u.name',
                'u.email',
                'u.phone',
                'u.is_bot',
            ])
            ->orderByRaw('CASE WHEN er.cancelled_at IS NULL THEN 0 ELSE 1 END ASC')
            ->orderByDesc('er.id')
            ->get();

        $location = null;
        if (!empty($event->location_id) && Schema::hasTable('locations')) {
            $location = DB::table('locations')
                ->where('id', (int) $event->location_id)
                ->first(['id', 'name', 'address', 'city_id']);
        }

        $q     = trim((string) $request->query('q', ''));
        $users = collect();
 
        if ($q !== '') {
            $isBotKeyword = in_array(mb_strtolower($q), ['bot', 'бот', 'боты', 'bots'], true);
 
            if ($isBotKeyword) {
                $users = User::query()
                    ->select(['id', 'name', 'email', 'is_bot'])
                    ->where('is_bot', true)
                    ->orderBy('name')
                    ->limit(40)
                    ->get();
            } else {
                $like  = "%{$q}%";
                $users = User::query()
                    ->select(['id', 'name', 'email', 'is_bot'])
                    ->where(fn ($w) => $w
                        ->where('name', 'ilike', $like)
                        ->orWhere('email', 'ilike', $like)
                        ->orWhere('first_name', 'ilike', $like)
                        ->orWhere('last_name', 'ilike', $like))
                    ->orderBy('name')
                    ->limit(30)
                    ->get();
            }
        }

        $groupInvites = collect();
        if ($direction === 'beach' && Schema::hasTable('event_registration_group_invites')) {
            $groupInvites = DB::table('event_registration_group_invites as i')
                ->join('users as fu', 'fu.id', '=', 'i.from_user_id')
                ->join('users as tu', 'tu.id', '=', 'i.to_user_id')
                ->where('i.event_id', (int) $event->id)
                ->orderByDesc('i.id')
                ->get([
                    'i.id',
                    'i.group_key',
                    'i.from_user_id',
                    'i.to_user_id',
                    'i.status',
                    'i.auto_join_after_registration',
                    'i.created_at',
                    'fu.name as from_user_name',
                    'fu.email as from_user_email',
                    'tu.name as to_user_name',
                    'tu.email as to_user_email',
                ]);
        }

        // Дата: если открыта конкретная occurrence — берём её дату, иначе дату события
        $tz = (string) ($occurrence->timezone ?? $event->timezone ?: 'UTC');
        $startsLocal = null;
        $endsLocal   = null;

        $startsAt = $occurrence->starts_at ?? $event->starts_at ?? null;
        if (!empty($startsAt)) {
            $startsLocal = Carbon::parse($startsAt, 'UTC')->setTimezone($tz);
        }
        if (!$occurrence && !empty($event->ends_at)) {
            $endsLocal = Carbon::parse($event->ends_at, 'UTC')->setTimezone($tz);
        }

        // История действий по occurrence (или всему event если occurrence не выбран)
        $registrationLogs = collect();
        if (Schema::hasTable('event_registration_logs')) {
            $logsQuery = DB::table('event_registration_logs as rl')
                ->join('users as u', 'u.id', '=', 'rl.user_id')
                ->leftJoin('users as a', 'a.id', '=', 'rl.actor_id')
                ->where('rl.event_id', (int) $event->id)
                ->select([
                    'rl.id',
                    'rl.registration_id',
                    'rl.user_id',
                    'rl.actor_id',
                    'rl.action',
                    'rl.created_at',
                    DB::raw("COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u.last_name, u.first_name)), ''), u.name) as user_name"),
                    DB::raw("COALESCE(NULLIF(TRIM(CONCAT_WS(' ', a.last_name, a.first_name)), ''), a.name) as actor_name"),
                ])
                ->orderByDesc('rl.created_at')
                ->orderByDesc('rl.id');

            if ($occurrenceId) {
                $logsQuery->where('rl.occurrence_id', $occurrenceId);
            }

            $registrationLogs = $logsQuery->limit(200)->get();
        }

        return view('events.registrations.index', [
                    'event'              => $event,
                    'location'           => $location,
                    'registrations'      => $registrations,
                    'groupInvites'       => $groupInvites,
                    'maxPlayers'         => $maxPlayers,
                    'activeRegs'         => $activeRegs,
                    'freeSeats'          => $freeSeats,
                    'tz'                 => $tz,
                    'startsLocal'        => $startsLocal,
                    'endsLocal'          => $endsLocal,
                    'freeCount'          => is_null($freeSeats) ? 0 : (int) $freeSeats,
                    'activeCount'        => (int) $activeRegs,
                    'hasPosition'        => $hasPosition,
                    'hasCancelledAt'     => $hasCancelledAt,
                    'hasIsCancelled'     => $hasIsCancelled,
                    'hasStatus'          => $hasStatus,
                    'q'                  => $q,
                    'users'              => $users,
                    'occurrenceId'       => $occurrenceId ?: null,
                    // game context
                    'direction'          => $direction,
                    'gameSubtype'        => $gameSubtype,
                    'availablePositions' => $availablePositions,
                    'freePositionSlots'  => $freePositionSlots,
                    'hasOrgNote'         => $hasOrgNote,
                    'registrationLogs'   => $registrationLogs,
                ]);
    }

    /**
     * POST /events/{event}/registrations/add
     */
    public function addPlayer(Request $request, Event $event)
    {
        $authUser = $request->user();
        if (!$authUser) return redirect()->route('login');

        $this->ensureCanCreateEvents($authUser);
        $this->ensureCanManageEvent($authUser, $event);

        if (!Schema::hasColumn('event_registrations', 'cancelled_at')) {
            return back()->with('error', 'Нет колонки cancelled_at в event_registrations.');
        }

        $event->loadMissing('gameSettings');
        $addDirection = (string)($event->direction ?? 'classic');
        $addPositions = $this->resolvePositions(
            $addDirection,
            (string)($event->gameSettings?->subtype ?? ''),
            (string)($event->gameSettings?->libero_mode ?? 'with_libero')
        );

        // Добавляем 'reserve' если настроено
        $addSlots      = null;
        $addReserveMax = 0;
        if ($addDirection === 'classic') {
            $addSlots        = app(\App\Services\EventRoleSlotService::class)->getSlots($event);
            $addReserveSlot  = $addSlots->firstWhere('role', 'reserve');
            $addReserveMax   = $addReserveSlot
                ? (int) $addReserveSlot->max_slots
                : (int) ($event->gameSettings?->reserve_players_max ?? 0);
            if ($addReserveMax > 0) {
                $addPositions['reserve'] = __('events.positions.reserve');
            }
        }
        $positionRequired = $addDirection === 'classic' && count($addPositions) > 0;

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'position' => $positionRequired
                ? ['required', 'string', 'max:255', 'in:' . implode(',', array_keys($addPositions))]
                : ['nullable', 'string', 'max:255'],
        ]);

        $userId = (int) $data['user_id'];
        $pos = trim((string) ($data['position'] ?? ''));

        // Определяем occurrence_id из запроса или ближайший
        $occurrenceId = (int) $request->input('occurrence_id', $request->query('occurrence', 0));
        if (!$occurrenceId) {
            $occurrenceId = \App\Models\EventOccurrence::where('event_id', $event->id)
                ->whereNull('cancelled_at')
                ->where(function ($q) {
                    $q->whereNull('is_cancelled')->orWhere('is_cancelled', false);
                })
                ->orderBy('starts_at')
                ->value('id');
        }

        // Проверяем лимит слота позиции
        if ($pos !== '' && $occurrenceId && $addDirection === 'classic') {
            $checkSlots = $addSlots ?? app(\App\Services\EventRoleSlotService::class)->getSlots($event);
            $slot       = $checkSlots->firstWhere('role', $pos);
            $maxForPos  = $slot ? (int) $slot->max_slots : ($pos === 'reserve' ? $addReserveMax : 0);

            if ($maxForPos > 0) {
                $taken = (int) DB::table('event_registrations')
                    ->where('occurrence_id', $occurrenceId)
                    ->whereNull('cancelled_at')
                    ->where('position', $pos)
                    ->count();
                if ($taken >= $maxForPos) {
                    $lbl = $addPositions[$pos] ?? $pos;
                    return back()->with('error', "Позиция «{$lbl}» заполнена ({$taken}/{$maxForPos}).");
                }
            }
        }

        $existing = DB::table('event_registrations')
            ->where('event_id', (int) $event->id)
            ->where('user_id', $userId)
            ->when($occurrenceId, fn($q) => $q->where('occurrence_id', $occurrenceId))
            ->first(['id', 'cancelled_at']);

        if ($existing) {
            if (!empty($existing->cancelled_at)) {
                $upd = [
                    'cancelled_at' => null,
                    'occurrence_id' => $occurrenceId ?: null,
                    'updated_at' => now(),
                ];

                if (Schema::hasColumn('event_registrations', 'position')) {
                    $upd['position'] = $pos;
                }

                DB::table('event_registrations')
                    ->where('id', (int) $existing->id)
                    ->update($upd);

                if ($occurrenceId) {
                    app(EventOccurrenceStatsService::class)->increment($occurrenceId);
                }

                if (Schema::hasTable('event_registration_logs')) {
                    DB::table('event_registration_logs')->insert([
                        'registration_id' => (int) $existing->id,
                        'event_id'        => (int) $event->id,
                        'occurrence_id'   => $occurrenceId ?: null,
                        'user_id'         => $userId,
                        'actor_id'        => (int) $authUser->id,
                        'action'          => 'restored',
                        'created_at'      => now(),
                    ]);
                }

                $this->userNotificationService->createRegistrationCreatedNotification(
                    userId: $userId,
                    eventId: (int) $event->id,
                    occurrenceId: null,
                    eventTitle: (string) ($event->title ?? ('Мероприятие #' . $event->id))
                );

                if ($occurrenceId) {
                    \App\Jobs\NotifyOrganizerRegistrationJob::dispatch(
                        $occurrenceId,
                        $userId,
                        'org_registered',
                        (int) $authUser->id,
                        $pos ?: null
                    )->onQueue('default')->afterCommit();
                }

                return back()->with('status', 'Игрок восстановлен ✅');
            }

            return back()->with('error', 'Этот игрок уже зарегистрирован на мероприятие.');
        }

        $insert = [
            'event_id' => (int) $event->id,
            'occurrence_id' => $occurrenceId ?: null,
            'user_id' => $userId,
            'status' => 'confirmed',
            'cancelled_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('event_registrations', 'position')) {
            $insert['position'] = $pos;
        }

        $newRegId = DB::table('event_registrations')->insertGetId($insert);

        if ($occurrenceId) {
            app(EventOccurrenceStatsService::class)->increment($occurrenceId);
        }

        if (Schema::hasTable('event_registration_logs')) {
            DB::table('event_registration_logs')->insert([
                'registration_id' => $newRegId,
                'event_id'        => (int) $event->id,
                'occurrence_id'   => $occurrenceId ?: null,
                'user_id'         => $userId,
                'actor_id'        => (int) $authUser->id,
                'action'          => 'registered',
                'created_at'      => now(),
            ]);
        }

        $this->userNotificationService->createRegistrationCreatedNotification(
            userId: $userId,
            eventId: (int) $event->id,
            occurrenceId: $occurrenceId ?: null,
            eventTitle: (string) ($event->title ?? ('Мероприятие #' . $event->id))
        );

        if ($occurrenceId) {
            \App\Jobs\NotifyOrganizerRegistrationJob::dispatch(
                $occurrenceId,
                $userId,
                'org_registered',
                (int) $authUser->id,
                $pos ?: null
            )->onQueue('default')->afterCommit();
        }

        // Лог Staff
        if ($authUser->isStaff()) {
            $orgId = $authUser->getOrganizerIdForStaff();
            if ($orgId) app(StaffLogService::class)->log($authUser, $orgId, 'add_participant', 'event', $event->id, "Добавил участника # в мероприятие: {$event->title}");
        }

        return back()->with('status', 'Игрок добавлен ✅');
    }

    /**
     * PATCH /events/{event}/registrations/{registration}/position
     */
    public function updatePosition(Request $request, Event $event, int $registration)
    {
        $user = $request->user();
        if (!$user) return redirect()->route('login');

        $this->ensureCanCreateEvents($user);
        $this->ensureCanManageEvent($user, $event);

        if (!Schema::hasColumn('event_registrations', 'position')) {
            return back()->with('error', 'В таблице event_registrations нет колонки position (место).');
        }

        $event->loadMissing('gameSettings');
        $updDirection = (string)($event->direction ?? 'classic');
        $updPositions = $this->resolvePositions(
            $updDirection,
            (string)($event->gameSettings?->subtype ?? ''),
            (string)($event->gameSettings?->libero_mode ?? 'with_libero')
        );

        // Добавляем 'reserve' если настроено
        $updSlots      = null;
        $updReserveMax = 0;
        if ($updDirection === 'classic') {
            $updSlots       = app(\App\Services\EventRoleSlotService::class)->getSlots($event);
            $updReserveSlot = $updSlots->firstWhere('role', 'reserve');
            $updReserveMax  = $updReserveSlot
                ? (int) $updReserveSlot->max_slots
                : (int) ($event->gameSettings?->reserve_players_max ?? 0);
            if ($updReserveMax > 0) {
                $updPositions['reserve'] = __('events.positions.reserve');
            }
        }
        $posRequired = $updDirection === 'classic' && count($updPositions) > 0;

        $data = $request->validate([
            'position' => $posRequired
                ? ['required', 'string', 'max:255', 'in:' . implode(',', array_keys($updPositions))]
                : ['nullable', 'string', 'max:255'],
        ]);

        $row = DB::table('event_registrations')
            ->where('id', $registration)
            ->where('event_id', (int) $event->id)
            ->first(['id', 'position', 'occurrence_id']);

        if (!$row) {
            return back()->with('error', 'Регистрация не найдена.');
        }

        $newPos     = trim((string) ($data['position'] ?? ''));
        $currentPos = (string) ($row->position ?? '');
        $occId      = (int) ($row->occurrence_id ?? 0);

        // Проверяем лимит только если позиция меняется
        if ($newPos !== '' && $newPos !== $currentPos && $occId && $updDirection === 'classic') {
            $checkSlots = $updSlots ?? app(\App\Services\EventRoleSlotService::class)->getSlots($event);
            $slot       = $checkSlots->firstWhere('role', $newPos);
            $maxForPos  = $slot ? (int) $slot->max_slots : ($newPos === 'reserve' ? $updReserveMax : 0);

            if ($maxForPos > 0) {
                $taken = (int) DB::table('event_registrations')
                    ->where('occurrence_id', $occId)
                    ->whereNull('cancelled_at')
                    ->where('position', $newPos)
                    ->count();
                if ($taken >= $maxForPos) {
                    $lbl = $updPositions[$newPos] ?? $newPos;
                    return back()->with('error', "Позиция «{$lbl}» заполнена ({$taken}/{$maxForPos}).");
                }
            }
        }

        DB::table('event_registrations')
            ->where('id', $registration)
            ->update([
                'position' => $newPos,
                'updated_at' => now(),
            ]);

        return back()->with('status', 'Место обновлено ✅');
    }

    /**
     * PATCH /events/{event}/registrations/{registration}/cancel
     * toggle:
     * - если активна -> отменяем (cancelled_at = now)
     * - если отменена -> восстанавливаем (cancelled_at = null)
     */
    public function cancel(Request $request, Event $event, int $registration)
    {
        $authUser = $request->user();
        if (!$authUser) return redirect()->route('login');

        $this->ensureCanCreateEvents($authUser);
        $this->ensureCanManageEvent($authUser, $event);

        if (!Schema::hasColumn('event_registrations', 'cancelled_at')) {
            return back()->with('error', 'Нет колонки cancelled_at в event_registrations.');
        }

        $row = DB::table('event_registrations')
            ->where('id', $registration)
            ->where('event_id', (int) $event->id)
            ->first(['id', 'user_id', 'group_key', 'cancelled_at', 'occurrence_id', 'position']);

        if (!$row) {
            return back()->with('error', 'Регистрация не найдена.');
        }

        $isCancelled = !empty($row->cancelled_at);

        if (!$isCancelled && !empty($row->group_key)) {
            try {
                $this->groupService->leaveGroup((int) $event->id, (int) $row->user_id);
            } catch (DomainException $e) {
                return back()->with('error', $e->getMessage());
            }
        }

        $cancelledAt = $isCancelled ? null : now();

        DB::table('event_registrations')
            ->where('id', $registration)
            ->update([
                'cancelled_at'  => $cancelledAt,
                'is_cancelled'  => $isCancelled ? false : true,
                'status'        => $isCancelled ? 'confirmed' : 'cancelled',
                'updated_at'    => now(),
            ]);

        if (Schema::hasTable('event_registration_logs')) {
            DB::table('event_registration_logs')->insert([
                'registration_id' => $registration,
                'event_id'        => (int) $event->id,
                'occurrence_id'   => $row->occurrence_id ?? null,
                'user_id'         => (int) $row->user_id,
                'actor_id'        => (int) $authUser->id,
                'action'          => $isCancelled ? 'restored' : 'cancelled',
                'created_at'      => now(),
            ]);
        }

        $occId = $row->occurrence_id ?? null;
        if ($occId) {
            if ($isCancelled) {
                app(EventOccurrenceStatsService::class)->increment((int) $occId);
            } else {
                app(EventOccurrenceStatsService::class)->decrement((int) $occId);
                $occ = \App\Models\EventOccurrence::find((int) $occId);
                if ($occ) {
                    app(\App\Services\WaitlistService::class)->onSpotFreed($occ, $row->position ?: '');
                }
            }
        }

        if ($isCancelled) {
            $this->userNotificationService->createRegistrationCreatedNotification(
                userId: (int) $row->user_id,
                eventId: (int) $event->id,
                occurrenceId: null,
                eventTitle: (string) ($event->title ?? ('Мероприятие #' . $event->id))
            );

            if ($occId) {
                \App\Jobs\NotifyOrganizerRegistrationJob::dispatch(
                    (int) $occId,
                    (int) $row->user_id,
                    'org_registered',
                    (int) $authUser->id,
                    $row->position ?: null
                )->onQueue('default')->afterCommit();
            }

            return back()->with('status', 'Восстановлено ✅');
        }

        $this->userNotificationService->createRegistrationCancelledByOrganizerNotification(
            userId: (int) $row->user_id,
            eventId: (int) $event->id,
            occurrenceId: $row->occurrence_id ?? null,
            eventTitle: (string) ($event->title ?? ('Мероприятие #' . $event->id)),
            cancelledByUserId: (int) $authUser->id
        );

        if ($occId) {
            \App\Jobs\NotifyOrganizerRegistrationJob::dispatch(
                (int) $occId,
                (int) $row->user_id,
                'org_cancelled',
                (int) $authUser->id,
                $row->position ?: null
            )->onQueue('default')->afterCommit();
        }

        // Лог Staff
        if ($authUser->isStaff()) {
            $orgId = $authUser->getOrganizerIdForStaff();
            if ($orgId) app(StaffLogService::class)->log($authUser, $orgId, 'cancel_registration', 'event', $event->id, "Отменил запись в мероприятие: {$event->title}");
        }

        return back()->with('status', 'Бронь отменена ✅');
    }

    /**
     * DELETE /events/{event}/registrations/{registration}
     */
    public function destroy(Request $request, Event $event, int $registration)
    {
        $authUser = $request->user();
        if (!$authUser) return redirect()->route('login');

        $this->ensureCanCreateEvents($authUser);
        $this->ensureCanManageEvent($authUser, $event);

        $row = DB::table('event_registrations')
            ->where('id', $registration)
            ->where('event_id', (int) $event->id)
            ->first(['id', 'user_id', 'group_key', 'occurrence_id', 'cancelled_at', 'is_cancelled', 'status', 'position']);

        if (!$row) {
            return back()->with('error', 'Регистрация не найдена.');
        }

        if (!empty($row->group_key)) {
            try {
                $this->groupService->leaveGroup((int) $event->id, (int) $row->user_id);
            } catch (DomainException $e) {
                return back()->with('error', $e->getMessage());
            }
        }

        $deleted = DB::table('event_registrations')
            ->where('id', $registration)
            ->where('event_id', (int) $event->id)
            ->delete();

        if (!$deleted) {
            return back()->with('error', 'Регистрация не найдена.');
        }

        // Уменьшаем счётчик только если запись была активной
        $wasActive = empty($row->cancelled_at) && !$row->is_cancelled && $row->status !== 'cancelled';
        if ($wasActive && !empty($row->occurrence_id)) {
            app(EventOccurrenceStatsService::class)->decrement((int) $row->occurrence_id);
            $occ = \App\Models\EventOccurrence::find((int) $row->occurrence_id);
            if ($occ) {
                app(\App\Services\WaitlistService::class)->onSpotFreed($occ, $row->position ?: '');
            }
        }

        $this->userNotificationService->createRegistrationCancelledByOrganizerNotification(
            userId: (int) $row->user_id,
            eventId: (int) $event->id,
            occurrenceId: null,
            eventTitle: (string) ($event->title ?? ('Мероприятие #' . $event->id)),
            cancelledByUserId: (int) $authUser->id
        );

        if (!empty($row->occurrence_id)) {
            \App\Jobs\NotifyOrganizerRegistrationJob::dispatch(
                (int) $row->occurrence_id,
                (int) $row->user_id,
                'org_deleted',
                (int) $authUser->id,
                $row->position ?: null
            )->onQueue('default')->afterCommit();
        }

        // Лог Staff
        if ($authUser->isStaff()) {
            $orgId = $authUser->getOrganizerIdForStaff();
            if ($orgId) app(StaffLogService::class)->log($authUser, $orgId, 'remove_participant', 'event', $event->id, "Удалил участника из мероприятия: {$event->title}");
        }

        return back()->with('status', 'Регистрация удалена ✅');
    }

    /**
     * POST /events/{event}/registrations/{registration}/group/create
     */
    public function createGroup(Request $request, Event $event, int $registration)
    {
        $user = $request->user();
        if (!$user) return redirect()->route('login');

        $this->ensureCanCreateEvents($user);
        $this->ensureCanManageEvent($user, $event);

        $row = DB::table('event_registrations')
            ->where('id', $registration)
            ->where('event_id', (int) $event->id)
            ->first(['id', 'user_id', 'cancelled_at']);

        if (!$row) {
            return back()->with('error', 'Регистрация не найдена.');
        }

        if (!empty($row->cancelled_at)) {
            return back()->with('error', 'Нельзя создать группу для отменённой регистрации.');
        }

        try {
            $groupKey = $this->groupService->createGroupForRegistration(
                (int) $event->id,
                (int) $row->user_id
            );

            return back()->with('status', 'Группа создана ✅ ' . $groupKey);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * POST /events/{event}/registrations/group/invite
     */
    public function inviteToGroup(Request $request, Event $event)
    {
        $user = $request->user();
        if (!$user) return redirect()->route('login');

        $this->ensureCanCreateEvents($user);
        $this->ensureCanManageEvent($user, $event);

        $data = $request->validate([
            'from_user_id' => ['required', 'integer', 'exists:users,id'],
            'to_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        try {
            $this->groupService->inviteToGroup(
                (int) $event->id,
                (int) $data['from_user_id'],
                (int) $data['to_user_id']
            );

            return back()->with('status', 'Приглашение в группу отправлено ✅');
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * PATCH /events/{event}/registrations/{registration}/group/leave
     */
    public function leaveGroup(Request $request, Event $event, int $registration)
    {
        $user = $request->user();
        if (!$user) return redirect()->route('login');

        $this->ensureCanCreateEvents($user);
        $this->ensureCanManageEvent($user, $event);

        $row = DB::table('event_registrations')
            ->where('id', $registration)
            ->where('event_id', (int) $event->id)
            ->first(['id', 'user_id']);

        if (!$row) {
            return back()->with('error', 'Регистрация не найдена.');
        }

        try {
            $this->groupService->leaveGroup(
                (int) $event->id,
                (int) $row->user_id
            );

            return back()->with('status', 'Игрок убран из группы ✅');
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
    /**
     * PATCH /events/{event}/registrations/{registration}/note
     */
    public function updateNote(Request $request, Event $event, int $registration)
    {
        $user = $request->user();
        if (!$user) abort(401);

        $this->ensureCanCreateEvents($user);
        $this->ensureCanManageEvent($user, $event);

        $data = $request->validate([
            'organizer_note' => ['nullable', 'string', 'max:500'],
        ]);

        $row = DB::table('event_registrations')
            ->where('id', $registration)
            ->where('event_id', (int) $event->id)
            ->first(['id']);

        if (!$row) abort(404);

        DB::table('event_registrations')
            ->where('id', $registration)
            ->update([
                'organizer_note' => trim((string) ($data['organizer_note'] ?? '')),
                'updated_at'     => now(),
            ]);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('status', 'Комментарий сохранён ✅');
    }

    /**
     * GET /events/{event}/registrations/pdf
     */
    public function exportPdf(Request $request, Event $event)
    {
        $user = $request->user();
        if (!$user) return redirect()->route('login');

        $this->ensureCanCreateEvents($user);
        $this->ensureCanManageEvent($user, $event);

        $event->loadMissing('gameSettings');

        [$tz, $startsLocal, $endsLocal, $registrations, $location] = $this->resolveExportContext($request, $event, withCreatedAt: true);

        $rawFields    = $request->query('fields', 'name,phone,position');
        $fields       = array_filter(explode(',', is_array($rawFields) ? implode(',', $rawFields) : (string) $rawFields));
        if (empty($fields)) $fields = ['name', 'phone', 'position'];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('events.registrations.pdf', compact(
            'event', 'registrations', 'startsLocal', 'endsLocal', 'tz', 'location', 'fields'
        ))->setPaper('a4', 'portrait');

        $filename = 'registrations-' . $event->id . '-' . now()->format('Ymd') . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * GET /events/{event}/registrations/txt
     */
    public function exportTxt(Request $request, Event $event)
    {
        $user = $request->user();
        if (!$user) return redirect()->route('login');

        $this->ensureCanCreateEvents($user);
        $this->ensureCanManageEvent($user, $event);

        $event->loadMissing('gameSettings');

        [$tz, $startsLocal, $endsLocal, $registrations, $location] = $this->resolveExportContext($request, $event);

        $posLabels = [
            'setter'   => 'Связующий',
            'outside'  => 'Доигровщик',
            'opposite' => 'Диагональный',
            'middle'   => 'Центральный',
            'libero'   => 'Либеро',
            'reserve'  => 'Резерв',
        ];

        $rawFields    = $request->query('fields', 'name,phone,position');
        $fields       = array_filter(explode(',', is_array($rawFields) ? implode(',', $rawFields) : (string) $rawFields));
        if (empty($fields)) $fields = ['name', 'phone', 'position'];
        $showName     = in_array('name',     $fields, true);
        $showPhone    = in_array('phone',    $fields, true);
        $showPosition = in_array('position', $fields, true);

        $dateLine = '—';
        if ($startsLocal) {
            $dateLine = $startsLocal->format('d.m.Y') . ' · ' . $startsLocal->format('H:i');
            if ($endsLocal) $dateLine .= '–' . $endsLocal->format('H:i');
            $dateLine .= ' (' . $tz . ')';
        }

        $locationLine = '—';
        if ($location) {
            $parts = array_filter([$location->city_name ?? null, $location->address ?? null, $location->name ?? null]);
            $locationLine = implode(', ', $parts) ?: '—';
        }

        $lines = [];
        $lines[] = $event->title;
        $lines[] = 'Дата: ' . $dateLine;
        $lines[] = 'Место: ' . $locationLine;
        $lines[] = 'Участников: ' . $registrations->count();
        $lines[] = str_repeat('─', 50);
        $lines[] = '';

        foreach ($registrations as $i => $r) {
            $fullName = trim(implode(' ', array_filter([
                $r->last_name  ?? '',
                $r->first_name ?? '',
                $r->patronymic ?? '',
            ])));
            $name = $fullName ?: ($r->name ?: ('User #' . $r->user_id));
            if (!empty($r->is_bot)) $name .= ' (бот)';

            $phone    = $r->phone ?: '—';
            $posKey   = $r->position ?? '';
            $posLabel = $posKey ? ($posLabels[$posKey] ?? $posKey) : '—';
            $note     = $r->organizer_note ?? '';

            $line = ($i + 1) . '.';
            if ($showName)     $line .= ' ' . $name;
            if ($showPhone)    $line .= '  |  ' . $phone;
            if ($showPosition) $line .= '  |  ' . $posLabel;
            if ($note !== '')  $line .= '  |  ' . $note;
            $lines[] = $line;
        }

        $lines[] = '';
        $lines[] = str_repeat('─', 50);
        $lines[] = 'Сформировано: ' . now()->setTimezone($tz)->format('d.m.Y H:i') . ' (' . $tz . ')';

        $content  = "\xEF\xBB\xBF" . implode("\n", $lines); // UTF-8 BOM для корректного отображения кириллицы в Windows
        $filename = 'registrations-' . $event->id . '-' . now()->format('Ymd') . '.txt';

        return response($content, 200, [
            'Content-Type'        => 'text/plain; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function resolveExportContext(Request $request, Event $event, bool $withCreatedAt = false): array
    {
        $occurrenceId = (int) $request->query('occurrence', 0);
        $occurrence   = null;
        if ($occurrenceId) {
            $occurrence = DB::table('event_occurrences')
                ->where('id', $occurrenceId)
                ->where('event_id', (int) $event->id)
                ->first(['id', 'starts_at', 'timezone', 'location_id']);
        }

        $tz          = (string) ($occurrence->timezone ?? $event->timezone ?: 'UTC');
        $startsAt    = $occurrence->starts_at ?? $event->starts_at ?? null;
        $startsLocal = $startsAt ? Carbon::parse($startsAt, 'UTC')->setTimezone($tz) : null;
        $endsLocal   = (!$occurrence && !empty($event->ends_at))
            ? Carbon::parse($event->ends_at, 'UTC')->setTimezone($tz)
            : null;

        $hasOrgNote  = Schema::hasColumn('event_registrations', 'organizer_note');
        $locationId  = $occurrence->location_id ?? $event->location_id ?? null;

        $select = [
            'er.id',
            'er.user_id',
            'er.position',
            $hasOrgNote ? 'er.organizer_note' : DB::raw("NULL::text as organizer_note"),
            'u.name',
            'u.first_name',
            'u.last_name',
            'u.patronymic',
            'u.phone',
            'u.is_bot',
        ];
        if ($withCreatedAt) {
            $select[] = 'er.created_at';
        }

        $registrations = DB::table('event_registrations as er')
            ->join('users as u', 'u.id', '=', 'er.user_id')
            ->where('er.event_id', (int) $event->id)
            ->when($occurrenceId, fn ($q) => $q->where('er.occurrence_id', $occurrenceId))
            ->whereNull('er.cancelled_at')
            ->where(function ($q) {
                $q->whereNull('er.is_cancelled')->orWhere('er.is_cancelled', false);
            })
            ->where(function ($q) {
                $q->whereNull('er.status')->orWhere('er.status', '!=', 'cancelled');
            })
            ->select($select)
            ->orderBy('er.id')
            ->get();

        $location = null;
        if ($locationId && Schema::hasTable('locations')) {
            $location = DB::table('locations as l')
                ->leftJoin('cities as c', 'c.id', '=', 'l.city_id')
                ->where('l.id', (int) $locationId)
                ->first(['l.name', 'l.address', 'c.name as city_name']);
        }

        return [$tz, $startsLocal, $endsLocal, $registrations, $location];
    }

    private function resolvePositions(string $direction, string $subtype, string $liberoMode): array
    {
        if ($direction === 'beach') return [];
 
        $labels = [
            'setter'   => 'Связующий',
            'outside'  => 'Доигровщик',
            'opposite' => 'Диагональный',
            'middle'   => 'Центральный',
            'libero'   => 'Либеро',
        ];
 
        $map = [
            '4x2' => ['setter', 'outside'],
            '4x4' => ['setter', 'outside', 'opposite'],
            '5x1' => ['setter', 'outside', 'opposite', 'middle'],
        ];
 
        $keys = $map[$subtype] ?? array_keys($labels);
 
        if ($subtype === '5x1' && $liberoMode === 'with_libero') {
            $keys[] = 'libero';
        }
 
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $labels[$key] ?? $key;
        }
        return $result;
    }
    
    private function ensureCanCreateEvents($user): void
    {
        if (!$user) abort(403);

        $role = (string) ($user->role ?? 'user');
        if (!in_array($role, ['admin', 'organizer', 'staff'], true)) {
            abort(403);
        }
    }

    private function ensureCanManageEvent($user, Event $event): void
    {
        $role = (string) ($user->role ?? 'user');

        if ($role === 'admin') return;

        if ($role === 'organizer') {
            if ((int) $event->organizer_id !== (int) $user->id) abort(403);
            return;
        }

        if ($role === 'staff') {
            $orgId = $this->resolveOrganizerIdForCreator($user);
            if ((int) $orgId <= 0) abort(403);
            if ((int) $event->organizer_id !== (int) $orgId) abort(403);
            return;
        }

        abort(403);
    }

    private function resolveOrganizerIdForCreator($user): int
    {
        $role = (string) ($user->role ?? 'user');

        if ($role === 'organizer') return (int) $user->id;

        if ($role === 'staff') {
            $row = DB::table('organizer_staff')
                ->where('staff_user_id', (int) $user->id)
                ->orderBy('id')
                ->first(['organizer_id']);

            return $row ? (int) $row->organizer_id : 0;
        }

        return 0;
    }
}