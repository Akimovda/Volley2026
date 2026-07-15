# Аудит денормализованных кеш-счётчиков: `event_role_slots.taken_slots` и `event_occurrence_stats.registered_count`

Дата: 2026-07-16. Dev, branch `main`. ТОЛЬКО ЧТЕНИЕ — код и БД не менялись, только `/tmp/*.php` диагностические скрипты (SELECT-only, ничего не писали).

## Главный вывод (коротко)

- **`taken_slots`** — счётчик уже фактически МЁРТВ как источник бизнес-решений: полный grep по всей кодовой базе показал **ровно один** читатель значения (сама diagnostic-команда `event-slots:resync`, и то только для сравнения "было/стало" в логе перед перезаписью). Всё, что раньше читало эту колонку напрямую, уже переведено на live COUNT (комментарии в коде это прямо подтверждают: "раньше эти места читали event_role_slots.taken_slots напрямую... стабильно давало неверный результат"). **Премиса задачи «влияет на автоназначение из вейтлиста» — устарела**: `WaitlistService` уже использует `hasFreeSlot()`/`tryTakeSlot()` (оба — live COUNT), не читает саму колонку. Рекомендация: **A (полный выпил)**, причём с МЕНЬШИМ риском, чем ожидалось — переводить читателей никого не нужно, они уже переведены.
- **`event_occurrence_stats.registered_count`** — здесь ожидание задачи «дашборд её больше не читает» **верно только частично**. Есть **2 живых читателя**, оба нашёл аудит: (1) блок «Эффективность ботов» на `/org/dashboard` (НЕ тот блок, что чинили 15.07 — тот назывался «Загрузка мероприятий»); (2) публичный встраиваемый виджет организатора (`WidgetPublicController::getEvents()`) — реально показывает счётчик занятых мест **посетителям стороннего сайта**. Оба места конвертируются в live COUNT дёшево (ограниченный размер выборки, не N+1). Рекомендация: **A (полный выпил)**, но ФИКС читателей — обязательный первый шаг, а не факультативный (там реальный публичный трафик).

## Масштаб дрейфа в цифрах

| Счётчик | Всего строк-целей | Есть кеш-строка | Совпадает с live | Расходится с live | Худший разрыв |
|---|---|---|---|---|---|
| `taken_slots` (event_role_slots, по паре event+role) | 53 | 53 (создаётся всегда при syncRoleSlots) | для одноразовых событий: 8 из 10 совпадают | 2 одноразовых события; **13 из 13** пар «event+role» повторяющихся событий структурно не могут совпадать со всеми occurrences одновременно (один общий счётчик на N occurrences с разными реальными значениями) | event 367/setter: `taken_slots=5` не совпадает НИ С ОДНОЙ из 30 occurrences события (реальные значения только 0 или 6) |
| `registered_count` (event_occurrence_stats, по occurrence) | 358 occurrences всего | 48 (86.6% occurrences НИКОГДА не создавали кеш-строку — обычно потому что там просто не было активности, а не потому что путь дырявый) | 40 из 48 (83%) | 8 из 48 (16.7%) | ±2 (occurrences 12100, 12099, 12068, 12056) |

---

# ФАЗА 1 — АУДИТ

## Счётчик 1: `event_role_slots.taken_slots`

### Схема и модель
Таблица уникальна по `(event_id, role)` — **НЕ occurrence-scoped**: один счётчик на пару событие+роль, общий сразу на ВСЕ occurrences повторяющегося события. Это структурный корень проблемы, задокументированный в самом коде сервиса:
```php
/**
 * Живой COUNT активных регистраций на роль в рамках конкретной occurrence.
 * Единственный источник истины — event_role_slots.taken_slots не occurrence-scoped
 * (один счётчик на всё повторяющееся событие) и структурно не может быть верным.
 */
```

Модель `app/Models/EventRoleSlot.php` — тонкая, без бизнес-логики, только `fillable`/`casts` и `belongsTo(Event::class)`.

