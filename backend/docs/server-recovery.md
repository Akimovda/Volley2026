# Восстановление и переезд сервера

_Сверено с серверами 2026-11-20 (прод на FirstVDS с 18.11). Прежняя версия (сервер 141.98, 04.11) — в истории git.
Репозиторий публичный: только пути и порядок действий, никаких паролей, токенов и ключей._

Связанные документы: `db-pitr.md` (архив WAL и восстановление на момент времени), `deploy.md`, `server-ops.md`.

> ## ⚠️ ВРЕМЯ И ЧАСОВОЙ ПОЯС — проверять при ЛЮБОМ восстановлении
> - Приложение и данные — в **UTC**. Сессия PostgreSQL **обязана быть UTC**: `ALTER DATABASE volleyplay SET timezone TO 'UTC';` + `'timezone' => 'UTC'` в `config/database.php`. ОС прода — Europe/Moscow, на это влиять не должно.
> - Иначе все `timestamptz` сдвигаются на −3 ч, при правке организатором плодятся дубли occurrences (было 02–04.11.2026).
> - Проверка: `DB::selectOne('show timezone')` → `UTC`; сохранить событие в откатываемой транзакции — время не сдвигается.

## 1. Серверы

| Роль | Адрес | Что там |
|---|---|---|
| **Прод** | `91.132.188.90`, FirstVDS, hostname `akimovda.fvds.ru` | Ubuntu 24.04, 4 vCPU, 8 ГБ, диск 119 ГБ, TZ Europe/Moscow. Образ с ispmanager (панель, `ihttpd`, php5.6/7.4/8.4 из панели, exim) — **наш стек ставится вручную мимо панели**. |
| **Бэкапы + мониторинг** | `185.225.232.210`, Contabo (Германия) | Приём бэкапов, скрипты `/opt/pgbackup/`, Uptime Kuma (`/opt/uptime-kuma`), GlitchTip (`/opt/glitchtip`), уведомления в Telegram. |
| Выведены | `141.98.188.140` (UFO, до 17.11), `85.198.87.192` (Beget, до 02.11) | Не использовать. 141.98 — удалить VPS в панели (данные стёрты 20.11). |

DNS — Cloudflare (A `volleyplay.club`, `www` → прод, «DNS only»). Менять только по явной команде владельца, перед правкой сохранить текущие записи.

## 2. Что крутится на проде

- Код: `/var/www/volleyplay` — корень git (`origin` = Akimovda/Volley2026, локальная ветка `production` ← `origin/main`), Laravel в `backend/`.
- Стек: nginx (`/etc/nginx/sites-available/volleyplay.club` + `conf.d/`, в т.ч. `zzz-override.conf`), PHP 8.3-FPM (пул `prod.conf`, user appuser, сокет `/run/php/php8.3-fpm-prod.sock`), PostgreSQL 16 (база и роль `volleyplay`), Redis 7, Node 20, Composer 2.
- supervisor: `volleyplay-queue` и `volleyplay-queue-telegram` (по `numprocs=2`), `volleyplay-reverb`.
- cron appuser: `schedule:run` каждую минуту + три бэкап-скрипта (раздел 3).
- Боты: код `Akimovda/VolleyBot` → `/opt/volleybot` (`deploy.sh`), рабочие копии `/opt/volleyplay-telegram-bot` (8094), `/opt/volleyplay-max-bot` (8093), systemd `volleyplay-*-bot.service` от **www-data**.
- Telegram API с прода — через релей Cloudflare (`TELEGRAM_API_BASE` в `.env`).
- SSL: certbot (`/etc/letsencrypt/live/volleyplay.club`, `certbot.timer`).
- Защита: ufw (22, 80, 443, порт панели ispmanager), fail2ban (jails `sshd`, `exim-isp`).

## 3. Бэкапы — полная схема

