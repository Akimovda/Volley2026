<?php

namespace Tests\Support;

use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\EventRegistration;
use App\Models\OccurrenceWaitlist;
use App\Models\User;
use App\Services\EventGameSettingsService;
use App\Services\EventRoleSlotService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Билдер «мероприятие-игра + дата» для доменных тестов.
 *
 * Настройки игры создаются ТЕМ ЖЕ путём, что и при создании мероприятия в EventStoreService:
 * normalizeGameDefaults() → normalizeGenderPolicy() → createGameSettings() — поэтому event_role_slots
 * и max_players в тестах совпадают с боевыми (а не прописаны руками).
 *
 * Готовые пресеты: classic42(), classic51(), classic51Libero(), classicNoPositions(), beach2x2(), beach2x2Roles().
 * Опции ($o): teams, reserve, min_players, paid (bool), payment_method, price_minor, gender (массив game_gender_*),
 *             format (game|training_game), starts_in_hours, registration_ends_in_hours, title.
 */
trait BuildsGameEvents
{
    protected ?User $organizer = null;

    protected function organizer(): User
    {
        return $this->organizer ??= $this->makeUser(['first_name' => 'Орг', 'last_name' => 'Тестовый', 'role' => 'organizer']);
    }

    protected ?int $locationId = null;

