# Аккаунты, OAuth, push, impersonation, дубли/слияние, ограничения, staff

_Вынесено из CLAUDE.md 2026-10-23 без изменения содержания. Полный исходник: docs/archive/CLAUDE.full-2026-10-23.md_

## Google OAuth
- Провайдер: Laravel Socialite driver('google'); конфиг: config/services.php (GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET)
- Колонка: users.google_id; контроллер: GoogleAuthController; отвязка: AccountUnlinkController::google
- Кнопки OAuth в ДВУХ местах: login.blade.php + voll-layout.blade.php (попап шапки) — обновлять оба
- Apple скрывается на Android; Google скрывается на Apple (iPhone/iPad/Macintosh)

## Apple Sign In — диагностика
- `invalid_request` от Apple = code пустой (WKWebView race condition); `invalid_grant` = credentials OK, код невалиден (норма)
- Конфиг: client_id=club.volleyplay.app.signin, team_id=V762R44QWF, key_id=A78BY2CKKQ; ключ: storage/app/apple/AuthKey_A78BY2CKKQ.p8
- AppleAuthController: guard для пустого code (ранний выход + лог) + session()->save() в redirect()
- Долгосрочный fix: @capacitor-community/apple-sign-in (нативный ASAuthorizationAppleIDProvider)

## Push-уведомления (APNs) и Face ID

### Push-уведомления
- Таблица: device_tokens (user_id, platform ios/android, token unique, is_active bool); модель: DeviceToken, сервис: PushNotificationService
- APNs — прямая реализация через curl HTTP/2 + JWT (ES256), БЕЗ внешних пакетов (laravel-notification-channels/apn несовместим с Laravel 12 + PHP 8.3)
- HTTP 410 = токен устарел → is_active=false; HTTP 400 BadDeviceToken → is_active=false
- Конфиг: config/apn.php; переменные: APN_KEY_ID, APN_TEAM_ID, APN_BUNDLE_ID, APN_PRIVATE_KEY, APN_PRODUCTION; .p8 → storage/app/apns/AuthKey.p8
- 4 типа с push: registration_created, event_reminder, event_cancelled, friend_joined_event
- **ВАЖНО**: Xcode/debug → sandbox-токены; TestFlight/App Store → production-токены; APN_PRODUCTION=false только для dev
- Prod-приложение → volleyplay.club → prod DB; при тестировании с Xcode: токены в prod DB, отправлять через prod-сервер с APN_PRODUCTION=false
- APNs возвращает 200 OK на sandbox но не доставляет если entitlements некорректны — проверять aps-environment:development

### Face ID и Biometric
- users.biometric_token (string 64, nullable, unique), скрыт в $hidden
- Генерация: Str::random(64); НЕ crypto.randomUUID() (недоступен в WKWebView до iOS 15.4)
- BiometricController (Api/): register/login/revoke/webLogin
- Web endpoints: POST /auth/biometric-login (исключён из CSRF в bootstrap/app.php), POST /auth/biometric-register
- deleteCredentials только при 422; при 419/500 — сохранять credentials
- SESSION_SAME_SITE=none, SESSION_SECURE_COOKIE=true

### Universal Links
- AASA: public/.well-known/apple-app-site-association; appID: V762R44QWF.club.volleyplay.app
- nginx: location ^~ /.well-known/ ДО блока `deny all`; после изменений nginx — restart (не reload)

## Impersonation (вход от имени пользователя)
- Контроллер: `Admin/ImpersonationController`; middleware: `BlockInImpersonation` (алиас `block.impersonation`)
- Session key: `impersonator_id`; leave() — БЕЗ `can:is-admin`; логирует через DB::table('admin_audits') с actor_user_id = impersonator_id
- Заблокированные действия: отвязка OAuth, удаление аккаунта, платежи, biometric-register
- Нельзя войти от имени другого администратора
- **AuthenticateSessionWithImpersonation** (`config/jetstream.php` → `auth_session`): синхронизирует `password_hash_<driver>` в session при активной impersonation — без этого расхождение hash → session()->flush() → потеря impersonator_id