### Полный код `app/Services/EventRoleSlotService.php`
```php
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
        // ... создаёт/обновляет max_slots, при СОЗДАНИИ новой роли пишет taken_slots=>0
        // ... удаляет роли, отсутствующие в $roles
    }

    private function countActive(int $occurrenceId, string $role): int
    {
        return \DB::table('event_registrations')
            ->where('occurrence_id', $occurrenceId)
            ->where('position', $role)
            ->whereNull('cancelled_at')
            ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
            ->count();
    }

    /**
     * Try to take slot (atomic).
     * Uses actual registration count per occurrence — not the stale taken_slots counter.
     * Caller must hold pg_advisory_xact_lock(occurrence_id, roleKey) before calling.
     * Updates taken_slots to actual+1 so the counter stays in sync.
     */
    public function tryTakeSlot(Event $event, string $role, int $occurrenceId): bool
    {
        $slot = EventRoleSlot::where('event_id', $event->id)->where('role', $role)->first();
        if (!$slot) return false;

        $taken = $this->countActive($occurrenceId, $role);   // ← РЕШЕНИЕ принимается по live count
        if ($taken >= $slot->max_slots) return false;

        \DB::table('event_role_slots')->where('event_id', $event->id)->where('role', $role)
            ->update(['taken_slots' => $taken + 1]);          // ← taken_slots пишется ПОСЛЕ решения, чисто как зеркало
        $this->clear($event);
        return true;
    }

    /**
     * Предикат без побочных эффектов: есть ли живое свободное место на роль
     * прямо сейчас, для конкретной occurrence... раньше эти места читали
     * event_role_slots.taken_slots напрямую, что стабильно давало неверный
     * результат для повторяющихся событий (счётчик общий на все occurrences).
     */
    public function hasFreeSlot(int $occurrenceId, string $role): bool
    {
        $eventId = \DB::table('event_occurrences')->where('id', $occurrenceId)->value('event_id');
        if (!$eventId) return false;
        $slot = EventRoleSlot::where('event_id', $eventId)->where('role', $role)->first();
        if (!$slot) return false;
        return $this->countActive($occurrenceId, $role) < $slot->max_slots;   // ← опять live count
    }

    /**
     * Resync taken_slots to the actual count for a given occurrence.
     * Call after cancellation or any out-of-band change.
     */
    public function resyncTakenSlots(Event $event, string $role, int $occurrenceId): void
    {
        $actual = \DB::table('event_registrations')->where('occurrence_id', $occurrenceId)
            ->where('position', $role)->whereNull('cancelled_at')
            ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')->count();
        \DB::table('event_role_slots')->where('event_id', $event->id)->where('role', $role)
            ->update(['taken_slots' => $actual]);
        $this->clear($event);
    }

    public function clear(Event $event): void { Cache::forget($this->cacheKey($event)); }
}
```

### 1. ЧИТАТЕЛИ

Полный grep `\$slot->taken_slots` / `['taken_slots']` по всему `app/` и `resources/views/` — **ровно одно совпадение вне самого сервиса**:

**`app/Console/Commands/ResyncRoleSlotCounters.php:20,54,60`** (artisan-команда `event-slots:resync`):
```php
->select('ers.event_id', 'ers.role', 'ers.max_slots', 'ers.taken_slots');   // строка 20 — читает старое значение
...
if ((int) $slot->taken_slots !== $actual) {                                  // строка 54 — сравнение перед перезаписью
    DB::table('event_role_slots')->where(...)->update(['taken_slots' => $actual]);
    $this->line("event={$slot->event_id} role={$slot->role}: {$slot->taken_slots} → {$actual}"); // строка 60 — лог diff
}
```
Что сломается при неверном значении: ничего в бизнес-логике — это read-only сравнение ради консольного вывода "было→стало" перед тем же самым перезаписывающим `UPDATE`. Итоговое (правильное) значение вычисляется заново из `event_registrations` независимо от того, что там было раньше.

**Места, которые логически ДОЛЖНЫ БЫЛИ БЫ читать `taken_slots`, но на самом деле читают только `max_slots` + отдельный live-COUNT** (перечислены, т.к. формально работают с моделью `EventRoleSlot`/вызывают `getSlots()`, но саму больную колонку не трогают):
- `app/Http/Controllers/EventRegistrationsManagementController.php:93-108, 375-425, 586-634, 719-731` — список свободных мест, ручное добавление игрока, смена позиции, восстановление отменённой записи.
- `app/Services/EventRegistrationGuard.php:516-549` (`calculatePositions()`).
- `app/Services/WaitlistService.php:137-165, 590-626` (`occupiedPositions()` и внутренний предикат свободности для `join()`).
- `app/Services/BotAssistantService.php:241-280` (`pickBotPosition()`).
- `app/Http/Controllers/EventWaitlistManagementController.php:99,147` (`hasFreeSlot()` перед авто-букингом организатором).

