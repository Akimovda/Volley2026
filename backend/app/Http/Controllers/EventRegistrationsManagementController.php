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

        $maxPlayers = (int) DB::table('event_game_settings')
            ->where('event_id', (int) $event->id)
            ->value('max_players');

        $activeRegs = (int) DB::table('event_registrations')
            ->where('event_id', (int) $event->id)
            ->whereNull('cancelled_at')
            ->count();

        $freeSeats = null;
        if ($maxPlayers > 0) {
            $freeSeats = max(0, $maxPlayers - $activeRegs);
        }

        $registrations = DB::table('event_registrations as er')
            ->join('users as u', 'u.id', '=', 'er.user_id')
            ->where('er.event_id', (int) $event->id)
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

        $tz = (string) ($event->timezone ?: 'UTC');
        $startsLocal = null;
        $endsLocal = null;

        if (!empty($event->starts_at)) {
            $startsLocal = Carbon::parse($event->starts_at, 'UTC')->setTimezone($tz);
        }

        if (!empty($event->ends_at)) {
            $endsLocal = Carbon::parse($event->ends_at, 'UTC')->setTimezone($tz);
        }

        $freeCount = is_null($freeSeats) ? 0 : (int) $freeSeats;
        $activeCount = (int) $activeRegs;

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
                    // game context
                    'direction'          => $direction,
                    'gameSubtype'        => $gameSubtype,
                    'availablePositions' => $availablePositions,
                    'hasOrgNote'         => $hasOrgNote,
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

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'position' => ['nullable', 'string', 'max:255'],
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

        $existing = DB::table('event_registrations')
            ->where('event_id', (int) $event->id)
            ->where('user_id', $userId)
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

                $this->userNotificationService->createRegistrationCreatedNotification(
                    userId: $userId,
                    eventId: (int) $event->id,
                    occurrenceId: null,
                    eventTitle: (string) ($event->title ?? ('Мероприятие #' . $event->id))
                );

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

        DB::table('event_registrations')->insert($insert);

        if ($occurrenceId) {
            app(EventOccurrenceStatsService::class)->increment($occurrenceId);
        }

        $this->userNotificationService->createRegistrationCreatedNotification(
            userId: $userId,
            eventId: (int) $event->id,
            occurrenceId: $occurrenceId ?: null,
            eventTitle: (string) ($event->title ?? ('Мероприятие #' . $event->id))
        );

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

        $data = $request->validate([
            'position' => ['nullable', 'string', 'max:255'],
        ]);

        $row = DB::table('event_registrations')
            ->where('id', $registration)
            ->where('event_id', (int) $event->id)
            ->first(['id']);

        if (!$row) {
            return back()->with('error', 'Регистрация не найдена.');
        }

        DB::table('event_registrations')
            ->where('id', $registration)
            ->update([
                'position' => trim((string) ($data['position'] ?? '')),
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
            ->first(['id', 'user_id', 'group_key', 'cancelled_at', 'occurrence_id']);

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

        DB::table('event_registrations')
            ->where('id', $registration)
            ->update([
                'cancelled_at' => $isCancelled ? null : now(),
                'is_cancelled'  => $isCancelled ? false : true,
                'status'        => $isCancelled ? 'confirmed' : 'cancelled',
                'updated_at'    => now(),
            ]);

        $occId = $row->occurrence_id ?? null;
        if ($occId) {
            if ($isCancelled) {
                app(EventOccurrenceStatsService::class)->increment((int) $occId);
            } else {
                app(EventOccurrenceStatsService::class)->decrement((int) $occId);
            }
        }

        if ($isCancelled) {
            $this->userNotificationService->createRegistrationCreatedNotification(
                userId: (int) $row->user_id,
                eventId: (int) $event->id,
                occurrenceId: null,
                eventTitle: (string) ($event->title ?? ('Мероприятие #' . $event->id))
            );

            return back()->with('status', 'Восстановлено ✅');
        }

        $this->userNotificationService->createRegistrationCancelledByOrganizerNotification(
            userId: (int) $row->user_id,
            eventId: (int) $event->id,
            occurrenceId: $row->occurrence_id ?? null,
            eventTitle: (string) ($event->title ?? ('Мероприятие #' . $event->id)),
            cancelledByUserId: (int) $authUser->id
        );

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
            ->first(['id', 'user_id', 'group_key', 'occurrence_id', 'cancelled_at', 'is_cancelled', 'status']);

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
        }

        $this->userNotificationService->createRegistrationCancelledByOrganizerNotification(
            userId: (int) $row->user_id,
            eventId: (int) $event->id,
            occurrenceId: null,
            eventTitle: (string) ($event->title ?? ('Мероприятие #' . $event->id)),
            cancelledByUserId: (int) $authUser->id
        );

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

        $tz = (string) ($event->timezone ?: 'UTC');
        $startsLocal = null;
        $endsLocal   = null;
        if (!empty($event->starts_at)) {
            $startsLocal = Carbon::parse($event->starts_at, 'UTC')->setTimezone($tz);
        }
        if (!empty($event->ends_at)) {
            $endsLocal = Carbon::parse($event->ends_at, 'UTC')->setTimezone($tz);
        }

        $hasOrgNote = Schema::hasColumn('event_registrations', 'organizer_note');

        $occurrenceId = (int) $request->query('occurrence', 0);

        $regsQuery = DB::table('event_registrations as er')
            ->join('users as u', 'u.id', '=', 'er.user_id')
            ->where('er.event_id', (int) $event->id)
            ->whereNull('er.cancelled_at')
            ->where(function ($q) {
                $q->whereNull('er.is_cancelled')->orWhere('er.is_cancelled', false);
            })
            ->select([
                'er.id',
                'er.user_id',
                'er.position',
                $hasOrgNote ? 'er.organizer_note' : DB::raw("NULL::text as organizer_note"),
                'er.created_at',
                'u.name',
                'u.phone',
                'u.is_bot',
            ])
            ->orderBy('er.id');

        if ($occurrenceId) {
            $regsQuery->where('er.occurrence_id', $occurrenceId);
        }

        $registrations = $regsQuery->get();

        $location = null;
        if (!empty($event->location_id) && Schema::hasTable('locations')) {
            $location = DB::table('locations as l')
                ->leftJoin('cities as c', 'c.id', '=', 'l.city_id')
                ->where('l.id', (int) $event->location_id)
                ->first(['l.name', 'l.address', 'c.name as city_name']);
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('events.registrations.pdf', compact(
            'event', 'registrations', 'startsLocal', 'endsLocal', 'tz', 'location'
        ))->setPaper('a4', 'portrait');

        $filename = 'registrations-' . $event->id . '-' . now()->format('Ymd') . '.pdf';

        return $pdf->download($filename);
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