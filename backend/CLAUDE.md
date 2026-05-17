# Volley2026 — Контекст проекта

## Язык
- Всегда отвечай на русском языке

## Стек
- Laravel 12, PHP 8.3, PostgreSQL, Blade (частично Vue), jQuery v3.7.1
- Telegram/VK/MAX бот интеграции
- Сервер: /var/www/volley-bot/backend (dev), /var/www/volleyplay/backend (prod)

## Локализация (i18n)
- Сайт двуязычный: RU (по умолчанию) и EN
- Все строки интерфейса через `{{ __('файл.ключ') }}`, не хардкодом в blade
- При добавлении нового текста — обязательно добавлять перевод в `lang/ru/*.php` И `lang/en/*.php`
- Языковые файлы: `ui`, `auth`, `events`, `profile`, `locations`, `subscriptions`, `notifications`, `admin`, `tournaments`, `seasons`, `pages`, `welcome`
- Контент пользователей (названия событий, описания, имена, города) НЕ переводится
- HTML в переводах выводить через `{!! __('файл.ключ') !!}`
- Подстановки: `__('events.foo', ['name' => $value])` → ключ содержит `:name`
- Locale переключается через `SetLocale` middleware + `LocaleController`; кука/сессия хранят выбор пользователя

## Серверные особенности
- php artisan tinker --execute НЕ работает
- Использовать: cat > /tmp/file.php + php -r с bootstrap
- sudo нужен для root-файлов (org.js, некоторые blade)
- sed -i для простых замен, Python для сложных (спецсимволы, табы)
- CarbonImmutable::diffInSeconds() — absolute=false по умолчанию, возвращает отрицательные значения; использовать $a->timestamp - $b->timestamp
- createCustomSelect в script.js оборачивает все .form select — селекты без name атрибута не отправляются на сервер
- ExpandEventOccurrencesJob/OccurrenceExpansionService: offset для reg_starts/reg_ends/cancel_lock берётся из первой (reference) occurrence, не хардкод

## Storage permissions (DOMPDF, кеш шрифтов)
- PHP-FPM работает под www-data, файлы в `storage/fonts/` исторически могут принадлежать `appuser:appuser` → www-data не может перезаписать `installed-fonts.json` → 500 при экспорте PDF
- Симптом: `file_put_contents(.../storage/fonts/installed-fonts.json): Permission denied` при `EventRegistrationsManagementController::exportPdf`
- Фикс: `sudo chown -R appuser:www-data storage/fonts && sudo chmod -R g+rwX storage/fonts && sudo chmod g+s storage/fonts`
- `g+s` (setgid) критично — новые файлы наследуют group=www-data; на dev уже `drwxrwsr-x`

## PHP-FPM opcache (КРИТИЧНО на проде)
- `php artisan config:cache` / `route:cache` обновляют файлы, но opcache продолжает отдавать старые версии
- При изменениях в `app/Http/Middleware/*`, `config/*.php`, новых классов — **обязательно** `sudo systemctl reload php8.3-fpm` ПОСЛЕ deploy
- Признак: код новый, `php artisan` (CLI) видит правильно, но веб-запросы ведут себя как со старой версией
- supervisorctl restart НЕ заменяет reload php-fpm

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

## PostgreSQL
- is_cancelled (boolean) — фильтровать через whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
- Добавлять explicit boolean casts в модели
- **whereRaw с OR** — ВСЕГДА оборачивать в скобки, иначе AND>OR нарушает логику

## Blade
- @include передаёт все parent переменные автоматически
- Partials occurrence_edit: 13 штук в views/events/_partials/
- Trix editor: /assets/trix.css + /assets/trix.js (v2.1.15, локально)

## Форма создания события — data-show-if / data-hide-if
- Логика в `step2.blade.php` → `applyAllowRegShowIf()` (глобально через window)
- Синтаксис data-show-if: `field=val` (одно), `f1=v1,f2=v2` (AND), `f=v1|v2|v3` (OR внутри поля)
- Синтаксис data-hide-if: `f=v1,v2,v3` (OR по значениям), `f1=v1|f2=v2` (OR между полями)
- Поддерживаемые поля: allow_registration, registration_type, registration_mode, format
- Триггер: `$('form').on('change', '#registration_mode, #format', ...)` в create.blade.php