Если бы эти места читали "гнилой" `taken_slots` (как было раньше, судя по комментариям) — заниженное значение дало бы ложное "место свободно" → пересадка сверх лимита; завышенное — ложное "мест нет" → блокировка реально свободного места. **Сейчас этот риск нейтрализован**, т.к. переведено на live COUNT.

### 2. ПИСАТЕЛИ

| Метод | Строка | Путь вызова | Покрыт? |
|---|---|---|---|
| `syncRoleSlots()` — пишет `taken_slots=>0` при создании роли | `EventRoleSlotService.php:49` | `EventManagementController.php:1214,1242`, `EventGameSettingsService.php:583,709` — админ. настройка структуры ролей организатором | Не про отмену — обнуляет при пересоздании структуры |
| `tryTakeSlot()` — пишет `taken+1` | `EventRoleSlotService.php:102` | (а) `EventRegistrationController.php:346-352` — self-service запись игрока (classic И beach, под `pg_advisory_xact_lock`); (б) `WaitlistService.php:389` (`autoBookNext()`, classic) | Инкремент корректен на обеих ветках |
| `resyncTakenSlots()` — полная перезапись на live | `EventRoleSlotService.php:153` | **Единственный вызов**: `EventRegistrationController.php:715-717`, метод `persistCancellation()`, вызывается ТОЛЬКО из `destroyOccurrence()` (роут `DELETE /occurrences/{occurrence}/leave` — self-cancel игрока), И ТОЛЬКО если `$event->direction === 'classic'` | **Дырявый**: beach self-cancel не ресинкает (условие direction отсекает); organizer cancel/destroy/updatePosition/addPlayer вообще не вызывают `EventRoleSlotService` |
| `ResyncRoleSlotCounters` (artisan) | `.php:55-58` | Только вручную/по крону, `php artisan event-slots:resync [--event_id=]` | Воркэраунд, не встроен в HTTP-цикл; берёт один occurrence на событие — при общем на все occurrences счётчике чинит "под" один конкретный occurrence, не под все сразу |

Прямых `DB::table('event_role_slots')->increment()/update()` вне сервиса в кодовой базе нет.

### 3. Карта путей отмены — кто трогает `taken_slots`

| Путь | Обновляет `taken_slots`? |
|---|---|
| Player self-cancel, classic (`EventRegistrationController::destroyOccurrence`) | **ДА** (`resyncTakenSlots`) |
| Player self-cancel, beach (тот же код, `direction==='beach'`) | **НЕТ** — условие `=== 'classic'` отсекает |
| Organizer cancel/restore (`EventRegistrationsManagementController::cancel`) | **НЕТ** |
| Organizer hard-delete (`::destroy`) | **НЕТ** |
| Organizer addPlayer | **НЕТ** — прямой insert/update в обход `tryTakeSlot()` |
| Organizer updatePosition | **НЕТ** |
| Waitlist autoBookNext, classic | **ДА, но только инкремент** (`tryTakeSlot`) |
| Waitlist autoBookNext, beach | **НЕТ** — своя ветка без `EventRoleSlotService` |
| Organizer правит очередь ожидания (`EventWaitlistManagementController`) | Косвенно через `autoBookNext`, только инкремент |

**Вывод**: декремент реален ровно в одном месте (self-cancel classic); почти все остальные пути — либо не трогают счётчик вовсе, либо только инкрементят. Счётчик системно "уезжает вверх" со временем — подтверждено данными выше (event 367/setter: значение не совпадает НИ С ОДНОЙ из 30 occurrences).

### 4. Вейтлист — где именно stale `taken_slots` мог бы дать неверное решение