## Прогрессивное заполнение профиля (v2)
- Уровень 1 (доступ): `User::isProfileComplete()` — только first_name, last_name, phone; middleware `EnsureProfileCompleted`
- Уровень 2 (при записи): `User::getMissingFieldsForEvent()` — age_policy→birth_date, direction+levels→уровни, gender_policy→gender; редирект на `/profile/complete?missing=...&return_to=...`
- Блокировка полей после заполнения (protectedOnce): first_name, last_name, patronymic, phone, city_id, birth_date, gender, classic_level, beach_level; в `ProfileUpdateGuard::selfEdit()` и `ProfileExtraController`
- OAuth при создании: НЕ заполняют first_name/last_name/phone/gender; пользователь заполнит через profile/complete
- **users.gender значения**: `'m'` и `'f'` (не 'male'/'female') — при фильтрах/keyBy использовать однобуквенные коды
- profile/complete.blade.php постит на `route('profile.extra.update')` (ProfileExtraController)

## Дубли пользователей и очистка неактивных аккаунтов
- Поиск дублей: `UserMergeService::findDuplicates()` — по телефону + по first_name+last_name (case-insensitive)
- Таблица стаффа: `organizer_staff` (колонка staff_user_id); роли: users.role IN ('admin','superadmin','organizer')
- `CheckUserDuplicatesJob` (`users:check-duplicates`) — только уведомляет в Telegram (TELEGRAM_BOT_TOKEN, TELEGRAM_ADMIN_CHAT_ID), ничего не меняет/не удаляет. В расписании (`routes/console.php`, `weeklyOn(1, '04:00')`) с 2026-09-25.
- `PurgeInactiveUsersJob` (`users:purge-inactive [--dry-run]`) — критерии: profile_completed_at IS NULL + нет регистраций/платежей/баланса + не bot/admin/organizer/staff, мягкое удаление (`deleted_at=now()`). **НЕ в расписании (отключена до решения)** — сам краш входа (см. ниже) устранён 2026-09-25, но `PurgeInactiveUsersJob` при мягком удалении не обнуляет provider id/notify-поля вообще (только `deleted_at`) — если включить как есть, снова начнёт копиться тот же хвост незанулённых полей, что был почищен (см. ниже). Нужна отдельная правка джобы (обнулять provider id по аналогии с `AccountDeleteRequestController`) перед включением.

### Баг входа мягко удалённых пользователей — исправлен 2026-09-25 (500 на UniqueConstraintViolation)
- **Было**: `users_telegram_id_unique`/`vk_id`/`yandex_id`/`apple_id`/`google_id` — ПОЛНЫЕ уникальные индексы (без `WHERE deleted_at IS NULL`), а `AccountDeleteRequestController`/`UserMergeService` не всегда обнуляли provider id при удалении/слиянии (google_id вообще был пропущен в первом; во втором — secondary сохранял поле, если у primary уже было своё значение). При возврате такого пользователя через тот же OAuth-провайдер (Telegram/VK/Yandex/Google/Apple — все 5 auth-контроллеров ищут `User::where('provider_id', $id)->first()`, Eloquent исключает soft-deleted) код не находил старую запись и пытался создать новую с тем же provider id → `save()` падал на уникальном индексе → 500, пользователь не мог войти вообще без ручного вмешательства в БД. На проде обнаружено 7 живых пострадавших аккаунтов (4 через merge с vk_id, 3 через self-delete с google_id) — почищены одноразовой командой.
- **Фикс, три независимых слоя защиты**:
  1. `AccountDeleteRequestController::store()` — обнуляет ВСЕ 5 provider id (включая ранее пропущенный `google_id`) + `telegram_notify_chat_id`/`telegram_notify_linked_at`/`vk_notify_user_id`/`vk_notify_linked_at` (отдельные поля привязки бот-уведомлений через `/start notify_<token>`, доступны любому игроку, не только организаторам — не то же самое, что OAuth `telegram_id`/`vk_id`).
  2. `UserMergeService::merge()` — provider id у secondary обнуляются ВСЕГДА (не только когда переносятся в primary из-за `empty($primary->$f)`); аналогично для `telegram_notify_chat_id`/`vk_notify_user_id`/`max_chat_id`.
  3. **Частичные уникальные индексы** (`WHERE deleted_at IS NULL`) на всех 5 provider-полей — последняя линия защиты на уровне БД, независимая от корректности кода выше: soft-deleted строка больше не блокирует создание новой с тем же provider id.
