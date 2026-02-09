<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EventManagementController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) return redirect()->route('login');
        $this->ensureCanCreateEvents($user);

        // ✅ оставляем только archive|mine
        $tab = (string) $request->query('tab', 'mine');

        // ✅ чтобы старые ссылки /?tab=templates не ломались
        if ($tab === 'templates') {
            return redirect()->route('events.create.event_management', ['tab' => 'archive']);
        }

        $tab = in_array($tab, ['archive', 'mine'], true) ? $tab : 'mine';

        $role = (string) ($user->role ?? 'user');
        $organizerIdForStaff = $this->resolveOrganizerIdForCreator($user); // staff -> organizer_id

        // ✅ активные регистрации считаем через cancelled_at (у вас она есть)
        // если вдруг таблицы нет (на всякий) — считаем 0
        $regs = DB::table('event_registrations')
            ->select('event_id', DB::raw('COUNT(*)::int as active_regs'))
            ->groupBy('event_id');

        if (Schema::hasTable('event_registrations') && Schema::hasColumn('event_registrations', 'cancelled_at')) {
            $regs->whereNull('cancelled_at');
        } else {
            // если cancelled_at почему-то нет — не ломаем страницу
            // но лучше так не жить :)
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
                // admin видит всё в "Мои" (оставляем как было)
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