**По факту — НИГДЕ, сейчас**. `WaitlistService::autoBookNext()`, `onSpotFreed()`, `join()` — все ветки классики идут через `hasFreeSlot()`/`tryTakeSlot()` (оба — live COUNT), beach — вообще отдельная ветка с прямым `COUNT` без `EventRoleSlotService`. Полный grep `WaitlistService.php` подтверждает: единственные обращения к `EventRoleSlotService` — `hasFreeSlot()` (строки 119, 149), `tryTakeSlot()` (строка 389), `getSlots()` (строки 143, 611, но там читается только `->max_slots`, не `->taken_slots`).

Это меняет оценку риска из легенды задачи: раньше (судя по комментариям в коде) прямое чтение `taken_slots` в вейтлисте РЕАЛЬНО было и ломало автоназначение для повторяющихся событий — но это уже исправлено предыдущей сессией/коммитом. Сегодня риск чисто гипотетический: если кто-то в будущем добавит код, читающий `taken_slots` напрямую (соблазн есть — имя колонки говорящее), он немедленно получит структурно неверные данные для любого повторяющегося события.

---

## Счётчик 2: `event_occurrence_stats.registered_count`

### Полный код `app/Services/EventOccurrenceStatsService.php`
```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Events\OccurrenceStatsUpdated;
use Illuminate\Support\Facades\Cache;

class EventOccurrenceStatsService
{
    /**
     * Получить количество зарегистрированных — live COUNT из event_registrations.
     * Не используем event_occurrence_stats: счётчик устаревает при массовых отменах через QueryBuilder.
     */
    public function getRegisteredCount(int $occurrenceId): int
    {
        return (int) DB::table('event_registrations')
            ->where('occurrence_id', $occurrenceId)
            ->whereNull('cancelled_at')
            ->where(fn($q) => $q->whereNull('is_cancelled')->orWhere('is_cancelled', false))
            ->where(fn($q) => $q->whereNull('status')->orWhere('status', '!=', 'cancelled'))
            ->count();
    }

    public function increment(int $occurrenceId): void
    {
        DB::statement('
            INSERT INTO event_occurrence_stats (occurrence_id, registered_count, created_at, updated_at)
            VALUES (?, 1, NOW(), NOW())
            ON CONFLICT (occurrence_id) DO UPDATE SET
                registered_count = event_occurrence_stats.registered_count + 1, updated_at = NOW()
        ', [$occurrenceId]);
        Cache::forget("event_page:{$occurrenceId}");
        $count = $this->getRegisteredCount($occurrenceId);          // ← live count, не кеш!
        event(new OccurrenceStatsUpdated($occurrenceId, $count));   // ← в broadcast идёт ЖИВОЕ значение
    }

    public function decrement(int $occurrenceId): void
    {
        DB::statement('
            INSERT INTO event_occurrence_stats (occurrence_id, registered_count, created_at, updated_at)
            VALUES (?, 0, NOW(), NOW())
            ON CONFLICT (occurrence_id) DO UPDATE SET
                registered_count = GREATEST(event_occurrence_stats.registered_count - 1, 0), updated_at = NOW()
        ', [$occurrenceId]);
        Cache::forget("event_page:{$occurrenceId}");
        $count = $this->getRegisteredCount($occurrenceId);
        event(new OccurrenceStatsUpdated($occurrenceId, $count));
    }
}
```

**Важная находка**: `increment()/decrement()` после записи в кеш-таблицу диспетчат `OccurrenceStatsUpdated` (реализует `ShouldBroadcast`, канал `occurrence.{id}`, событие `stats.updated`) — но в payload кладут результат `getRegisteredCount()` (live COUNT), НЕ значение из только что записанной кеш-строки. То есть даже если кеш врёт, реалтайм-broadcast всегда честный. Проверено: слушателей этого broadcast-канала на фронтенде (`resources/js`, `public/js`, кроме вендорного `echo.iife.js`) НЕ найдено — похоже, эта функциональность реального времени сейчас не подключена ни к какому UI (мёртвый broadcast, не относится напрямую к теме аудита, но раз уж всплыло — стоит знать при будущей уборке).

