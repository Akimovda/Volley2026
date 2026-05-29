<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class EventRegistrationsOverviewController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) return redirect()->route('login');

        $role = (string) ($user->role ?? 'user');
        if (!in_array($role, ['admin', 'organizer', 'staff'], true)) {
            abort(403);
        }

        $sort     = $request->query('sort', 'date');
        $dir      = $request->query('dir', 'asc') === 'desc' ? 'desc' : 'asc';
        $showPast = (bool) $request->query('past', 0);

        $sort = in_array($sort, ['date', 'title', 'address'], true) ? $sort : 'date';

        $organizerIdForStaff = 0;
        if ($role === 'staff') {
            $row = DB::table('organizer_staff')
                ->where('staff_user_id', (int) $user->id)
                ->orderBy('id')
                ->first(['organizer_id']);
            $organizerIdForStaff = $row ? (int) $row->organizer_id : 0;
        }

        $today = Carbon::now('UTC')->startOfDay();

        // Регистрации для обычных событий (по occurrence_id)
        $regsSub = DB::table('event_registrations')
            ->select('occurrence_id', DB::raw('COUNT(*)::int as active_regs'))
            ->where(function ($w) {
                $w->whereNull('is_cancelled')->orWhere('is_cancelled', false);
            })
            ->whereNull('cancelled_at')
            ->whereNotNull('occurrence_id')
            ->groupBy('occurrence_id');

        // Подтверждённые команды для турниров (approved/confirmed)
        $teamsSub = DB::table('event_teams')
            ->select('occurrence_id', DB::raw('COUNT(*)::int as active_teams'))
            ->whereIn('status', ['approved', 'confirmed'])
            ->whereNotNull('occurrence_id')
            ->groupBy('occurrence_id');

        // Команды в листе ожидания турнира (submitted)
        $teamsWaitlistSub = DB::table('event_teams')
            ->select('occurrence_id', DB::raw('COUNT(*)::int as waitlist_teams'))
            ->where('status', 'submitted')
            ->whereNotNull('occurrence_id')
            ->groupBy('occurrence_id');

        // Резерв: event_role_slots.role='reserve' → max_slots; fallback из game_settings
        $reserveSlotsSub = DB::table('event_role_slots')
            ->select('event_id', DB::raw('max_slots as reserve_slots'))
            ->where('role', 'reserve');

        $q = DB::table('event_occurrences as eo')
            ->join('events as e', 'e.id', '=', 'eo.event_id')
            ->leftJoin('locations as l', 'l.id', '=', 'e.location_id')
            ->leftJoin('event_game_settings as egs', 'egs.event_id', '=', 'e.id')
            ->leftJoinSub($regsSub, 'ar', 'ar.occurrence_id', '=', 'eo.id')
            ->leftJoinSub($teamsSub, 'at', 'at.occurrence_id', '=', 'eo.id')
            ->leftJoinSub($teamsWaitlistSub, 'atw', 'atw.occurrence_id', '=', 'eo.id')
            ->leftJoinSub($reserveSlotsSub, 'ers', 'ers.event_id', '=', 'e.id')
            ->select([
                'eo.id as occurrence_id',
                'eo.starts_at',
                'eo.timezone',
                'e.id as event_id',
                'e.title',
                'e.format',
                'e.tournament_teams_count',
                DB::raw('COALESCE(eo.max_players, 0) as max_players'),
                DB::raw('COALESCE(ers.reserve_slots, egs.reserve_players_max, 0) as reserve_max'),
                DB::raw('COALESCE(eo.allow_registration, e.allow_registration) as allow_registration'),
                'e.organizer_id',
                'l.name as loc_name',
                'l.address as loc_address',
                DB::raw('COALESCE(ar.active_regs, 0) as active_regs'),
                DB::raw('COALESCE(at.active_teams, 0) as active_teams'),
                DB::raw('COALESCE(atw.waitlist_teams, 0) as waitlist_teams'),
            ])
            ->where(function ($w) {
                $w->whereNull('eo.is_cancelled')->orWhere('eo.is_cancelled', false);
            });

        if (!$showPast) {
            $q->where('eo.starts_at', '>=', $today);
        }

        // Доступ
        if ($role === 'admin') {
            // все события
        } elseif ($role === 'organizer') {
            $q->where('e.organizer_id', (int) $user->id);
        } elseif ($role === 'staff') {
            if ($organizerIdForStaff > 0) {
                $q->where(function ($w) use ($user, $organizerIdForStaff) {
                    $w->where('e.organizer_id', $organizerIdForStaff)
                        ->orWhereExists(function ($sub) use ($user) {
                            $sub->select(DB::raw(1))
                                ->from('event_occurrence_trainers as eot')
                                ->whereColumn('eot.occurrence_id', 'eo.id')
                                ->where('eot.user_id', (int) $user->id);
                        });
                });
            } else {
                $q->whereExists(function ($sub) use ($user) {
                    $sub->select(DB::raw(1))
                        ->from('event_occurrence_trainers as eot')
                        ->whereColumn('eot.occurrence_id', 'eo.id')
                        ->where('eot.user_id', (int) $user->id);
                });
            }
        } else {
            $q->whereRaw('1=0');
        }

        match ($sort) {
            'title'   => $q->orderBy('e.title', $dir)->orderBy('eo.starts_at', 'asc'),
            'address' => $q->orderByRaw("COALESCE(l.address, l.name, '') {$dir}")->orderBy('eo.starts_at', 'asc'),
            default   => $q->orderBy('eo.starts_at', $dir)->orderBy('e.id', 'asc'),
        };

        $occurrences = $q->paginate(25)->withQueryString();

        return view('events.registrations.overview', compact(
            'occurrences',
            'sort',
            'dir',
            'showPast',
            'role',
        ));
    }
}
