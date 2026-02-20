<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EventRegistrationsManagementController extends Controller
{
    /**
     * GET /events/{event}/registrations
     */
    public function index(Request $request, Event $event)
    {
        $user = $request->user();
        if (!$user) return redirect()->route('login');

        $this->ensureCanCreateEvents($user);
        $this->ensureCanManageEvent($user, $event);

        // Единый источник истины для "отменено/активно"
        if (!Schema::hasColumn('event_registrations', 'cancelled_at')) {
            return back()->with('error', 'Нет колонки cancelled_at в event_registrations.');
        }

        $hasPosition = Schema::hasColumn('event_registrations', 'position');

        // max players
        $maxPlayers = (int) DB::table('event_game_settings')
            ->where('event_id', (int) $event->id)
            ->value('max_players');

        // active count — считаем в БД
        $activeRegs = (int) DB::table('event_registrations')
            ->where('event_id', (int) $event->id)
            ->whereNull('cancelled_at')
            ->count();

        $freeSeats = null;
        if ($maxPlayers > 0) {
            $freeSeats = max(0, $maxPlayers - $activeRegs);
        }

        // registrations (включая отменённые — для UI), сортировка: активные сверху
        $registrations = DB::table('event_registrations as er')
            ->join('users as u', 'u.id', '=', 'er.user_id')
            ->where('er.event_id', (int) $event->id)
            ->select([
                'er.id',
                'er.user_id',
                $hasPosition ? 'er.position' : DB::raw('NULL::text as position'),
                'er.cancelled_at',
                'er.created_at',
                'u.name',
                'u.email',
                // если у вас есть phone:
                // 'u.phone',
            ])
            ->orderByRaw('CASE WHEN er.cancelled_at IS NULL THEN 0 ELSE 1 END ASC')
            ->orderByDesc('er.id')
            ->get();

        // header: location
        $location = null;
        if (!empty($event->location_id) && Schema::hasTable('locations')) {
            $location = DB::table('locations')
                ->where('id', (int) $event->location_id)
                ->first(['id', 'name', 'city', 'address']);
        }

        // add player search (если q пустой — покажем последних 200 как “без JS”)
        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $users = User::query()
                ->select(['id', 'name', 'email'])
                ->where(function ($w) use ($q) {
                    $w->where('name', 'ilike', "%{$q}%")
                        ->orWhere('email', 'ilike', "%{$q}%");
                })
                ->orderBy('name')
                ->limit(30)
                ->get();
        } else {
            $users = User::query()
                ->select(['id', 'name', 'email'])
                ->orderByDesc('id')
                ->limit(200)
                ->get();
        }

        // ✅ переменные, которые ждёт твой blade
        $tz = (string) ($event->timezone ?: 'UTC');
        $startsLocal = null;
        $endsLocal = null;

        if (!empty($event->starts_at)) {
            $startsLocal = $event->starts_at ? Carbon::parse($event->starts_at, 'UTC')->setTimezone($tz) : null;
        }
        if (!empty($event->ends_at)) {
            $endsLocal   = $event->ends_at   ? Carbon::parse($event->ends_at,   'UTC')->setTimezone($tz) : null;
        }

        // blade ждёт freeCount/activeCount
        $freeCount = is_null($freeSeats) ? 0 : (int) $freeSeats;
        $activeCount = (int) $activeRegs;

        return view('events.registrations.index', [
            'event' => $event,
            'location' => $location,
            'registrations' => $registrations,

            'maxPlayers' => $maxPlayers,
            'activeRegs' => $activeRegs,
            'freeSeats' => $freeSeats,

            // ✅ алиасы под текущий blade
            'tz' => $tz,
            'startsLocal' => $startsLocal,
            'endsLocal' => $endsLocal,
            'freeCount' => $freeCount,
            'activeCount' => $activeCount,

            'hasPosition' => $hasPosition,
            'q' => $q,
            'users' => $users,
        ]);
    }

    /**
     * POST /events/{event}/registrations/add
     */
    public function addPlayer(Request $request, Event $event)
    {
        $user = $request->user();
        if (!$user) return redirect()->route('login');

        $this->ensureCanCreateEvents($user);
        $this->ensureCanManageEvent($user, $event);

        if (!Schema::hasColumn('event_registrations', 'cancelled_at')) {
            return back()->with('error', 'Нет колонки cancelled_at в event_registrations.');
        }

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'position' => ['nullable', 'string', 'max:255'],
        ]);

        $userId = (int) $data['user_id'];
        $pos = trim((string) ($data['position'] ?? ''));

        // Если запись уже есть:
        // - если отменена -> восстанавливаем
        // - если активна -> ошибка
        $existing = DB::table('event_registrations')
            ->where('event_id', (int) $event->id)
            ->where('user_id', $userId)
            ->first(['id', 'cancelled_at']);

        if ($existing) {
            if (!empty($existing->cancelled_at)) {
                $upd = ['cancelled_at' => null, 'updated_at' => now()];
                if (Schema::hasColumn('event_registrations', 'position')) {
                    $upd['position'] = $pos;
                }
                DB::table('event_registrations')->where('id', (int) $existing->id)->update($upd);

                return back()->with('status', 'Игрок восстановлен ✅');
            }

            return back()->with('error', 'Этот игрок уже зарегистрирован на мероприятие.');
        }

        $insert = [
            'event_id' => (int) $event->id,
            'user_id' => $userId,
            'cancelled_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('event_registrations', 'position')) {
            $insert['position'] = $pos;
        }

        DB::table('event_registrations')->insert($insert);

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

        if (!$row) return back()->with('error', 'Регистрация не найдена.');

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
     * ✅ toggle:
     * - если активна -> отменяем (cancelled_at = now)
     * - если отменена -> восстанавливаем (cancelled_at = null)
     */
    public function cancel(Request $request, Event $event, int $registration)
    {
        $user = $request->user();
        if (!$user) return redirect()->route('login');

        $this->ensureCanCreateEvents($user);
        $this->ensureCanManageEvent($user, $event);

        if (!Schema::hasColumn('event_registrations', 'cancelled_at')) {
            return back()->with('error', 'Нет колонки cancelled_at в event_registrations.');
        }

        $row = DB::table('event_registrations')
            ->where('id', $registration)
            ->where('event_id', (int) $event->id)
            ->first(['id', 'cancelled_at']);

        if (!$row) return back()->with('error', 'Регистрация не найдена.');

        $isCancelled = !empty($row->cancelled_at);

        DB::table('event_registrations')
            ->where('id', $registration)
            ->update([
                'cancelled_at' => $isCancelled ? null : now(),
                'updated_at' => now(),
            ]);

        return back()->with('status', $isCancelled ? 'Восстановлено ✅' : 'Бронь отменена ✅');
    }

    /**
     * DELETE /events/{event}/registrations/{registration}
     */
    public function destroy(Request $request, Event $event, int $registration)
    {
        $user = $request->user();
        if (!$user) return redirect()->route('login');

        $this->ensureCanCreateEvents($user);
        $this->ensureCanManageEvent($user, $event);

        $deleted = DB::table('event_registrations')
            ->where('id', $registration)
            ->where('event_id', (int) $event->id)
            ->delete();

        if (!$deleted) return back()->with('error', 'Регистрация не найдена.');

        return back()->with('status', 'Регистрация удалена ✅');
    }

    // ----------------- access control helpers -----------------

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