### Расхождение схемы с миграцией (побочная находка)
Миграция `database/migrations/2026_03_08_032137_create_event_occurrence_stats.php` объявляет только `occurrence_id` (PK), `registered_count`, `updated_at` — **колонки `created_at` в Blueprint НЕТ**. При этом реальная схема (`database/schema/pgsql-schema.sql:604-611`) содержит `created_at timestamp without time zone` — эта колонка появилась в БД в обход миграционной истории (вручную или отдельным SQL, не найденным ни в одной миграции). `increment()/decrement()` явно пишут `created_at` в raw `INSERT` — на текущей БД это работает (колонка физически есть), но при разворачивании окружения "с нуля" через `php artisan migrate:fresh` (без ручного вмешательства) эти `INSERT` упадут с `column "created_at" does not exist`. Не проверял/не трогал prod — фиксирую как находку для будущей миграции-уборки.

### 1. ЧИТАТЕЛИ (реальные, не через `getRegisteredCount()`)

**(1) `app/Http/Controllers/OrgDashboardController.php:130-158`** — блок `$botEffect` («🤖 Эффективность ботов» на `/org/dashboard`, **НЕ** тот блок «Загрузка мероприятий», который чинили 15.07):
```php
$botEffect = DB::table('event_occurrences as eo')
    ->join('events as e', 'e.id', '=', 'eo.event_id')
    ->join('event_occurrence_stats as eos', 'eos.occurrence_id', '=', 'eo.id')   // INNER JOIN!
    ->where('e.organizer_id', $orgId)
    ->where('eo.starts_at', '>=', now()->subMonths(3))
    ->select(
        DB::raw('SUM(CASE WHEN EXISTS(... u2.is_bot = true ...) THEN eos.registered_count ELSE 0 END)::float / NULLIF(COUNT(*), 0) as avg_with_bots'),
        DB::raw('SUM(CASE WHEN NOT EXISTS(...) THEN eos.registered_count ELSE 0 END)::float / NULLIF(COUNT(*), 0) as avg_without_bots'),
        DB::raw('COUNT(CASE WHEN EXISTS(...) THEN 1 END) as occurrences_with_bots'),
        DB::raw('COUNT(CASE WHEN NOT EXISTS(...) THEN 1 END) as occurrences_without_bots')
    )->first();
```
Рендерится в `resources/views/dashboard/org.blade.php:227-251` — 4 карточки («Мероприятий с ботами», «Без ботов», «Ср. записей с ботами», «Ср. записей без ботов») + текстовый вывод сравнения.

**Что сломается**: `INNER JOIN` (не `LEFT JOIN`, как было в старой версии «Загрузки мероприятий» до фикса 15.07) означает, что occurrences БЕЗ строки в `event_occurrence_stats` **вообще выпадают** из выборки — а таких, по нашим данным, **86.6%** всех occurrences. И `COUNT(*)`, и оба `SUM(...)` считаются только по оставшимся ~13%, у которых к тому же ~17% значений сами по себе неточны (±1-2). Итог: метрика "боты дают +N записей" строится на сильно смещённой (survivorship bias) и местами неточной выборке — то есть реального доверия к цифрам на этой карточке сегодня быть не должно, хотя явной ошибки/пустых ячеек (как было в «Загрузке мероприятий» до фикса) здесь нет — просто тихо неверные числа.

**(2) `app/Http/Controllers/WidgetPublicController.php:124-176`** — метод `getEvents()`, публичный встраиваемый виджет организатора (роут отдаёт JSON, который сторонний сайт рендерит для посетителей):
```php
$occurrences = \App\Models\EventOccurrence::query()
    ->join('events', ...)->leftJoin('locations', ...)->leftJoin('cities', ...)
    ->leftJoin('event_occurrence_stats', 'event_occurrence_stats.occurrence_id', '=', 'event_occurrences.id')  // LEFT JOIN
    ->where('events.organizer_id', $userId)
    ->where('events.allow_registration', true)
    ->whereRaw('(event_occurrences.is_cancelled IS NULL OR event_occurrences.is_cancelled = false)')
    ->where('event_occurrences.starts_at', '>', now())
    ->orderBy('event_occurrences.starts_at')->limit($limit)   // limit ≤ 50 (валидация в OrganizerWidgetController.php:36)
    ->select([..., 'event_occurrence_stats.registered_count', ...])
    ->get();
...
$taken = (int) ($occ->registered_count ?? 0);          // строка 170
$free  = max(0, $maxP - $taken);
$slotsInfo = ['taken' => $taken, 'max' => $maxP, 'free' => $free];   // уходит прямо в JSON виджета
```
**Что сломается**: `LEFT JOIN` + `?? 0` означает, что для occurrence без кеш-строки (86.6% случаев) виджет покажет **«занято 0 из N»** даже если реально есть записи — прямо на стороннем сайте, посетителям, которые не видят внутреннюю админку и не могут перепроверить. Это САМЫЙ пользователь-видимый риск из всего аудита: публичный виджет систематически занижает занятость почти для всех событий.

