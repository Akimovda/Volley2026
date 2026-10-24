# Дашборд организатора, аналитика, кеш-счётчики

_Вынесено из CLAUDE.md 2026-10-23 без изменения содержания. Полный исходник: docs/archive/CLAUDE.full-2026-10-23.md_

## Дашборд организатора и аналитика игроков

### Файлы
- Дашборд: `app/Http/Controllers/OrgDashboardController.php` → `resources/views/dashboard/org.blade.php`
- Аналитика игроков: `app/Http/Controllers/OrgPlayersController.php` → `resources/views/dashboard/org_players.blade.php`
- Маршруты: `/org/dashboard` (org.dashboard), `/org/players` (org.players), `/org/tournament-analytics` (org.tournament-analytics)

### Паттерн multi-period топ-игроков
- Один SQL-запрос с `COUNT(CASE WHEN er.created_at >= NOW() - INTERVAL '30 days' THEN 1 END)` для всех периодов одновременно
- Передавать всё в `@json($topPlayers)`, JS-переключатель фильтрует и сортирует массив по нужному полю
- Так избегаем 5 отдельных запросов к БД

### users.gender — значения 'm' / 'f'
- **КРИТИЧНО**: `users.gender` хранит `'m'` и `'f'`, НЕ `'male'`/`'female'`
- При `->keyBy('gender')` обращаться через `->get('m')` и `->get('f')`
- Аналогично при любых WHERE-условиях по полу

### Блоки страницы /org/players
- Сводка (4 карточки), топ активных с периодами (JS-табы), новые за 30 дней, риск оттока (≥3 визита + последний >60 дней назад), распределение по полу/уровням (Chart.js), часто в резерве (position='reserve')
- Чтобы добавить новый блок — добавлять запрос в `OrgPlayersController::index()` и секцию в blade

### Блок «История посещений» на /org/players (2026-10-22, Pro)
- Игрок (поиск ТОЛЬКО среди аудитории организатора) + диапазон дат → список «Название, дата время - позиция», 10 на страницу, экспорт PDF (`org_players_history_pdf`) и настоящий XLSX (`SimpleXlsx`). Роуты `org.players.history*`, все с `organizer.pro`; чужой игрок → 404.
- «Посещал» = прошедший неотменённый тур с неотменённой регистрацией (отметки «пришёл» в системе нет). `eo.starts_at` — timestamp БЕЗ tz (UTC): границы дат считаются в tz организатора, показ — в tz тура.

### Блок «Загрузка мероприятий» на /org/dashboard — баг с event_occurrence_stats (исправлен 2026-07-15)
- **Баг**: старый запрос `$occurrenceLoad` брал `SUM/AVG` по `event_occurrence_stats.registered_count` (LEFT JOIN) — это **кеш**, который не обновляется при массовых отменах через `EventRegistrationsManagementController::cancel()` (прямой `DB::table()->update()`, без вызова `EventOccurrenceStatsService`). У большинства occurrences строки в `event_occurrence_stats` вообще не было (`eos=NULL`) → `SUM`/`AVG` в PostgreSQL игнорируют NULL (не считают за 0) → «Всего записей» пустое, а не `0`, для событий без строки в кеше. Дополнительно: PostgreSQL `ORDER BY ... DESC` кладёт `NULL` **первыми** (не последними, вопреки интуиции) → `LIMIT 10` в первую очередь набирал мероприятия без данных, вытесняя из топа мероприятия с реальными записями (эффект «бар только у одного мероприятия из 4+»). Вместимость (`egs.max_players`) бралась без учёта типа турнира — для командных турниров (`team_classic`/`team_beach`) это состав ОДНОЙ команды, а не число команд, давая неверный %.
- **Фикс**: живой `COUNT` вместо кеша — `event_registrations` (с условиями как в `EventOccurrenceStatsService::getRegisteredCount()`) для обычных/`tournament_individual`/`king_beach`, `event_teams` (статусы `draft/ready/pending_members/submitted/confirmed/approved`, как в `EventRegistrationGuard`) для командных турниров. Вместимость — тот же приоритет `tournament_teams_count → event_tournament_settings.teams_count → event_game_settings.teams_count` для команд, `event_tournament_settings.total_players_max` для individual/king_beach, `event_game_settings.max_players` для обычных. Все три цифры (бар/«Всего записей»/«Средняя загрузка») по-прежнему из ОДНОГО SQL-запроса (`fromSub` — подзапрос считает per-occurrence `registered`/`capacity`, внешний `GROUP BY` агрегирует). Отменённые occurrences (`is_cancelled=true`) исключены из выборки — иначе события, где ВСЕ occurrences отменены, занимали место в `LIMIT 10` пустыми строками.
- Подробности диагностики и проверка на данных организатора 260 (dev) — `report_org_dashboard_load_2026-07-15.md` в корне backend.
- Дрейф самого `event_occurrence_stats` (упомянутый как «не пофикшено» в этом же коммите) закрыт полностью 2026-07-16 — см. следующий раздел.
- Превышение 100% загрузки, когда фактических регистраций больше `max_players` за счёт резерва (`reserve_players_max` не учтён в знаменателе) — отдельное наблюдение, не трогали, не относится к кеш-счётчикам.

## Выпил денормализованных кеш-счётчиков (2026-07-16)

### `event_role_slots.taken_slots` — write-пути удалены
- Колонка структурно не могла быть верной: НЕ occurrence-scoped (`UNIQUE(event_id, role)` — один счётчик на ВСЕ occurrences повторяющегося события сразу). Источник истины — live COUNT (`EventRoleSlotService::countActive()`/`hasFreeSlot()`/`tryTakeSlot()`, включая `WaitlistService::autoBookNext()`) — так было и раньше, колонка нигде не читалась для бизнес-решений.
- `tryTakeSlot()`/`syncRoleSlots()` больше не пишут `taken_slots`; `resyncTakenSlots()` удалён вместе с единственным вызовом (`EventRegistrationController::persistCancellation()`).
- `php artisan event-slots:resync` — команда осталась (файл не удалён), но теперь no-op с warning об устаревании.
- НЕ done: `DROP` колонки `event_role_slots.taken_slots` и партиального индекса `idx_event_role_slots_available` — отдельной миграцией после недели наблюдения.

### `event_occurrence_stats` — write-пути + таблица-кандидат на DROP
- Живых читателей было 2 (не ноль): `OrgDashboardController::$botEffect` и `WidgetPublicController::getEvents()` — оба переведены на live COUNT (`fromSub`+CASE / коррелированный скалярный подзапрос) ДО удаления write-путей.
- Убраны все вызовы `increment()`/`decrement()`: `EventRegistrationController`, `EventRegistrationsManagementController`, `BotAssistantService`, `WaitlistService::autoBookNext()`. Соседний `$wasActive`-гейт перед `WaitlistService::onSpotFreed()` сохранён — трогали только вызов статистики.
- `EventOccurrenceStatsService` теперь содержит только `getRegisteredCount()` (живой COUNT), используется `GET /occurrences/{id}/stats` — единственный оставшийся потребитель, сервис целиком не удалять.
- Удалён `app/Events/OccurrenceStatsUpdated.php` (мёртвый broadcast, слушателей не было).
- НЕ done: `DROP` таблицы `event_occurrence_stats` — отдельной миграцией после недели наблюдения.