Шифрование: **age**. Открытый ключ (recipient) — на проде `/home/appuser/.age/recipient.txt`, на Contabo `/opt/pgbackup/backup_encryption.pub` (одинаковые).
**Закрытый ключ — только у владельца (Mac).** Без него зашифрованные бэкапы не прочитать — хранить его копию отдельно от обоих серверов.

На Contabo три пользователя-приёмника, ключи в их `authorized_keys` ограничены `from="91.132.188.90"`, `command="rrsync -wo …"`, `restrict` — **с прода можно только писать, прочитать бэкапы с прода нельзя**. Забирать файлы — root-ом на самом Contabo.

### 3.1 Прод → Contabo (время МСК)

| Когда | Кто / скрипт | Что | Куда на Contabo |
|---|---|---|---|
| каждые ≤5 мин | postgres, `archive_command` → `/usr/local/bin/pg_archive_wal.sh` | сегменты WAL (gzip), ключ `/var/lib/postgresql/.ssh/id_ed25519_wal` | `pgwal`: `/home/pgwal/wal/*.gz` |
| вс 01:30 | `/etc/cron.d/pg_basebackup` → `/usr/local/bin/pg_basebackup_push.sh` (postgres) | базовая копия `pg_basebackup -Ft -z -X fetch` + `startwal.txt` | `/home/pgwal/base/<ГГГГММДД_ЧЧММСС>/` |
| 02:00 | cron appuser → `~/pgbackup/pg_backup_push.sh` | `pg_dump -Fc` → **age** | `pgbackup`: `/home/pgbackup/pgdumps/volleyplay_ДАТА.dump.age` |
| 02:05 | `/etc/cron.d/pg_config_snapshot` → `/usr/local/sbin/pg_config_snapshot.sh` (root) | копирует `pg_hba.conf`, `postgresql.auto.conf`, `enable-wal-archiving.sh`, ключ WAL + known_hosts в `~appuser/filesbackup/pgcfg/` | (уезжает в 02:10 внутри архива конфигов) |
| 02:10 | cron appuser → `~/filesbackup/env_backup_push.sh` | `.env` сайта, ключ Firebase, ключ APNs, `.env` обоих ботов — **открытым текстом**; архив `server-config_ДАТА.tar.gz` → **age** | `filesbackup`: `/home/filesbackup/backups/env/` |
| 02:20 | cron appuser → `~/filesbackup/media_backup_push.sh` | `storage/app/public/` (rsync без `--delete`, ≈2 ГБ) | `/home/filesbackup/backups/storage/` |

Состав `server-config_ДАТА.tar.gz.age`: nginx (`sites-available/volleyplay.club`, `conf.d/`), `php/8.3/fpm/pool.d/prod.conf`, 3 файла supervisor, 2 юнита ботов, `postgresql.conf`, скрипты `pg_archive_wal.sh`, `pg_basebackup_push.sh`, `pg_config_snapshot.sh`, `/etc/cron.d/pg_basebackup` и `pg_config_snapshot`, сами бэкап-скрипты appuser, каталог `pgcfg/` (см. 02:05), crontab appuser.

### 3.2 На Contabo (время CET, root crontab + `/etc/cron.d/pgbackup-self`)

| Когда | Скрипт `/opt/pgbackup/` | Что делает |
|---|---|---|
| каждые 3 мин | `arrival_notify.sh` | Telegram «бэкап получен» (лог `/var/log/pgbackup/arrival_notify.log`) |
| 02:40 | `backup_self.sh` | архив самих скриптов `/opt/pgbackup`, crontab root и `authorized_keys` приёмников → age → `backups/config/pgbackup_config_ДАТА.tar.gz.age` |
| 03:00 / 03:05 / 03:15 | `retention.sh` / `env_retention.sh` / `config_retention.sh` | удаление старше 30 дней (дампы / env / config) |
| 03:10 | `disk_check.sh` | тревога при заполнении диска ≥ 85 % |
| 03:50 | `wal_retention.sh` | остаются 3 последние базовые копии, WAL старше `startwal` самой старой — удаляется |
| 04:45 | `daily_check.sh` | Telegram-сводка: есть ли дамп и .env за сегодня, размер медиа, диск |
| 05:00 | `encrypt_old_backups.sh` | шифрует age всё незашифрованное старше суток в `pgdumps`, `env`, `config` (т.е. `.env`/ключи лежат открытыми ~1–2 суток) |
| 1-го числа 04:45 | `restore_test.sh` | ежемесячный тест `pg_restore` последнего дампа |
| вручную | `pitr_restore_test.sh ["момент"]` | репетиция PITR в изолированном контейнере `postgres:16` (см. `db-pitr.md`) |

