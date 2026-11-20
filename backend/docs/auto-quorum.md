# Авто увеличение кворума — спецификация (решения владельца 2026-11-20)

## Где работает (v1)
- format: game, training_game. Турниры, мастер-классы — нет.
- Классика: registration_mode=single (все схемы). Пляж: single, в т.ч. «по амплуа» (Бл+Защ).
- НЕ в v1 (этап 4): классика team, пляж mixed_group.
- Нельзя совместить с collect_stats («Статистика» допускает ≤2 команд) — валидация при сохранении.

## Настройки (event_game_settings)
- auto_quorum_enabled (bool, false)
- auto_quorum_players_per_team (smallint null): «игроков на команду/пару для расширения». null → по умолчанию players_per_team − 1 для классики (4-2/5-1 → 5, 5-1 с либеро → 6), 2 для пляжа.
- auto_quorum_max_teams (smallint null): максимум команд/пар, ≥ teams_count.
- Форма создания: тумблер сразу после подсказки «Для классики: либо одиночная запись…» в блоке «Режим регистрации».
- Форма редактирования: сразу после «Макс. игроков», перед «Гендерными ограничениями».
- При включении — два поля + живой текст расчёта: «3-я команда откроется при 15 записавшихся, 4-я — при 20».
- Менять можно при наличии записей. Нельзя поставить максимум меньше, чем уже расширилась любая будущая дата. Выключение не сворачивает уже расширенные даты.

## Расчёт
- N = основа + запасные (position='reserve') + лист ожидания (occurrence_waitlist) на эту дату.
- Команда k открывается при N ≥ k × perTeam. Ступени по +1, до max. Пример: perTeam=5, base=2 → 3-я при 15, 4-я при 20.
- Расширяется только конкретная дата (event_occurrences.quorum_teams_count), не серия.
- Только рост. При отменах не сворачиваем.
- Работает пока дата не началась и регистрация открыта.

## Перевод в основу
- Только из листа ожидания, существующим WaitlistService::autoBookNext по ролям новых слотов (очередь, Premium, гендер, оплата, уведомления — штатно).
- Только на свободные места по амплуа; кому нет места — остаются в листе ожидания.
- Запасные остаются запасными (организатор переводит вручную).
- Пляж «по амплуа»: новая пара = +1 blocker +1 defender.

## Гендерные квоты
- Масштабируются пропорционально числу команд даты: квота_даты = floor(gender_limited_max / base_teams × teams_даты).

## Уведомления
- Организатору: новый тип event_quorum_expanded «Игра {дата} расширена до N команд — проверьте площадку», каналы как у уведомлений о записи.
- Игрокам: стандартное «вы записаны из листа ожидания» (+ оплата как обычно).
- После расширения — RefreshOccurrenceAnnouncementJob.
- Второй корт автоматически не бронируем.

## Защита от гонок
- Отдельный advisory lock на дату (константа QUORUM_KEY ≠ crc32(position)); пересчёт под замком; условный UPDATE quorum_teams_count; журнал event_occurrence_quorum_log.
- Проверка вызывается ПОСЛЕ коммита записи (не внутри транзакции): запись, вступление в лист ожидания, ручное добавление организатором, автозапись по абонементу/Premium; страховочный проход планировщика раз в 5 мин.

## Этапы (каждый — отдельный деплой после ОК владельца)
1. Тесты на текущую запись/резерв/автоперевод — без изменения поведения.
2. Ёмкость даты: слоты и max_players считаются с учётом даты (EventRoleSlotService, Guard, EventOccurrence::effectiveMaxPlayers, WaitlistService, анонсы, NotifyOrganizerRegistrationJob, EventIndexService, FriendlyGameService). Внешне ничего не меняется.
3. Авторасширение: настройки, форма, AutoQuorumService::maybeExpand, журнал, уведомления, гендерные квоты.
4. (позже) Классика team: +1 команда при ≥3 в листе ожидания; пляж mixed_group: расширение кратно (пример: кворум 6 → при 3 в листе ожидания до 12), значение задаёт организатор.

