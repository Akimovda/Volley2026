# Volley2026 — Контекст проекта

## Язык
- Всегда отвечай на русском языке

## Стек
- Laravel 12, PHP 8.3, PostgreSQL, Blade (частично Vue), jQuery v3.7.1
- Telegram/VK/MAX бот интеграции
- Сервер: /var/www/volley-bot/backend (dev), /var/www/volleyplay/backend (prod)

## Серверные особенности
- php artisan tinker --execute НЕ работает
- Использовать: cat > /tmp/file.php + php -r с bootstrap
- sudo нужен для root-файлов (org.js, некоторые blade)
- sed -i для простых замен, Python для сложных (спецсимволы, табы)
- CarbonImmutable::diffInSeconds() — absolute=false по умолчанию, возвращает отрицательные значения; использовать $a->timestamp - $b->timestamp
- createCustomSelect в script.js оборачивает все .form select — селекты без name атрибута не отправляются на сервер
- ExpandEventOccurrencesJob/OccurrenceExpansionService: offset для reg_starts/reg_ends/cancel_lock берётся из первой (reference) occurrence, не хардкод

## JS файлы
- lib.js — везде, script.js — логика+swal, fas.js — fancybox+swiper, org.js — орг. панель
- swal: класс .btn-alert + data-атрибуты
- Fancybox = jQuery: jQuery.fancybox.open({src:'#id',type:'inline'}), НЕ standalone
- Safari: использовать jQuery.ajax (не fetch — CORS), polling 200мс (не input/keyup)
- Safari select bug: использовать change (не input) для <select> — input не срабатывает
- Класс form-select-dropdown даёт visibility:hidden — НЕ использовать для dropdown
- createCustomSelect оборачивает .form select → дропдаун обрезается если .card имеет overflow:hidden → добавлять style="overflow:visible" на карточку

## Окно регистрации (паттерн)
- Данные хранятся как UTC-метки: registration_starts_at, registration_ends_at, cancel_self_until
- При отображении формы редактирования — вычислять обратно из diff UTC-меток (как в occurrence_edit.blade.php)
- Формат: часы+минуты split (select h + select m) + hidden total_minutes + JS change→sync
- event_management_edit: вычислять из event->starts_at + event->cancel_self_until (fixed)
- event_management_edit reg_starts: два поля reg_starts_days_before + reg_starts_hours_before; вычислять через timestamp diff: days=floor(diffSec/86400), hours=floor((diffSec%86400)/3600)
- НЕ использовать hardcoded old('field', 60) без $savedValue из модели
- РЕШЕНИЕ: давать <select> атрибут name и вычислять итог на сервере (reg_ends_h + reg_ends_m → минуты)
- step2 create: selects имеют name="reg_ends_h","reg_ends_m","cancel_lock_h","cancel_lock_m","reg_starts_d","reg_starts_h"
- EventOccurrenceService::buildRegistrationWindows() читает эти поля и вычисляет minutes (приоритет перед hidden полями)
- Scheduled expand: events:expand-recurring работает ежедневно в 03:10 и перезаписывает окна регистрации у всех future occurrences

## Occurrence override паттерн
- NULL в occurrence = наследуется от серии
- Значение записывается ТОЛЬКО при отличии от event
- editOccurrence передаёт effective-переменные через $eff() хелпер
- EventShowService::handle() накладывает overrides ПОСЛЕ Cache::remember

## PostgreSQL
- is_cancelled (boolean) — фильтровать через whereRaw('is_cancelled IS NULL OR is_cancelled = false')
- Добавлять explicit boolean casts в модели

## Blade
- @include передаёт все parent переменные автоматически
- Partials occurrence_edit: 13 штук в views/events/_partials/
- Все используют effective-переменные из контроллера
- Trix editor: /assets/trix.css + /assets/trix.js (v2.1.15, локально)

## Форма создания события — data-show-if / data-hide-if
- Логика в `step2.blade.php` → `applyAllowRegShowIf()` (глобально через window)
- Синтаксис data-show-if: `field=val` (одно), `f1=v1,f2=v2` (AND между полями), `f=v1|v2|v3` (OR внутри поля)
- Синтаксис data-hide-if: `f=v1,v2,v3` (OR по значениям), `f1=v1|f2=v2` (OR между полями — pipe разделяет условия)
- Поддерживаемые поля: allow_registration, registration_type, registration_mode, format
- Триггер: `$('form').on('change', '#registration_mode, #format', ...)` в create.blade.php