## Турниры (format=tournament) — карточка
- `tournament_teams_count` (events) — кол-во команд в турнире (НЕ использовать game_settings.max_players)
- `game_settings.subtype` = '2x2' → team_size = 2 (parse через regex `/^(\d+)x\d+$/`)
- Счётчик команд: EventRegistrationGuard добавляет tournament_teams_max/registered/remaining в meta
- Зарегистрированных команд: COUNT(DISTINCT group_key), fallback ceil(registered_total / team_size)
- Карточка: data-is-tournament="1", label " команд" через `<span data-seat-unit>`

## Индивидуальная запись на турнир (tournament_individual)
- `registration_mode = 'tournament_individual'` — игроки записываются по амплуа, не командами
- Чекбокс `tournament_individual_reg` в форме создания (step1.blade.php) в блоке `#tournament_settings_block`
- Role slots создаются как для обычной игры: позиции × teams_count; резерв = reserve_per_team × teams_count
- EventGameSettingsService: при `tournament_individual_reg=1` → `game_subtype = tournament_game_scheme`, `teams_count = tournament_teams_count`
- Guard: `tournament_individual` НЕ требует команду; waitlist включён (как обычная игра)
- players.blade.php: `$isIndividualTournament = format=tournament && registration_mode=tournament_individual`; кнопка "Распределить случайно" → POST /events/{id}/distribute-individual
- TournamentTeamDistributionService::distributeRandom() — round-robin по позициям, первый участник = капитан, не перезаписывает существующие команды

## Dark mode (body.dark)
- Inline style="color:..." нельзя переопределить CSS-классом
- Решение: добавить класс + text-shadow с белым glow в `body.dark .class`
- Уровень 7 ("Профи М.С.") = #212121 — чёрный → class `level-color-badge`; CSS: `body.dark .level-color-badge { text-shadow: 0 0 8px rgba(255,255,255,.85); }`

## Ключевые компоненты
- Карточка мероприятия: resources/views/events/_card.blade.php
- Меню профиля: resources/views/profile/_menu.blade.php
- Аватары: Spatie Media, collection='avatar', конверсия thumb
- Фото мероприятия: event_photos (JSON array Media IDs) в колонке events.event_photos (cast array); конверсия event_thumb; Swiper+чекбоксы → hidden input event_photos = JSON

## Боты
- Telegram dev: /opt/volley-telegram-bot/bot.py (порт 8092)
- Telegram prod: /opt/volleyplay-telegram-bot/bot.py (порт 8094)
- MAX dev: /opt/volley-max-bot/bot.py (порт 8091)
- VK dev: /opt/vk-bot/bot/index.php (PHP)

## MAX API — редактирование сообщений с фото
- PUT /messages удаляет фото если не передать photo_id обратно в attachments
- `MaxChannelPublisher::update()` извлекает photo_id из `previousMeta.saved_image_attachment`, затем из `raw.raw.message.body.attachments`
- После PUT сохраняет `saved_image_attachment` в meta для следующих обновлений

## Деплой (dev к prod)
- Прод находится на ветке `production` (НЕ `main`)
- Деплой: merge origin/main в production; проверить: `git merge-base --is-ancestor <hash> HEAD && echo "уже в проде"`

```
cd /var/www/volleyplay/backend
git fetch origin && git merge origin/main --no-edit
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan livewire:publish --assets
php artisan migrate --force
php artisan config:clear && php artisan config:cache
php artisan route:clear && php artisan route:cache
php artisan view:clear && php artisan view:cache
sudo supervisorctl restart volleyplay-queue:* volleyplay-reverb
sudo systemctl reload php8.3-fpm   # ← обязательно при изменении PHP-классов/config
```

## Текущая версия: v1.9.4

## Правила работы
- В конце каждой сессии обновляй этот файл: добавляй новые находки, баги, паттерны
- Коммить изменения CLAUDE.md вместе с кодом
- Не дублируй — обновляй существующие секции
- НЕ изучай структуру проекта заново каждый раз — вся информация уже в этом файле
- Сразу приступай к задаче, используя контекст из CLAUDE.md

## Турнирная страница управления (setup.blade.php)
- Файл: `resources/views/tournaments/setup.blade.php` (~1650 строк)
- Partials: `resources/views/tournaments/_partials/`
- `group_crosstable.blade.php` — кросс-таблица; принимает: `$group`, `$groupMatches`, `$groupClean`, `$groupOutsiders`; ячейки победа=зелёный/поражение=красный/не сыгран=серый; итоги: М, В, Сеты, Очки, Мячи, Место
- Переключатель Список/Шахматка: `.ct-view-btn`, блоки `.ct-view-list`/`.ct-view-crosstable[data-group]`; localStorage `ct_view_pref`; показывается только при `$hasCrosstable`
- matches-цикл: `$stage->matches->groupBy('group_id')`; `$groupForCross = $stage->groups->firstWhere('id', $groupId)`; `$crossClean = $cleanStatsByGroup[$groupId] ?? []`