- **Уведомления удалённым/смёрженным**: `NotificationDeliverySender::sendById()` — если получатель `User::withTrashed()` мягко удалён или `merged_into_user_id !== null`, доставка помечается `status='skipped'`, `error='recipient deleted'`, `is_retryable=false` (не `'failed'` — не ретраится, `notifications:retry-failed` фильтрует строго `status='failed'`).
- Подробности расследования — `report/kernel_cleanup_recon.md` (dev, не в git, содержит найденные id пользователей).

### `account_links` — таблица псевдонимов входа (реализовано 2026-09-25)
- До этого таблица существовала с 2026-01-08, но была **полностью мертва** (0 строк на dev/prod, ни одного `INSERT`/`SELECT` в коде) — задумывалась под линковку, но реальная реализация (`account_link_codes`+`account_link_audits`, тоже почти не используется) пошла другим путём и писала provider id напрямую в `users`. Разведка — `report/account_links_audit_2026-09-25.md`.
- Схема дополнена: `source` (string, nullable — сейчас единственное значение `'merge'`) + `merged_from_user_id` (nullable FK → users, nullOnDelete). Модель — `App\Models\AccountLink` (`AccountLink::resolveUser(provider, providerUserId)` → `?User`, `AccountLink::isTaken(provider, providerUserId, ?exceptUserId)` → bool).
- **`UserMergeService::merge()`** — если у secondary непустое provider-поле (`telegram_id`/`vk_id`/`yandex_id`/`apple_id`/`google_id`), а у primary это же поле УЖЕ занято своим значением (раньше в этом случае id secondary терялся молча, см. баг выше) — id сохраняется как строка в `account_links` (`user_id`=primary, `source='merge'`, `merged_from_user_id`=secondary.id) вместо простого обнуления. Если primary был пуст — по-прежнему прямой перенос в `users` (в `account_links` не попадает). **Цепочка мержей**: если secondary сам был `user_id`-владельцем чужих алиасов (из более раннего merge, где он был primary), эти строки `account_links` перепривязываются на нового primary — иначе алиас осиротеет, указывая на мягко удалённого пользователя.
- **Единственный постоянный след слияния** — `AdminAuditLogger::log('user.merge', 'user', primary->id, [...])` внутри той же транзакции (meta: `secondary_user_id`, `transferred_to_primary`, `moved_to_account_links`, `registrations_transferred`, `cancelled_conflicts`). До 2026-09-25 merge() не писал НИКУДА в БД — только `Log::info`, который на этом сервере никогда не долетает до диска (`LOG_LEVEL=warning`).
- **Все 5 auth-контроллеров (Telegram×2 потока, VK, Yandex, Google, Apple×2 потока) + `LinkConsumeController`** — при поиске пользователя по provider id для логина, если прямой `User::where('provider_id', $id)->first()` не находит (и остальные существующие фоллбэки типа `_notify_chat_id`/email/phone тоже не находят) — фоллбэк на `AccountLink::resolveUser()` ПЕРЕД созданием нового пользователя. При привязке провайдера к уже залогиненному аккаунту ("занят ли провайдер?") — проверка `User::where(...)->exists() || AccountLink::isTaken(...)` в паре. Найденный через alias пользователь НЕ получает provider id обратно в `users` — запись остаётся только в `account_links`.
- **`admin_audits` — историческая особенность схемы**: одновременно существуют `actor_user_id` (bigint, **NOT NULL**, FK→users, из самой первой миграции) и `admin_user_id` (nullable, добавлен позже) — `AdminAuditLogger::log()` пишет в оба. Из консольной команды (`auth()->user()` = null) вызов `AdminAuditLogger::log()` упадёт на NOT NULL constraint — для CLI-действий писать в `admin_audits` вручную с явным `actor_user_id` (пример — `BackfillMergedAccountLinks`, требует `--actor=<id>`).
- **Одноразовая `users:backfill-merged-account-links`** — восстанавливает 4 vk_id (id 469/396/457/624, secondary после merge, потеряны 2026-09-25 при `users:clear-deleted-provider-fields`, восстановлены из локального утреннего pg_dump-бэкапа `/home/appuser/pgbackup/dumps/`) в `account_links`. `--force` требует `--actor=`. Удалить класс отдельным коммитом после применения на проде.

