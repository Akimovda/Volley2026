# Мероприятия: окна регистрации, occurrence, режимы, меню организатора

_Вынесено из CLAUDE.md 2026-10-23 без изменения содержания. Полный исходник: docs/archive/CLAUDE.full-2026-10-23.md_

## Окно регистрации (паттерн)
- Данные хранятся как UTC-метки: registration_starts_at, registration_ends_at, cancel_self_until
- При отображении формы редактирования — вычислять обратно из diff UTC-меток (как в occurrence_edit.blade.php)
- Формат: часы+минуты split (select h + select m) + hidden total_minutes + JS change→sync
- event_management_edit reg_starts: два поля reg_starts_days_before + reg_starts_hours_before; вычислять через timestamp diff: days=floor(diffSec/86400), hours=floor((diffSec%86400)/3600)
- НЕ использовать hardcoded old('field', 60) без $savedValue из модели
- step2 create: selects имеют name="reg_ends_h","reg_ends_m","cancel_lock_h","cancel_lock_m","reg_starts_d","reg_starts_h"
- EventOccurrenceService::buildRegistrationWindows() читает эти поля и вычисляет minutes (приоритет перед hidden полями)
- Scheduled expand: events:expand-recurring работает ежедневно в 03:10 и перезаписывает окна регистрации у всех future occurrences

## Occurrence override паттерн
- NULL в occurrence = наследуется от серии
- Значение записывается ТОЛЬКО при отличии от event
- editOccurrence передаёт effective-переменные через $eff() хелпер
- EventShowService::handle() накладывает overrides ПОСЛЕ Cache::remember
- **effectiveCancelSelfUntil() баг**: метод возвращает `occurrence.cancel_self_until ?? event.cancel_self_until`. Установить NULL только в occurrence недостаточно — fallback на event срабатывает автоматически. Чтобы снять ограничение для конкретной occurrence нужно обнулять ОБА поля: и occurrence, и event. Иначе истёкший event.cancel_self_until блокирует выход даже при occurrence = NULL. Тот же паттерн применим ко всем `effective*`-методам модели.
- **effectiveCancelSelfUntil() и повторяющиеся серии**: форма event_management/edit пересчитывает event.cancel_self_until от event.starts_at (первый тур, обычно в прошлом). При следующем сохранении event.cancel_self_until снова станет истёкшим → снова заблокирует выход. Долгосрочный фикс: добавить sentinel-значение (например far future) или хранить смещения (minutes_before) вместо абсолютных дат на уровне occurrence.
- **event_management/edit — не обновлял future occurrences серии**: `updateOrCreate` в `EventManagementController::update()` работал только с occurrence от `event.starts_at` (обычно прошлый тур). Все будущие туры серии игнорировались → cancel_self_until, окна регистрации не применялись. Фикс: после updateOrCreate для `is_recurring=true` обновлять все occurrences с `starts_at > now()` (не отменённые), пересчитывая поля от их `starts_at`. При этом если рассчитанный `cancel_self_until < now()` — писать NULL (дедлайн уже прошёл, не блокировать выход).

## Классика без амплуа — «Запись по амплуа» (игра 4x2, 2026-10-04)
- Только `format=game`, `direction=classic`, подтип `4x2`, только игры (не турниры). Чекбокс «Запись по амплуа» в форме создания (`step1.blade.php`, блок `#reg_by_positions_block`, `data-show-if="direction=classic,format=game,game_subtype=4x2"`) и редактирования (`event_management_edit`), **по умолчанию включён**; снять = запись общим списком, как в пляжке. Поле формы `game_registration_by_positions` (hidden 0 + checkbox 1; нет поля → «по амплуа», как раньше; обязано быть в `EventCreateValidator`/`update()` rules), флаг в БД — `event_game_settings.registration_without_positions` (true = БЕЗ амплуа), `Event::registersWithoutPositions()`.
- Что меняется при флаге: `EventGameSettingsService::wantsNoPositions()` → слоты `['player' => max_players]` вместо `setter/outside` (`createGameSettings()` и пересчёт в `EventManagementController::update()`), `positions=[]`, **квота по полу — общий лимит**: `gender_limited_positions=['player']` (в форме выбор позиций скрыт, JS-валидация позиций пропускается — `isNoPositionsMode()` в `events-create.js`), сообщение квоты — `events.gender_quota_general_full`. Остальное работает «само» через уже существовавшую логику «единственная основная роль» (`isSingleMainRole` в guard/слотах, `requiresPositionChoice()=false` → автозапись по абонементу/Premium подставляет `player`).
- Позиции для UI организатора — `EventRoleSlotService::resolvePositionsForEvent($event)` (все 4 вызова в `EventRegistrationsManagementController` переведены на него; для флага = `['player'=>'Игрок']`, при отсутствии резерва колонка/выбор позиции скрыты — `$hasPositions` в `registrations/index`, `positionRequired` не требуется, `player` подставляется автоматически). В анонсах подпись «- player» не выводится (`buildPlayerList()` — и для пляжки тоже).
- **Смена флага на существующем событии**: только пока нет активных записей на будущие туры (иначе 422 `events.reg_by_positions_locked`, у записанных позиции разошлись бы со слотами); смена подтипа с 4x2 у события с флагом тоже сбрасывает флаг под тем же условием.
- Подписи подтипов классики в формах создания/редактирования: `4-2`, `5-1`, `4×4` (значения `4x2`/`5x1`/`4x4` не менялись; пляж — `2×2/3×3/4×4`). Остальные места приведены к тому же виду 2026-10-07 — см. «Обозначения схем игры» ниже.
- Тест на dev (транзакция с откатом, событие 363): флаг → слоты `{player:12}`, квота 2 женщин блокирует 3-ю, позиция `setter` отклоняется, снятие/возврат флага при отсутствии/наличии записей — ожидаемо.

