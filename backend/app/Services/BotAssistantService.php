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

        if (!$event || !$event->bot_assistant_enabled) {
            return;
        }

        // Получаем настройки игры
        $maxPlayers = $this->getMaxPlayers($event);
        if ($maxPlayers < 4) {
            return; // слишком маленький формат
        }

        $startsAt = Carbon::parse($occurrence->starts_at, 'UTC');

        // Заморозка за 3 часа до начала
        if ($startsAt->diffInHours(now(), false) > -self::FREEZE_HOURS_BEFORE) {
            Log::info("BotAssistant: occurrence #{$occurrence->id} frozen (< 3h before start)");
            return;
        }

        // Регистрация должна быть открыта
        $registrationStartsAt = $event->registration_starts_at
            ? Carbon::parse($event->registration_starts_at)
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
            ? $registrationStartsAt->diffInHours(now())
            : Carbon::parse($occurrence->created_at)->diffInHours(now());

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

        Log::info("BotAssistant: occurrence #{$occurrence->id}", [
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
            count: $count,
            excludeUserIds: $currentBotUserIds
        );

        foreach ($bots as $bot) {
            // Случайная задержка — имитация органичной записи
            // В реальности джоб запускается раз в N минут, задержка тут символическая
            // Для более реалистичного поведения джоб сам управляет расписанием
            $this->registerBot($bot, $occurrence, $event);

            Log::info("BotAssistant: bot #{$bot->id} ({$bot->name}) joined occurrence #{$occurrence->id}");
        }
    }

    private function selectBots(Event $event, int $count, array $excludeUserIds): Collection
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

        // Уровень: берём ботов подходящего уровня для мероприятия
        $direction = $event->direction ?? 'classic';
        $levelField = $direction === 'beach' ? 'beach_level' : 'classic_level';

        $levelMin = $direction === 'beach' ? $event->beach_level_min : $event->classic_level_min;
        $levelMax = $direction === 'beach' ? $event->beach_level_max : $event->classic_level_max;

        if ($levelMin) {
            $query->where($levelField, '>=', $levelMin);
        }
        if ($levelMax) {
            $query->where($levelField, '<=', $levelMax);
        }

        return $query->inRandomOrder()->limit($count)->get();
    }

    private function registerBot(User $bot, EventOccurrence $occurrence, Event $event): void
    {
        DB::transaction(function () use ($bot, $occurrence, $event) {
            // Проверяем — вдруг уже записан
            $exists = EventRegistration::query()
                ->where('user_id', $bot->id)
                ->where('occurrence_id', $occurrence->id)
                ->exists();

            if ($exists) {
                return;
            }

            // Определяем позицию — боты всегда в хвосте списка
            $maxPosition = EventRegistration::query()
                ->where('occurrence_id', $occurrence->id)
                ->max('position') ?? 0;

            EventRegistration::query()->create([
                'user_id'       => $bot->id,
                'event_id'      => $event->id,
                'occurrence_id' => $occurrence->id,
                'status'        => 'confirmed',
                'position'      => $maxPosition + 1,
            ]);
        });
    }

    // -------------------------------------------------------------------------
    // Удаление ботов
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