Уведомления — `notify.sh` (бот и чат в `/opt/pgbackup/telegram.conf`, root 600).

### 3.3 Мониторинг (Kuma на Contabo)
Мониторы: сайт (http), push: scheduler, queue, notifications, expand-recurring, диск, память, CPU, очередь, **WAL archive**, TLS 1.2, входы через соцсети. Push-адреса — в `.env` прода (`KUMA_PUSH_*`); метрики шлёт `php artisan ops:report-metrics` (каждые 5 мин). Ошибки приложения — GlitchTip, проект `volleyplay-backend` (DSN в `.env`).
Монитор «WAL archive» проверяет только свежесть выгрузки, **не целостность цепочки** — после переезда/восстановления обязательно `pitr_restore_test.sh`.

### 3.4 Не в бэкапе
Правила ufw и fail2ban, SSH-ключ appuser `~/.ssh/id_ed25519` (при потере — новый ключ в `authorized_keys` `pgbackup` и `filesbackup`), открытый ключ age `~appuser/.age/recipient.txt` (взять с Contabo, `/opt/pgbackup/backup_encryption.pub`), `/etc/letsencrypt` (перевыпуск), состояние ботов (`known_chats.json`, `antispam_state.json`), Redis, кеши, настройки ispmanager.

## 4. Секреты — где лежат (в чат/git не вставлять, не печатать)
- `backend/.env` (appuser:appuser 600): БД, ключи магазинов, OAuth, ЮKassa, Reverb, Sentry, Kuma, GeoNames, почта и т.д.
- `.env` ботов (`/opt/volleyplay-*-bot/.env`, **appuser:www-data 640**, иначе `PermissionError`).
- Firebase: `backend/storage/app/fcm/service-account.json` (`FCM_SERVICE_ACCOUNT_PATH`). APNs: `backend/storage/app/apns/AuthKey_<KEYID>.p8` (`APN_*` в `.env`; каталог 700, файл 600).
- Пароль БД — в 4 местах: `backend/.env`, `~appuser/.pgpass`, `DATABASE_URL` в `.env` двух ботов. **При восстановлении старый пароль не нужен**: базу поднимаем из дампа и задаём роли новый пароль во все 4 места → `config:cache`, reload php-fpm, `queue:restart`, перезапуск Reverb и ботов.
- Отдельно у владельца (не на серверах): закрытый ключ age, доступ к Contabo и FirstVDS (панель/консоль), Cloudflare, ключи магазинов.

## 5. Порядок восстановления / переезда на новый сервер

**0. Подготовка (на Contabo, root).** Взять последние файлы: `pgdumps/volleyplay_ДАТА.dump.age`, из `backups/env/` — `env_ДАТА`, `fcm_*`, `apns_*`, `bot-*_env_ДАТА` (старше суток — уже `.age`), `server-config_ДАТА.tar.gz.age`; медиа `backups/storage/`. Для PITR — `/home/pgwal/base/<последняя>/` и `/home/pgwal/wal/` (см. `db-pitr.md`). Расшифровка — **на Mac владельца** (`age -d -i <ключ> файл.age > файл`), на новый сервер переносить уже расшифрованное `scp`-ом; закрытый ключ на серверах не оставлять. После завершения — удалить расшифрованные копии (16.11 в `/opt/recovery` на проде остались ключ и секреты — удалено 20.11).