## /my/events, /my/bookings, /my/court-bookings (обмен ролей, 2026-07-15)
- **`/my/bookings`** (`player.my-bookings`) — личные записи игрока на мероприятия (текущие/архивные), для ВСЕХ авторизованных. Контроллер `PlayerDashboardController::myEvents()`, вид `player/my-bookings.blade.php` (переименован из `my-events.blade.php`, логика не менялась). Пункт меню «Мои брони» (`club.my_bookings`).
- **`/my/events`** (`organizer.my-events`) — НОВАЯ страница: упрощённый карточный список мероприятий организатора (название/дата/место + «Управление турниром» если `format=tournament` + «Управление регистрациями»). Контроллер `OrgDashboardController::myEvents()`, вид `dashboard/org_my_events.blade.php`. Доступ: `role IN ('organizer','admin')`, иначе 403 (тот же паттерн, что в `EventRegistrationsOverviewController`, НЕ через `Gate::is-organizer` — тот строго исключает admin).
- Дубль лейбла «Мои мероприятия» (organizer.my-events vs `events.create.event_management`) устранён 2026-07-15 в рамках реструктуризации меню организатора — см. следующий раздел, у `event_management` теперь свой текст «Управление мероприятиями» (`ui.org_events_management`).
- **`/my/court-bookings`** (`player.my-court-bookings`) — прямая бронь корта игроком (бывший Фаза 5 `/my/bookings`, переехал при свапе выше). Контроллер/вид не менялись (`PlayerCourtBookingController::myBookings()` → `player/bookings.blade.php`), только URL+route name+action-роуты (`.../cancel`, `.../pay`). Пункт меню «Брони кортов» (`club.my_court_bookings`) показывается ТОЛЬКО если `$user->courtBookings()->exists()` (relation добавлен в `User`) — то есть только если у игрока реально есть история бронирования корта.

## Меню Организатора — единая структура (2026-07-15)
- Рендерится ровно в ДВУХ местах (проверено grep по `org_menu_title`/«Таб: Организатор» — других мест нет): `components/voll-layout.blade.php` (шапка, колонка 3 «Дополнительное меню», сокращённый набор из 13 пунктов) и `profile/_menu.blade.php` (сайдбар, таб «Организатор», те же 13 + доп. пункты — staff/staff_logs, «Выданные абонементы» (`subscriptions.index`, ОТЛИЧАЕТСЯ от «Абонементы»=`subscription_templates.index` в канонической 13!), школа). Оба места гейтятся `role IN ['organizer','admin']`.
- Канонический порядок (одинаковый в обоих местах): Панель организатора → Панель арендатора → Управление мероприятиями → Управление регистрациями → Мои мероприятия → 🎪 Брони кортов → 📆 Создать мероприятие → 🪪 Абонементы → 🎟 Купоны → 🏆 Мои лиги и сезоны → 📣 Каналы уведомлений → 🌐 Виджет на сайт → ⭐ Организатор Pro.
- Эмодзи у 🏆/🌐/⭐ зашиты В ЗНАЧЕНИИ перевода (`ui.org_my_leagues/org_widget/org_pro`); у 🎪/📆/🪪/🎟/📣 — хардкод в blade рядом с `__()` (тот же паттерн, что раньше был у `club.bookings_title`) — при переводе на другой язык эмодзи не переводить, значение ключа остаётся без эмодзи.
- **«Панель арендатора» (`club.analytics`, переименована из «Аналитика») и «Брони кортов» (`club.bookings_title`) — гейт «арендодатель кортов»**: `$user->is_club_manager && $user->ownedLocations()->exists()`. В схеме БД НЕТ отдельного флага «корты выставлены на аренду» — сам контроллер `ClubBookingController` авторизует ещё шире (`is_club_manager || isAdmin()`, без проверки владения локацией вообще); `ownedLocations()->exists()` — чисто UX-фильтр в меню (не показывать тем, кто ещё не завёл ни одной локации). Это тот же паттерн, что `$user->courtBookings()->exists()` для пункта «Брони кортов» у игрока (прошлая сессия).

