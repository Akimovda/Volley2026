<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Event;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Jobs\ExpandEventOccurrencesJob;


class EventManagementController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) return redirect()->route('login');

        $this->ensureCanCreateEvents($user);

        $tab = (string) $request->query('tab', 'mine');

        // ✅ чтобы старые ссылки /?tab=templates не ломались
        if ($tab === 'templates') {
            return redirect()->route('events.create.event_management', ['tab' => 'archive']);
        }

        $tab = in_array($tab, ['archive', 'mine'], true) ? $tab : 'mine';

        $role = (string) ($user->role ?? 'user');
        $organizerIdForStaff = $this->resolveOrganizerIdForCreator($user);

        // ✅ активные регистрации считаем через cancelled_at (у вас она есть)
        $regs = DB::table('event_registrations')
            ->select('event_id', DB::raw('COUNT(*)::int as active_regs'))
            ->groupBy('event_id');

        if (Schema::hasTable('event_registrations') && Schema::hasColumn('event_registrations', 'cancelled_at')) {
            $regs->whereNull('cancelled_at');
        } else {
            $regs->whereRaw('1=0');
        }

        $q = Event::query()
            ->with([
                'location:id,name,city,address',
                'organizer:id,name,email,role',
                'gameSettings:event_id,max_players',
            ])
            ->leftJoinSub($regs, 'ar', function ($join) {
                $join->on('events.id', '=', 'ar.event_id');
            })
            ->addSelect([
                'events.*',
                DB::raw('COALESCE(ar.active_regs, 0) as active_regs'),
            ])
            ->orderByDesc('events.id');

        // --- tabs
        if ($tab === 'archive') {
            $now = now();
            if (Schema::hasColumn('events', 'ends_at')) {
                $q->where(function ($w) use ($now) {
                    $w->whereNotNull('events.ends_at')->where('events.ends_at', '<', $now)
                        ->orWhere(function ($w2) use ($now) {
                            $w2->whereNull('events.ends_at')
                                ->whereNotNull('events.starts_at')
                                ->where('events.starts_at', '<', $now);
                        });
                });
            } else {
                $q->whereNotNull('events.starts_at')->where('events.starts_at', '<', $now);
            }
        }

        if ($tab === 'mine') {
            if ($role === 'admin') {
                // admin видит всё
            } elseif ($role === 'organizer') {
                $q->where('events.organizer_id', (int) $user->id);
            } elseif ($role === 'staff') {
                $q->where('events.organizer_id', (int) $organizerIdForStaff);
            } else {
                $q->whereRaw('1=0');
            }
        }

        $events = $q->paginate(20)->withQueryString();

        // удобное поле для блейда
        foreach ($events as $e) {
            $e->max_players = (int) ($e->gameSettings?->max_players ?? 0);
        }

        return view('events.event_management', [
            'tab' => $tab,
            'events' => $events,
        ]);
    }

    public function edit(Request $request, Event $event)
    {
        $user = $request->user();
        if (!$user) return redirect()->route('login');

        $this->ensureCanCreateEvents($user);

        $role = (string)($user->role ?? 'user');
        $organizerIdForStaff = $this->resolveOrganizerIdForCreator($user);

        if ($role === 'organizer' && (int)$event->organizer_id !== (int)$user->id) abort(403);
        if ($role === 'staff' && (int)$event->organizer_id !== (int)$organizerIdForStaff) abort(403);

        $event->load(['location', 'gameSettings']);

        $activeRegs = 0;
        if (Schema::hasTable('event_registrations')) {
            $r = DB::table('event_registrations')->where('event_id', (int)$event->id);
            if (Schema::hasColumn('event_registrations', 'cancelled_at')) $r->whereNull('cancelled_at');
            $activeRegs = (int)$r->count();
        }

        $locations = Location::query()
            ->with('media')
            ->orderBy('name')
            ->get();

        return view('events.event_management_edit', [
            'event' => $event,
            'activeRegs' => (int)$activeRegs,
            'locations' => $locations,
        ]);
    }

        public function update(Request $request, Event $event)
    {
        $user = $request->user();
        if (!$user) return redirect()->route('login');
        $this->ensureCanCreateEvents($user);
    
        $role = (string)($user->role ?? 'user');
        $organizerIdForStaff = $this->resolveOrganizerIdForCreator($user);
    
        if ($role === 'organizer' && (int)$event->organizer_id !== (int)$user->id) abort(403);
        if ($role === 'staff' && (int)$event->organizer_id !== (int)$organizerIdForStaff) abort(403);
    
        $data = $request->validate([
            'title' => ['required','string','max:255'],
            'starts_at' => ['required','date'],
            'ends_at' => ['nullable','date','after_or_equal:starts_at'],
            'timezone' => ['required','string','max:64'],
            'location_id' => ['required','integer'],
    
            // checkbox -> лучше в форме hidden=0, checkbox=1, тогда можно required|boolean
            'allow_registration' => ['sometimes','boolean'],
    
            // levels
            'classic_level_min' => ['nullable','integer','min:0','max:10'],
            'classic_level_max' => ['nullable','integer','min:0','max:10'],
            'beach_level_min'   => ['nullable','integer','min:0','max:10'],
            'beach_level_max'   => ['nullable','integer','min:0','max:10'],
    
            // offsets
            'reg_starts_days_before' => ['nullable','integer','min:0','max:365'],
            'reg_ends_minutes_before' => ['nullable','integer','min:0','max:10080'],
            'cancel_lock_minutes_before' => ['nullable','integer','min:0','max:10080'],
    
            // game settings (как в create)
            'game_max_players' => ['nullable','integer','min:0'],
            'game_min_players' => ['nullable','integer','min:0'],
            'game_subtype' => ['nullable','string','max:32'],
            'game_libero_mode' => ['nullable','string','max:32'],
    
            'game_gender_policy' => ['nullable','string','max:64'],
            'game_gender_limited_side' => ['nullable','string','max:16'],
            'game_gender_limited_max' => ['nullable','integer','min:0'],
            'game_gender_limited_positions' => ['nullable','array'],
            'game_gender_limited_positions.*' => ['string','max:32'],
    
            // legacy
            'game_allow_girls' => ['nullable','boolean'],
            'game_girls_max'   => ['nullable','integer','min:0'],
        ]);
    
        DB::transaction(function () use ($event, $data) {
    
            $tz = (string)$data['timezone'];
    
            // datetime-local => трактуем как локальное время события, сохраняем в UTC
            $startsUtc = Carbon::parse($data['starts_at'], $tz)->utc();
            $endsUtc = !empty($data['ends_at'])
                ? Carbon::parse($data['ends_at'], $tz)->utc()
                : null;
    
            $allowReg = (bool)($data['allow_registration'] ?? false);
    
            $daysBefore      = (int)($data['reg_starts_days_before'] ?? 3);
            $endsMinBefore   = (int)($data['reg_ends_minutes_before'] ?? 15);
            $cancelMinBefore = (int)($data['cancel_lock_minutes_before'] ?? 60);
    
            // --- 1) events
            $event->title = $data['title'];
            $event->timezone = $tz;
            $event->location_id = (int)$data['location_id'];
            $event->starts_at = $startsUtc;
            $event->ends_at   = $endsUtc;
            $event->allow_registration = $allowReg;
    
            $event->classic_level_min = $data['classic_level_min'] ?? null;
            $event->classic_level_max = $data['classic_level_max'] ?? null;
            $event->beach_level_min   = $data['beach_level_min'] ?? null;
            $event->beach_level_max   = $data['beach_level_max'] ?? null;
    
            if ($allowReg) {
                $event->registration_starts_at = $startsUtc->copy()->subDays($daysBefore);
                $event->registration_ends_at   = $startsUtc->copy()->subMinutes($endsMinBefore);
                $event->cancel_self_until      = $startsUtc->copy()->subMinutes($cancelMinBefore);
            } else {
                $event->registration_starts_at = null;
                $event->registration_ends_at   = null;
                $event->cancel_self_until      = null;
            }
    
            $event->save();
    
            // --- 2) game settings UPSERT (сначала!)
            $glp = $data['game_gender_limited_positions'] ?? null;
            if (is_array($glp)) {
                $glp = json_encode(array_values($glp), JSON_UNESCAPED_UNICODE);
            }
    
            $gsPayload = [
                'subtype' => $data['game_subtype'] ?? null,
                'libero_mode' => $data['game_libero_mode'] ?? null,
                'min_players' => $data['game_min_players'] ?? null,
                'max_players' => $data['game_max_players'] ?? null,
    
                'gender_policy' => $data['game_gender_policy'] ?? null,
                'gender_limited_side' => $data['game_gender_limited_side'] ?? null,
                'gender_limited_max' => $data['game_gender_limited_max'] ?? null,
                'gender_limited_positions' => $glp,
    
                'allow_girls' => array_key_exists('game_allow_girls', $data) ? (bool)$data['game_allow_girls'] : null,
                'girls_max' => $data['game_girls_max'] ?? null,
            ];
    
            $gsPayload = array_filter($gsPayload, static fn($v) => $v !== null);
    
            if (!empty($gsPayload)) {
                $event->gameSettings()->updateOrCreate(
                    ['event_id' => (int)$event->id],
                    $gsPayload
                );
            }
    
            // чтобы $event->gameSettings точно был свежий для max_players
            $event->load('gameSettings');
    
        });
        // ✅ после транзакции
        $event->refresh(); // на всякий случай
        if ((bool)$event->is_recurring && trim((string)$event->recurrence_rule) !== '') {
            ExpandEventOccurrencesJob::dispatch((int)$event->id, 90, 500);
        }
    
        return redirect()
            ->route('events.create.event_management', ['tab' => 'mine'])
            ->with('status', 'Мероприятие обновлено. Активные записи сохранены.');
    }

    private function ensureCanCreateEvents($user): void
    {
        if (!$user) abort(403);

        $role = (string) ($user->role ?? 'user');
        if (!in_array($role, ['admin', 'organizer', 'staff'], true)) {
            abort(403);
        }
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
