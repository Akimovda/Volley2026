<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\OccurrenceWaitlist;
use App\Models\User;
use App\Services\WaitlistService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventWaitlistManagementController extends Controller
{
    // -----------------------------------------------------------------------
    // POST /events/{event}/waitlist/management
    // Добавить игрока в лист ожидания
    // -----------------------------------------------------------------------
    public function store(Request $request, Event $event): RedirectResponse
    {
        $authUser = $request->user();
        $this->ensureCanManage($authUser, $event);

        $occurrenceId = (int) $request->input('occurrence_id', 0);
        $occurrence   = $this->resolveOccurrence($event, $occurrenceId);

        $userId = (int) $request->input('user_id', 0);
        $user   = User::find($userId);
        if (!$user) {
            return back()->with('error', 'Игрок не найден.');
        }

        $alreadyRegistered = DB::table('event_registrations')
            ->where('occurrence_id', $occurrence->id)
            ->where('user_id', $user->id)
            ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
            ->exists();
        if ($alreadyRegistered) {
            return back()->with('error', 'Игрок уже записан в основной состав.');
        }

        $alreadyWaiting = OccurrenceWaitlist::where('occurrence_id', $occurrence->id)
            ->where('user_id', $user->id)
            ->exists();
        if ($alreadyWaiting) {
            return back()->with('error', 'Игрок уже в листе ожидания.');
        }

        $positions = array_values(array_filter((array) $request->input('positions', [])));

        app(WaitlistService::class)->join($occurrence, $user, $positions);

        return back()->with('status', 'Игрок добавлен в лист ожидания.');
    }

    // -----------------------------------------------------------------------
    // DELETE /events/{event}/waitlist/management/{entry}
    // Удалить игрока из листа ожидания
    // -----------------------------------------------------------------------
    public function destroy(Request $request, Event $event, OccurrenceWaitlist $entry): RedirectResponse
    {
        $this->ensureCanManage($request->user(), $event);
        $this->ensureEntryBelongsToEvent($entry, $event);

        $occurrence = $entry->occurrence;
        $positions  = (array) ($entry->positions ?? []);
        $userId     = (int) $entry->user_id;

        if ($occurrence && $occurrence->event) {
            app(\App\Services\UserNotificationService::class)->createWaitlistRemovedByOrganizerNotification(
                userId: $userId,
                eventId: (int) $occurrence->event->id,
                occurrenceId: (int) $occurrence->id,
                eventTitle: (string) ($occurrence->event->title ?? ''),
            );
        }

        $entry->delete();

        // Если были свободные слоты — авто-бук следующего в очереди
        if ($occurrence) {
            foreach ($positions as $pos) {
                if ($pos === 'reserve') continue;
                if (app(\App\Services\EventRoleSlotService::class)->hasFreeSlot($occurrence->id, $pos)) {
                    app(WaitlistService::class)->autoBookNext($occurrence, $pos);
                }
            }
        }

        return back()->with('status', 'Игрок удалён из листа ожидания.');
    }

    // -----------------------------------------------------------------------
    // PATCH /events/{event}/waitlist/management/{entry}/positions
    // Изменить позиции участника очереди
    // -----------------------------------------------------------------------
    public function updatePositions(Request $request, Event $event, OccurrenceWaitlist $entry): RedirectResponse
    {
        $this->ensureCanManage($request->user(), $event);
        $this->ensureEntryBelongsToEvent($entry, $event);

        $positions = array_values(array_filter((array) $request->input('positions', [])));

        // Тот же инвариант, что в WaitlistService::join() — не пишем positions=[] для
        // classic (organizer правит очередь напрямую в БД, мимо join(), поэтому
        // предохранитель нужен здесь отдельно).
        $occurrence = $entry->occurrence()->with('event.gameSettings')->first();
        $waitlist   = app(WaitlistService::class);
        if ($occurrence) {
            $direction = (string) ($occurrence->event->direction ?? 'classic');
            if ($direction !== 'beach' && empty($positions)) {
                $positions = $waitlist->occupiedPositions($occurrence);
                \Illuminate\Support\Facades\Log::warning("Waitlist: updatePositions() получил пустой positions для entry #{$entry->id} (direction={$direction}) — заполнено занятыми позициями", [
                    'positions' => $positions,
                ]);
            }
        }

        DB::table('occurrence_waitlist')
            ->where('id', $entry->id)
            ->update(['positions' => json_encode($positions)]);

        // Если среди новых позиций есть свободные слоты — авто-бук сразу
        if ($occurrence) {
            foreach ($positions as $pos) {
                if ($pos === 'reserve') {
                    $reserveMax = (int)($occurrence->event->gameSettings?->reserve_players_max ?? 0);
                    if ($reserveMax > 0) {
                        $waitlist->autoBookNext($occurrence, 'reserve');
                    }
                } else {
                    if (app(\App\Services\EventRoleSlotService::class)->hasFreeSlot($occurrence->id, $pos)) {
                        $waitlist->autoBookNext($occurrence, $pos);
                    }
                }
            }
        }

        return back()->with('status', 'Позиции обновлены.');
    }

    // -----------------------------------------------------------------------
    // POST /events/{event}/waitlist/management/{entry}/move
    // Переместить вверх или вниз в очереди (меняет sort_order с соседом)
    // -----------------------------------------------------------------------
    public function move(Request $request, Event $event, OccurrenceWaitlist $entry): RedirectResponse
    {
        $this->ensureCanManage($request->user(), $event);
        $this->ensureEntryBelongsToEvent($entry, $event);

        $direction = $request->input('direction'); // 'up' | 'down'

        DB::transaction(function () use ($entry, $direction) {
            $neighbor = match ($direction) {
                'up'   => OccurrenceWaitlist::where('occurrence_id', $entry->occurrence_id)
                              ->where(fn($q) => $q
                                  ->where('sort_order', '<', $entry->sort_order)
                                  ->orWhere(fn($q2) => $q2
                                      ->where('sort_order', $entry->sort_order)
                                      ->where('id', '<', $entry->id)
                                  )
                              )
                              ->orderByDesc('sort_order')->orderByDesc('id')
                              ->first(),
                'down' => OccurrenceWaitlist::where('occurrence_id', $entry->occurrence_id)
                              ->where(fn($q) => $q
                                  ->where('sort_order', '>', $entry->sort_order)
                                  ->orWhere(fn($q2) => $q2
                                      ->where('sort_order', $entry->sort_order)
                                      ->where('id', '>', $entry->id)
                                  )
                              )
                              ->orderBy('sort_order')->orderBy('id')
                              ->first(),
                default => null,
            };

            if (!$neighbor) return;

            $tmpSort = $entry->sort_order;
            DB::table('occurrence_waitlist')->where('id', $entry->id)
                ->update(['sort_order' => $neighbor->sort_order]);
            DB::table('occurrence_waitlist')->where('id', $neighbor->id)
                ->update(['sort_order' => $tmpSort]);
        });

        return back();
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function resolveOccurrence(Event $event, int $occurrenceId): EventOccurrence
    {
        if ($occurrenceId) {
            $occ = EventOccurrence::where('id', $occurrenceId)
                ->where('event_id', $event->id)
                ->first();
            if ($occ) return $occ;
        }

        $occ = EventOccurrence::where('event_id', $event->id)
            ->orderBy('starts_at')
            ->first();

        if (!$occ) abort(404, 'Occurrence not found');

        return $occ;
    }

    private function ensureEntryBelongsToEvent(OccurrenceWaitlist $entry, Event $event): void
    {
        $belongs = DB::table('event_occurrences')
            ->where('id', $entry->occurrence_id)
            ->where('event_id', $event->id)
            ->exists();

        if (!$belongs) abort(403);
    }

    private function ensureCanManage($user, Event $event): void
    {
        if (!$user) abort(403);

        $role = (string) ($user->role ?? 'user');

        if ($role === 'admin') return;

        if ($role === 'organizer') {
            if ((int) $event->organizer_id !== (int) $user->id) abort(403);
            return;
        }

        if ($role === 'staff') {
            $row = DB::table('organizer_staff')
                ->where('staff_user_id', (int) $user->id)
                ->orderBy('id')
                ->first(['organizer_id']);
            $orgId = $row ? (int) $row->organizer_id : 0;
            if ($orgId <= 0 || (int) $event->organizer_id !== $orgId) abort(403);
            return;
        }

        abort(403);
    }
}