### UserMergeService::merge() — что переносится
- OAuth поля (unique): telegram_id, vk_id, yandex_id, apple_id, google_id — переносятся в primary только если у primary пусто, но у secondary обнуляются ВСЕГДА (фикс 2026-09-25 — раньше обнулялись только вместе с переносом, оставляя мёртвый provider id, если у primary уже было своё значение)
- Каналы бот-уведомлений (telegram_notify_chat_id, vk_notify_user_id, max_chat_id) — та же логика: перенос если у primary пусто, иначе обнуление у secondary (добавлено 2026-09-25)
- Профиль (если пусто у primary): phone, first_name, last_name, patronymic, birth_date, city_id, gender, height_cm, classic_level, beach_level
- user_beach_zones (дедупликация по zone), user_classic_positions (по position)
- Также: event_registrations, payments, virtual_wallets, subscriptions, coupons, premium_subscriptions, user_notification_channels, occurrence_waitlist, event_team_members/invites/applications, friendships, статистика, device_tokens
- НЕ переносятся: event_team_member_audits, notification_deliveries, page_views, account_delete_requests

## Ограничения пользователей (user_restrictions)
- Таблица: `user_restrictions` (user_id, scope, ends_at, event_ids, reason, created_by)
- `scope='events'` + `event_ids=[367,...]` — запрет записи на конкретные мероприятия; `ends_at=null` = пожизненно
- Middleware: `EnsureUserNotRestricted` (алиас `user.restricted`) — блокирует `events.join` И `occurrences.join`
- **КРИТИЧНО**: middleware проверяет только эти два маршрута; `events.registrations.add` (добавление организатором) намеренно не блокируется — организатор может добавить игрока вручную несмотря на ограничение
- **Баг (исправлен в f411a30)**: изначально middleware блокировал только `events.join` (legacy), но не `occurrences.join` (новый). Все записи через UI шли через `occurrences.join` → ограничение не работало. Игрок мог обойти запрет простой записью на сайте.
- **Автозапись и бан (исправлено 2026-10-06, игрок 823 / организатор 260)**: `EnsureUserNotRestricted` закрывает только ручные маршруты join, а автоматические пути (`WaitlistService::autoBookNext`, `AutoBookingSubscriptionJob`, `PremiumAutoBookingJob`) бан не проверяли — забаненный за неоплату наличными игрок из листа ожидания мог сам занять освободившееся место. Теперь единая проверка `UserRestrictionService::blockKind()/blockKinds()` (events точечно/глобально + organizer; админы от organizer-бана освобождены): `autoBookNext` пропускает забаненного (в очереди он остаётся, место получает следующий), джобы абонемента/Premium шлют `auto_booking_failed`/`premium_auto_booking_failed` «запись … ограничена», а `checkWaitlistGate` не считает забаненных в очереди «блокирующими» для остальных. **Логика middleware и сервиса продублирована** (middleware не трогали) — править обе при изменении правил. Ручное добавление организатором (`events.registrations.add`) по-прежнему не блокируется; существующие записи бан не отменяет.
- **Push ко всем уведомлениям (2026-10-06)**: ~70 вызовов `UserNotificationService::create()` перечисляли каналы вручную без `'push'` (в т.ч. `cash_payment_reminder`/`cash_payment_banned` — игрок 823 с активным токеном получил их только in_app). Теперь `create()` сам добавляет `push`, если в списке есть хоть один внешний канал; `normalizeChannels()` отсеивает игроков без активного токена iOS/Android. **Исключения**: списки строго `['in_app']` (и дефолт) — намеренно только внутри сайта; типы `organizer_broadcast`/`admin_broadcast`/`admin_broadcast_test` — каналы выбираются галочками, охват считается заранее. Новый тип-«рассылка» с ручным выбором каналов — добавить в этот список исключений.
- **Цикл бана за неоплату наличными**: организатор на странице учёта отмечает оплативших → у неотмеченных `payments.cash_ban_deadline_at = now()+12ч` → `payments:ban-unpaid-cash` (каждые 5 мин) создаёт `user_restrictions` scope=organizer, `ends_at=NULL` → отметка «оплатил» → `liftOrganizerBan()` ставит `ends_at=now()` (поэтому в истории у игрока «истёкшие» ограничения — это снятые). Админка `/admin/users/{id}` показывает только ДЕЙСТВУЮЩИЕ ограничения.
- При ручной отмене записи ограниченного игрока через скрипт: установить `is_cancelled=true`, `cancelled_at=now()`, `status='cancelled'`; вызвать `WaitlistService::onSpotFreed()`, `UserNotificationService::createRegistrationCancelledByOrganizerNotification()` (⚠️ `EventOccurrenceStatsService::decrement()` вызывать больше НЕ нужно — метод удалён 2026-07-16, см. раздел «Выпил кеш-счётчиков» ниже)
- `banEvents` делает **жёсткий DELETE** существующих регистраций (не soft cancel) — при снятии ограничений записи не восстанавливаются автоматически

