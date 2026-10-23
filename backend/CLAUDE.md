# Volley2026 — Контекст проекта

Полная версия до реорганизации: `docs/archive/CLAUDE.full-2026-10-23.md` (ничего не удалено). Детали по темам — в `docs/*.md`, см. оглавление ниже.

## Язык и стек
- Всегда отвечай на русском.
- Laravel 12, PHP 8.3, PostgreSQL, Blade (частично Vue), jQuery 3.7.1; Telegram/VK/MAX боты.
- Dev: `/var/www/volley-bot/backend` (volley-bot.store, ветка `main`). Prod: `/var/www/volleyplay/backend` (volleyplay.club, ветка `production`, НЕ `main`). Перед правкой проверять `pwd`/`git branch` — не править прод напрямую.
- Ops (GlitchTip, Uptime Kuma, бэкапы) — в `~/.claude/CLAUDE.md`; при прод-багах сначала issues в GlitchTip.

## Оглавление docs/ — «перед работой над темой X прочитай docs/X.md»
- `deploy.md` — протокол деплоя, перенос отдельных коммитов dev→prod
- `server-ops.md` — серверные особенности, php-fpm/opcache/nginx, инцидент 504, SQL-правила, планировщик, миграция домена
- `frontend.md` — JS/CSS/Blade, WKWebView, custom select, чекбоксы, Trix, sticky-шапка, скачивание файлов, autocomplete, dark mode
- `i18n-locale.md` — локализация, автодетект языка
- `events-registration.md` — окна регистрации, occurrence override, повторяющиеся серии, master_class, схемы игры, «по амплуа», меню организатора, управление регистрациями
- `waitlist-teams.md` — лист ожидания, race/advisory lock, команды, сохранённые команды
- `tournaments.md` — турнирная система, setup, king beach, рейтинги (OpenSkill/Elo), лиги/сезоны/промоушен, игра со статистикой
- `subscriptions-payments.md` — абонементы/купоны, Premium, Организатор Pro, ЮKassa, кошельки
- `notifications-bots.md` — уведомления, анонсы в каналах (rich/VK/MAX), ретрай доставок, боты, тест на уровень, антиспам
- `accounts-auth.md` — OAuth, Apple/Face ID/APNs, impersonation, профиль, дубли/слияние, ограничения, пустышки, staff
- `brands-widget.md` — white-label бренды, «Настройка App», токены темы, виджет
- `broadcast.md` — трансляция матчей (оверлей, ведущий, каналы, эфир из приложения)
- `club-module.md` — клубный модуль, школы, локации
- `trainer-module.md`, `dashboard-analytics.md`, `features-pages.md` (погода, фото, SEO, /about, /help, профиль/уровни, мультистрановость), `history.md` (хронология), `working-rules.md`
- Старые: `claude_docs/*.md` (rating, seo, club, ble, tournament bugs и т.д.), `docs/architecture.md`, `docs/events.md`, `docs/security.md`.

## Запреты (жёстко)
- На проде НЕ создавать тестовые роуты/пользователей/данные. Проверки мутаций — только `DB::beginTransaction()`/`rollBack()` (dev; рассылки/push живым пользователям при тестах — тоже только в откатываемой транзакции или с `Http::fake`). Синтетика для гонок — отдельные процессы + ручной DELETE.
- Диагностические PHP-скрипты (особенно на проде) сразу после bootstrap отвязывают Sentry: `\Sentry\SentrySdk::getCurrentHub()->bindClient(\Sentry\ClientBuilder::create(['dsn'=>null])->getClient());` (`config()`/env/`SENTRY_LARAVEL_DSN=` на проде не помогают — DSN в кеше конфига). Иначе ложный алерт «PROD» в Telegram.
- `sudo` в сессии без пароля нет: `sudo`-команды (reload php-fpm, supervisorctl, nginx restart, `deploy.sh` ботов) запускает пользователь в СВОЁМ терминале (не `!`-префиксом в чате — в bash `!` отрицает команду). После раскатки сверять результат (`cmp`, `ps -eo lstart`, `systemctl is-active`, лог).
- Файлы бэкапа/`.env`-подобное на проде, которое не бэкапится, — сообщить пользователю, не чинить самому. Сторона backup-сервера не наша.
- Отчёты >50 строк — файлом `report_*.md`/`deploy_*.md`/`diagnosis_*.md` в корень dev (в `.gitignore`, в git не коммитить, не восстанавливать удалённые пользователем). Ответ >75 строк вывода — в `report/`, в чат резюме.
- Не добавлять Service Worker. Не слать «контрольные» сообщения в боевые группы/каналы. `git push origin main` разрешён, перед пушем проверять `git log origin/main..main`.
- Перед коммитом незнакомые изменения в diff — спрашивать, не делать выводов самому. Коммит CLAUDE.md вместе с кодом.