## Турниры (format=tournament) — карточка
- `tournament_teams_count` (events) — кол-во команд в турнире (НЕ использовать game_settings.max_players)
- `game_settings.subtype` = '2x2' → team_size = 2 (parse через regex `/^(\d+)x\d+$/`)
- Счётчик команд: EventRegistrationGuard добавляет tournament_teams_max/registered/remaining в meta
- Зарегистрированных команд: COUNT(DISTINCT group_key), fallback ceil(registered_total / team_size)
- Карточка: data-is-tournament="1", label " команд" через `<span data-seat-unit>`

## Dark mode (body.dark)
- Inline style="color:..." нельзя переопределить CSS-классом
- Решение для тёмных цветов: добавить класс на элемент + text-shadow с белым glow в `body.dark .class`
- Уровень 7 ("Профи М.С.") = #212121 — чёрный, невидим на тёмном фоне → class level-color-badge
- CSS: `body.dark .level-color-badge { text-shadow: 0 0 8px rgba(255,255,255,.85); }`

## Ключевые компоненты
- Карточка мероприятия: resources/views/events/_card.blade.php
- Меню профиля: resources/views/profile/_menu.blade.php
- Аватары: Spatie Media, collection='avatar', конверсия thumb
- Фото мероприятия: event_photos (JSON array Media IDs) в колонке events.event_photos (cast array)
  - Пользовательские фото: user->getMedia('event_photos'), конверсия event_thumb
  - Выбор: swiper с чекбоксами → hidden input event_photos = JSON → EventStoreService/EventManagementController
  - event_management_edit поддерживает редактирование фото: Swiper с суффиксом Edit (уникальные IDs)

## Боты
- Telegram dev: /opt/volley-telegram-bot/bot.py (порт 8092)
- Telegram prod: /opt/volleyplay-telegram-bot/bot.py (порт 8094)
- MAX dev: /opt/volley-max-bot/bot.py (порт 8091)
- VK dev: /opt/vk-bot/bot/index.php (PHP)

## Деплой (dev к prod)
cd /var/www/volleyplay/backend
git fetch origin && git merge origin/main --no-edit
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan livewire:publish --assets
php artisan migrate --force
php artisan config:clear && php artisan config:cache
php artisan route:cache && php artisan view:cache
sudo supervisorctl restart volleyplay-queue:* volleyplay-reverb

## Текущая версия: v1.9.2

## Правила работы
- В конце каждой сессии обновляй этот файл: добавляй новые находки, баги, паттерны
- Коммить изменения CLAUDE.md вместе с кодом
- Не дублируй — обновляй существующие секции
- НЕ изучай структуру проекта заново каждый раз — вся информация уже в этом файле
- Сразу приступай к задаче, используя контекст из CLAUDE.md
- Изучай конкретные файлы только когда нужно для текущей задачи

## Турнирная система v2.1
- Форматы classic: round robin, groups+playoff, single elimination, swiss
- Форматы beach: pool play, king of court, double elimination, thai, swiss
- WinRate на 4 уровнях: матч, турнир, серия, общий
- 8 новых таблиц: tournament_stages, tournament_groups, tournament_group_teams, tournament_matches, tournament_standings, player_tournament_stats, player_career_stats
- MVP = Round Robin + Олимпийка + WinRate (~15 дней)
- Полный план: tournament_plan_final.md (project files)

### Ранжирование в группе (TournamentStandingsService::rankGroup)
1. Победы (rating_points) — desc
2. Набранные очки (points_scored) — desc, без матчей против аутсайдеров
3. Разница мячей (points_scored - points_conceded) — desc, без матчей против аутсайдеров
4. Личная встреча (head-to-head)
5. Жеребьёвка — resolved tiebreaker из таблицы tournament_tiebreakers
- Аутсайдер = команда с 0 побед при played > 0; матчи против неё исключаются из критериев 2 и 3
- Тайбрейк: автодетекция pending-пар после каждого rankGroup(); организатор выбирает «матч» или «жребий»
- is_tiebreaker=true в tournament_matches → матч не учитывается в standings
- Enum method: 'match' | 'lottery' (не 'lot')