---

## Дополнение владельца (2026-11-20): анонсы
- Нововведение затрагивает анонсы в каналах: после расширения анонс обновляется, а список и «Осталось мест: X из Y» должны авто-расширяться до новой ёмкости даты.
- Сейчас (этап 1, зафиксировано тестом) `OccurrenceAnnouncementMessageBuilder` берёт `max_players` и `reserve_players_max` из **настроек мероприятия** (`loadGameSettings()`), а не даты — на этапе 2 перевести на ёмкость даты.

## Этап 2 — ёмкость даты (на проде с 2026-11-20, `main` 3fb430ea)
- Колонка `event_occurrences.quorum_teams_count smallint NULL` (миграция `2026_11_21_100000_…`). NULL — дата не расширена.
- Сервис `App\Services\OccurrenceLimitService` (не путать с `OccurrenceCapacityService` — площадка/брони):
  `isExpanded`, `teamsFor` (quorum ?? override.teams_count ?? event.teams_count), `baseTeams`, `perTeamRoles`/`teamSize` (из `GameCalculator` по схеме даты; «без амплуа» — player = размер команды; пляж — player = размер пары, «По амплуа» — Бл 1 + Защ 1; пляжная 4×4 → ключ `4x4_beach`), `roleLimits`/`roleLimit`, `maxPlayers`, `seriesMaxPlayers`, `reserveMax`.
- **Инвариант:** не расширенная дата возвращает ровно прежние значения — `max_slots` слотов; `maxPlayers` = `occ.max_players ?? event.max_players` (как `effectiveMaxPlayers()`); `seriesMaxPlayers` = `event.max_players` (для мест, что раньше читали только мероприятие: анонс, пляжная ветка листа ожидания, быстрая запись на пляж, лимит листа ожидания). Новый расчёт — только у расширенной даты. Запасные не масштабируются.
- Потребители: `EventRoleSlotService::tryTakeSlot/hasFreeSlot` (через `slotLimitForOccurrence`: при NULL — один `SELECT quorum_teams_count`, модель даты не грузится), `EventRegistrationGuard` (maxPlayers + лимиты ролей в `calculatePositions` → свободные позиции, запасные, waitlist-гейт), `EventOccurrence::effectiveMaxPlayers()`, `WaitlistService` (пляж в `autoBookNext`, `isSpotAvailable`), `OccurrenceAnnouncementMessageBuilder` («Осталось мест N из M»), `NotifyOrganizerRegistrationJob`, `EventIndexService` (лента и карточки), `FriendlyGameService::addBotTeam` (лимит команд у расширенной даты игры), а также `EventRegistrationController` (быстрая запись на пляж без позиции), `EventRegistrationsManagementController` (ручное добавление/смена позиции/восстановление организатором, счётчики страницы), `OccurrenceWaitlistController` (лимит листа ожидания), `RepeatChannelAnnouncements`.
- Не переведены (сознательно): `BotAssistantService` (у расширенной даты боты займут меньше — безопасно), `checkGenderQuotaForUser` (масштаб квот — этап 3), страница события «N–M игроков» (`events/show/description.blade.php`, уровень мероприятия), `TrainerAnalyticsService`, `FriendlyGameService::botTeamComposition` (состав одной команды от даты не зависит).
- Advisory-локи записи (дата, позиция) не менялись.
- Тесты: `tests/Unit/OccurrenceLimitServiceTest.php`, `tests/Feature/DateCapacityTest.php`; тесты этапа 1 — без правок, зелёные.