## Аккаунты-«пустышки» (2026-10-07, dev, не задеплоено)
- Организатор/админ создаёт игрока без точек входа (для составов команд/игр), позже сливает его с реальным игроком. `users.is_placeholder` + `users.created_by_user_id` (миграция `2026_10_07_100000`). Email — `placeholder-<uuid>@placeholder.local` (NOT NULL + unique), пароль случайный — войти нельзя. Телефон необязателен, но по нему (и по ФИО/перестановке) `findDuplicates()` и `PlaceholderUserService::suggestMatches()` находят совпадение.
- Раздел `/my/placeholders` (`PlaceholderUserController`, `PlaceholderUserService`, вью `placeholders/*`, lang `placeholders.php`): список СВОИХ пустышек (админ — всех), создать (фамилия/имя/пол/телефон) → редирект на `/profile/complete?user_id=X` (для своей пустышки режим `admin_other` — все поля; `ProfileUpdateGuard::viewMode`, `ProfileExtraController` с `$ownPlaceholder`, групповая проверка ФИО+телефон пропускается), удалить (только без записей/команд/листа ожидания), объединить (`/my/placeholders/{id}/merge`: подсказки совпадений + поиск реального; сервер проверяет, что secondary — своя пустышка, primary — реальный не-бот). Staff-помощникам раздел недоступен (403, пункт меню скрыт). Права — `User::isPlaceholderManagedBy($actor)`.
- Решение владельца: пустышки НЕ скрываются — они видны в общем поиске игроков (с пометкой «👻 пустышка» в `meta`, `UserSearchController`), в рейтингах и в `findDuplicates()` (там `buildGroupStats()` никогда не рекомендует пустышку в primary). Слияние — штатный `UserMergeService::merge(real, placeholder)`; дублирующая запись пустышки на то же мероприятие отменяется (принято владельцем).
- **Страница слияния (подсказки, 2026-10-07)**: блок «Что с чем объединяется» (пустышка → реальный игрок, что переходит, правило «поля профиля копируются только в пустые, данные игрока не перезаписываются», конфликты записей), у пустышки — счётчики данных (`PlaceholderUserService::summary()`), у каждого кандидата — какие поля получит (`fieldsToFill()`), число конфликтующих будущих записей (`conflicts()` → `UserMergeService::upcomingConflicts`) и отметка совпавшего телефона. Тест рендера: в `Request::create()` убирать `Accept-Language`, а `strip_tags` на странице с `<script>` съедает текст — проверять по сырому HTML.
- Не сделано: пометка пустышки в самих карточках/составах (только в подсказках поиска и в разделе), страница слияния админа (`admin/users/duplicates`) без бейджа пустышки.