## Лиги и Сезоны
- Иерархия: League (долгоживущая) -> Season (временной период) -> Events (туры)
- Таблица leagues: HasMedia, соцсети, логотип, organizer_id, slug
- tournament_seasons.league_id FK
- Терминология: в турнире — "Группа A/B/Hard/Medium/Lite", в сезоне — "Дивизион"
- Публичные URL: /l/{leagueSlug}/s/{seasonSlug}
- Навигация: "Мои лиги и сезоны"
- Контроллеры: LeagueController (CRUD+public+admin), TournamentSeasonController
- Промоушен: TournamentPromotionService (promote/eliminate/reserve)
- Автосоздание: TournamentSeasonAutoCreateService
- План дивизионов: season_auto_pipeline_plan.md (project files)

## Система абонементов и купонов
- Модели: SubscriptionTemplate, Subscription, SubscriptionUsage, CouponTemplate, Coupon
- Сервисы: SubscriptionService, CouponService
- Jobs: CheckExpiredSubscriptions, AutoBookingSubscriptionJob, AutoUnconfirmBookingJob
- Колонки в event_registrations: subscription_id, coupon_id, confirmed_at, auto_booked
- Блок подтверждения показывается только при auto_booked=true

## Уведомления организаторов (каналы)
- Привязка через ProfileNotificationChannelController
- Платформы: telegram, vk, max
- Telegram: группы, супергруппы, каналы (channel_post)
- Форум-темы: /topic в нужной теме -> сохраняет message_thread_id в channel meta
- TelegramChannelPublisher: передаёт message_thread_id в send payload
- VK: беседы (peer_id >= 2000000000), bind_TOKEN в чат
- Таблицы: user_notification_channels, channel_bind_requests, event_notification_channels
- Анонсы: PublishOccurrenceAnnouncementService -> OccurrenceAnnouncementMessageBuilder

## Платежи и кошельки
- PaymentService — создаёт Payment при записи (методы: cash, online, wallet)
- YookassaService — онлайн-платежи через ЮKassa, вебхук: YookassaWebhookController
- VirtualWallet — wallet_id per (user_id, organizer_id), баланс в минорных единицах (balance_minor / 100)
- WalletTransaction — типы: credit / debit, хранит reason, event_id, payment_id
- PaymentSetting — настройки организатора: payment_hold_minutes (дефолт 15)
- OrganizerSubscriptionService — подписки организаторов (не путать с абонементами игроков)

## Премиум-подписки игроков
- Модель: PremiumSubscription, сервис: PremiumService
- Планы: trial (7д), month (30д), quarter (90д), year (365д)
- Поля уведомлений: weekly_digest, notify_level_min/max, notify_city_id
- Контроллер: PremiumController, настройки: PremiumSettingsController

## Лист ожидания (Waitlist)
- Модель: OccurrenceWaitlist (таблица occurrence_waitlist), сервис: WaitlistService
- Поля: positions (массив), notified_at, notification_expires_at
- Окно уведомления: 15 минут (NOTIFICATION_WINDOW_MINUTES)
- Job: CheckWaitlistNotificationJob

## Команды (EventTeam)
- Модель: EventTeam — принадлежит event_id + occurrence_id
- team_kind: classic_team | beach_pair
- Участники: EventTeamMember, приглашения: EventTeamInvite, аудит: EventTeamMemberAudit
- Заявки: EventTeamApplication
- Контроллеры: TournamentTeamController, TournamentTeamInviteController

## Миграция volleyplay.club
- Dev: volley-bot.store, Prod: volleyplay.club
- Протокол: migration-protocol.md (project files)
- Два Telegram бота: dev (VolleyEvent_bot, порт 8092), prod (VolleyEvents_bot, порт 8094)
- НЕ держать оба бота в одном канале

## Паттерн поиска игроков (autocomplete)

Эталон: `resources/views/events/show/players.blade.php` — блоки `invite-ac-*` (мульти) и `group-invite-ac-*` (одиночный).
Используется также в: trainer override (occurrence_edit), group invite.

### HTML структура

```html
{{-- Обёртка — position:relative, БЕЗ overflow:hidden --}}
<div style="position:relative" class="mb-2" id="xxx-ac-wrap">
    <input type="text" id="xxx-ac-input" autocomplete="off" class="form-control"
        placeholder="Введите имя или email игрока…">
    <div id="xxx-ac-dd" class="form-select-dropdown trainer_dd"></div>
</div>

{{-- Одиночный выбор: hidden + индикатор --}}
<input type="hidden" name="user_id" id="xxx-user-id" value="">
<div id="xxx-selected"></div>

{{-- Мульти-выбор: список chips (hidden inputs добавляются в форму динамически) --}}
<div id="xxx-selected-list" class="mb-2"></div>
```

