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

        $sort    = $request->query('sort', 'date');
        $dir     = $request->query('dir', 'asc') === 'desc' ? 'desc' : 'asc';
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

        $q = DB::table('event_occurrences as eo')
            ->join('events as e', 'e.id', '=', 'eo.event_id')
            ->leftJoin('locations as l', 'l.id', '=', 'e.location_id')
            // кол-во активных регистраций на конкретный occurrence (через event_id)
            ->leftJoinSub(
                DB::table('event_registrations')
                    ->select('event_id', DB::raw('COUNT(*)::int as active_regs'))
                    ->where(function ($w) {
                        $w->whereNull('is_cancelled')->orWhere('is_cancelled', false);
                    })
                    ->whereNull('cancelled_at')
                    ->groupBy('event_id'),
                'ar',
                'ar.event_id',
                '=',
                'e.id'
            )
            ->select([
                'eo.id as occurrence_id',
                'eo.starts_at',
                'eo.timezone',
                'e.id as event_id',
                'e.title',
                DB::raw('COALESCE(eo.max_players, 0) as max_players'),
                DB::raw('COALESCE(eo.allow_registration, e.allow_registration) as allow_registration'),
                'e.organizer_id',
                'l.name as loc_name',
                'l.address as loc_address',
                DB::raw('COALESCE(ar.active_regs, 0) as active_regs'),
            ])
            ->where(function ($w) {
                $w->whereNull('eo.is_cancelled')->orWhere('eo.is_cancelled', false);
            });

        if ($showPast) {
            // всё — без ограничения по дате
        } else {
            $q->where('eo.starts_at', '>=', $today);
        }

        // Доступ
        if ($role === 'admin') {
            // все события
        } elseif ($role === 'organizer') {
            $q->where('e.organizer_id', (int) $user->id);
        } elseif ($role === 'staff') {
            if ($organizerIdForStaff > 0) {
                // события своего организатора ИЛИ occurrences где этот staff — тренер
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
                // нет организатора — только occurrences где тренер
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

        // Сортировка
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
