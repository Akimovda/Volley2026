# Аудит: резерв и лист ожидания в турнирных мероприятиях

Дата: 2026-06-15  
Страницы: `/events/376/tournament/setup?occurrence_id=12194` и `/events/376?occurrence=12194`

---

## Выявленные проблемы

---

### ПРОБЛЕМА 1 (критическая): `sendTeamToWaitlist` для `classic_team` — сломанная ветка

**Кнопка ⏳ на setup.blade.php:768** → `POST /events/{event}/teams/{team}/send-to-waitlist`

Для `classic_team` (`TournamentTeamController::sendTeamToWaitlist:385–396`):
1. Команда **удаляется** целиком
2. Участники добавляются в `occurrence_waitlist` через `WaitlistService::join()`

Но после этого они оказываются в мёртвой зоне:

| Проверка | Результат для classic_team |
|---|---|
| `players.blade.php:627` `$isTournament = in_array($regMode, ['team_classic','team_beach'])` | `true` |
| `players.blade.php:698` `$showWaitlist = !$isTournament && ...` | **скрыт** — игрок не видит свою позицию в очереди |
| `players.blade.php:833` `@if(!$isTournament)` — блок списка для организатора | **скрыт** — организатор тоже не видит |
| `WaitlistService:174–176` — autoBookNext для tournament | **return false** — авто-запись не работает |
| `WaitlistService:148–151` — onSpotFreed → `notifyNext()` | вызывает CheckWaitlistNotificationJob, но в очереди нет никого из нормального потока |

**Итог:** Участники добавляются в `occurrence_waitlist`, но:
- не видят себя там (UI скрыт для турниров)
- не могут выйти
- не будут авто-записаны
- организатор не видит их в очереди

Это **сломанный dead code** для `classic_team` турниров. Команда просто исчезает без трассировки.

---

### ПРОБЛЕМА 2: ⏳ кнопка `sendToWaitlist` — две разные логики в одном методе

`TournamentTeamController::sendTeamToWaitlist:363–396`:

```
beach_pair   → удалить команду + создать соло-пары → попадают в "⏳ Ищут партнёра" ✓ (видно)
classic_team → удалить команду + WaitlistService::join() → попадают в occurrence_waitlist ✗ (невидимо)
```

Для `beach_pair` это работает нормально — соло-пары видны в блоке "Ищут партнёра" на setup.  
Для `classic_team` — orphaned записи в таблице `occurrence_waitlist`, которые нигде не отображаются.

---

### ПРОБЛЕМА 3: Два разных механизма "резерва" для турниров

| Механизм | Таблица | Показывается где | Когда используется |
|---|---|---|---|
| `TournamentLeagueTeam.status='reserve'` | `tournament_league_teams` | setup.blade.php "⏳ Лист ожидания" | Лиговые турниры (`season_id != null`) |
| `EventTeam.reserve_position IS NOT NULL` | `event_teams` | players.blade.php `· X в резерве`, в счётчике | Нелиговые турниры |
| `occurrence_waitlist` | `occurrence_waitlist` | players.blade.php (ТОЛЬКО если `!$isTournament`) | Обычные мероприятия |

`sendTeamToWaitlist` для `classic_team` пишет в третий механизм — **не предназначенный для турниров** и скрытый для них в UI.

---

### ПРОБЛЕМА 4: `notifyNext()` — мёртвый путь для командных турниров

`WaitlistService::onSpotFreed:148–151`:
```php
if ($event->format === 'tournament') {
    if (!$isIndividualTournament) {
        $this->notifyNext($occurrence->id, $position); // ← вызывается для classic_team/team_beach
        return;
    }
}
```

`notifyNext` → `CheckWaitlistNotificationJob` — ищет кого-то в `occurrence_waitlist`.  
Для командных турниров там никто не стоит в нормальном потоке. Этот вызов ничего не делает, но добавляет джоб в очередь при каждом освобождении места.

---

## Что работает корректно (не трогать)

| Функционал | Статус |
|---|---|
| `occurrence_waitlist` для обычных мероприятий | ✅ работает |
| `occurrence_waitlist` для `tournament_individual` | ✅ работает (явно разрешено везде) |
| Лист ожидания лиги (`TournamentLeagueTeam status='reserve'`) на setup | ✅ работает |
| ⏳ кнопка для `beach_pair` → соло-пары в "Ищут партнёра" | ✅ работает |
| Резерв нелиговых турниров `EventTeam.reserve_position` в счётчике | ✅ работает |
| Блокировка `checkWaitlistGate` и `autoBookNext` для командных турниров | ✅ работает |

---

## Что нужно убрать / исправить

1. **Убрать ветку `classic_team` из `sendTeamToWaitlist`** (`TournamentTeamController:385–396`) — вызов `WaitlistService::join()` для классики не работает и вводит в заблуждение. Для классики кнопка ⏳ должна либо:
   - переводить команду в `EventTeam.reserve_position` (если нужен резерв), или
   - просто расформировывать (как `disbandTeam`)

2. **Убрать `$this->notifyNext()` в `WaitlistService::onSpotFreed`** для `format=tournament && !isIndividual` — лишний джоб в очередь без эффекта

3. **На странице setup.blade.php** для нелиговых `classic_team`: кнопка ⏳ по факту делает то же что и 🗑 (удаляет команду). Либо убрать её, либо перевести в `reserve_position`-механизм.