### JS логика (IIFE, всегда)

```js
(function() {
    var input  = document.getElementById('xxx-ac-input');
    var dd     = document.getElementById('xxx-ac-dd');
    var timer  = null;

    if (!input) return; // guard обязателен

    function showDd() { dd.classList.add('form-select-dropdown--active'); }
    function hideDd() { dd.classList.remove('form-select-dropdown--active'); }
    function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

    function render(items) {
        dd.innerHTML = '';
        if (!items.length) {
            dd.innerHTML = '<div class="city-message">Ничего не найдено</div>';
            showDd(); return;
        }
        items.forEach(function(item) {
            var div = document.createElement('div');
            div.className = 'trainer-item form-select-option';
            div.innerHTML = '<div class="text-sm text-gray-900">' + esc(item.label || item.name) + '</div>';
            div.addEventListener('click', function() { pick(item.id, item.label || item.name); });
            dd.appendChild(div);
        });
        showDd();
    }

    input.addEventListener('input', function() {
        clearTimeout(timer);
        var q = input.value.trim();
        if (q.length < 2) { hideDd(); return; }
        dd.innerHTML = '<div class="city-message">Поиск…</div>';
        showDd();
        timer = setTimeout(function() {
            fetch('/api/users/search?q=' + encodeURIComponent(q), {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            })
            .then(function(r) { return r.json(); })
            .then(function(data) { render(data.items || []); })
            .catch(function() {
                dd.innerHTML = '<div class="city-message">Ошибка загрузки</div>';
                showDd();
            });
        }, 250);
    });

    document.addEventListener('click', function(e) {
        var wrap = document.getElementById('xxx-ac-wrap');
        if (wrap && !wrap.contains(e.target)) hideDd();
    });

    input.addEventListener('keydown', function(e) { if (e.key === 'Escape') hideDd(); });
})();
```

### Chips (мульти-выбор)

- `selected` — объект `{ id: label }`, hidden inputs добавляются в `form` динамически
- Chip: `span.className = 'd-flex mb-1 between f-16 fvc pl-1 pr-1'`
- Кнопка удаления: `button.className = 'trainer-chip-remove btn btn-small btn-secondary'`, `textContent = '×'`
- Hidden per chip: `input type="hidden" name="to_user_ids[]" value="ID"` + `data-invite-hidden="ID"` для удаления
- Уже выбранный item в dropdown: `div.style.opacity = '0.4'`, обработчик клика не вешается

### Одиночный выбор

- `hidden.value = String(id)`, `input.value = label`, индикатор `selected.textContent = '✅ Выбран: ' + label`
- `reset()` при вводе нового текста: `hidden.value = ''`, `btn.disabled = true`

### API

- Endpoint: `GET /api/users/search?q=QUERY`
- Ответ: `{ items: [ { id, label, name } ] }`
- `item.label || item.name` — использовать оба варианта

### Важно

- НЕ использовать `class="form-select-dropdown"` для управления видимостью — он даёт `visibility:hidden`
- Показ/скрытие ТОЛЬКО через `form-select-dropdown--active`
- `fetch` с `credentials:'same-origin'` — достаточно для большинства браузеров; на Safari при CORS-проблемах заменить на `jQuery.ajax`
- Весь JS — в IIFE `(function(){...})()`
- Guard `if (!input) return;` в начале каждого блока
- Debounce 250мс, минимум 2 символа

## Google OAuth

- Провайдер: Laravel Socialite driver('google')
- Конфиг: config/services.php → 'google' (GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET)
- Колонка: users.google_id (varchar, nullable, unique)
- Контроллер: GoogleAuthController (паттерн VkAuthController: redirect/callback, link/login intent)
- Роуты: GET /auth/google/redirect, GET /auth/google/callback
- Отвязка: POST /account/unlink/google (AccountUnlinkController::google)
- Кнопки: login.blade.php + profile/show.blade.php (Google карточка)
- Apple (только не-Android) скрывается через @unless(str_contains(UA, 'Android')), Google показывается всем
- После настройки Google Cloud Console: заменить placeholder в .env на реальные значения

## Apple Sign In — диагностика и известные баги

