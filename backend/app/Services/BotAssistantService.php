<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Помощник записи — управляет ботами на мероприятиях.
 *
 * Логика:
 * 1. Проверяем, нужна ли помощь (прошли сутки, < threshold% живых игроков)
 * 2. Боты постепенно занимают места (не более bot_assistant_max_fill_pct% от max_players)
 * 3. Всегда оставляем минимум 2 свободных места для живых
 * 4. Когда живых становится больше — боты постепенно выходят (с задержкой 1-4 часа)
 * 5. За 3 часа до начала — ничего не трогаем
 */
final class BotAssistantService
{
    // Минимум свободных мест которые всегда должны быть для живых
    private const MIN_FREE_SLOTS = 2;

    // За сколько часов до начала боты «замораживаются»
    private const FREEZE_HOURS_BEFORE = 3;

    public function processOccurrence(EventOccurrence $occurrence): void
    {
        $event = $occurrence->event ?? $occurrence->load('event')->event;

        if (!$event) {
            return;
        }

        // NULL на occurrence = наследуем от события
        $occOverride = $occurrence->getRawOriginal('bot_assistant_enabled');
        $enabled = $occOverride === null
            ? (bool) ($event->bot_assistant_enabled ?? false)
            : (bool) $occOverride;

        if (!$enabled) {
            return;
        }

        // Получаем настройки игры
        $maxPlayers = $this->getMaxPlayers($event);
        if ($maxPlayers < 4) {
            return; // слишком маленький формат
        }

       $startsAt = Carbon::parse($occurrence->starts_at, 'UTC');

        $hoursUntilStart = now('UTC')->diffInHours($startsAt, true);
        if ($hoursUntilStart < self::FREEZE_HOURS_BEFORE) {
            Log::warning("BotAssistant: occurrence #{$occurrence->id} frozen (< 3h before start)");
            return;
        }

        // Боты не работают при командной записи
        if (($event->registration_type ?? 'individual') === 'team') {
            return;
        }

        // Регистрация должна быть открыта
        $registrationStartsAt = $occurrence->registration_starts_at
            ?? $event->registration_starts_at;
        $registrationStartsAt = $registrationStartsAt
            ? Carbon::parse($registrationStartsAt)
            : null;

        if ($registrationStartsAt && $registrationStartsAt->isFuture()) {
            return; // запись ещё не открылась
        }

        // Считаем живых и ботов
        $registrations = $this->getRegistrations($occurrence->id);
        $liveCount     = $registrations->where('is_bot', false)->count();
        $botCount      = $registrations->where('is_bot', true)->count();
        $totalCount    = $registrations->count();

        // Порог: прошли ли сутки с открытия записи?
        $registrationAge = $registrationStartsAt
            ? $registrationStartsAt->diffInHours(now('UTC'), true)
            : Carbon::parse($occurrence->created_at)->diffInHours(now('UTC'), true);

        $threshold    = (int) ($event->bot_assistant_threshold ?? 10);
        $maxFillPct   = (int) ($event->bot_assistant_max_fill_pct ?? 40);

        // Максимум ботов одновременно (% от maxPlayers)
        $maxBots = (int) floor($maxPlayers * $maxFillPct / 100);
        $maxBots = max(1, $maxBots);

        // Свободных мест для ботов (с учётом MIN_FREE_SLOTS для живых)
        $freeSlotsForBots = max(0, $maxPlayers - $liveCount - self::MIN_FREE_SLOTS);

        // Сколько ботов должно быть сейчас
        $targetBotCount = $this->calcTargetBotCount(
            liveCount: $liveCount,
            maxPlayers: $maxPlayers,
            maxBots: $maxBots,
            freeSlotsForBots: $freeSlotsForBots,
            threshold: $threshold,
            registrationAgeHours: $registrationAge
        );

        Log::warning("BotAssistant: occurrence #{$occurrence->id}", [
            'live' => $liveCount,
            'bots' => $botCount,
            'target_bots' => $targetBotCount,
            'max_players' => $maxPlayers,
            'registration_age_h' => $registrationAge,
        ]);

        if ($targetBotCount > $botCount) {
            $this->addBots(
                occurrence: $occurrence,
                event: $event,
                count: $targetBotCount - $botCount,
                currentBotUserIds: $registrations->where('is_bot', true)->pluck('user_id')->all()
            );
        } elseif ($targetBotCount < $botCount) {
            $this->removeBots(
                occurrenceId: $occurrence->id,
                count: $botCount - $targetBotCount,
                registrations: $registrations
            );
        }
    }