## Турнирная система v2.1
- Форматы classic: round robin, groups+playoff, single elimination, swiss
- Форматы beach: pool play, king of court, double elimination, thai, swiss
- WinRate на 4 уровнях: матч, турнир, серия, общий
- 8 новых таблиц: tournament_stages, tournament_groups, tournament_group_teams, tournament_matches, tournament_standings, player_tournament_stats, player_career_stats
- Полный план: tournament_plan_final.md (project files)

### Ранжирование в группе (TournamentStandingsService::rankGroup)
1. Победы (rating_points) — desc
2. Набранные очки (clean points_scored) — desc, без матчей против аутсайдеров
3. Разница мячей (clean diff) — desc, без матчей против аутсайдеров
4. Личная встреча (h2h транзитивно среди tied tuple)
5. resolved_order из `tournament_tiebreaker_sets` (новый формат, N команд)
6. Legacy: попарный resolved tiebreaker из `tournament_tiebreakers`
7. Иначе — все команды tuple получают одинаковый rank, создаётся pending `TournamentTiebreakerSet`
- Аутсайдер = команда с 0 побед при played > 0; матчи против неё исключаются из критериев 2 и 3
- `TournamentTiebreakerSet`: 3 варианта resolve: `full_diff` | `match` (RR, points_to_win 1..30, two_point_margin) | `lottery`
- Метод `match`: создаются is_tiebreaker=true матчи; при submitScore последнего — `maybeResolveTiebreakerSet` авторезолвит
- is_tiebreaker=true → матч не учитывается в standings/cleanStats
- В standings показ `чистая / (полная)` если отличаются; аутсайдер — opacity 0.7, подпись «· аутсайдер»

## Лиги и Сезоны
- Иерархия: League (долгоживущая) -> Season (временной период) -> Events (туры)
- Таблица leagues: HasMedia, соцсети, логотип, organizer_id, slug; tournament_seasons.league_id FK
- Терминология: в турнире — "Группа A/B/Hard/Medium/Lite", в сезоне — "Дивизион"
- Публичные URL: /l/{leagueSlug}/s/{seasonSlug}
- Контроллеры: LeagueController (CRUD+public+admin), TournamentSeasonController
- Промоушен: TournamentPromotionService; автосоздание: TournamentSeasonAutoCreateService
- План дивизионов: season_auto_pipeline_plan.md (project files)

## Система абонементов и купонов
- Модели: SubscriptionTemplate, Subscription, SubscriptionUsage, CouponTemplate, Coupon
- Сервисы: SubscriptionService, CouponService
- Jobs: CheckExpiredSubscriptions, AutoBookingSubscriptionJob, AutoUnconfirmBookingJob
- Колонки в event_registrations: subscription_id, coupon_id, confirmed_at, auto_booked

## Уведомления организаторов (каналы)
- Привязка через ProfileNotificationChannelController; платформы: telegram, vk, max
- Telegram: группы/супергруппы/каналы; форум-темы: сохраняет message_thread_id в channel meta
- VK: беседы (peer_id >= 2000000000), bind_TOKEN в чат
- Таблицы: user_notification_channels, channel_bind_requests, event_notification_channels, event_channel_messages, channel_publish_logs
- Анонсы: PublishOccurrenceAnnouncementService -> OccurrenceAnnouncementMessageBuilder
- При создании event: анонс сразу для первой occurrence с `registration_starts_at <= now()` (EventStoreService.php:617)
- Будущие occurrences: `events:publish-pending-announcements` (routes/console.php, каждую минуту)
- При записи/отписке: `RefreshOccurrenceAnnouncementJob`; повторные publish() с тем же hash → skip

## Laravel 12 schedule
- ВСЕ scheduled команды в `routes/console.php` (`Schedule::command(...)`), НЕ в Console/Kernel.php
- `bootstrap/app.php` → `->withSchedule(...)` — наследие (одна команда), новые туда НЕ добавляем
- Проверка: `php artisan schedule:list`