    protected function locationId(): int
    {
        return $this->locationId ??= (int) DB::table('locations')->insertGetId([
            'name'         => 'Тестовый зал',
            'address'      => 'ул. Тестовая, 1',
            'organizer_id' => $this->organizer()->id,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    /** Пользователь с заполненным профилем (без уровня — у тестовых событий ограничений уровня нет). */
    protected function makeUser(array $attrs = []): User
    {
        $u = User::factory()->create(array_merge([
            'first_name' => 'Игрок',
            'last_name'  => 'Тест' . Str::random(4),
            'gender'     => 'm',
            'is_test'    => true,
        ], $attrs));

        return $u->fresh();
    }

    protected function makePremium(User $user): void
    {
        DB::table('premium_subscriptions')->insert([
            'user_id'    => $user->id,
            'plan'       => 'month',
            'status'     => 'active',
            'starts_at'  => now()->subDay(),
            'expires_at' => now()->addMonth(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ---------------------------------------------------------------- пресеты

    protected function classic42(array $o = []): EventOccurrence
    {
        return $this->makeGame('classic', array_merge(['game_subtype' => '4x2'], $o));
    }

    protected function classic51(array $o = []): EventOccurrence
    {
        return $this->makeGame('classic', array_merge(['game_subtype' => '5x1', 'game_libero_mode' => 'without_libero'], $o));
    }

    protected function classic51Libero(array $o = []): EventOccurrence
    {
        return $this->makeGame('classic', array_merge(['game_subtype' => '5x1', 'game_libero_mode' => 'with_libero'], $o));
    }

    /** Классика 4×2 «запись без амплуа» — один слот 'player'. */
    protected function classicNoPositions(array $o = []): EventOccurrence
    {
        return $this->makeGame('classic', array_merge(['game_subtype' => '4x2', 'game_registration_by_positions' => 0], $o));
    }

    protected function beach2x2(array $o = []): EventOccurrence
    {
        return $this->makeGame('beach', array_merge(['game_subtype' => '2x2'], $o));
    }

    /** Пляж 2×2 «По амплуа» — слоты blocker/defender. */
    protected function beach2x2Roles(array $o = []): EventOccurrence
    {
        return $this->makeGame('beach', array_merge(['game_subtype' => '2x2', 'game_registration_by_roles' => 1], $o));
    }

    // ---------------------------------------------------------------- сборка

    protected function makeGame(string $direction, array $o = []): EventOccurrence
    {
        $format = (string) ($o['format'] ?? 'game');
        $paid   = (bool) ($o['paid'] ?? false);

        $startsAt = now('UTC')->addHours((int) ($o['starts_in_hours'] ?? 72))->startOfMinute();

        $event = Event::query()->forceCreate([
            'title'              => $o['title'] ?? ('Тест ' . $direction . ' ' . Str::random(5)),
            'organizer_id'       => $this->organizer()->id,
            'location_id'        => $this->locationId(),
            'direction'          => $direction,
            'format'             => $format,
            'registration_mode'  => 'single',
            'timezone'           => 'Europe/Moscow',
            'starts_at'          => $startsAt,
            'visibility'         => 'public',
            'allow_registration' => true,
            'is_recurring'       => false,
            'is_paid'            => $paid,
            'price_minor'        => $paid ? (int) ($o['price_minor'] ?? 50000) : null,
            'price_currency'     => $paid ? 'RUB' : null,
            'payment_method'     => $paid ? (string) ($o['payment_method'] ?? 'tbank_link') : null,
            'age_policy'         => 'any',
        ]);

        $data = [
            'direction'         => $direction,
            'format'            => $format,
            'registration_mode' => 'single',
            'teams_count'       => (int) ($o['teams'] ?? 2),
            'game_subtype'      => $o['game_subtype'] ?? null,
            'game_min_players'  => $o['min_players'] ?? null,
        ];
        foreach (['game_libero_mode', 'game_registration_by_positions', 'game_registration_by_roles'] as $k) {
            if (array_key_exists($k, $o)) {
                $data[$k] = $o[$k];
            }
        }
        if (array_key_exists('reserve', $o)) {
            $data['game_reserve_players_max'] = $o['reserve'];
        }
        foreach ((array) ($o['gender'] ?? []) as $k => $v) {
            $data[$k] = $v; // game_gender_policy / _side / _max / _positions / _reg_starts_days_before
        }

        /** @var EventGameSettingsService $svc */
        $svc  = app(EventGameSettingsService::class);
        $game = $svc->normalizeGameDefaults($data, $direction, $format);
        $this->assertSame([], $game['errors'], 'normalizeGameDefaults: ' . json_encode($game['errors'], JSON_UNESCAPED_UNICODE));
        $data = $game['data'];

        $gender = $svc->normalizeGenderPolicy($data, $direction, $format, $game['minPlayers'] ?? null, $game['maxPlayers'] ?? null);
        $this->assertSame([], $gender['errors'], 'normalizeGenderPolicy: ' . json_encode($gender['errors'], JSON_UNESCAPED_UNICODE));
        $data = $gender['data'];

        $svc->createGameSettings($event, $data, $game, $gender, $direction);
        app(EventRoleSlotService::class)->clear($event);

        $occ = EventOccurrence::query()->forceCreate([
            'event_id'             => $event->id,
            'starts_at'            => $startsAt,
            'timezone'             => 'Europe/Moscow',
            'location_id'          => $this->locationId(),
            'uniq_key'             => 'event:' . $event->id . ':' . $startsAt->copy()->utc()->format('YmdHis'),
            'allow_registration'   => true,
            'registration_ends_at' => isset($o['registration_ends_in_hours'])
                ? now('UTC')->addHours((int) $o['registration_ends_in_hours'])
                : null,
        ]);

        return $occ->fresh(['event.gameSettings']);
    }

    /** Ещё одна дата той же серии через $days дней (время то же); событие помечается повторяющимся. */
    protected function addOccurrence(EventOccurrence $first, int $days, array $attrs = []): EventOccurrence
    {
        $event = $first->event;
        if (!$event->is_recurring) {
            $event->forceFill(['is_recurring' => true])->save();
        }
        $startsAt = \Illuminate\Support\Carbon::parse($first->starts_at, 'UTC')->addDays($days);

        return EventOccurrence::query()->forceCreate(array_merge([
            'event_id'           => $event->id,
            'starts_at'          => $startsAt,
            'timezone'           => $first->timezone,
            'location_id'        => $first->location_id,
            'uniq_key'           => 'event:' . $event->id . ':' . $startsAt->format('YmdHis'),
            'allow_registration' => true,
        ], $attrs))->fresh(['event.gameSettings']);
    }

    /**
     * Сохранение формы редактирования мероприятия (PUT /events/create/event_management/{id}) от организатора.
     * Минимальный набор обязательных полей + $fields поверх.
     */
    protected function updateEventHttp(EventOccurrence $occ, array $fields = [])
    {
        $event = $occ->event->fresh();
        $local = \Illuminate\Support\Carbon::parse($event->starts_at, 'UTC')->setTimezone($event->timezone)->format('Y-m-d H:i');

        return $this->actingAs($this->organizer())->put("/events/create/event_management/{$event->id}", array_merge([
            'title'        => $event->title,
            'direction'    => $event->direction,
            'format'       => $event->format,
            'starts_at'    => $local,
            'timezone'     => $event->timezone,
            'location_id'  => $event->location_id,
            'game_subtype' => $event->gameSettings?->subtype,
        ], $fields));
    }

    // ---------------------------------------------------------------- действия

    /** Запись через настоящий HTTP-путь POST /occurrences/{id}/join (Guard + persistRegistration). */
    protected function joinHttp(User $user, EventOccurrence $occ, ?string $position = null, array $extra = [])
    {
        $payload = array_merge($position !== null ? ['position' => $position] : [], $extra);

        return $this->actingAs($user)->postJson("/occurrences/{$occ->id}/join", $payload);
    }

    /** Прямая вставка активной записи (быстро заполнить основу, минуя Guard). */
    protected function seatDirect(EventOccurrence $occ, ?string $position, ?User $user = null): EventRegistration
    {
        $user ??= $this->makeUser();
        $reg = new EventRegistration();
        $reg->user_id       = $user->id;
        $reg->event_id      = $occ->event_id;
        $reg->occurrence_id = $occ->id;
        $reg->status        = 'confirmed';
        $reg->is_cancelled  = false;
        $reg->position      = $position;
        $reg->save();

        return $reg;
    }

    /** Заполнить все основные слоты события (по event_role_slots). Возвращает записи. */
    protected function fillMain(EventOccurrence $occ): array
    {
        $regs = [];
        foreach ($this->slots($occ) as $role => $max) {
            if ($role === 'reserve') {
                continue;
            }
            for ($i = 0; $i < $max; $i++) {
                $regs[] = $this->seatDirect($occ, $role);
            }
        }

        return $regs;
    }

    /** Карта role => max_slots из event_role_slots. */
    protected function slots(EventOccurrence $occ): array
    {
        return DB::table('event_role_slots')->where('event_id', $occ->event_id)
            ->orderBy('role')->pluck('max_slots', 'role')->map(fn ($v) => (int) $v)->all();
    }

    protected function activeRegs(EventOccurrence $occ): \Illuminate\Support\Collection
    {
        return EventRegistration::query()
            ->where('occurrence_id', $occ->id)
            ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
            ->whereNull('cancelled_at')
            ->get();
    }

    protected function isActive(EventOccurrence $occ, User $user): bool
    {
        return $this->activeRegs($occ)->contains('user_id', $user->id);
    }

    protected function inWaitlist(EventOccurrence $occ, User $user): bool
    {
        return OccurrenceWaitlist::query()->where('occurrence_id', $occ->id)->where('user_id', $user->id)->exists();
    }

    /** Отмена через Eloquent save() — так, как это делает persistCancellation (срабатывает Observer). */
    protected function cancelReg(EventRegistration $reg): void
    {
        $reg->refresh();
        $reg->is_cancelled = true;
        $reg->cancelled_at = now();
        $reg->status       = 'cancelled';
        $reg->save();
    }
}