    // -------------------------------------------------------------------------
    // Логика расчёта целевого количества ботов
    // -------------------------------------------------------------------------

    private function calcTargetBotCount(
        int $liveCount,
        int $maxPlayers,
        int $maxBots,
        int $freeSlotsForBots,
        int $threshold,
        int $registrationAgeHours
    ): int {
        // До суток — боты не включаются
        if ($registrationAgeHours < 24) {
            return 0;
        }

        $livePercent = $maxPlayers > 0 ? ($liveCount / $maxPlayers) * 100 : 0;

        // Живых достаточно — боты не нужны
        if ($livePercent >= $threshold) {
            return 0;
        }

        // Вычисляем сколько мест нужно «оживить»
        // Цель: сделать вид что мероприятие заполнено на 60-70%
        $targetFillPct = min(70, $threshold + 30);
        $targetTotal   = (int) ceil($maxPlayers * $targetFillPct / 100);
        $needed        = max(0, $targetTotal - $liveCount);

        // Ограничиваем максимумом ботов и свободными слотами
        return min($needed, $maxBots, $freeSlotsForBots);
    }

    // -------------------------------------------------------------------------
    // Добавление ботов
    // -------------------------------------------------------------------------

   private function addBots(
        EventOccurrence $occurrence,
        Event $event,
        int $count,
        array $currentBotUserIds
    ): void {
        $bots = $this->selectBots(
            event: $event,
            occurrence: $occurrence,
            count: $count,
            excludeUserIds: $currentBotUserIds
        );
    
        foreach ($bots as $bot) {
            $registered = $this->registerBot($bot, $occurrence, $event);
    
            if ($registered) {
                Log::info("BotAssistant: bot #{$bot->id} ({$bot->name}) joined occurrence #{$occurrence->id}");
            } else {
                Log::warning("BotAssistant: bot #{$bot->id} ({$bot->name}) skipped (full or duplicate)");
            }
        }
    }
    

    private function registerBot(User $bot, EventOccurrence $occurrence, Event $event): bool
    {
        // Дубликат
        $exists = EventRegistration::query()
            ->where('user_id', $bot->id)
            ->where('occurrence_id', $occurrence->id)
            ->exists();
    
        if ($exists) {
            return false;
        }
    
        // Проверяем что ещё есть место (общий лимит)
        $maxPlayers = $this->getMaxPlayers($event);
        $currentCount = EventRegistration::query()
            ->where('occurrence_id', $occurrence->id)
            ->where('status', 'confirmed')
            ->where('is_cancelled', false)
            ->count();
    
        if ($maxPlayers > 0 && $currentCount >= $maxPlayers) {
            return false;
        }
    
        // Выбираем позицию
        $position = $this->pickBotPosition($event, $occurrence);
    
        DB::transaction(function () use ($bot, $occurrence, $event, $position) {
            EventRegistration::query()->create([
                'user_id'       => $bot->id,
                'event_id'      => $event->id,
                'occurrence_id' => $occurrence->id,
                'status'        => 'confirmed',
                'position'      => $position,
                'is_cancelled'  => false,
            ]);
        });
    
        app(\App\Services\EventOccurrenceStatsService::class)->increment($occurrence->id);
    
        return true;
    }
    