## Этап 3 — авторасширение (ветка `auto-quorum-stage3`, на проде — после ОК владельца)
- **A. Ёмкость даты = число команд.** «Команд на этой дате» в форме даты (`event_occurrence_game_settings.teams_count`) реально меняет ёмкость: `OccurrenceLimitService::hasOwnTeams()` → лимиты ролей по схеме; `occurrence.max_players = размер команды × команд даты` (`derivedMaxPlayers()`) при сохранении даты и при правке серии, в т.ч. у `is_individually_edited` (своё число команд/авторасширение сохраняется, иначе — команды серии). `seriesMaxPlayers` убран: анонс, пляж, лист ожидания читают `maxPlayers` даты.
- **B. Настройки:** `event_game_settings.auto_quorum_enabled / auto_quorum_players_per_team / auto_quorum_max_teams`, `event_occurrences.quorum_expanded_at`, журнал `event_occurrence_quorum_log`. Валидация (создание и редактирование) — `AutoQuorumService::validateSettings()`: game/training_game, одиночная запись, не со «Статистикой», максимум ≥ команд и ≥ уже расширенных будущих дат, игроков на команду 1–20. Выключение не сворачивает.
- **C. Форма:** партиал `events/_partials/auto_quorum.blade.php`, переключатель `.up-switch` (как в профиле). perTeam по умолчанию: классика — размер команды − 1 (4-2/5-1 → 5, с либеро → 6), пляж — 2. При включении максимум предзаполняется «команд + 2».
- **D. `AutoQuorumService::maybeExpand()`:** `pg_advisory_xact_lock(occurrence_id, QUORUM_KEY = -20261120)` (отрицательный — не пересекается с `crc32(position) & 0x7fffffff`); N = активные (основа + запасные) + лист ожидания; `target = min(max, max(текущие, floor(N / perTeam)))`; условный `UPDATE … WHERE COALESCE(quorum_teams_count, base) = old` (идемпотентно); журнал. После коммита — `autoBookNext` по ролям новых слотов (роль × добавленных команд; пляж «По амплуа» +1 Бл +1 Защ), `promoted_user_ids` в журнал, `RefreshOccurrenceAnnouncementJob`, `NotifyOrganizerQuorumExpandedJob` (тип `event_quorum_expanded`, организатор + помощники, каналы как у уведомлений о записи). Гендерная квота даты — `OccurrenceLimitService::genderLimitFor()`. `BotAssistantService` — ёмкость даты.
- **E. Точки вызова** (`safeMaybeExpand`, после коммита, ошибка → лог + Sentry, запись не ломается): `storeOccurrence`, `WaitlistService::join`, ручное добавление организатором, `PremiumAutoBookingJob` / `AutoBookingSubscriptionJob`, сохранение настроек мероприятия (`expandEventDates`), `auto-quorum:sweep` каждые 5 минут.
- **F.** Страница «Управление записями»: «Расширено до N команд (дата/время)».
- Тесты: `tests/Feature/AutoQuorumTest.php`, `tests/Unit/AutoQuorumCalcTest.php`; два теста этапа 1 про странности №3/№4 переписаны под правило A.

## Решения владельца по этапу 3 (2026-11-20)
- perTeam по умолчанию для пляжа = размер пары (2×2 → 2, 3×3 → 3, 4×4 → 4).
- Уведомление о расширении (`event_quorum_expanded`) — организатору и помощникам всегда, независимо от флага уведомлений о записях игроков.
- Тексты ru — правки отдельным коммитом после деплоя этапа 3.

## Дальше (бэклог)
- Горизонтальный overflow +67px в форме редактирования мероприятия на 1280 px (был до авто-кворума, остаётся со скрытым блоком тумблера).
- Дата со своей схемой (переопределение `subtype` в форме даты, напр. 5-1 в серии 4-2): роли новой схемы не появляются — слоты берутся из `event_role_slots` мероприятия.
- Тесты на вызов `safeMaybeExpand` в `PremiumAutoBookingJob` и `AutoBookingSubscriptionJob`.
- Этап 4: классика `team` (+1 команда при ≥3 в листе ожидания) и пляж `mixed_group` (расширение кратно, значение задаёт организатор).

