# Восстановление сервера (после сбоя 2026-11-02)

_Составлено 2026-11-04 по итогам переезда на новый сервер. Секретов в этом файле нет — только где они лежат. Репозиторий публичный, пароли и ключи сюда не добавлять._

> ## ⚠️ ВАЖНО: ВРЕМЯ И ЧАСОВОЙ ПОЯС (проверять при ЛЮБОМ восстановлении)
> - Приложение работает в **UTC** (`APP_TIMEZONE`/`config('app.timezone')` = UTC), время в БД хранится в UTC.
> - Сессия PostgreSQL **обязана быть в UTC**: `ALTER DATABASE volleyplay SET timezone TO 'UTC';` и в коде `'timezone' => 'UTC'` в `config/database.php` (подключение `pgsql`). Часовой пояс ОС/PostgreSQL сервера (на новом сервере — Europe/Moscow) на это влиять не должен.
> - Если этого не сделать: Laravel пишет время UTC-строками без смещения, PostgreSQL читает их как московское и **сдвигает все `timestamptz` на −3 часа** (окна регистрации, отмены, старты событий). Следствие — **дубли мероприятий и occurrences при правке организатором** (ключ повтора строится из `starts_at`) и неверные даты. Так было 2026-11-02…04.
> - Проверка после восстановления: `SHOW timezone;` в сессии Laravel (`DB::selectOne('show timezone')`) → `UTC`; сохранить тестовое событие в откатываемой транзакции и убедиться, что время не сдвигается; `events:expand-recurring` не должен менять смещения регистрации/отмены у существующих записей.
> - Если сдвиг уже произошёл: не править вручную вслепую — сначала `pg_dump`, затем искать записи, у которых смещение окна регистрации/отмены относительно старта отличается от смещения события ровно на −3 ч, и пересчитывать генератором (см. `history.md`, 2026-11-04).

## Что где (сервер 141.98.188.140, один сервер = prod)
- Сайт: `/var/www/volleyplay` — корень git (`origin` = Akimovda/Volley2026, ветка `production` отслеживает `origin/main`), Laravel в `backend/`. nginx → php-fpm пул `prod` (user appuser, сокет `/run/php/php8.3-fpm-prod.sock`).
- Очереди и Reverb — supervisor (`volleyplay-queue`, `volleyplay-queue-telegram`, `volleyplay-reverb`, user appuser). Планировщик — cron appuser `schedule:run`.
- Боты: код — git `Akimovda/VolleyBot` (`/opt/volleybot`, `deploy.sh`), рабочие копии `/opt/volleyplay-telegram-bot` (порт 8094) и `/opt/volleyplay-max-bot` (8093), systemd-юниты `volleyplay-*-bot.service` работают от **www-data**.
- БД PostgreSQL `volleyplay`, роль `volleyplay`. Telegram с сервера напрямую недоступен — через релей Cloudflare (`TELEGRAM_API_BASE` в `.env`).
- Безопасность: ufw (SSH, 80, 443), fail2ban для sshd (в `ignoreip` — IP администратора, может устареть).

## Секреты — где лежат и как прочитать (в чат/git не вставлять)
- Пароль БД: `backend/.env` (`DB_PASSWORD`), `~appuser/.pgpass` (для `pg_dump`/`psql`), у ботов — `DATABASE_URL` в `/opt/volleyplay-telegram-bot/.env` и `/opt/volleyplay-max-bot/.env`. Прочитать: `grep '^DB_PASSWORD=' /var/www/volleyplay/backend/.env` в своём терминале. Пароль менялся 2026-11-04; при смене обновлять все четыре места, потом `config:cache`, reload php-fpm, `queue:restart`, перезапуск Reverb и ботов.
- **Если сервер потерян целиком:** читать `.env` на нём уже нельзя. Берём копию с бэкап-сервера `185.225.232.210` (каталог `env/`, файлы `env_ДАТА`, `bot-telegram_env_ДАТА`, `bot-max_env_ДАТА`, `fcm_service-account_ДАТА.json`, `server-config_ДАТА.tar.gz`) — приёмник работает только на запись, забирать нужно уже на самом бэкап-сервере (доступ у владельца). **Прежний пароль БД для восстановления не нужен:** базу поднимаем из дампа и задаём роли `volleyplay` новый пароль, затем прописываем его во все четыре места (см. выше). Отдельно хранить у владельца (менеджер паролей, не git): доступ к бэкап-серверу, доступ к хостингу/консоли, ключи магазинов и сервисов из `.env`.
- Firebase (push Android): `backend/storage/app/fcm/service-account.json` (путь в `FCM_SERVICE_ACCOUNT_PATH`).
- Остальные ключи (магазины, Reverb, OAuth, ЮKassa) — в `backend/.env`.
- Права: `backend/.env` и `.env` ботов не должны читаться всеми. `backend/.env` — `appuser:appuser 600`; **боты — `appuser:www-data 640`** (иначе `PermissionError`, сервис в цикле перезапусков).

