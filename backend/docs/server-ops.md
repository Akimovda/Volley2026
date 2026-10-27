# Сервер, PHP-FPM, nginx, SQL, планировщик

_Вынесено из CLAUDE.md 2026-10-23 без изменения содержания. Полный исходник: docs/archive/CLAUDE.full-2026-10-23.md_

## Серверные особенности
- php artisan tinker --execute НЕ работает
- Использовать: cat > /tmp/file.php + php -r с bootstrap
- sudo нужен для root-файлов (org.js, некоторые blade)
- sed -i для простых замен, Python для сложных (спецсимволы, табы)
- CarbonImmutable::diffInSeconds() — absolute=false по умолчанию, возвращает отрицательные значения; использовать $a->timestamp - $b->timestamp
- createCustomSelect в script.js оборачивает все .form select — селекты без name атрибута не отправляются на сервер
- **Динамически созданный `<select>`/чекбокс внутри `.form` — невидим без явной обвязки**: `@media (hover:hover){ .form select {position:absolute;width:1px;height:1px;clip:rect(0,0,0,0);...} }` (style.css) безусловно схлопывает ЛЮБОЙ select в 1px, ожидая рядом `.form-select-wrapper` от `createCustomSelect()`. Если JS создаёт `<select>` через `document.createElement` и просто вставляет в DOM — он не «не рендерится», он схлопнут в точку. Нужно сразу после вставки в DOM вызвать `createCustomSelect(jQuery(selectEl))`. Аналогично `.checkbox-item`/`.radio-item`: `.form .checkbox-item input {display:none}` — реальный чекбокс всегда скрыт, видимый квадратик рисует СОСЕДНИЙ `<div class="custom-checkbox">` (`input:checked ~ .custom-checkbox::after`) — без этого div чекбокс есть в DOM и работает, но невидим. Также если JS позже ПЕРЕЗАПОЛНЯЕТ `<option>`ы существующего обёрнутого select (`select.innerHTML = ...`), кастомная обёртка не обновляется сама — нужно `window.customSelect.destroy(id)` + заново `createCustomSelect()` (нет никакого `window.safeRerenderEl` — эта функция нигде не реализована, несмотря на защищённые вызовы в events-create.js).
- ExpandEventOccurrencesJob/OccurrenceExpansionService: offset для reg_starts/reg_ends/cancel_lock берётся из первой (reference) occurrence, не хардкод
- **OccurrenceExpansionService — level-поля НЕ копируются** (фикс 2adc9e3): `beach_level_min/max`, `classic_level_min/max` — override-поля, хранят NULL (наследуй из серии). ExpandService раньше копировал их паразитно → при изменении серии occurrence держала старое значение до следующего ExpandJob. Миграция 2026-06-24 обнулила 451 паразитную копию (`/home/appuser/backups/occ_levels_backup_2026-06-24.csv`). EventShowService применяет override: `if (!is_null($occ->field)) $evt->field = $occ->field` — только ненулевые значения переопределяют серию.

## Storage permissions (DOMPDF, кеш шрифтов)
- PHP-FPM работает под www-data, файлы в `storage/fonts/` исторически могут принадлежать `appuser:appuser` → www-data не может перезаписать `installed-fonts.json` → 500 при экспорте PDF
- Симптом: `file_put_contents(.../storage/fonts/installed-fonts.json): Permission denied` при `EventRegistrationsManagementController::exportPdf`
- Фикс: `sudo chown -R appuser:www-data storage/fonts && sudo chmod -R g+rwX storage/fonts && sudo chmod g+s storage/fonts`
- `g+s` (setgid) критично — новые файлы наследуют group=www-data; на dev уже `drwxrwsr-x`
- **Git-гигиена закрыта (коммит f133a8e, 2026-07-14)**: `storage/fonts/*` (включая `installed-fonts.json`, регенерируется под www-data при каждом экспорте с новым хешем) добавлен в `.gitignore`, убран из индекса через `git rm --cached` (файлы на диске не тронуты). После следующего `git merge origin/main` на проде статус станет чистым без ручного `git checkout -- storage/fonts`. Права/setgid по-прежнему нужны отдельно — гитигнор не заменяет фикс permissions выше, только убирает шум из `git status`.

## PHP-FPM opcache (КРИТИЧНО на проде)
- `php artisan config:cache` / `route:cache` обновляют файлы, но opcache продолжает отдавать старые версии
- При изменениях в `app/Http/Middleware/*`, `config/*.php`, новых классов — **обязательно** `sudo systemctl reload php8.3-fpm` ПОСЛЕ deploy
- Признак: код новый, `php artisan` (CLI) видит правильно, но веб-запросы ведут себя как со старой версией
- supervisorctl restart НЕ заменяет reload php-fpm