## Деплой (кратко; детали — docs/deploy.md)
Прод: `git fetch origin && git merge origin/main --no-edit` → `composer install --no-dev --optimize-autoloader` → `npm install --legacy-peer-deps && npm run build` (`npm ci` не работает) → `migrate --force` → `config/route/view:clear+cache` → `sudo supervisorctl restart volleyplay-queue:* volleyplay-reverb` → `sudo systemctl reload php8.3-fpm` (обязательно при изменении PHP-классов/config/middleware — opcache). Если есть миграция + код, трогающий новую таблицу на каждом запросе — merge→migrate→cache ОДНИМ блоком без пауз. После прод-деплоя — проверить unresolved issues в GlitchTip. Новые файлы в `config/` и добавленные методы в сервисах, вызываемых из очереди, — `config:cache` / `queue:restart`. Перед удалением/переименованием роута — grep по URL И имени (`route('name')`), `php artisan routes:check-usage`.

## Ключевые правила и ловушки (по одной строке)
- `php artisan tinker --execute` не работает → `cat > /tmp/x.php` + bootstrap. `sudo` нужен для root-файлов (часть blade, org.js).
- Scheduled-задачи — ТОЛЬКО в `routes/console.php` (Kernel.php удалён); сохранять `pingOnSuccessIf`/heartbeat для 4 push-мониторов; проверка `schedule:list`. При `route:cache` файлы `routes/*.php` не исполняются — `RateLimiter::for()` и т.п. держать в провайдере.
- Laravel 12 CSRF: исключения только `bootstrap/app.php` → `validateCsrfTokens(except:[...])` (`$except` и `withoutMiddleware` не работают). Глобальный middleware, читающий сессию, должен быть в `appendToGroup('web')` (после StartSession).
- php-fpm: `PHP_VALUE/PHP_ADMIN_VALUE` из nginx прилипают к воркеру общего пула — настройки dev/prod только в `php_admin_value` отдельного пула. nginx — только `restart`, не `reload`. DOMPDF `storage/fonts` — chown appuser:www-data + setgid.
- `users.gender` = `'m'`/`'f'`. PostgreSQL: `whereRaw` с OR — в скобки; `is_cancelled` фильтровать `(is_cancelled IS NULL OR is_cancelled=false)`; `LOWER()` не понижает кириллицу; ORDER BY DESC кладёт NULL первыми.
- Поля, отсутствующие в `$fillable` (напр. `event_registrations.subscription_id/auto_booked`) — ставить свойствами + `save()`; Eloquent `save()` (не `DB::table`) там, где нужен Observer (освобождение места/лист ожидания).
- Blade: `{{ }}` внутри `<script>` ломает JS — только `@json($x)`/`(int)`; `@json()` в `onclick` запрещён (данные в `data-*`); сложные массивы/`__()` внутри `@json(...)` собирать в `@php` заранее; `@else@include` без пробелов не компилируется; `view:cache` не ловит битый скомпилированный Blade (проверять `tools/blade-lint.php`).
- WKWebView: `<datalist>` не работает; `<a href="#anchor">` без `preventDefault` дёргает viewport; скролл к якорю под липкой шапкой — не нативный smooth (rAF-анимация), отступ через `window.getFixedHeaderBottom()`.
- Динамические `<select>`/чекбоксы внутри `.form` невидимы без `createCustomSelect()` / `.custom-checkbox`; `.checkbox-item` требует предка `.form`; в `.row row2` нет вертикального gap; `.card{height:100%}`; Bootstrap-классов в проекте нет.
- Кнопка скачивания файла/отчёта ОБЯЗАНА иметь `data-file-download` (иначе ломается в Capacitor).
- Картинки nginx кеширует 30 дней immutable — при замене менять имя файла. APK в `public/downloads/` не в git.
- Цифры «на сейчас» (места, очередь) в джобах-уведомлениях считать в конструкторе, не в `handle()`.
- Новый потребитель матчей для рейтингов — `->ratable()`; подсчёт команд турнира — `TournamentTeamService::countRegisteredTeams()`; позиции классики — `EventRoleSlotService::resolvePositions()`.
- Скоуп бренда: «Настройка App»/`/admin/apps` — только white-label (`!$brand->is_default`), основной VolleyClub не трогать; проверять curl с UA `SVSVolleyApp`/`VolleyPlayApp`/браузер.
- Дедуп повторяющихся команд — отдельной колонкой `notified_*_at`, не бизнес-полем.
- Бот-код — отдельный репозиторий `/home/appuser/volley-bots` (`./deploy.sh <бот> <env>`), `/opt/*` руками не править.
- Новый текст интерфейса — через `__('файл.ключ')` в `lang/ru` И `lang/en`; пользовательский контент не переводится.
- После правок вёрстки — проверка скриншотами (`tools/shot.mjs`, скилл `visual-check`) на 360/390/1280 в обеих темах.

## Правила работы
- Не изучай структуру проекта заново: сначала `docs/<тема>.md`, затем задача. В конце сессии новые находки — в соответствующий `docs/*.md` (не в этот файл; сюда — только то, что нужно в КАЖДОЙ сессии). Не дублируй — обновляй существующие секции. Хронология — `docs/history.md`.