**Оценка производительности перевода на live COUNT**: обе точки — НЕ N+1. `$botEffect` — один агрегатный запрос, уже считает по многим occurrences разом (как и почти пофикшенный `$occurrenceLoad` 15.07 — тот же паттерн `fromSub` с per-occurrence CASE-подзапросом решит и это). Виджет — один запрос с `LIMIT ≤ 50`, замена `LEFT JOIN event_occurrence_stats` на скалярный коррелированный подзапрос `(SELECT COUNT(*) FROM event_registrations WHERE occurrence_id = event_occurrences.id AND ...)` в SELECT — не более 50 дополнительных index-scan по уже проиндексированному `occurrence_id`, стоимость пренебрежимо мала.

### 2. ПИСАТЕЛИ — 10 корректных точек вызова

| # | Файл:строка | Метод/контекст | Путь |
|---|---|---|---|
| 1 | `EventRegistrationController.php:460` | `persistRegistration()` — increment | Self-service запись игрока (classic+beach), HTTP |
| 2 | `EventRegistrationController.php:720` | `persistCancellation()` — decrement | Self-cancel игрока, HTTP (`DELETE /occurrences/{id}/leave`) |
| 3 | `EventRegistrationsManagementController.php:463` | `addPlayer()` — increment (новая запись) | Organizer добавляет игрока, HTTP |
| 4 | `EventRegistrationsManagementController.php:518` | `addPlayer()` — increment (восстановление отменённой) | Organizer, HTTP |
| 5 | `EventRegistrationsManagementController.php:775` | `cancel()` — increment (restore) | Organizer toggle cancel/restore, HTTP |
| 6 | `EventRegistrationsManagementController.php:777` | `cancel()` — decrement (cancel) | Organizer toggle cancel/restore, HTTP |
| 7 | `EventRegistrationsManagementController.php:873` | `destroy()` — decrement | Organizer hard-delete, HTTP |
| 8 | `BotAssistantService.php:236` | increment | Бот записывается на игру, job/scheduler |
| 9 | `BotAssistantService.php:339` | decrement | Бот отписывается, job/scheduler |
| 10 | `WaitlistService.php:485` | increment | `autoBookNext()` — авто-посадка из очереди |

Прямых `bypass`-записей в саму таблицу (минуя сервис) не найдено — весь путь записи идёт через `increment()`/`decrement()`.

### 3. Дырявые пути (найдены и подтверждены независимо, grep + чтение кода)

| Путь | Файл:строка | Что происходит | Почему не вызывает decrement |
|---|---|---|---|
| Удаление аккаунта пользователя | `AccountDeleteRequestController.php:24-29` | `DB::table('event_registrations')->where(...)->update(['is_cancelled'=>true,'cancelled_at'=>now()])` — массовая отмена ВСЕХ регистраций пользователя разом | Прямой QueryBuilder update без вызова сервиса — ровно та ситуация, которую описывает докстринг `getRegisteredCount()` |
| Автоматическое снятие неподтверждённой брони | `AutoUnconfirmBookingJob.php:33,51-52` | Job, `is_cancelled=>true` массово | Тот же паттерн — прямой update |
| Слияние дублей пользователей | `UserMergeService.php:97-102` (и ещё несколько мест 59-101, переносящих/отменяющих регистрации) | Массовые UPDATE регистраций secondary-аккаунта | Тот же паттерн |
| Административное ограничение пользователя (бан на мероприятия) | `AdminUserRestrictionController.php:175-186` (`dropUserRegistrationsForEvents()`, вызывается из `banEvents()`) | **Физический `DELETE`** (не soft-cancel!), без фильтра "только активные" — удаляет вообще все строки, включая уже отменённые | Метод возвращает только количество удалённых строк "для аудита", в `EventOccurrenceStatsService` не обращается вовсе |

