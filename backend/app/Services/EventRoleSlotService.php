<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventRoleSlot;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Collection;

class EventRoleSlotService
{
    protected function cacheKey(Event $event): string
    {
        return "event_role_slots_{$event->id}";
    }

    public function getSlots(Event $event): Collection
    {
        return Cache::remember(
            $this->cacheKey($event),
            now()->addMinutes(1),
            fn () => $event->roleSlots()->orderBy('role')->get()
        );
    }

    public function syncRoleSlots(Event $event, array $roles): void
    {
        if (empty($roles)) {
            $this->clear($event);
            return;
        }

        $existing = EventRoleSlot::where('event_id', $event->id)
            ->get()
            ->keyBy('role');

        foreach ($roles as $role => $count) {
            $count = max(0, (int) $count);

            if ($existing->has($role)) {
                $slot = $existing[$role];
                $slot->max_slots = $count;
                $slot->save();
            } else {
                // taken_slots не указан — DEFAULT 0 на уровне БД (NOT NULL DEFAULT 0),
                // колонка write-only-мёртвая, подлежит DROP отдельной миграцией
                // (см. report_cache_counters_audit_2026-07-16.md).
                EventRoleSlot::create([
                    'event_id'  => $event->id,
                    'role'      => $role,
                    'max_slots' => $count,
                ]);
            }
        }

        EventRoleSlot::where('event_id', $event->id)
            ->whereNotIn('role', array_keys($roles))
            ->delete();

        $this->clear($event);
    }

    /**
     * Живой COUNT активных регистраций на роль в рамках конкретной occurrence.
     * Единственный источник истины — event_role_slots.taken_slots не occurrence-scoped
     * (один счётчик на всё повторяющееся событие) и структурно не может быть верным.
     *
     * $isSingleMainRole — у события ровно ОДНА не-reserve роль (пляжка/king_beach:
     * единственная роль 'player', в отличие от классики с несколькими именованными
     * позициями). В этом случае считаем ЛЮБУЮ активную регистрацию, кроме reserve,
     * занявшей эту роль — НЕ сверяя строго position === role. Причина: организатор
     * мог добавить игрока вручную через EventRegistrationsManagementController::
     * addPlayer() — для не-classic направлений форма не показывает выбор позиции,
     * и до фикса (2026-07-30) position писался пустой строкой. Такие регистрации
     * молча выпадали из строгого подсчёта, давая заниженный "занято"/завышенный
     * "свободно" и риск реального перебора вместимости через tryTakeSlot() (учитывал
     * меньше реально живых регистраций, чем есть). Для классики (несколько ролей)
     * поведение не меняется — там строгое сравнение обязательно.
     */
    private function countActive(int $occurrenceId, string $role, bool $isSingleMainRole = false): int
    {
        $query = \DB::table('event_registrations')
            ->where('occurrence_id', $occurrenceId)
            ->whereNull('cancelled_at')
            ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)');

        if ($role !== 'reserve' && $isSingleMainRole) {
            $query->where('position', '!=', 'reserve');
        } else {
            $query->where('position', $role);
        }

        return $query->count();
    }

    private function isSingleMainRole(Collection $slots): bool
    {
        return $slots->where('role', '!=', 'reserve')->count() === 1;
    }

    /**
     * Try to take slot (atomic).
     * Uses actual registration count per occurrence — decision is live COUNT-based,
     * taken_slots не пишется (write-only-мёртвая колонка, см. отчёт об аудите).
     * Caller must hold pg_advisory_xact_lock(occurrence_id, roleKey) before calling.
     */
    public function tryTakeSlot(Event $event, string $role, int $occurrenceId): bool
    {
        $slots = EventRoleSlot::where('event_id', $event->id)->get();
        $slot  = $slots->firstWhere('role', $role);

        if (!$slot) {
            return false;
        }

        $taken = $this->countActive($occurrenceId, $role, $this->isSingleMainRole($slots));

        if ($taken >= $slot->max_slots) {
            return false;
        }

        return true;
    }

    /**
     * Предикат без побочных эффектов: есть ли живое свободное место на роль
     * прямо сейчас, для конкретной occurrence. Не пишет в БД, не меняет кеш.
     * Используется для eager-проверок (join(), ручные действия организатора
     * в EventWaitlistManagementController) — раньше эти места читали
     * event_role_slots.taken_slots напрямую, что стабильно давало неверный
     * результат для повторяющихся событий (счётчик общий на все occurrences).
     */
    public function hasFreeSlot(int $occurrenceId, string $role): bool
    {
        $eventId = \DB::table('event_occurrences')
            ->where('id', $occurrenceId)
            ->value('event_id');

        if (!$eventId) {
            return false;
        }

        $slots = EventRoleSlot::where('event_id', $eventId)->get();
        $slot  = $slots->firstWhere('role', $role);

        if (!$slot) {
            return false;
        }

        return $this->countActive($occurrenceId, $role, $this->isSingleMainRole($slots)) < $slot->max_slots;
    }

    public function clear(Event $event): void
    {
        Cache::forget($this->cacheKey($event));
    }
}