## php-fpm: `PHP_VALUE`/`PHP_ADMIN_VALUE` из nginx прилипают к воркеру пула (2026-10-23)
- Проверено FastCGI-пробой по сокету общего пула `www`: после запроса с `fastcgi_param PHP_ADMIN_VALUE "opcache.enable=0"` следующие запросы на том же воркере (в т.ч. прод) идут с `opcache.enable=0` (ini не восстанавливается на shutdown). Раньше dev-vhost нёс эту строку при ОДНОМ пуле на dev и prod → прод периодически работал без opcache. **Правило: настройки PHP, отличающиеся между dev и prod, — только в `php_admin_value[...]` отдельного пула, не через `fastcgi_param`.** План разделения пулов (dev/prod, сокеты, лимиты, fail2ban, PostgreSQL) — `report/server_stability_2026-10-23.md`; конфиги — `/home/appuser/server-stability/` (вне git).
- **Аварийный вход через backup-сервер** (`ssh -J … akimoff.su`): НЕ проверен (ключи appuser этого сервера на 185.225.232.210 не принимаются; проверять с машины владельца). После проверки записать сюда команду.

## Прод «504 / CPU 100%» — сканер уязвимостей (2026-10-22)
- Сервер 2-ядерный, `pm.max_children=20`, один пул `www` на dev и prod. Алерт Kuma «Down 504» + load average 22 + `/` отвечает 14 с — причина: один IP (`193.138.7.163`, 7464 запроса за ~12 мин, nuclei: `/nuclei.svg`, `/wp-admin/`, `/manager/html`, `/jmx-console/`, `/realms/…`) держался ровно на пороге старого лимита `10r/s`. Каждый несуществующий URL проходит весь Laravel (boot+Sentry+404) ≈0,2 с CPU → 10 запросов/с занимают оба ядра. Оверлей `/o/{token}/state` и Reverb к инциденту не причастны (WS на проде работает, 101 Upgrade).
- Защита (настроена вручную в nginx/fail2ban, **в git не входит**, при переезде сервера воссоздать): `/etc/nginx/conf.d/limit-req.conf` — `rate=4r/s` (было 10; localhost и backup-сервер `185.225.232.210` исключены), в vhost `burst=40 nodelay`, статус 429; fail2ban-джейл `/etc/fail2ban/jail.d/nginx-limit-req.local` (фильтр `nginx-limit-req` из коробки, `maxretry=50`/`findtime=60`/`bantime=3600`, `ignoreip` localhost+backup). **Ловушка**: в `jail.d/defaults-debian.conf` глобально `backend=systemd` — для джейла с `logpath` нужен `backend = auto`, иначе он молча ничего не читает. Лимит без бана почти бесполезен (сканер на пороге не получает 429), бан без снижения порога — тоже; нужны оба.
- Диагностика: логи `/var/log/nginx/access.log` (общий для dev и prod, формат combined) и `/var/log/php8.3-fpm-slow.log` читает только root/`adm` — запускать `sudo` пользователю в СВОЁМ терминале. Порядок: Kuma `/metrics` → `uptime`/`top` → разбивка access.log по минутам и по IP за окно инцидента (`awk '$4 ~ /22\/Oct\/2026:(18:5[89]|19:0)/ {print $1}' … | sort | uniq -c | sort -rn`) → slow log. Ручной бан: `sudo fail2ban-client set nginx-limit-req banip <IP>`. `nginx` — только `restart`, не `reload`.
- Побочная находка (не исправлена): `PublishOccurrenceRegistrationOpenJob` каждую минуту падает в `403 bot was kicked from the supergroup chat` — Telegram-канал, откуда выгнали бота, не отвязан; на CPU почти не влияет, но засоряет очередь и лог.

## PostgreSQL
- is_cancelled (boolean) — фильтровать через whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
- Добавлять explicit boolean casts в модели
- **whereRaw с OR** — ВСЕГДА оборачивать в скобки, иначе AND>OR нарушает логику