## Платежи и кошельки
- PaymentService — создаёт Payment при записи (методы: cash, online, wallet)
- YookassaService — онлайн-платежи, вебхук: YookassaWebhookController
- VirtualWallet — wallet_id per (user_id, organizer_id), баланс в минорных единицах (balance_minor / 100)
- WalletTransaction — типы: credit / debit; PaymentSetting — payment_hold_minutes (дефолт 15)
- OrganizerSubscriptionService — подписки организаторов (не путать с абонементами игроков)

## Премиум-подписки игроков
- Модель: PremiumSubscription, сервис: PremiumService
- Планы: trial (7д), month (30д), quarter (90д), year (365д)
- Поля уведомлений: weekly_digest, notify_level_min/max, notify_city_id
- Контроллер: PremiumController, настройки: PremiumSettingsController

## Лист ожидания (Waitlist)
- Модель: OccurrenceWaitlist (таблица occurrence_waitlist), сервис: WaitlistService
- Триггер: EventRegistrationObserver::deleted/updated → WaitlistService::onSpotFreed
- **Индивидуальная регистрация (НЕ турниры)** — `autoBookNext`: АВТОМАТИЧЕСКИ записывает первого подходящего; очередь: премиум первыми, затем по created_at
  - **КРИТИЧНО**: PostgreSQL не поддерживает FOR UPDATE на nullable стороне LEFT JOIN → использовать EXISTS subquery вместо leftJoin для сортировки по premium
  - Лимит: 20 итераций на один вызов; при превышении — warning в лог
  - Платные: PaymentService::createForRegistration; **releaseExpired использует Eloquent save()** (не Query Builder) — иначе Observer не срабатывает
  - auto_booked=true в event_registrations, поле НЕ в $fillable — через свойства+save
- **Турниры** — старая логика (`notifyNext` + CheckWaitlistNotificationJob); UI записи в waitlist на турнир заблокирован
- **checkWaitlistGate** (`EventRegistrationGuard`): блокирует только если в очереди есть участник, который РЕАЛЬНО может занять одну из свободных основных позиций:
  - Проверяется пересечение `waitlist.positions` (empty = любая) с `freeMainKeys` (free_positions без reserve)
  - При `mixed_limited`: применяется гендерный фильтр — если ограниченный пол не может взять ни одну свободную позицию, он не считается блокирующим
  - Если `hasBlockingOthers=false` → gate не блокирует, даже при наличии людей в очереди
  - Если `hasBlockingOthers=true` и есть свободный reserve → разрешает только reserve (waitlist_only не ставится)
  - Если `hasBlockingOthers=true` и reserve нет → `waitlist_only=true`, ошибка; турниры пропускаются
  - **Типичный баг**: autoBookNext не нашёл никого для освободившегося слота (гендер/позиции), место осталось пустым, но очередь не очищается → gate некорректно блокировал. Фикс: смотреть в checkWaitlistGate на eligibility, а не просто на exists()
- **Взаимное исключение**: нельзя встать в waitlist если уже в составе (OccurrenceWaitlistController::store()); нельзя в состав если уже в waitlist (EventRegistrationController::storeOccurrence())
- Слоты: классика — event_role_slots (setter/outside/middle/opposite/libero/reserve); пляжка — один слот role='player'
- `getSlots` кешируется — два вызова в blade = один SQL запрос

## Команды (EventTeam)
- Модель: EventTeam — принадлежит event_id + occurrence_id; team_kind: classic_team | beach_pair
- Участники: EventTeamMember, приглашения: EventTeamInvite, аудит: EventTeamMemberAudit, заявки: EventTeamApplication
- Контроллеры: TournamentTeamController, TournamentTeamInviteController

## Сохранённые команды игрока (UserTeam)
- Таблицы: `user_teams` + `user_team_members` (role_code, position_code)
- Сервис: `UserTeamValidationService` — validateForEvent() + checkTeamSize()
- Контроллер: `UserTeamController` CRUD на `/user/teams/{team}`
- `TournamentTeamController::saveToProfile()` — EventTeam → UserTeam (только капитан)
- `TournamentTeamController::fromSaved()` — EventTeam из UserTeam, рассылает invites
- При ошибках валидации → редирект на `/user/teams/{team}/edit?event_id=X` с session('team_validation_errors')

## Позиция reserve в регистрациях
- `resolvePositions()` НЕ включает 'reserve' — добавляется отдельно в index()/addPlayer()/updatePosition()
- Источник лимита: event_role_slots.role='reserve' ИЛИ game_settings.reserve_players_max (fallback)
- Если reserveMax=0 но есть legacy-записи с position='reserve' → показываем без лимита