## Тип мероприятия «Мастер-класс» (`format=master_class`, 2026-10-06)
- Последний пункт селекта «Тип мероприятия», для классики и пляжа. Не привязан к командным схемам/слотам — большое число участников (до 1000). **Не гонять через `normalizeGameDefaults` как обычную игру**: у формата своя ветка (`EventGameSettingsService::normalizeGameDefaults/normalizeGenderPolicy/createGameSettings`) — один слот `player` = `master_max_players`, `teams_count=0`, `gender_policy='mixed_open'`, `registration_mode='single'` (принудительно, что бы ни пришло из формы). `event_game_settings.subtype` NOT NULL → технический дефолт `2x2`/`4x2`, на логику не влияет.
- Форма создания (`step1.blade.php`): остаются «Климатические условия» (на улице), блок `#master_class_block` (поля `master_min_players`/`master_max_players` — ОТДЕЛЬНЫЕ имена, не `game_*`, иначе дубли с hidden-полями блока игры) и «Ограничения» без гендерного блока (`#gender_policy_block`, `data-hide-if`) — возраст, уровни, «вид мероприятия» как раньше. Блоки игры и «режим регистрации» скрыты `data-hide-if="format=tournament|master_class"` (**в реальной реализации `matchVisibilityRule` запятая = AND, `|` = OR внутри поля** — не как в описании выше). Валидация шага 1 в `events-create.js` — отдельная ветка `format==='master_class'` ДО `validateGameClassic()` (иначе для классики требует подтип/max игры).
- Редактирование (`event_management_edit` + `EventManagementController::update()`): тип мастер-класса НЕ меняется ни на него, ни с него (в селекте disabled + hidden `format`, контроллер принудительно возвращает формат события); в блоке «Игровые настройки» — только min/max; максимум нельзя опустить ниже самого заполненного будущего тура; пересчёт вместимости — своя ветка (без `GameCalculator`, который иначе затёр бы max по подтипу×командам, как раньше у King Beach).
- Прочее: ключи `events.fmt_master_class`/`master_*` (RU+EN), метки в фильтрах `/events` и школы, `format_name()`, «создать на основе» подставляет `master_*` из `event_game_settings`. Проверено на dev в транзакции с откатом (слот `player`=150, политика `mixed_open`, ошибки min>max/пустой max); в браузере форму НЕ прогоняли вживую.

## Обозначения схем игры (2026-10-07)
- **Единый хелпер** `volley_scheme_label($subtype, $hasLibero=false, $withLiberoWord=true)` (`app/helpers.php`): `4x2` → «4-2», `5x1` → «5-1», `5x1_libero` (или `5x1` + `libero_mode='with_libero'` при `$hasLibero`) → «5-1 с либеро» (`events.libero_word`), остальное (`2x2/3x3/4x4`) — с «×». Любое новое место с подписью схемы — через него, не хардкодом. (Владелец в запросе написал «4х2 = 4-1» — принято за опечатку, везде «4-2» как в тултипах «4 нападающих + 2 связующих».)
- Применено: пилюля схемы на карточке `/events` (`_card.blade.php`; для схемы с либеро — ОТДЕЛЬНАЯ пилюля «Либеро», ключ `events.card_badge_libero`), строка «Формат» на странице мероприятия (`show/description`, «5-1 с либеро»), селекты создания/редактирования (в т.ч. турнирные схемы), `players/rating|teams`, карточка команды турнира, «Мои команды», `team_config`, `rating_info`, анонсы (`OccurrenceAnnouncementMessageBuilder::formatLabel()`, без отдельной «Либеро» — знает только `5x1_libero`), виджет.
- Поле «Подтип»/«Подтип игры» переименовано в **«Схема/формат»** (`events.subtype_label` на создании, `events.game_subtype` на редактировании; EN — Scheme/format). Ключи не менялись.
- **Мастер-класс (`format=master_class`) НЕ показывает схему**: `event_game_settings.subtype` у него NOT NULL-заглушка (`2x2`/`4x2`), читать её для UI нельзя — карточка и страница события выводят «Мастер-класс» (`events.fmt_master_class`) вместо схемы, без тултипа и «Либеро»; в виджете то же (`WidgetEventsService::extras()`, бейдж `subtype`). Любое новое место, читающее `gs->subtype`, обязано проверять `format === 'master_class'`.
- Виджет: бейдж `libero` выводится вместе с `subtype` под одной настройкой `badge_subtype`; кеш виджета — ключ `widget_events_v5_` (при смене состава `extras()` поднимать версию).
- Уже опубликованные анонсы в каналах остаются со старой подписью до следующего refresh. После деплоя нужен `reload php8.3-fpm` (новый хелпер в `helpers.php` — autoload `files`).