## Тесты (этап 1)

**Где и на чём.** На Contabo (`185.225.232.210`), локальный PostgreSQL 16 (как на проде — 16.x), отдельная БД `volley_test` и пользователь `volley_test` (только localhost, `scram-sha-256`, сессия в UTC: `ALTER DATABASE volley_test SET timezone TO 'UTC'`). Прод-БД и прод-креды тесты не используют.
- Настройки — `backend/.env.testing` (в `.gitignore`, права 600): `DB_CONNECTION=pgsql`, `DB_HOST=127.0.0.1`, `DB_DATABASE=volley_test`, `DB_USERNAME=volley_test`, `DB_PASSWORD=…`, `APP_KEY`.
- Конфиг PHPUnit — `phpunit.pgsql.xml` (Unit + доменные Feature). Обычный `phpunit.xml` (sqlite) не трогали.
- **Не запускать на pgsql стандартные тесты Jetstream** (`RefreshDatabase`): они делают `migrate:fresh` и ломают `volley_test`. Поэтому в `phpunit.pgsql.xml` они не включены; новые доменные тесты добавлять туда `<file>`-ом.
- Предохранители: `tools/test-pgsql.sh` останавливается, если `DB_HOST` не localhost или БД не `volley_test`, и печатает, куда подключается; `tests/Support/PgsqlTestCase` пропускает тест при чужом подключении.

**Запуск** (на Contabo нет PHP — PHP 8.3 в Docker-образе `volley-php83`, `/root/volley-testenv/Dockerfile`):
```bash
/root/volley-testenv/run.sh                              # весь phpunit.pgsql.xml
/root/volley-testenv/run.sh --filter RegistrationFlowTest
/root/volley-testenv/run.sh rebuild                      # пересоздать схему volley_test
```
`run.sh` = `docker run --rm --network host -v /root/repo-check/Volley2026:/repo -w /repo/backend volley-php83 tools/test-pgsql.sh "$@"`. На машине с PHP 8.3 и pgsql — напрямую `tools/test-pgsql.sh …` из `backend/`.

**Схема БД.** `rebuild` = `database/schema/pgsql-schema.sql` + все миграции по одной (дамп новее своей таблицы `migrations`: миграции, падающие с «already exists», отмечаются выполненными) + `BrandSeeder` (без бренда по умолчанию HTTP-запросы падают). Первичная настройка (один раз, от root):
```sql
CREATE ROLE volley_test LOGIN PASSWORD '…';
CREATE DATABASE volley_test OWNER volley_test;
ALTER DATABASE volley_test SET timezone TO 'UTC';
```
`composer install` (с dev-зависимостями) — в `backend/vendor` через тот же образ.

**Что есть.**
- `tests/Support/PgsqlTestCase.php` — база: `DatabaseTransactions` (каждый тест откатывается), `Http::fake()`, `Queue::fake()`, Sentry отвязан.
- `tests/Support/BuildsGameEvents.php` — билдер «игра + дата» тем же путём, что создание мероприятия (`normalizeGameDefaults` → `normalizeGenderPolicy` → `createGameSettings`): `classic42()`, `classic51()`, `classic51Libero()`, `classicNoPositions()`, `beach2x2()`, `beach2x2Roles()`; опции `teams`, `reserve`, `min_players`, `paid`, `payment_method`, `gender`, `format`, `starts_in_hours`, `registration_ends_in_hours`. Хелперы: `joinHttp()`, `seatDirect()`, `fillMain()`, `cancelReg()`, `addOccurrence()`, `updateEventHttp()`, `makeUser()`, `makePremium()`.
- `tests/Feature/RegistrationFlowTest.php` — характеризационные тесты текущего поведения; `tests/Unit/GameCalculatorTest.php`, `tests/Unit/EventRegistrationRulesTest.php`.
- Отчёт этапа 1 — `report/auto_quorum_stage1.md` (локально, в git не коммитится).
