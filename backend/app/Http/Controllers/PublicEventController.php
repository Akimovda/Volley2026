<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventOccurrence;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PublicEventController extends Controller
{
    /**
     * GET /e/{token}
     * Публичный просмотр приватного события по токену
     */
    public function show(Request $request, string $token)
    {
        // ✅ 1) Находим приватное событие по токену
        $q = Event::query()
            ->where('public_token', $token);

        // если колонка is_private есть — дополнительно требуем приватность
        if (Schema::hasColumn('events', 'is_private')) {
            $q->where('is_private', 1);
        }

        $event = $q->firstOrFail();

        // ✅ 2) Загружаем связи (не ломаемся, если relation trainer_user не существует)
        $relations = [
            'location:id,name,city,address',
            'gameSettings:event_id,subtype,libero_mode,max_players,positions,gender_policy,gender_limited_side,gender_limited_max,gender_limited_positions,allow_girls,girls_max',
            'media',
        ];

        // ⚠️ FIX: грузим trainer_user только если relation реально объявлен в модели Event
        if (method_exists($event, 'trainer_user')) {
            $relations[] = 'trainer_user:id,name,email,nickname,username,phone';
        }

        $event->load($relations);

        // ✅ 3) Выбираем occurrence (чтобы events.show не падал)
        $occurrenceId = (int)$request->query('occurrence', 0);
        $occurrence = null;

        if (Schema::hasTable('event_occurrences')) {
            if ($occurrenceId > 0) {
                $occurrence = EventOccurrence::query()
                    ->where('id', $occurrenceId)
                    ->where('event_id', (int)$event->id)
                    ->first();
            }

            if (!$occurrence) {
                $occurrence = EventOccurrence::query()
                    ->where('event_id', (int)$event->id)
                    ->orderBy('starts_at', 'asc')
                    ->first();
            }
        }

        // ✅ 4) Availability (как в EventsController)
        $availability = $this->buildAvailabilityForEvent($event);

        if ($occurrence) {
            $payload = $this->availabilityOccurrence($occurrence)->getData(true);

            $availability = [
                'max_players'       => (int)($payload['meta']['max_players'] ?? 0),
                'registered_total'  => (int)($payload['meta']['registered_total'] ?? 0),
                'remaining_total'   => (int)($payload['meta']['remaining_total'] ?? 0),
                'free_positions'    => $payload['free_positions'] ?? [],
                'meta'              => $payload['meta'] ?? [],
            ];
        }

        // ✅ 5) ВАЖНО: всегда передаём $occurrence, иначе show.blade.php падает
        return view('events.show', [
            'event'        => $event,
            'occurrence'   => $occurrence,   // <-- FIX
            'availability' => $availability,
        ]);
    }

    /**
     * Ниже — минимально нужные методы, чтобы events.show работал и "места" считались.
     * Это копия логики из EventsController, без приватных проверок.
     */

    private function applyActiveScope($q, bool $hasCancelledAt, bool $hasIsCancelled, bool $hasStatus, string $prefix = ''): void
    {
        if ($hasIsCancelled) {
            $q->where($prefix . 'is_cancelled', false);
        }
        if ($hasCancelledAt) {
            $q->whereNull($prefix . 'cancelled_at');
        }
        if ($hasStatus) {
            $q->where($prefix . 'status', 'confirmed');
        }
        if (Schema::hasColumn('event_registrations', 'deleted_at')) {
            $q->whereNull($prefix . 'deleted_at');
        }
    }

    private function buildAvailabilityForEvent(Event $event): array
    {
        $gs = $event->gameSettings;
        $maxPlayers = (int)($gs->max_players ?? 0);

        return [
            'max_players'       => $maxPlayers,
            'registered_total'  => 0,
            'remaining_total'   => $maxPlayers,
            'free_positions'    => [],
            'meta'              => [
                'max_players'      => $maxPlayers,
                'registered_total' => 0,
                'remaining_total'  => $maxPlayers,
            ],
        ];
    }

    public function availabilityOccurrence(EventOccurrence $occurrence)
    {
        $hasRegTable    = Schema::hasTable('event_registrations');
        $hasCancelledAt = $hasRegTable && Schema::hasColumn('event_registrations', 'cancelled_at');
        $hasIsCancelled = $hasRegTable && Schema::hasColumn('event_registrations', 'is_cancelled');
        $hasStatus      = $hasRegTable && Schema::hasColumn('event_registrations', 'status');

        $occurrence->load(['event.gameSettings']);
        $event = $occurrence->event;
        if (!$event) {
            return response()->json(['ok' => false, 'message' => 'Событие для occurrence не найдено.'], 404);
        }

        $gs = $event->gameSettings;
        $maxPlayers = (int)($gs->max_players ?? 0);

        if (!(bool)$event->allow_registration) {
            return response()->json([
                'ok' => true,
                'meta' => [
                    'max_players'       => $maxPlayers,
                    'registered_total'  => 0,
                    'remaining_total'   => $maxPlayers,
                ],
                'free_positions' => [],
            ]);
        }

        $positions = $gs?->positions;
        if (is_string($positions)) {
            $decoded = json_decode($positions, true);
            $positions = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($positions)) $positions = [];
        $positions = array_values(array_unique(array_map('strval', $positions)));

        $totalQ = DB::table('event_registrations')
            ->where('occurrence_id', (int)$occurrence->id);
        $this->applyActiveScope($totalQ, $hasCancelledAt, $hasIsCancelled, $hasStatus);
        $registeredTotal = (int)$totalQ->count();
        $remainingTotal = max(0, $maxPlayers - $registeredTotal);

        return response()->json([
            'ok' => true,
            'meta' => [
                'max_players'       => $maxPlayers,
                'registered_total'  => $registeredTotal,
                'remaining_total'   => $remainingTotal,
            ],
            'free_positions' => [],
        ]);
    }
}