Все 4 — административные/фоновые операции над МНОЖЕСТВОМ регистраций разом (не одиночная отмена игроком/организатором через штатный UI), что и объясняет, почему это НЕ основная причина 86.6%-разрыва (те occurrences просто никогда не имели активности), а причина именно ±1-2 дрейфа там, где кеш-строка всё-таки существует.

**Для контраста**: `EventRegistrationsManagementController::destroy()` (корректный путь №7 в таблице выше) — единственное место среди ВСЕХ путей отмены/удаления (что для `taken_slots`, что для `registered_count`), где явно проверяется `$wasActive = empty($row->cancelled_at) && !$row->is_cancelled && $row->status !== 'cancelled'` перед декрементом — т.е. разработчик здесь осознанно предусмотрел edge-case "не декрементировать за уже неактивную запись". В 4 дырявых путях выше такой аккуратности нет — там decrement просто отсутствует как класс, а не пропущен по логике.

**Побочная находка** (вне прямой темы аудита, но всплыла при проверке scheduled-команд): `CancelEventsByQuorum` (`events:cancel-by-quorum`, фикс которой делали 15-16.07) вообще не трогает `event_occurrence_stats` — при автоотмене occurrence по недобору кворума счётчик для этой occurrence не корректируется (хотя это отдельный вопрос — нужно ли вообще каскадно отменять сами регистрации при отмене occurrence по кворуму; сейчас `event_registrations` остаются активными, просто occurrence помечается `is_cancelled=true`). Не разбирал это отдельно, не в скоупе текущей задачи — фиксирую как связанное наблюдение.

### 4. Кандидат на выпил?

**Да, но не "ноль читателей"** — как и предполагалось в задаче ("event_occurrence_stats → скорее A, если аудит не найдёт живых читателей"), решение остаётся A, но потому что 2 найденных читателя дёшево переводятся на live COUNT (не потому что читателей нет).

---

# ФАЗА 2 — ПРЕДЛОЖЕНИЕ (без реализации)

## `event_role_slots.taken_slots` → **Стратегия A (полный выпил)**

Обоснование: колонка **уже сегодня** не имеет ни одного функционального читателя — только диагностическая artisan-команда, которая сама же его чинит. Все места, где решение реально принимается ("свободен ли слот"), используют `countActive()`/`hasFreeSlot()`/live COUNT. Плюс структурный порок (не occurrence-scoped) означает, что чинить write-пути (Стратегия B) бессмысленно в принципе — даже идеально закрыв все 6 дырявых путей отмены (раздел 3), счётчик всё равно физически не может быть верным сразу для всех occurrences повторяющегося события. Только полный выпил устраняет и дрейф, и саму возможность будущего бага ("кто-то решит прочитать говорящее по имени поле taken_slots напрямую").

Шаги (два этапа для безопасного отката):
1. Код перестаёт писать `taken_slots`: убрать запись из `syncRoleSlots()`, `tryTakeSlot()`, `resyncTakenSlots()` (оставить сами методы для расчёта live COUNT/max_slots — их сигнатуры не завязаны на чтение самой колонки, но `tryTakeSlot`/`resyncTakenSlots` физически пишут в неё — эти `UPDATE`-вызовы убрать).
2. Удалить artisan-команду `event-slots:resync`/`ResyncRoleSlotCounters.php` (или оставить как no-op с предупреждением на переходный период).
3. Отдельным более поздним коммитом — миграция `DROP COLUMN taken_slots` (не сразу, чтобы был путь отката без миграции, если что-то всплывёт).
4. Партиальный индекс `idx_event_role_slots_available` (по `taken_slots < max_slots`) — снести вместе с колонкой, он и сейчас не используется ни одним запросом (проверено grep — нигде в коде нет `WHERE taken_slots < max_slots`).

## `event_occurrence_stats.registered_count` → **Стратегия A (полный выпил), но фикс читателей — ОБЯЗАТЕЛЬНЫЙ первый шаг**