## Staff (помощники организатора) — права, меню, мастер (2026-10-05)
- Привязка — `organizer_staff` (`StaffAssignment`, unique на пару; `store()` дополнительно не даёт быть помощником у двух организаторов). **`StaffController::store()/toggleMaster()` гейтятся `Gate::authorize('assign-staff')`** (раньше Gate был объявлен, но нигде не применялся — любой `role=user` мог назначить помощника); самоназначение запрещено. Таблицы `staff_assignments` НЕ СУЩЕСТВУЕТ (миграция-призрак) — `EventIndexService` читал её и валил `/events` 500 у любого `staff`, исправлено на `organizer_staff`.
- Блок «Помощники организатора» на `/admin/users/{id}` (для organizer/admin): список + поиск ЛЮБОГО пользователя + `organizer_id_override`. Старый блок «Привязка Staff» делает обратное (привязывает ЭТУ страницу к организатору) — не путать.
- **Меню помощника** (роль `staff` + есть привязка; `$isStaffHelper` в `voll-layout` и `profile/_menu`): полное меню организатора БЕЗ «Панели организатора» (контроллер даёт 403 для staff), «Виджет», «Организатор Pro», «Помощники/Логи/Школа». Абонементы/купоны — только при флаге «мастер». «Мои мероприятия»/«Лиги»/«Каналы» в меню есть, но контроллеры пускают только organizer/admin (403 для staff) — открытый вопрос.
- **Мастер**: флаг `organizer_staff.can_manage_subs` (НЕ отдельная роль — `users.role` глобальная и зашита во многих местах). Включает владелец/админ (`POST /staff/{assignment}/master`). `EventAccessService::subsOrganizerIds()/canManageSubsOf()/ensureCanUseSubs()/resolveSubsOrganizerId()` — единая точка прав на шаблоны/выдачу абонементов и купонов; шаблоны мастера создаются от `organizer_id` его организатора; действия пишутся в `staff_logs` (`logForOther`).
- **Создание мероприятия**: staff — только от своего организатора (явный чужой `organizer_id` отклоняется); organizer с привязками — выбор «Для себя / организатор, у которого помощник» (`EventAccessService::creatableOrganizerIds()`, блок в `step1`). Блок «Серия турниров» по-прежнему показывает сезоны создающего, не выбранного организатора.
- **«Мои мероприятия» (`/my/events`)**: пускает organizer/admin/staff; для помощника — мероприятия организатора(ов), у кого он помощник; у организатора с привязкой — вкладки «Мои» / «Имя организатора» (`?org=`, чужой id подменить нельзя).
- **Видимость приватных мероприятий для помощника не зависит от собственной роли** (`EventVisibilityService`, `EventIndexService`): раньше срабатывало только при `role=staff`, организатор-помощник не видел приватку другого организатора сразу после её создания. Проверка идёт по `organizer_staff` для любой роли.
- **Лента `/events` — регион города** (`EventIndexService` + `City::feedRegionScope()`, 2026-10-05): житель города видит мероприятия всего региона города; Москва↔Московская область и СПб↔Ленинградская область — явные пары (`FEDERAL_REGION_PAIRS`, у федеральных городов `cities.region` = их название), правило двустороннее. `city=all` и пользователь без города — как раньше. Рассылка жителям города, страницы школ и виджет не затронуты. На проде 1 будущее мероприятие в МО (Марфино) приватное — вживую в ленте не проверялось, на dev проверено.
- **Ловушка тестов ленты**: в `occurrenceIndex()` пустая строка = «все» для direction/format (не `'all'`), окно ленты — 10 дней от ближайшего occurrence, `offset` в запросе; через рефлексию вызывать с `Request::create('/events','GET',['offset'=>0])` в `app()->instance('request', ...)`.