### `invalid_request` от Apple token endpoint
- Причина: `code` параметр пустой/отсутствует в POST от Apple
- Диагностика: `php /tmp/test_apple_token.php` с dummy кодом → должен вернуть `invalid_grant` (не `invalid_request`)
- `invalid_grant` = credentials OK, код невалиден → всё нормально
- `invalid_request` = credentials OK, но code пустой → проблема на стороне WKWebView form_post
- Конфиг: client_id=club.volleyplay.app.signin, team_id=V762R44QWF, key_id=A78BY2CKKQ
- Ключ: /var/www/volleyplay/backend/storage/app/apple/AuthKey_A78BY2CKKQ.p8
- AppleAuthController: добавлен guard для пустого code (ранний выход с логом) + session()->save() в redirect()
- Если `[APPLE_OAUTH] missing code` в логах — смотреть `keys` (что пришло в POST теле)
- Возможная причина: WKWebView не выполнил Apple form_post JS перед навигацией (race condition)
- Долгосрочный fix: @capacitor-community/apple-sign-in плагин (нативный ASAuthorizationAppleIDProvider)

## Push-уведомления (APNs) и Face ID

### Push-уведомления
- Таблица: device_tokens (user_id, platform ios/android, token unique, is_active bool)
- Модель: DeviceToken, сервис: PushNotificationService
- APNs — прямая реализация через curl HTTP/2 + JWT (ES256) — БЕЗ внешних пакетов
- laravel-notification-channels/apn несовместим с Laravel 12 + PHP 8.3
- PushNotificationService: send(userId, title, body, data=[]) — берёт активные iOS токены
- JWT: header.payload.signature, подпись openssl_sign+ES256, DER→JOSE конвертация вручную
- HTTP 410 от APNs = токен устарел → автоматически is_active=false
- HTTP 400 BadDeviceToken = токен невалиден → автоматически is_active=false
- Конфиг: config/apn.php, переменные: APN_KEY_ID, APN_TEAM_ID, APN_BUNDLE_ID, APN_PRIVATE_KEY, APN_PRODUCTION
- .p8 файл хранить в storage/app/apns/AuthKey.p8
- Канал 'push' добавлен в UserNotificationService + NotificationDeliverySender
- 4 типа с push: registration_created, event_reminder, event_cancelled, friend_joined_event
- normalizeChannels() проверяет наличие активных iOS токенов перед добавлением канала
- ВАЖНО: Xcode/debug сборка → sandbox-токены (api.sandbox.push.apple.com), TestFlight/App Store → production-токены (api.push.apple.com)
- APN_PRODUCTION=false только для dev-сервера; prod должен быть true когда идут реальные пользователи с TestFlight/App Store
- Токены регистрируются на том сервере, на который указывает WebView (prod-приложение → volleyplay.club → prod DB)
- При тестировании push с Xcode-сборкой: токены попадают в prod DB, отправлять нужно через prod-сервер с APN_PRODUCTION=false
- APNs возвращает 200 OK на sandbox но не доставляет если приложение свёрнуто и entitlements некорректны — проверять aps-environment:development в Target → Signing & Capabilities

### API endpoints
- POST /api/device-token (auth:sanctum) — сохранить/обновить токен
- DELETE /api/device-token (auth:sanctum) — деактивировать токен
- POST /api/biometric/register (auth:sanctum) — API-версия (требует Sanctum токен, НЕ для WebView)
- POST /api/biometric/login — авторизация по biometric_token, возвращает Sanctum токен
- DELETE /api/biometric/revoke (auth:sanctum) — удалить biometric_token

### Web endpoints (Capacitor/WebView — используют web-сессию)
- POST /auth/biometric-login — вход по biometric_token, создаёт web-сессию, возвращает JSON {ok, redirect}
- POST /auth/biometric-register (auth:sanctum+jetstream) — регистрация biometric_token через web-сессию
- Исключены из CSRF в bootstrap/app.php → validateCsrfTokens(except): auth/biometric-login
- НЕ использовать VerifyCsrfToken::$except — в Laravel 12 он игнорируется, только bootstrap/app.php