    private function pickBotPosition(Event $event, EventOccurrence $occurrence): ?string
    {
        $direction = (string)($event->direction ?? 'classic');
    
        // Пляжка — позиций нет
        if ($direction === 'beach') {
            return null;
        }
    
        // Берём слоты через тот же сервис что и Guard
        $slotService = app(\App\Services\EventRoleSlotService::class);
        $slots = $slotService->getSlots($event);
    
        if (empty($slots)) {
            return 'player'; // fallback
        }
    
        // Считаем занятые позиции на этом occurrence
        $taken = DB::table('event_registrations')
            ->where('occurrence_id', $occurrence->id)
            ->where('status', 'confirmed')
            ->where('is_cancelled', false)
            ->select('position', DB::raw('count(*) as cnt'))
            ->groupBy('position')
            ->pluck('cnt', 'position')
            ->toArray();
    
        // Собираем свободные позиции (НО оставляем последний слот каждой позиции людям)
        $free = [];
        foreach ($slots as $slot) {
            $takenCount = (int)($taken[$slot->role] ?? 0);
            $maxSlots = (int)$slot->max_slots;
            // Боты могут занимать только если остается минимум 1 свободное место
            $availableForBots = max(0, $maxSlots - $takenCount - 1);
            if ($availableForBots > 0) {
                // Добавляем с весом — чем больше мест, тем чаще выбирается
                for ($i = 0; $i < $availableForBots; $i++) {
                    $free[] = $slot->role;
                }
            }
        }
    
        if (empty($free)) {
            return 'player'; // все слоты заняты, запишем как обычный игрок
        }
    
        return $free[array_rand($free)];
    }

    private function selectBots(Event $event, EventOccurrence $occurrence, int $count, array $excludeUserIds): Collection
    {
        $query = User::query()
            ->where('is_bot', true)
            ->whereNotIn('id', $excludeUserIds);

        // Гендерная политика мероприятия
        $genderPolicy = $this->getGenderPolicy($event);
        if ($genderPolicy === 'male_only') {
            $query->where('gender', 'm');
        } elseif ($genderPolicy === 'female_only') {
            $query->where('gender', 'f');
        }
        // mixed_open и mixed_balanced — любые боты

        // Уровень: occurrence переопределяет event (как в Guard)
        $direction = $event->direction ?? 'classic';
        $levelField = $direction === 'beach' ? 'beach_level' : 'classic_level';

        if ($direction === 'beach') {
            $levelMin = $occurrence->beach_level_min ?? $event->beach_level_min;
            $levelMax = $occurrence->beach_level_max ?? $event->beach_level_max;
        } else {
            $levelMin = $occurrence->classic_level_min ?? $event->classic_level_min;
            $levelMax = $occurrence->classic_level_max ?? $event->classic_level_max;
        }

        if ($levelMin) {
            $query->where($levelField, '>=', $levelMin);
        }
        if ($levelMax) {
            $query->where($levelField, '<=', $levelMax);
        }

        return $query->inRandomOrder()->limit($count)->get();
    }
    // -------------------------------------------------------------------------
    private function removeBots(int $occurrenceId, int $count, Collection $registrations): void
    {
        // Удаляем ботов с конца (самые последние в очереди уходят первыми)
        $botRegistrations = $registrations
            ->where('is_bot', true)
            ->sortByDesc('position')
            ->take($count);

        foreach ($botRegistrations as $reg) {
            EventRegistration::query()
                ->where('id', $reg->registration_id)
                ->delete();
            app(\App\Services\EventOccurrenceStatsService::class)->decrement($occurrenceId);
            
            Log::info("BotAssistant: bot #{$reg->user_id} ({$reg->user_name}) left occurrence #{$occurrenceId}");
        }
    }

    // -------------------------------------------------------------------------
    // Вспомогательные
    // -------------------------------------------------------------------------

    private function getRegistrations(int $occurrenceId): Collection
    {
        return DB::table('event_registrations as er')
            ->join('users as u', 'u.id', '=', 'er.user_id')
            ->where('er.occurrence_id', $occurrenceId)
            ->where('er.status', 'confirmed')
            ->select([
                'er.id as registration_id',
                'er.user_id',
                'er.position',
                'u.name as user_name',
                'u.is_bot',
            ])
            ->get();
    }

    private function getMaxPlayers(Event $event): int
    {
        $settings = DB::table('event_game_settings')
            ->where('event_id', $event->id)
            ->value('max_players');

        return (int) ($settings ?? 0);
    }

    private function getGenderPolicy(Event $event): string
    {
        $policy = DB::table('event_game_settings')
            ->where('event_id', $event->id)
            ->value('gender_policy');

        return (string) ($policy ?? 'mixed_open');
    }
}