Обоснование: 2 живых читателя есть, но оба недороги в переводе на live COUNT (агрегатный `fromSub`-паттерн — тот же, что уже применён 15.07 для `$occurrenceLoad` в `OrgDashboardController`; виджет — скалярный подзапрос при `LIMIT≤50`). Учитывая, что один из читателей — публичный виджет с реальным внешним трафиком (наиболее пользователь-видимый риск во всём аудите), тянуть с фиксом нежелательно.

Шаги (два этапа):
1. Перевести `$botEffect` (`OrgDashboardController.php:130-158`) на live COUNT по паттерну `occurrenceLoad` (одна SQL с подзапросом на occurrence, не через `event_occurrence_stats`).
2. Перевести `WidgetPublicController::getEvents()` (строки 137,149,170) — заменить `LEFT JOIN event_occurrence_stats` на коррелированный `(SELECT COUNT(*) FROM event_registrations WHERE occurrence_id=... AND ...)`.
3. После перевода обоих читателей — убрать `increment()`/`decrement()` как write-пути везде (10 точек вызова из раздела 2), оставить `getRegisteredCount()` (он и так уже live COUNT, менять не нужно — это финальная целевая реализация).
4. Разобраться с `OccurrenceStatsUpdated`/broadcast — раз слушателей на фронте нет, можно убрать вызов `event(new OccurrenceStatsUpdated(...))` вместе с write-путями (или оставить, если планируется когда-то подключить реалтайм-виджет — решение не техническое, а продуктовое, выношу как открытый вопрос).
5. Отдельным более поздним коммитом — миграция `DROP TABLE event_occurrence_stats`.
6. Заодно закрыть находку про рассинхрон миграции/схемы (`created_at` в реальной БД, но не в Blueprint) — либо зафиксировать миграцией "добавить created_at" перед дропом (для консистентности истории), либо просто не переживать, раз таблица всё равно удаляется целиком.

## Оценка производительности (что просили проверить отдельно)

- **Карточки `/events`** — уже ходят в `/occurrences/{id}/availability` по одному occurrence за раз (существующий паттерн, не трогаем).
- **`/org/dashboard` `$botEffect`** — один агрегатный запрос на много occurrences разом (не цикл) → перевод на live COUNT через `fromSub`+CASE-подзапрос, как в `$occurrenceLoad`, — тоже один запрос, не N+1.
- **Виджет `WidgetPublicController`** — `LIMIT` жёстко ограничен валидацией `max:50` (`OrganizerWidgetController.php:36`) → даже наивный вариант (без выноса в один общий агрегат, а просто добавить скалярный подзапрос в SELECT) — до 50 дополнительных index-scan по `occurrence_id`, не проблема.
- **`EventRegistrationsManagementController::index()`** (список регистраций организатора, читает `EventRoleSlotService::getSlots()`) — уже использует `max_slots` + отдельный `groupBy('position')` count по ОДНОЙ occurrence за раз (не список occurrences) — не в зоне риска этого аудита, не трогаем.
- Явных мест со списком/циклом occurrences, которые сейчас читают именно `taken_slots`/`registered_count` в цикле по каждой строке (реальный N+1), — **не найдено**. Единственный кандидат на потенциальный N+1 — если перевод виджета на live COUNT сделать через Eloquent-цикл `foreach ($occurrences as $occ) { $occ->registered_count = ... }` вместо одного SQL с подзапросом в SELECT — этого делать не нужно, писать сразу как один запрос (как и предложено в шаге 2 выше).

## Открытые вопросы к решению пользователя

1. `event_role_slots` — удалять write-пути и колонку одним PR или в два отдельных коммита (код → потом миграция)? Задача явно просит "в два этапа", уточняю просто порядок/тайминг.
2. `OccurrenceStatsUpdated`/broadcast-канал `occurrence.{id}` — удалить вместе со счётчиком (слушателей на фронте не найдено) или оставить задел на будущее?
3. Расхождение схемы/миграции (`created_at` в БД, но не в Blueprint) — чинить отдельной миграцией для консистентности истории перед дропом таблицы, или не важно раз всё равно удаляем?
4. 4 "дырявых" пути записи для `event_occurrence_stats` (AccountDeleteRequestController и т.д.) — если решение по счётчику будет B (не A) для какого-то из двух счётчиков, эти 4 места нужно будет доработать явно; при выборе A (мой текущий вывод) — можно игнорировать, т.к. таблица всё равно уходит.