## Позиция reserve в регистрациях
- `resolvePositions()` НЕ включает 'reserve' — добавляется отдельно в index()/addPlayer()/updatePosition()
- Источник лимита: event_role_slots.role='reserve' ИЛИ game_settings.reserve_players_max (fallback)
- Если reserveMax=0 но есть legacy-записи с position='reserve' → показываем без лимита
- **Счётчик вместимости** (`EventRegistrationsManagementController::index`): `maxPlayers += reserveMax` перед расчётом `freeSeats` — иначе activeRegs (включает запасных) > maxPlayers (только основные) → freeSeats=0 при наличии свободных мест

## event_management_edit — город и timezone
- Поле timezone: `<input type="hidden" name="timezone" id="mgmt_timezone_hidden">`
- City autocomplete → AJAX `/ajax/cities/meta` обновляет timezone + `/ajax/locations/by-city` фильтрует локации

## Редактирование повторяющейся серии — будущие occurrences

### Когда показывается диалог
- SweetAlert показывается ТОЛЬКО при изменении полей расписания: `starts_at`, `recurrence_type`, `recurrence_weekdays`, `recurrence_interval`, `recurrence_end_type/until/count`
- При смене описания, локации, фото, оплаты, настроек регистрации — форма отправляется без вопросов
- JS сравнивает текущие значения с `origSchedule` (снимок из PHP на момент загрузки страницы)
- Диалог не показывается если `is_recurring_edit` checkbox снят (серия отключается)

### Параметр future_occurrences_action
- `keep` (по умолчанию) — будущие occurrences не трогаются; ExpandJob добавит новые по обновлённому расписанию поверх существующих (дублей нет — защита через `uniq_key`)
- `cancel` — умная логика в контроллере:
  - **Occurrence с активными регистрациями** → `is_cancelled=true` + уведомление участникам (`notifyUsersAboutCancelledEvent`)
  - **Occurrence без регистраций** → `DELETE` (мусор, убирается навсегда)
  - После очистки — dispatch `ExpandEventOccurrencesJob` создаёт новые по обновлённому расписанию

### Что применяется ко всем будущим occurrence при сохранении серии (без диалога)
Контроллер всегда обновляет все future occurrences: `location_id`, `duration_sec`, `allow_registration`, `max_players`, `age_policy`, `is_snow`, окна регистрации (пересчёт от starts_at каждой occurrence).

### Что НЕ хранится в occurrence — берётся из events при отображении
`title`, `description_html`, `event_photos`, `is_paid`, `price_*` — в `event_occurrences` есть эти колонки, но заполняются только при editOccurrence (override). Если NULL — `EventShowService` подставляет значение из parent `events`. Изменение в серии сразу видно на всех турах.

### uniq_key — защита от дублей
Каждая occurrence: `uniq_key = "event:{id}:{YmdHis UTC}"`. `OccurrenceExpansionService` при каждом запуске делает `updateOrCreate` по этому ключу — дублей не создаёт никогда, даже при повторных запусках ExpandJob.

