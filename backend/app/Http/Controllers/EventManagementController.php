<?php

namespace App\Http\Controllers;

use App\Jobs\ExpandEventOccurrencesJob;
use App\Models\Event;
use App\Models\User;
use App\Models\EventOccurrence;
use App\Models\Location;
use App\Services\UserNotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EventManagementController extends Controller
{
    public function __construct(
        private UserNotificationService $userNotificationService
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        $this->ensureCanCreateEvents($user);

        $tab = (string) $request->query('tab', 'mine');

        if ($tab === 'templates') {
            return redirect()->route('events.create.event_management', ['tab' => 'archive']);
        }

        $tab = in_array($tab, ['archive', 'mine'], true) ? $tab : 'mine';

        $role = (string) ($user->role ?? 'user');
        $organizerIdForStaff = $this->resolveOrganizerIdForCreator($user);
        $nowUtc = Carbon::now('UTC');

        $organizerFilter = 0;
        if ($role === 'admin') {
            $organizerFilter = max(0, (int) $request->query('organizer_id', 0));
        }

        $regsSub = null;
        if (Schema::hasTable('event_registrations')) {
            $regsSub = DB::table('event_registrations')
            ->select('event_id', DB::raw('COUNT(*)::int as active_regs'));
        
        if (Schema::hasColumn('event_registrations', 'cancelled_at')) {
            $regsSub->whereNull('cancelled_at');
        }
        
        if (Schema::hasColumn('event_registrations', 'is_cancelled')) {
            $regsSub->where(function ($w) {
                $w->whereNull('is_cancelled')->orWhere('is_cancelled', false);
            });
        }
        
        if (Schema::hasColumn('event_registrations', 'status')) {
            $regsSub->where(function ($w) {
                $w->whereNull('status')->orWhere('status', 'confirmed');
            });
        }

            $regsSub->groupBy('event_id');
        }

        $nextOccSub = null;
        if (Schema::hasTable('event_occurrences')) {
            $nextOccSub = DB::table('event_occurrences as eo')
                ->select('eo.event_id', DB::raw('MIN(eo.starts_at) as next_occurrence_starts_at'))
                ->where('eo.starts_at', '>', $nowUtc);

            if (Schema::hasColumn('event_occurrences', 'cancelled_at')) {
                $nextOccSub->whereNull('eo.cancelled_at');
            }
            if (Schema::hasColumn('event_occurrences', 'is_cancelled')) {
                $nextOccSub->where(function ($w) {
                    $w->whereNull('eo.is_cancelled')->orWhere('eo.is_cancelled', false);
                });
            }

            $nextOccSub->groupBy('eo.event_id');
        }

        $q = Event::query()->with([
            'location' => function ($q) {
                $q->select('id', 'name', 'address', 'city_id')
                    ->with(['city:id,name,region']);
            },
            'organizer:id,name,first_name,last_name,role',
            'gameSettings:event_id,max_players',
        ]);

        if ($regsSub) {
            $q->leftJoinSub($regsSub, 'ar', function ($join) {
                $join->on('events.id', '=', 'ar.event_id');
            });
        }

        if ($nextOccSub) {
            $q->leftJoinSub($nextOccSub, 'no', function ($join) {
                $join->on('events.id', '=', 'no.event_id');
            });
        }

        $q->addSelect([
            'events.*',
            DB::raw($regsSub ? 'COALESCE(ar.active_regs, 0) as active_regs' : '0 as active_regs'),
            DB::raw($nextOccSub ? 'no.next_occurrence_starts_at as next_occurrence_starts_at' : 'NULL as next_occurrence_starts_at'),
        ]);

        if ($role === 'admin') {
            if ($organizerFilter > 0) {
                $q->where('events.organizer_id', $organizerFilter);
            }
        } elseif ($role === 'organizer') {
            $q->where('events.organizer_id', (int) $user->id);
        } elseif ($role === 'staff') {
            $q->where('events.organizer_id', (int) $organizerIdForStaff);
        } else {
            $q->whereRaw('1=0');
        }

        if ($tab === 'mine') {
            if ($nextOccSub) {
                $q->whereNotNull('no.next_occurrence_starts_at');
                $q->orderBy('no.next_occurrence_starts_at', 'asc')
                    ->orderBy('events.id', 'desc');
            } else {
                $q->whereRaw('1=0');
            }
        } else {
            if ($nextOccSub) {
                $q->whereNull('no.next_occurrence_starts_at');
            }
            $q->orderByDesc('events.id');
        }

        $events = $q->paginate(20)->withQueryString();

        foreach ($events as $e) {
            $e->max_players = (int) ($e->gameSettings?->max_players ?? 0);
        }



$organizers = [];

if ($role === 'admin') {
    $organizers = User::query()
        ->whereIn('id', function ($q) {
            $q->select('organizer_id')
              ->from('events')
              ->whereNotNull('organizer_id');
        })
        ->orderBy('first_name')
        ->orderBy('last_name')
        ->get();
}

        return view('events.event_management', [
            'tab' => $tab,
            'events' => $events,
            'organizers' => $organizers,
            'organizerFilter' => $organizerFilter,
        ]);
    }
   
    public function edit(Request $request, Event $event)
    {
        $user = $request->user();
        if (!$user) return redirect()->route('login');

        $this->ensureCanCreateEvents($user);

        $role = (string) ($user->role ?? 'user');
        $organizerIdForStaff = $this->resolveOrganizerIdForCreator($user);

        if ($role === 'organizer' && (int) $event->organizer_id !== (int) $user->id) abort(403);
        if ($role === 'staff' && (int) $event->organizer_id !== (int) $organizerIdForStaff) abort(403);

        $event->load(['location', 'gameSettings']);

        $activeRegs = 0;
        if (Schema::hasTable('event_registrations')) {
            $r = DB::table('event_registrations')->where('event_id', (int) $event->id);

        if (Schema::hasColumn('event_registrations', 'cancelled_at')) {
            $r->whereNull('cancelled_at');
        }
        
        if (Schema::hasColumn('event_registrations', 'is_cancelled')) {
            $r->where(function ($w) {
                $w->whereNull('is_cancelled')->orWhere('is_cancelled', false);
            });
        }
        
        if (Schema::hasColumn('event_registrations', 'status')) {
            $r->where(function ($w) {
                $w->whereNull('status')->orWhere('status', 'confirmed');
            });
        }
            $activeRegs = (int) $r->count();
        }

        $locations = Location::query()
            ->with('media')
            ->orderBy('name')
            ->get();

        return view('events.event_management_edit', [
            'event' => $event,
            'activeRegs' => (int) $activeRegs,
            'locations' => $locations,
        ]);
    }
    public function occurrences(\App\Models\Event $event)
    {
        $event->load([
            'location.city',
            'organizer',
        ]);
    
        $occurrences = $event->occurrences()
            ->withCount([
                'registrations as active_regs' => fn ($q) => $q->whereNull('cancelled_at'),
            ])
            ->orderBy('starts_at')
            ->get();
    
        return view('events.event_management_occurrences', [
            'event' => $event,
            'occurrences' => $occurrences,
        ]);
    }
    public function destroyOccurrence(\App\Models\EventOccurrence $occurrence, \Illuminate\Http\Request $request)
    {
        $deleteMode = (string)$request->input('delete_mode', 'single');
    
        if ($deleteMode === 'force') {
            $occurrence->delete();
            return back()->with('status', 'Повтор удалён навсегда.');
        }
    
        $occurrence->cancelled_at = now();
        $occurrence->save();
    
        return back()->with('status', 'Повтор отменён.');
    }

    public function update(Request $request, Event $event)
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }
    
        $this->ensureCanCreateEvents($user);
    
        $role = (string) ($user->role ?? 'user');
        $organizerIdForStaff = $this->resolveOrganizerIdForCreator($user);
    
        if ($role === 'organizer' && (int) $event->organizer_id !== (int) $user->id) {
            abort(403);
        }
    
        if ($role === 'staff' && (int) $event->organizer_id !== (int) $organizerIdForStaff) {
            abort(403);
        }
    
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'timezone' => ['required', 'string', 'max:64'],
            'location_id' => ['required', 'integer'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'allow_registration' => ['sometimes', 'boolean'],
            'classic_level_min' => ['nullable', 'integer', 'min:0', 'max:10'],
            'classic_level_max' => ['nullable', 'integer', 'min:0', 'max:10'],
            'beach_level_min'   => ['nullable', 'integer', 'min:0', 'max:10'],
            'beach_level_max'   => ['nullable', 'integer', 'min:0', 'max:10'],
            'reg_starts_days_before' => ['nullable', 'integer', 'min:0', 'max:365'],
            'reg_ends_minutes_before' => ['nullable', 'integer', 'min:0', 'max:10080'],
            'cancel_lock_minutes_before' => ['nullable', 'integer', 'min:0', 'max:10080'],
            'game_max_players' => ['nullable', 'integer', 'min:0'],
            'game_min_players' => ['nullable', 'integer', 'min:0'],
            'game_subtype' => ['nullable', 'string', 'max:32'],
            'game_libero_mode' => ['nullable', 'string', 'max:32'],
            'game_gender_policy' => ['nullable', 'string', 'max:64'],
            'game_gender_limited_side' => ['nullable', 'string', 'max:16'],
            'game_gender_limited_max' => ['nullable', 'integer', 'min:0'],
            'game_gender_limited_positions' => ['nullable', 'array'],
            'game_gender_limited_positions.*' => ['string', 'max:32'],
            'game_allow_girls' => ['nullable', 'boolean'],
            'game_girls_max'   => ['nullable', 'integer', 'min:0'],
            'bot_assistant_enabled'      => ['sometimes', 'boolean'],
            'bot_assistant_threshold'    => ['sometimes', 'integer', 'min:5', 'max:30'],
            'bot_assistant_max_fill_pct' => ['sometimes', 'integer', 'min:10', 'max:60'],
        ]);
    
        DB::transaction(function () use ($event, $data) {
            $tz = (string) $data['timezone'];
            $startsUtc = Carbon::parse($data['starts_at'], $tz)->utc();
            $durationSec = !empty($data['duration_minutes'])
                ? ((int) $data['duration_minutes'] * 60)
                : null;
    
            $allowReg = (bool) ($data['allow_registration'] ?? false);
            $daysBefore = (int) ($data['reg_starts_days_before'] ?? 3);
            $endsMinBefore = (int) ($data['reg_ends_minutes_before'] ?? 15);
            $cancelMinBefore = (int) ($data['cancel_lock_minutes_before'] ?? 60);
    
            $event->title = $data['title'];
            $event->timezone = $tz;
            $event->location_id = (int) $data['location_id'];
            $event->starts_at = $startsUtc;
            $event->duration_sec = $durationSec;
            $event->allow_registration = $allowReg;
            $event->classic_level_min = $data['classic_level_min'] ?? null;
            $event->classic_level_max = $data['classic_level_max'] ?? null;
            $event->beach_level_min = $data['beach_level_min'] ?? null;
            $event->beach_level_max = $data['beach_level_max'] ?? null;
            $event->bot_assistant_enabled      = (bool) ($data['bot_assistant_enabled'] ?? false);
            $event->bot_assistant_threshold    = max(5, min(30, (int) ($data['bot_assistant_threshold'] ?? 10)));
            $event->bot_assistant_max_fill_pct = max(10, min(60, (int) ($data['bot_assistant_max_fill_pct'] ?? 40)));
            
            if ($allowReg) {
                $event->registration_starts_at = $startsUtc->copy()->subDays($daysBefore);
                $event->registration_ends_at = $startsUtc->copy()->subMinutes($endsMinBefore);
                $event->cancel_self_until = $startsUtc->copy()->subMinutes($cancelMinBefore);
            } else {
                $event->registration_starts_at = null;
                $event->registration_ends_at = null;
                $event->cancel_self_until = null;
            }
    
            $event->save();
    
            $glp = $data['game_gender_limited_positions'] ?? null;
            if (is_array($glp)) {
                $glp = json_encode(array_values($glp), JSON_UNESCAPED_UNICODE);
            }
    
            $gsPayload = array_filter([
                'subtype' => $data['game_subtype'] ?? null,
                'libero_mode' => $data['game_libero_mode'] ?? null,
                'min_players' => $data['game_min_players'] ?? null,
                'max_players' => $data['game_max_players'] ?? null,
                'gender_policy' => $data['game_gender_policy'] ?? null,
                'gender_limited_side' => $data['game_gender_limited_side'] ?? null,
                'gender_limited_max' => $data['game_gender_limited_max'] ?? null,
                'gender_limited_positions' => $glp,
                'allow_girls' => array_key_exists('game_allow_girls', $data)
                    ? (bool) $data['game_allow_girls']
                    : null,
                'girls_max' => $data['game_girls_max'] ?? null,
            ], static fn ($v) => $v !== null);
    
            if (!isset($gsPayload['subtype']) && !$event->gameSettings) {
                $dir = (string) ($event->direction ?? 'classic');
                $gsPayload['subtype'] = ($dir === 'beach') ? '2x2' : '4x2';
            }
    
            if (!empty($gsPayload)) {
                $event->gameSettings()->updateOrCreate(
                    ['event_id' => (int) $event->id],
                    $gsPayload
                );
            }
    
            $event->load('gameSettings');
    
            if (Schema::hasTable('event_occurrences') && $event->starts_at) {
                $startUtc = Carbon::parse($event->starts_at, 'UTC');
                $uniq = "event:{$event->id}:{$startUtc->format('YmdHis')}";
                $nowUtc = Carbon::now('UTC');
    
                if (!(bool) ($event->is_recurring ?? false)) {
                    $cleanupQ = DB::table('event_occurrences')
                        ->where('event_id', (int) $event->id)
                        ->where('uniq_key', '!=', $uniq);
    
                    if (Schema::hasColumn('event_occurrences', 'starts_at')) {
                        $cleanupQ->where('starts_at', '>=', $nowUtc);
                    }
    
                    $payload = [
                        'updated_at' => $nowUtc,
                    ];
    
                    if (Schema::hasColumn('event_occurrences', 'cancelled_at')) {
                        $payload['cancelled_at'] = $nowUtc;
                    }
    
                    if (Schema::hasColumn('event_occurrences', 'is_cancelled')) {
                        $payload['is_cancelled'] = true;
                    }
    
                    $cleanupQ->update($payload);
                }
    
                EventOccurrence::query()->updateOrCreate(
                    ['uniq_key' => $uniq],
                    [
                        'event_id' => (int) $event->id,
                        'starts_at' => $startUtc,
                        'duration_sec' => $event->duration_sec,
                        'timezone' => $event->timezone ?: 'UTC',
                        'location_id' => $event->location_id ?? null,
                        'allow_registration' => $event->allow_registration ?? null,
                        'max_players' => $event->gameSettings?->max_players ?? null,
                        'registration_starts_at' => $event->registration_starts_at ?? null,
                        'registration_ends_at' => $event->registration_ends_at ?? null,
                        'cancel_self_until' => $event->cancel_self_until ?? null,
                        'age_policy' => $event->age_policy ?? null,
                        'is_snow' => $event->is_snow ?? null,
                    ]
                );
            }
        });
    
        $event->refresh();
    
        if ((bool) $event->is_recurring && trim((string) $event->recurrence_rule) !== '') {
            ExpandEventOccurrencesJob::dispatch((int) $event->id, 90, 500);
        }
    
        return redirect()
            ->route('events.create.event_management', ['tab' => 'mine'])
            ->with('status', 'Мероприятие обновлено. Активные записи сохранены.');
    }
    private function safeForceDeleteEvent(Event $event): void
    {
        DB::transaction(function () use ($event) {
            $eventId = (int) $event->id;
    
            $occurrenceIds = [];
            if (Schema::hasTable('event_occurrences')) {
                $occurrenceIds = DB::table('event_occurrences')
                    ->where('event_id', $eventId)
                    ->pluck('id')
                    ->map(fn ($v) => (int) $v)
                    ->all();
            }
    
            if (Schema::hasTable('notification_deliveries')) {
                if (Schema::hasColumn('notification_deliveries', 'event_id')) {
                    DB::table('notification_deliveries')
                        ->where('event_id', $eventId)
                        ->delete();
                }
    
                if (!empty($occurrenceIds) && Schema::hasColumn('notification_deliveries', 'occurrence_id')) {
                    DB::table('notification_deliveries')
                        ->whereIn('occurrence_id', $occurrenceIds)
                        ->delete();
                }
            }
    
            if (Schema::hasTable('event_trainers') && Schema::hasColumn('event_trainers', 'event_id')) {
                DB::table('event_trainers')
                    ->where('event_id', $eventId)
                    ->delete();
            }
    
            $event->delete();
        });
    }
    public function destroy(Request $request, Event $event)
    {
        $user = $request->user();
        if (!$user) return redirect()->route('login');
    
        $this->ensureCanCreateEvents($user);
    
        $role = (string) ($user->role ?? 'user');
        $organizerIdForStaff = $this->resolveOrganizerIdForCreator($user);
    
        if ($role === 'organizer' && (int) $event->organizer_id !== (int) $user->id) {
            abort(403);
        }
    
        if ($role === 'staff' && (int) $event->organizer_id !== (int) $organizerIdForStaff) {
            abort(403);
        }
    
        $mode = (string) $request->input('delete_mode', 'cancel');
    
        if ($mode === 'force') {
            $isPrivileged = in_array($role, ['admin'], true);
    
            if (!$isPrivileged) {
                return back()->with('error', 'Полное удаление доступно только администратору.');
            }
    
            $this->safeForceDeleteEvent($event);
    
            return back()->with('status', 'Мероприятие удалено навсегда.');
        }
    
        $now = CarbonImmutable::now('UTC');
    
        $isRootRecurring =
            (bool) ($event->is_recurring ?? false)
            && trim((string) ($event->recurrence_rule ?? '')) !== '';
    
        $buildCancelPayload = function () use ($now) {
            $payload = [
                'cancelled_at' => $now,
                'updated_at' => $now,
            ];
    
            if (Schema::hasColumn('event_occurrences', 'is_cancelled')) {
                $payload['is_cancelled'] = true;
            }
    
            return $payload;
        };
    
        // cancel series
        if ($mode === 'series') {
            if (!$isRootRecurring) {
                return back()->with('error', 'Удаление цепочки доступно только для повторяющегося (корневого) мероприятия.');
            }
    
            $cancelledOccurrenceIds = [];
    
            DB::transaction(function () use ($event, $now, $buildCancelPayload, &$cancelledOccurrenceIds) {
                $event->is_recurring = false;
                $event->recurrence_rule = null;
                $event->save();
    
                if (Schema::hasTable('event_occurrences')) {
                    $q = DB::table('event_occurrences')
                        ->where('event_id', (int) $event->id)
                        ->whereNotNull('starts_at')
                        ->where('starts_at', '>', $now);
    
                    if (Schema::hasColumn('event_occurrences', 'cancelled_at')) {
                        $q->whereNull('cancelled_at');
                    }
    
                    if (Schema::hasColumn('event_occurrences', 'is_cancelled')) {
                        $q->where(function ($w) {
                            $w->whereNull('is_cancelled')->orWhere('is_cancelled', false);
                        });
                    }
    
                    $cancelledOccurrenceIds = $q->pluck('id')
                        ->map(fn ($v) => (int) $v)
                        ->all();
    
                    if (!empty($cancelledOccurrenceIds)) {
                        DB::table('event_occurrences')
                            ->whereIn('id', $cancelledOccurrenceIds)
                            ->update($buildCancelPayload());
                    }
                }
            });
    
            foreach ($cancelledOccurrenceIds as $occurrenceId) {
                $this->notifyUsersAboutCancelledEvent(
                    event: $event,
                    occurrenceId: (int) $occurrenceId,
                    reason: 'Отменено организатором'
                );
            }
    
            return back()->with('status', 'Цепочка удалена: повторение выключено, будущие occurrences отменены, история сохранена.');
        }
    
        // default = cancel one future date
        $cancelledOccurrenceIds = [];
    
        DB::transaction(function () use ($event, $now, $buildCancelPayload, &$cancelledOccurrenceIds, $isRootRecurring) {
            if (!Schema::hasTable('event_occurrences')) {
                return;
            }
    
            $q = DB::table('event_occurrences')
                ->where('event_id', (int) $event->id)
                ->whereNotNull('starts_at')
                ->where('starts_at', '>', $now);
    
            if (Schema::hasColumn('event_occurrences', 'cancelled_at')) {
                $q->whereNull('cancelled_at');
            }
    
            if (Schema::hasColumn('event_occurrences', 'is_cancelled')) {
                $q->where(function ($w) {
                    $w->whereNull('is_cancelled')->orWhere('is_cancelled', false);
                });
            }
    
            if ($isRootRecurring && $event->starts_at) {
                $targetRows = (clone $q)
                    ->where('starts_at', '=', $event->starts_at)
                    ->pluck('id')
                    ->map(fn ($v) => (int) $v)
                    ->all();
    
                if (!empty($targetRows)) {
                    $cancelledOccurrenceIds = $targetRows;
                }
            }
    
            if (empty($cancelledOccurrenceIds)) {
                $row = $q->orderBy('starts_at')->first(['id']);
                if ($row) {
                    $cancelledOccurrenceIds = [(int) $row->id];
                }
            }
    
            if (!empty($cancelledOccurrenceIds)) {
                DB::table('event_occurrences')
                    ->whereIn('id', $cancelledOccurrenceIds)
                    ->update($buildCancelPayload());
            }
        });
    
        if (empty($cancelledOccurrenceIds)) {
            return back()->with('error', 'Нет будущих дат для отмены. История прошедших мероприятий сохраняется.');
        }
    
        foreach ($cancelledOccurrenceIds as $occurrenceId) {
            $this->notifyUsersAboutCancelledEvent(
                event: $event,
                occurrenceId: (int) $occurrenceId,
                reason: 'Отменено организатором'
            );
        }
    
        return back()->with('status', 'Мероприятие отменено: дата скрыта из UI, история сохранена.');
    }

  public function bulkDelete(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }
    
        $this->ensureCanCreateEvents($user);
    
        $tab = (string) $request->query('tab', 'mine');
        $tab = in_array($tab, ['archive', 'mine'], true) ? $tab : 'mine';
    
        $role = (string) ($user->role ?? 'user');
        $organizerIdForStaff = $this->resolveOrganizerIdForCreator($user);
    
        $deleteMode = (string) $request->input('delete_mode', 'cancel');
        $deleteMode = in_array($deleteMode, ['cancel', 'force'], true) ? $deleteMode : 'cancel';
    
        if ($deleteMode === 'force' && $role !== 'admin') {
            return back()->with('error', 'Полное удаление доступно только администратору.');
        }
    
        $ids = $request->input('ids', []);
        if (!is_array($ids)) {
            $ids = [];
        }
    
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $ids = array_values(array_filter($ids, fn ($v) => $v > 0));
    
        if (empty($ids)) {
            return back()->with('error', 'Ничего не выбрано для удаления.');
        }
    
        $q = Event::query()->whereIn('id', $ids);
    
        if ($role === 'admin') {
            // admin может любые
        } elseif ($role === 'organizer') {
            $q->where('organizer_id', (int) $user->id);
        } elseif ($role === 'staff') {
            $q->where('organizer_id', (int) $organizerIdForStaff);
        } else {
            $q->whereRaw('1=0');
        }
    
        $events = $q->get();
    
        $affected = 0;
        $cancelledFuture = 0;
        $deletedHard = 0;
        $skippedForbidden = count($ids) - $events->count();
        $skippedNothingToCancel = 0;
        $notificationsToSend = [];
    
        /*
        |--------------------------------------------------------------------------
        | FORCE DELETE (admin only)
        |--------------------------------------------------------------------------
        */
        if ($deleteMode === 'force') {
            foreach ($events as $event) {
                $this->safeForceDeleteEvent($event);
                $deletedHard++;
                $affected++;
            }
    
            $msg = "Bulk force delete: затронуто {$affected}.";
            if ($deletedHard > 0) {
                $msg .= " Удалено навсегда: {$deletedHard}.";
            }
            if ($skippedForbidden > 0) {
                $msg .= " Недоступно по правам/не найдено: {$skippedForbidden}.";
            }
    
            return redirect()
                ->route('events.create.event_management', ['tab' => $tab])
                ->with('status', $msg)
                ->with('bulk_affected', $affected)
                ->with('bulk_cancelled_future', $cancelledFuture)
                ->with('bulk_deleted_hard', $deletedHard)
                ->with('bulk_skipped_nothing', $skippedNothingToCancel)
                ->with('bulk_skipped_forbidden', $skippedForbidden);
        }
    
        /*
        |--------------------------------------------------------------------------
        | CANCEL MODE (default)
        |--------------------------------------------------------------------------
        */
        $nowUtc = Carbon::now('UTC');
    
        $hasOccTable = Schema::hasTable('event_occurrences');
        $occHasCancelledAt = $hasOccTable && Schema::hasColumn('event_occurrences', 'cancelled_at');
        $occHasIsCancelled = $hasOccTable && Schema::hasColumn('event_occurrences', 'is_cancelled');
    
        $buildCancelPayload = function () use ($nowUtc, $occHasIsCancelled) {
            $payload = [
                'cancelled_at' => $nowUtc,
                'updated_at' => $nowUtc,
            ];
    
            if ($occHasIsCancelled) {
                $payload['is_cancelled'] = true;
            }
    
            return $payload;
        };
    
        DB::transaction(function () use (
            $events,
            $nowUtc,
            $hasOccTable,
            $occHasCancelledAt,
            $occHasIsCancelled,
            $buildCancelPayload,
            &$affected,
            &$cancelledFuture,
            &$skippedNothingToCancel,
            &$notificationsToSend
        ) {
            foreach ($events as $event) {
                if (!$hasOccTable) {
                    // В новой схеме bulk cancel работает только через event_occurrences.
                    $skippedNothingToCancel++;
                    continue;
                }
    
                $occQ = DB::table('event_occurrences')
                    ->where('event_id', (int) $event->id)
                    ->whereNotNull('starts_at')
                    ->where('starts_at', '>', $nowUtc);
    
                if ($occHasCancelledAt) {
                    $occQ->whereNull('cancelled_at');
                }
    
                if ($occHasIsCancelled) {
                    $occQ->where(function ($w) {
                        $w->whereNull('is_cancelled')
                          ->orWhere('is_cancelled', false);
                    });
                }
    
                $idsToCancel = $occQ->pluck('id')
                    ->map(fn ($v) => (int) $v)
                    ->all();
    
                if (empty($idsToCancel)) {
                    $skippedNothingToCancel++;
                    continue;
                }
    
                DB::table('event_occurrences')
                    ->whereIn('id', $idsToCancel)
                    ->update($buildCancelPayload());
    
                // считаем, что bulk cancel выключает дальнейшую генерацию дат
                if ((bool) ($event->is_recurring ?? false) && trim((string) ($event->recurrence_rule ?? '')) !== '') {
                    $event->is_recurring = false;
                    $event->recurrence_rule = null;
                    $event->save();
                }
    
                foreach ($idsToCancel as $occurrenceId) {
                    $notificationsToSend[] = [
                        'event' => $event,
                        'occurrence_id' => (int) $occurrenceId,
                        'reason' => 'Отменено организатором',
                    ];
                }
    
                $cancelledFuture++;
                $affected++;
            }
        });
    
        foreach ($notificationsToSend as $item) {
            $this->notifyUsersAboutCancelledEvent(
                event: $item['event'],
                occurrenceId: $item['occurrence_id'],
                reason: $item['reason']
            );
        }
    
        $msg = "Bulk cancel: затронуто {$affected}.";
        if ($cancelledFuture > 0) {
            $msg .= " Отменено будущих occurrences: {$cancelledFuture} (история сохранена).";
        }
        if ($skippedNothingToCancel > 0) {
            $msg .= " Пропущено (нет будущих дат для отмены): {$skippedNothingToCancel}.";
        }
        if ($skippedForbidden > 0) {
            $msg .= " Недоступно по правам/не найдено: {$skippedForbidden}.";
        }
    
        return redirect()
            ->route('events.create.event_management', ['tab' => $tab])
            ->with('status', $msg)
            ->with('bulk_affected', $affected)
            ->with('bulk_cancelled_future', $cancelledFuture)
            ->with('bulk_deleted_hard', $deletedHard)
            ->with('bulk_skipped_nothing', $skippedNothingToCancel)
            ->with('bulk_skipped_forbidden', $skippedForbidden);
    }
    private function notifyUsersAboutCancelledEvent(Event $event, ?int $occurrenceId, string $reason): void
    {
        if (!Schema::hasTable('event_registrations')) {
            return;
        }

        $q = DB::table('event_registrations')
            ->where('event_id', (int) $event->id);

        if (!is_null($occurrenceId) && Schema::hasColumn('event_registrations', 'occurrence_id')) {
            $q->where('occurrence_id', (int) $occurrenceId);
        }

        if (Schema::hasColumn('event_registrations', 'cancelled_at')) {
            $q->whereNull('cancelled_at');
        }

        if (Schema::hasColumn('event_registrations', 'is_cancelled')) {
            $q->where(function ($w) {
                $w->whereNull('is_cancelled')->orWhere('is_cancelled', false);
            });
        }

        if (Schema::hasColumn('event_registrations', 'status')) {
            $q->where(function ($w) {
                $w->whereNull('status')->orWhere('status', 'confirmed');
            });
        }

        $userIds = $q->pluck('user_id')
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values();

        foreach ($userIds as $userId) {
            $this->userNotificationService->createEventCancelledNotification(
                userId: $userId,
                eventId: (int) $event->id,
                occurrenceId: $occurrenceId,
                eventTitle: (string) ($event->title ?? ('Мероприятие #' . $event->id)),
                reason: $reason
            );
        }
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

        if ($role === 'organizer') {
            return (int) $user->id;
        }

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