<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventRoleSlot;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Collection;

class EventRoleSlotService
{
    /**
     * Cache key
     */
    protected function cacheKey(Event $event): string
    {
        return "event_role_slots_{$event->id}";
    }

    /**
     * Get slots (cached)
     */
    public function getSlots(Event $event): Collection
    {
        return Cache::remember(
            $this->cacheKey($event),
            now()->addMinutes(1),
            fn () => $event
                ->roleSlots()
                ->orderBy('role')
                ->get()
        );
    }

    /**
     * Sync role slots (safe rebuild)
     */
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
        
                $count = max(0, (int)$count);
        
                if ($existing->has($role)) {
        
                    $slot = $existing[$role];
        
                    $slot->max_slots = $count;
        
                    $slot->save();
        
                } else {
        
                    EventRoleSlot::create([
                        'event_id' => $event->id,
                        'role' => $role,
                        'max_slots' => $count,
                        'taken_slots' => 0,
                    ]);
        
                }
            }
        
            /*
            |--------------------------------------------------------------------------
            | REMOVE OLD ROLES
            |--------------------------------------------------------------------------
            */
        
            EventRoleSlot::where('event_id', $event->id)
                ->whereNotIn('role', array_keys($roles))
                ->delete();
        
            $this->clear($event);
        }

    /**
     * Try to take slot (atomic)
     * Protects from race conditions
     */
    public function tryTakeSlot(Event $event, string $role): bool
    {
        $updated = EventRoleSlot::where('event_id', $event->id)
            ->where('role', $role)
            ->whereColumn('taken_slots', '<', 'max_slots')
            ->update([
                'taken_slots' => \DB::raw('taken_slots + 1')
            ]);

        if ($updated > 0) {
            $this->clear($event);
        }

        return $updated > 0;
    }

    /**
     * Release slot (when user cancels registration)
     */
    public function releaseSlot(Event $event, string $role): void
    {
        EventRoleSlot::where('event_id', $event->id)
            ->where('role', $role)
            ->where('taken_slots', '>', 0)
            ->decrement('taken_slots');

        $this->clear($event);
    }

    /**
     * Get availability info for UI / API
     */
    public function getAvailability(Event $event): array
    {
        $slots = $this->getSlots($event);

        $result = [];

        foreach ($slots as $slot) {

            $free = max(
                0,
                $slot->max_slots - $slot->taken_slots
            );

            $result[$slot->role] = [
                'max' => (int)$slot->max_slots,
                'taken' => (int)$slot->taken_slots,
                'free' => (int)$free,
            ];
        }

        return $result;
    }

    /**
     * Clear cached slots
     */
    public function clear(Event $event): void
    {
        Cache::forget($this->cacheKey($event));
    }
}