## Laravel 12 schedule
- ВСЕ scheduled команды объявляются ТОЛЬКО в `routes/console.php` (`Schedule::command(...)`/`Schedule::call(...)`/`Schedule::job(...)`) — единственное место, ничего в других файлах.
- `app/Console/Kernel.php` удалён 2026-09-25 (был полностью мёртв — Laravel 12 slim-bootstrap не биндит `App\Console\Kernel` нигде, `schedule()`/`commands()` в нём никогда не выполнялись; grep по проекту подтвердил отсутствие внешних ссылок на класс). Если в будущем кто-то создаст `app/Console/Kernel.php` заново — он снова будет мёртвым кодом, пока явно не забиндлен в `bootstrap/app.php`.
- `bootstrap/app.php` → `->withSchedule(...)` больше НЕ используется (последняя команда, `events:send-registration-reminders`, перенесена в `routes/console.php` 2026-09-25) — не добавлять туда новые задачи, даже единичные.
- Проверка: `php artisan schedule:list`
- **Паттерн dedupe для повторяющихся команд**: если команда запускается часто (каждые 5 мин) и выбирает записи по временному окну, НЕ использовать бизнес-поле (scored_at, completed_at) как флаг «уже обработано» — добавлять отдельную колонку `notified_*_at` (nullable timestamp). Пример: `tournament_matches.notified_upcoming_at` — команда `tournament:notify-upcoming` фильтрует `whereNull('notified_upcoming_at')`, после отправки записывает `now()`. Без этого одна запись попадает в каждый запуск пока окно перекрывается.

## Миграция volleyplay.club
- Dev: volley-bot.store, Prod: volleyplay.club
- Два Telegram бота: dev (VolleyEvent_bot, порт 8092), prod (VolleyEvents_bot, порт 8094)
- НЕ держать оба бота в одном канале

## SQL-безопасность
- whereRaw с OR — ВСЕГДА в скобках: `->whereRaw('(a IS NULL OR a = false)')`, иначе AND>OR захватывает чужие строки
- `AdminUserController::deleteOrPurge`: hard delete 30+ таблиц без транзакции — задача переход на soft delete (анонимизация PII + deleted_at). См. память project_soft_delete_users.md

## Telegram: доступ, релей, очередь, маскировка токена (2026-10-27)
- **Проблема**: с прод-сервера (85.198.87.192) до `api.telegram.org` (149.154.166.110) ~45% попыток соединения не проходят (выборочно по IP Telegram; `149.154.167.220` отвечал 3/3). С Contabo (185.225.232.210) — 20/20. Таймауты в `laravel.log` росли с 4/день (20.10) до 820 (27.10).
- **Все вызовы Bot API backend — через `App\Services\TelegramApi`**: `url($token, $method)` (база из `TELEGRAM_API_BASE`, по умолчанию `https://api.telegram.org`) и `http($timeout)` (connectTimeout 3 с, повтор ×3 только при сбое установки соединения — запрос гарантированно не дошёл, дубля не будет). Новый вызов Telegram — только так, не `Http::post("https://api.telegram.org/...")`.
- **Релей**: nginx на Contabo, `location ~ ^/tg-<секрет>/` в server-блоке `errors.akimoff.su` (сниппет `/etc/nginx/snippets/tg-relay.conf`, бэкап vhost — `/root/nginx-backup-20261027/`). `allow 85.198.87.192; deny all`, **`access_log off`** (токен бота в пути запроса). Адрес — `TELEGRAM_API_BASE=https://errors.akimoff.su/tg-<секрет>` в `.env` backend и ботов `/opt/*-telegram-bot/.env` (секрет — в `.env` и `/home/appuser/ops/.tg_relay_*`, не коммитить, не печатать). Замер: 50/50 успешно, ~0,29 с. Смена IP прода → поправить `allow` в сниппете и `systemctl reload nginx` на Contabo.
- **Очередь `telegram`**: задачи в мессенджеры (`SendNotificationDeliveryJob`, анонсы/обновления/удаление постов каналов) берут очередь из `config('queue.telegram_queue')` (`TELEGRAM_QUEUE`, по умолчанию `default`). Включать **только после** запуска воркера `queue:work --queue=telegram` (конфиги — `/home/appuser/ops/*-queue-telegram.conf`), иначе задачи встанут. Порядок: supervisor-воркер → `TELEGRAM_QUEUE=telegram` в `.env` → `config:cache` → `supervisorctl restart volleyplay-queue:*`.
- **Маскировка токена**: `App\Support\SecretMasker` (`bot<id>:<секрет>` → `bot<токен>`), tap `App\Logging\MaskSecretsTap` на каналах `single/daily/broadcast`, Sentry `before_send`/`before_breadcrumb` (в `config/sentry.php` — callable-массивы, не замыкания: `config:cache`). В логе бота `/opt/volleyplay-telegram-bot/bot.py` — `_mask_secrets`. Старые логи вычищены заменой на месте (`*` равной длины).