## Android WebView — скачивание файлов
- `Content-Disposition: attachment` молча игнорируется Android WebView
- Фикс: `if (window.Capacitor && window.Capacitor.getPlatform() === 'android') { window.open(url, '_system'); }`
- Применено в: registrations/index.blade.php (PDF/TXT), tournaments/public/show.blade.php (PDF)

## event_management_edit — город и timezone
- Поле timezone: `<input type="hidden" name="timezone" id="mgmt_timezone_hidden">`
- City autocomplete → AJAX `/ajax/cities/meta` обновляет timezone + `/ajax/locations/by-city` фильтрует локации

## Миграция volleyplay.club
- Dev: volley-bot.store, Prod: volleyplay.club
- Два Telegram бота: dev (VolleyEvent_bot, порт 8092), prod (VolleyEvents_bot, порт 8094)
- НЕ держать оба бота в одном канале

## Паттерн поиска игроков (autocomplete)
Эталон: `resources/views/events/show/players.blade.php` (блоки `invite-ac-*` и `group-invite-ac-*`).
- Обёртка: `position:relative`, БЕЗ `overflow:hidden`; дропдаун через класс `form-select-dropdown--active` (НЕ через `form-select-dropdown` — он даёт visibility:hidden)
- JS в IIFE; guard `if (!input) return;`; debounce 250мс, минимум 2 символа
- API: `GET /api/users/search?q=QUERY` → `{ items: [{id, label, name}] }`; использовать `item.label || item.name`
- Chips (мульти): `name="to_user_ids[]"` + `data-invite-hidden="ID"` для удаления; выбранные — opacity 0.4
- Safari: заменять fetch на jQuery.ajax при CORS-проблемах; credentials:'same-origin' обычно достаточно

## Google OAuth
- Провайдер: Laravel Socialite driver('google'); конфиг: config/services.php (GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET)
- Колонка: users.google_id; контроллер: GoogleAuthController; отвязка: AccountUnlinkController::google
- Кнопки OAuth в ДВУХ местах: login.blade.php + voll-layout.blade.php (попап шапки) — обновлять оба
- Apple скрывается на Android; Google скрывается на Apple (iPhone/iPad/Macintosh)

## Smart App Banner и RuStore баннер
- iOS Smart App Banner: `<meta name="apple-itunes-app" content="app-id=6764748613">` в voll-layout.blade.php; обёрнут в @if(!VolleyPlayApp)
- Android RuStore: JS-блок после `<body>`; UA=Android && !VolleyPlayApp; закрытие → localStorage 7 дней; MutationObserver на body.classList для dark mode

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
- profile/complete.blade.php постит на `route('profile.extra.update')` (ProfileExtraController)

## Дубли пользователей и очистка неактивных аккаунтов
- Поиск дублей: `UserMergeService::findDuplicates()` — по телефону + по first_name+last_name (case-insensitive)
- Таблица стаффа: `organizer_staff` (колонка staff_user_id); роли: users.role IN ('admin','superadmin','organizer')
- `CheckUserDuplicatesJob` — ежедневно 04:00, уведомляет в Telegram (TELEGRAM_BOT_TOKEN, TELEGRAM_ADMIN_CHAT_ID)
- `PurgeInactiveUsersJob` — ежедневно 04:30; критерии: profile_completed_at IS NULL + нет регистраций/платежей/баланса + не bot/admin/organizer/staff
- Artisan: `users:check-duplicates`, `users:purge-inactive [--dry-run]`

### UserMergeService::merge() — что переносится
- OAuth поля (unique): telegram_id, vk_id, yandex_id, apple_id, google_id — из secondary только если у primary пусто; сначала обнуляются у secondary
- Профиль (если пусто у primary): phone, first_name, last_name, patronymic, birth_date, city_id, gender, height_cm, classic_level, beach_level
- user_beach_zones (дедупликация по zone), user_classic_positions (по position)
- Также: event_registrations, payments, virtual_wallets, subscriptions, coupons, premium_subscriptions, user_notification_channels, occurrence_waitlist, event_team_members/invites/applications, friendships, статистика, device_tokens
- НЕ переносятся: event_team_member_audits, notification_deliveries, page_views, account_delete_requests

## SQL-безопасность
- whereRaw с OR — ВСЕГДА в скобках: `->whereRaw('(a IS NULL OR a = false)')`, иначе AND>OR захватывает чужие строки
- `AdminUserController::deleteOrPurge`: hard delete 30+ таблиц без транзакции — задача переход на soft delete (анонимизация PII + deleted_at). См. память project_soft_delete_users.md