1. **ОС**: Ubuntu 24.04. Пакеты: nginx, php8.3-fpm + расширения проекта, postgresql-16, redis-server, supervisor, certbot + python3-certbot-nginx, Node 20, composer, python3-venv, fail2ban, ufw, git, age, rsync, **библиотеки headless Chrome для PDF**: `libatk1.0-0t64 libatk-bridge2.0-0t64 libatspi2.0-0t64 libxcomposite1 libxdamage1 libxfixes3 libxrandr2 libgbm1 libasound2t64`. Пользователь `appuser`. На образе с ispmanager — не настраивать сайт через панель.
2. **Код**: `git clone` в `/var/www/volleyplay`, ветка `production` от `origin/main`; `composer install --no-dev --optimize-autoloader`; `npm install --legacy-peer-deps && npm run build`; `git status` — чисто.
3. **Файлы**: `.env` → `backend/.env` (appuser 600), Firebase/APNs — в `storage/app/fcm|apns/` (каталоги 700), медиа → `backend/storage/app/public/`; `php artisan storage:link`; `storage/fonts` — владелец appuser (иначе PDF падает на `fopen(.../storage/fonts/...)`, было 17.11).
4. **БД**: роль и база `volleyplay`, `pg_restore --no-owner -d volleyplay дамп` (или PITR по `db-pitr.md`). `unaccent` входит в дамп. **`ALTER DATABASE volleyplay SET timezone TO 'UTC';`** Пароль роли — новый, во все 4 места (раздел 4); `~appuser/.pgpass` (600).
5. **Конфиги** из `server-config_*.tar.gz`: nginx (симлинк в `sites-enabled`, `conf.d/`), `pool.d/prod.conf`, supervisor `volleyplay-*.conf`, юниты ботов, `/etc/cron.d/pg_basebackup` и `pg_config_snapshot`, скрипты в `/usr/local/bin|sbin`, crontab appuser, бэкап-скрипты в `~appuser/pgbackup|filesbackup`. `nginx -t`, **nginx только `restart`**, `supervisorctl reread && update`.
6. **SSL**: `certbot --nginx -d volleyplay.club -d www.volleyplay.club` (до этого DNS должен указывать на новый IP; или временно перенести `/etc/letsencrypt` со старого).
7. **Laravel**: `migrate --force` (если нужно), `config:cache route:cache view:cache` — **artisan только `sudo -u appuser`** (от root view-кэш → 500); `systemctl reload php8.3-fpm`; restart очередей и Reverb.
8. **Боты**: `/opt/volleybot` + `./deploy.sh`, `.env` из бэкапа (appuser:www-data 640), `systemctl enable --now volleyplay-telegram-bot volleyplay-max-bot`; вебхук Telegram (`setWebhook` на релей, `allowed_updates` — `docs/bots-incident-2026-10-29.md`), `getWebhookInfo`.
9. **Бэкапы с нового сервера**:
   - ключ age: `~appuser/.age/recipient.txt` = содержимое `/opt/pgbackup/backup_encryption.pub` с Contabo;
   - SSH-ключи appuser и postgres (`id_ed25519_wal`) — из архива (`pgcfg/pgwal_ssh/`) или новые;
   - на Contabo в `authorized_keys` пользователей **`pgbackup`, `filesbackup`, `pgwal`** заменить `from="<старый IP>"` на новый (сделать копию `authorized_keys.bak.ДАТА`);
   - `known_hosts` на новом сервере для `185.225.232.210` (у appuser и postgres — скрипты работают с `StrictHostKeyChecking=yes`);
   - WAL: `/usr/local/sbin/enable-wal-archiving.sh` (нужен перезапуск PostgreSQL);
   - **сразу базовая копия** `sudo -u postgres /usr/local/bin/pg_basebackup_push.sh` и **перенести базовые копии старого кластера из `/home/pgwal/base/` в отдельный каталог** (иначе `wal_retention.sh` посчитает «самой старой» чужую копию и удалит WAL нового кластера — так было 19.11);
   - вручную прогнать три cron-скрипта appuser и убедиться в Telegram-уведомлениях о приёме; `pitr_restore_test.sh` на Contabo.