## Управление регистрациями — toggle cancel/restore (EventRegistrationsManagementController::cancel)
- Три поля статуса отмены в `event_registrations`: `cancelled_at`, `is_cancelled`, `status` — все три должны быть синхронны
- **Баг (исправлен в 470e2ac)**: метод `cancel()` определял текущее состояние только по `cancelled_at`, игнорируя `is_cancelled` и `status`. При рассогласовании полей (напр. `status='cancelled'` + `is_cancelled=false` + `cancelled_at=null`) toggle шёл в неверную сторону.
- Рассогласование возникает при двойном клике или параллельных запросах. Симптом: страница управления считает запись активной (фильтр `is_cancelled=false`), а страница мероприятия исключает её (`status='cancelled'`) → расхождение счётчиков мест.
- Фикс: `$isCancelled = !empty($row->cancelled_at) || !empty($row->is_cancelled) || $row->status === 'cancelled';` — любого «отменённого» признака достаточно для restore.
- **Баг (исправлен): назначение/смена `position` организатором не проверяла гендерную квоту**. `cancel()` (восстановление с новой позицией), `updatePosition()`, `addPlayer()` (новая запись и восстановление отменённой) проверяли только `role.max_slots` живым COUNT — но не вызывали `EventRegistrationGuard::applyGenderPolicy()` (`only_male`/`only_female`/`mixed_5050`/`mixed_limited`). Организатор мог вручную посадить игрока на позицию, где гендерная квота уже исчерпана (напр. 5-ю женщину на `setter` при `gender_limited_max=4`).
- Фикс: `EventRegistrationGuard::checkGenderQuotaForUser(User, EventOccurrence, ?excludeRegistrationId)` — публичная обёртка над `applyGenderPolicy()` (переиспользуется и в `checkEligibility()`, чтобы не дублировать). Жёсткий запрет (не предупреждение) — квота настроена осознанно, обход = сломанный формат игры. Вызывается во всех трёх местах перед записью `position` в БД; `excludeRegistrationId` исключает саму перемещаемую регистрацию из подсчёта уже занятых мест. Сообщение об ошибке — `events.gender_quota_position_full` (ru/en), формируется внутри `applyGenderPolicy()` (mixed_limited ветка) с названием позиции + счётчиком `N из M`.

## Ранняя запись для владельцев абонементов (2026-10-13)
- Поле «Начало рег. абонементов» (шаг 2 создания, `event_management_edit`, `occurrence_edit`): за N дней/часов до начала, по умолчанию = общий старт; можно ТОЛЬКО раньше (в форме JS-clamp, на сервере `buildRegistrationWindows()`/контроллер: не больше общего → NULL). Хранение: `events.subscription_registration_starts_at` и `event_occurrences.subscription_registration_starts_at` (NULL = раннего окна нет, наследуется от серии как остальные окна), `event_occurrences.subscription_reg_notified_at` — дедуп уведомления. `OccurrenceExpansionService` берёт сдвиг у reference-тура; `EventOccurrence::effectiveSubscriptionRegistrationStartsAt()` возвращает значение только если оно раньше общего старта.
- Запись в раннее окно: `EventRegistrationGuard::checkAuthAndWindow()` → `hasEarlySubscriptionAccess()` (действующий абонемент на мероприятие через `SubscriptionService::findActiveForEvent`). Остальные проверки (позиции, квоты, уровень) без изменений.
- Если раннее окно есть — автозапись по абонементу на этот тур ВЫКЛЮЧЕНА полностью (`RunAutoBooking` фильтрует, `AutoBookingSubscriptionJob` выходит; `PremiumAutoBookingJob` больше не уступает абонементу на таких турах). Вместо неё — `subscriptions:notify-early-registration` (everyMinute, `routes/console.php`): уведомление `subscription_early_registration` держателям абонемента организатора (кроме уже записанных/ботов), по одному разу на тур. Равно общему старту — всё как раньше.
- Проверено на dev в транзакции с откатом (guard: держатель проходит, остальные «ещё не началась»; уведомление 1 раз; `subscriptions:auto-booking` — 0 jobs). Деплой: миграции + `route:cache`/`config:cache`/`view:cache` + `reload php8.3-fpm` одним блоком.
- **Доработки (2026-10-13)**: (1) «создать на основе» копирует И общий старт регистрации (`reg_starts_d/h`), И раннее окно (`sub_reg_starts_d/h`) — `EventCreateController::getEventPrefillData()` считает сдвиги от `starts_at` источника (раньше окна регистрации при копировании не переносились вообще, всегда дефолт 3 дня). (2) Мероприятия с будущим туром с ранним окном (`SubscriptionService::eventHasEarlySubscriptionWindow()`) НЕ предлагаются в настройке автозаписи по абонементу (`autoBookingEventsFor`) и не настраиваются автоматически; в уведомлении при выдаче абонемента (`notifyAutoBookingSetup`) добавлена оговорка про раннюю запись, а если настраивать автозапись не на что — отдельный текст «ранняя запись». Правило «любой будущий тур» грубое: серия, где раннее окно только у части туров, целиком выпадает из списка автозаписи. Ранее настроенные автозаписи не удаляются, на ранних турах просто не срабатывают.