### Face ID
- Поле biometric_token (string 64, nullable, unique) добавлено в таблицу users
- Скрыто в $hidden User модели
- Генерация: Str::random(64), НЕ crypto.randomUUID() (недоступен в старых WKWebView до iOS 15.4)
- Использовать: ([1e7]+-1e3+-4e3+-8e3+-1e11).replace(/[018]/g, ...) через getRandomValues
- BiometricController (Api/): register/login/revoke/webLogin
- JS в voll-layout.blade.php: tryBiometricLogin (гости) + offerBiometricSetup (@auth)
- tryBiometricLogin: guard if(!meta[name="user-authenticated"]) — не запускать для авторизованных
- deleteCredentials только при 422 (невалидный токен), при 419/500 — сохранять credentials
- SESSION_SAME_SITE=none (нужен для WebView OAuth), SESSION_SECURE_COOKIE=true

### Universal Links (настроено ✓)
- AASA файл: public/.well-known/apple-app-site-association — в git, деплоится автоматически
- appID: V762R44QWF.club.volleyplay.app
- Пути: /auth/telegram/callback*, /auth/vk/callback*, /auth/yandex/callback*, /events, /events/*
- webcredentials: тот же appID (для Face ID / Associated Domains)
- nginx: location ^~ /.well-known/ { default_type application/json; allow all; try_files $uri =404; }
  добавлен в /etc/nginx/sites-available/volleyplay.club ДО блока `location ~ /\. { deny all; }`
- ВАЖНО: после изменений nginx нужен `sudo systemctl restart nginx` (не reload — он не применял конфиг)
- Проверка: curl -sI https://volleyplay.club/.well-known/apple-app-site-association → 200 OK, application/json

## Impersonation (вход от имени пользователя)
- Контроллер: `Admin/ImpersonationController` — index(), search(), start(), leave()
- Middleware: `BlockInImpersonation` → алиас `block.impersonation` в bootstrap/app.php
- Роуты: GET /admin/impersonate, GET /admin/impersonate/search, POST /admin/impersonate/start/{user}
- Выход: POST /admin/impersonate/leave — БЕЗ `can:is-admin` (вошедший является другим пользователем)
- Session key: `impersonator_id` = ID реального администратора
- start(): логирует через AdminAuditLogger (auth() = админ)
- leave(): логирует напрямую через DB::table('admin_audits') с actor_user_id = impersonator_id
- Заблокированные действия: отвязка OAuth, удаление аккаунта, платежи (user-confirm, refund, payment-settings), biometric-register
- UI: красный бар сверху через `@include('_partials.impersonation_bar')` в voll-layout
- Поиск: собственный endpoint /admin/impersonate/search (включает email, role — только для админов)
- Нельзя войти от имени другого администратора

## Дубли пользователей и очистка неактивных аккаунтов
- Поиск дублей: `UserMergeService::findDuplicates()` — по телефону + по first_name+last_name (case-insensitive)
- Таблица стаффа: `organizer_staff` (колонка staff_user_id) — не `staff_assignments`
- Роли: `users.role` IN ('admin','superadmin','organizer') — ProfileUpdateGuard::isAdmin/isOrganizer
- Telegram уведомления: `config('services.telegram.bot_token')` + `config('services.telegram.admin_chat_id')` (env: TELEGRAM_BOT_TOKEN, TELEGRAM_ADMIN_CHAT_ID)
- `CheckUserDuplicatesJob` — ежедневно 04:00, уведомляет в Telegram о дублях
- `PurgeInactiveUsersJob` — ежедневно 04:30, soft-delete аккаунтов без профиля >14 дней
- Критерии очистки: profile_completed_at IS NULL + нет регистраций/платежей/баланса + is_bot IS NOT TRUE + не admin/organizer/staff
- Artisan: `users:check-duplicates`, `users:purge-inactive [--dry-run]`

## SQL-безопасность — известные паттерны

### whereRaw с OR — обязательно в скобках
Laravel Query Builder: `->whereRaw('a IS NULL OR a = false')` без скобок генерирует:
`WHERE prev_condition AND a IS NULL OR a = false` → из-за AND>OR захватывает чужие строки.
**Всегда писать:** `->whereRaw('(a IS NULL OR a = false)')`
Аудит проведён 2026-05-04, все места исправлены.

### AdminUserController::deleteOrPurge
Сейчас делает hard delete 30+ таблиц без транзакции (try-catch на каждый DELETE — намеренно для PostgreSQL).
**Задача:** переход на soft delete — анонимизировать PII (ФИО/телефон/email), ставить deleted_at, НЕ удалять историю (регистрации, платежи). См. память project_soft_delete_users.md
