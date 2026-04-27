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

## JS файлы
- lib.js — везде, script.js — логика+swal, fas.js — fancybox+swiper, org.js — орг. панель
- swal: класс .btn-alert + data-атрибуты
- Fancybox = jQuery: jQuery.fancybox.open({src:'#id',type:'inline'}), НЕ standalone
- Safari: использовать jQuery.ajax (не fetch — CORS), polling 200мс (не input/keyup)
- Safari select bug: использовать change (не input) для <select> — input не срабатывает
- Класс form-select-dropdown даёт visibility:hidden — НЕ использовать для dropdown

## Окно регистрации (паттерн)
- Данные хранятся как UTC-метки: registration_starts_at, registration_ends_at, cancel_self_until
- При отображении формы редактирования — вычислять обратно из diff UTC-меток (как в occurrence_edit.blade.php)
- Формат: часы+минуты split (select h + select m) + hidden total_minutes + JS change→sync
- event_management_edit: вычислять из event->starts_at + event->cancel_self_until (fixed)
- НЕ использовать hardcoded old('field', 60) без $savedValue из модели
- createCustomSelect (script.js) оборачивает все .form select — jQuery trigger может не вызывать нативные addEventListener
- РЕШЕНИЕ: давать <select> атрибут name и вычислять итог на сервере (reg_ends_h + reg_ends_m → минуты)
- step2 create: selects имеют name="reg_ends_h","reg_ends_m","cancel_lock_h","cancel_lock_m","reg_starts_d","reg_starts_h"
- EventOccurrenceService::buildRegistrationWindows() читает эти поля и вычисляет minutes (приоритет перед hidden полями)
- OccurrenceExpansionService::expand() читает offsets из reference occurrence (первый occ по uniq_key) и пропагирует на все future occs
- ВАЖНО: Carbon::diffInSeconds() по умолчанию absolute=false (знаковое!) — использовать timestamp арифметику: $a->timestamp - $b->timestamp
- Scheduled expand: events:expand-recurring работает ежедневно в 03:10 и ПЕРЕЗАПИСЫВАЕТ окна регистрации у всех future occurrences

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

## Ключевые компоненты
- Карточка мероприятия: resources/views/events/_card.blade.php
- Меню профиля: resources/views/profile/_menu.blade.php
- Аватары: Spatie Media, collection='avatar', конверсия thumb

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
php artisan migrate --force
php artisan config:clear && php artisan config:cache
php artisan route:cache && php artisan view:cache
sudo supervisorctl restart volleyplay-queue:* volleyplay-reverb

## Текущая версия: v1.9.2

## Правила работы
- В конце каждой сессии обновляй этот файл: добавляй новые находки, баги, паттерны
- Коммить изменения CLAUDE.md вместе с кодом
- Не дублируй — обновляй существующие секции

## Турнирная система v2.1
- Форматы classic: round robin, groups+playoff, single elimination, swiss
- Форматы beach: pool play, king of court, double elimination, thai, swiss
- WinRate на 4 уровнях: матч, турнир, серия, общий
- 8 новых таблиц: tournament_stages, tournament_groups, tournament_group_teams, tournament_matches, tournament_standings, player_tournament_stats, player_career_stats
- MVP = Round Robin + Олимпийка + WinRate (~15 дней)
- Полный план: tournament_plan_final.md (project files)

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