## Бэкапы (cron appuser, ежедневно; приёмник `185.225.232.210`, доступ rsync-only на запись, прочитать с сервера нельзя)
- 02:00 `pgbackup/pg_backup_push.sh` — `pg_dump -Fc` (пароль из `~/.pgpass`).
- 02:10 `filesbackup/env_backup_push.sh` — `.env` сайта, ключ Firebase, `.env` обоих ботов, архив `server-config_ДАТА.tar.gz` (nginx сайт и conf.d, пул php-fpm `prod.conf`, supervisor, юниты ботов, crontab appuser).
- 02:20 `filesbackup/media_backup_push.sh` — `storage/app/public/` (≈1,9 ГБ).
- **Не в бэкапе:** правила ufw, SSH-ключ appuser (`id_ed25519` — при потере добавить новый публичный ключ на бэкап-сервере), `/etc/letsencrypt` (перевыпуск certbot), состояние ботов (`known_chats.json` Max-бота, `antispam_state.json`), кеши.

## Порядок восстановления
1. ОС Ubuntu 24.04. Пакеты: nginx, php8.3-fpm (+ расширения проекта), postgresql, redis, supervisor, certbot, node 20, composer, python3-venv, fail2ban, ufw, git и **библиотеки для headless Chrome (PDF статистики)**: `libatk1.0-0t64 libatk-bridge2.0-0t64 libatspi2.0-0t64 libxcomposite1 libxdamage1 libxfixes3 libxrandr2 libgbm1 libasound2t64`. Пользователь `appuser`.
2. Код: клонировать репозиторий в `/var/www/volleyplay`, ветка `production` от `origin/main`; `composer install --no-dev --optimize-autoloader`; `npm install --legacy-peer-deps && npm run build`. Проверить `git status` — чисто.
3. Файлы из бэкапа: `.env` → `backend/.env` (appuser:appuser 600), ключ → `backend/storage/app/fcm/service-account.json` (каталог 700), медиа → `backend/storage/app/public/`.
4. БД: создать роль и базу `volleyplay`, `pg_restore`. Расширение `unaccent` (поиск городов без диакритики) входит в дамп; при чистой установке — `CREATE EXTENSION unaccent` от postgres. **Обязательно часовой пояс UTC**: `ALTER DATABASE volleyplay SET timezone TO 'UTC';` (в коде также `'timezone' => 'UTC'` в `config/database.php`). Иначе даты `timestamptz` сдвигаются на 3 часа при записи, дублируются occurrences (см. `history.md`, 2026-11-04). Пароль роли — как в `.env`; записать `~appuser/.pgpass` (600).
5. Конфиги из `server-config_*.tar.gz`: nginx (`sites-enabled` симлинк), `php/8.3/fpm/pool.d/prod.conf`, `supervisor/conf.d/volleyplay-*.conf`, юниты ботов; crontab appuser. `nginx -t`, **nginx только `restart`**, `supervisorctl reread && update`.
6. SSL: `certbot --nginx -d volleyplay.club -d www.volleyplay.club` (автообновление — `certbot.timer`).
7. Laravel: `php artisan migrate --force` (если нужно), `config:cache route:cache view:cache`, `sudo systemctl reload php8.3-fpm`, `supervisorctl restart volleyplay-queue:* volleyplay-queue-telegram:* volleyplay-reverb`.
8. Боты: клонировать `Akimovda/VolleyBot` в `/opt/volleybot`, `./deploy.sh`, `.env` из бэкапа (**appuser:www-data 640**), venv, `systemctl enable --now volleyplay-telegram-bot volleyplay-max-bot`. После смены токена/сервера поставить вебхук Telegram (`setWebhook` на `…/webhook` релея, `allowed_updates` как в `docs/bots-incident-2026-10-29.md`) и проверить `getWebhookInfo`. Max: подписки и `known_chats.json` восстанавливаются событиями `bot_added`/сообщениями.
9. ufw: `allow OpenSSH`, `80/tcp`, `443/tcp`, `enable`; fail2ban — `/etc/fail2ban/jail.d/sshd.local` (sshd, 5 попыток/10 мин → бан 1 ч, backend systemd, `ignoreip` с IP администратора).
10. Проверка: сайт и `/events` — 200; `/.well-known/apple-app-site-association` и `assetlinks.json` — 200; Reverb (WS) — 101; `schedule:list`; очереди RUNNING; боты active, вебхук без ошибок; PDF `/matches/{id}/stats.pdf` — `application/pdf`; в логе нет «FCM not configured»; ночные бэкапы отработали.

## Грабли, найденные при переезде 2026-11-02…04
- Часовой пояс БД (см. п. 4): сервер в Europe/Moscow давал сдвиг −3 ч в `timestamptz` и дубли occurrences; генератор повторов подставлял значения по умолчанию, пока не находил первую запись.
- Нет системных библиотек Chrome → PDF 500 (Browsershot).
- Нет ключа Firebase в бэкапе → push Android молча не уходили.
- `chmod 600` на `.env` ботов без смены группы → боты падали при перезапуске (`PermissionError`, сервис работает от www-data).
- Деплой теперь только `git fetch && git merge origin/main` (нет dev-контура и ветки `production` на GitHub); код на сервере руками не править.