10. **Мониторинг**: push-мониторы Kuma — адреса в `.env` прода (`KUMA_PUSH_*`), монитор сайта — на новый IP/домен; GlitchTip — `SENTRY_LARAVEL_DSN` в `.env`.
11. **ufw** (`OpenSSH`, 80, 443), **fail2ban** (`jail.d/sshd.local`).
12. **Старый сервер выключить полностью** (cron, supervisor, боты, nginx) до переключения DNS — иначе его планировщик со старой копией БД и теми же токенами шлёт дубли анонсов и пушей (было 17–20.11 со 141.98).
13. **Проверка**: `/` и `/events` — 200; `/.well-known/apple-app-site-association`, `assetlinks.json` — 200; Reverb (WS) — 101; `schedule:list`; очереди RUNNING; боты active, вебхук без ошибок; PDF `/matches/{id}/stats.pdf` — `application/pdf`; в логе нет «FCM not configured» / «APNs private key not found»; `show timezone` = UTC; GlitchTip без новых ошибок; ночные бэкапы пришли (Telegram-сводка 04:45 CET).

## 6. Грабли переездов (02–04.11 и 16–20.11.2026)
- Часовой пояс сессии PostgreSQL → сдвиг −3 ч и дубли occurrences.
- Нет библиотек Chrome → PDF 500. Права `storage/fonts` → PDF 500.
- Нет ключа Firebase в бэкапе → push Android молча не уходили (теперь ключи в бэкапе).
- `chmod 600` на `.env` ботов без группы www-data → боты в цикле перезапусков.
- Старый сервер остался живым после переезда → дубли анонсов, пушей и сообщений ботов.
- Базовые копии старого кластера в `/home/pgwal/base/` → чистка удалила WAL нового кластера (19.11).
- Расшифрованные секреты и закрытый ключ age оставлены на проде в `/opt/recovery` (16.11).
- Деплой — только `git fetch && git merge origin/main`, код на сервере руками не править.

## 7. Плановые работы на проде (для полноты картины)
- Воркеры очередей `numprocs=2` (дедупликация рассылок по общему чату при двух воркерах теоретически может пропустить дубль).
- Чистка 03:40: `maintenance:prune-old-records --days=45` (`channel_publish_logs`, `notification_deliveries`, `page_views` → `page_view_totals`).
- Метрики в Kuma каждые 5 мин: `ops:report-metrics` (пороги: диск ≥ 80 %, память < 15 %, load5/ядро > 1.0, очередь > 500 или старейшая > 10 мин, WAL > 30 мин).

## 8. Известные дыры (на 2026-11-20)
- `restore_test.sh` ищет только `*.dump/*.sql(.gz)`, а дампы теперь `*.dump.age`, закрытого ключа на Contabo нет → **тест 01.12 упадёт** («no dump files found»). Варианты: подменять его на `pitr_restore_test.sh` (базовые копии не шифруются) или проверять дамп на Mac.
- **Базовые копии и WAL не шифруются** (а в них вся БД), `.env`/ключи приходят открытыми и шифруются на Contabo только через сутки — шифрование дампов закрывает не всё.
- ~~Цепочка WAL порвана чисткой 19.11~~ — исправлено 20.11: старые копии удалены, защита в `wal_retention.sh`, новая базовая копия, репетиция PITR совпала с продом.
- ~~Закрытый ключ age и расшифрованные секреты в `/opt/recovery` на проде~~ — удалено 20.11.
