# База данных: архив WAL и восстановление на момент времени (PITR)

Введено 2026-11-05 (этап 1 плана отказоустойчивости; этап 2 — резервный узел на старом сервере). Без этого после аварии
мы теряли всё, что записано после ночной копии (до суток). С архивом WAL потеря — до 5 минут и можно выбрать момент.

## Как устроено
- PostgreSQL 16 на проде: `archive_mode=on`, `archive_timeout=300` (сегмент WAL уходит минимум раз в 5 минут),
  `archive_command='/usr/local/bin/pg_archive_wal.sh %p %f'` (параметры лежат в `postgresql.auto.conf`, попадает в еженедельный бэкап конфигов).
- `pg_archive_wal.sh` (запускается от пользователя `postgres`): сжимает сегмент gzip и отправляет `rsync`-ом на сервер бэкапов
  185.225.232.210, пользователь **`pgwal`**, каталог `wal/` (ключ `/var/lib/postgresql/.ssh/id_ed25519_wal`).
  Ключ в `authorized_keys` ограничен: `from="141.98.188.140"`, `command="rrsync -wo /home/pgwal"`, `restrict` — только запись, только с IP прода.
  Код возврата 0 только если сегмент доставлен (иначе PostgreSQL повторяет попытку).
- Базовая копия: `/usr/local/bin/pg_basebackup_push.sh`, cron `/etc/cron.d/pg_basebackup` — воскресенье 01:30 МСК;
  `pg_basebackup -Ft -z -X fetch` → `/home/pgwal/base/<ГГГГММДД_ЧЧММСС>/{base.tar.gz, backup_manifest, startwal.txt}`.
- Чистка на сервере бэкапов (`/opt/pgbackup/wal_retention.sh`, cron 03:50): остаются 3 последние базовые копии; WAL старше `startwal` самой старой — удаляются.
- Мониторинг: Kuma-монитор «VolleyPlay server: WAL archive» (метрика `wal` в `php artisan ops:report-metrics`): тревога, если последняя выгрузка > 30 минут назад или есть текущая ошибка.
  **Если архивация сломалась, `pg_wal` на проде растёт (сегменты не удаляются) — реагировать сразу** (диск 89 ГБ, 1 сегмент = 16 МБ).
- Включение архивации (однократно, нужен перезапуск PostgreSQL): `/usr/local/sbin/enable-wal-archiving.sh` — при неудаче проверки откатывает настройку сам. Лог `/var/log/enable-wal-archiving.log`.

## Проверка восстановления (репетиция, ничего не трогает на проде)
На сервере бэкапов: `/opt/pgbackup/pitr_restore_test.sh` или с целью времени `/opt/pgbackup/pitr_restore_test.sh "2026-11-05 12:30:00+03"`.
Скрипт берёт последнюю базовую копию + архив WAL, поднимает изолированный контейнер `postgres:16` (без сети), печатает числа users/events/event_registrations
и время последней записи, затем удаляет контейнер. Сравнить с продом. (В контейнере Debian, на проде Ubuntu — будет предупреждение `collation version mismatch`; при восстановлении на Ubuntu с тем же пакетом PostgreSQL его нет.)
Проверено 2026-11-05: базовая копия восстановилась, данные совпали с продом (1068 / 97 / 2721).

## Восстановление после аварии (новый сервер)
1. Поставить `postgresql-16` (Ubuntu 24.04, та же мажорная версия). Остановить кластер: `systemctl stop postgresql`; очистить `/var/lib/postgresql/16/main`.
2. **Скачать копию и WAL с сервера бэкапов под root-ключом (с Мака)**: пользователь `pgwal` только пишет и читать не может.
   Нужно: последний каталог из `/home/pgwal/base/` и все `*.gz` из `/home/pgwal/wal/`. Перенести на новый сервер: базу — в `/root/restore/`, WAL — в `/var/lib/postgresql/wal_restore/` (владелец postgres).
3. Распаковать базу от имени postgres: `sudo -u postgres tar -xzf /root/restore/base.tar.gz -C /var/lib/postgresql/16/main`.
4. Конфиги (`postgresql.conf`, `pg_hba.conf`) — из `server-config_*.tar.gz` в `/etc/postgresql/16/main/`.
   **Файл `postgresql.auto.conf` из копии удалить/очистить** (иначе восстановленный сервер начнёт архивировать в тот же архив). Часовой пояс сессии — UTC (см. `server-recovery.md`).
5. В `/etc/postgresql/16/main/postgresql.conf` добавить на время восстановления:
   `restore_command = 'gunzip -c /var/lib/postgresql/wal_restore/%f.gz > %p'`, `recovery_target_action = 'promote'`
   и, если нужен конкретный момент (например, за минуту до ошибочного действия): `recovery_target_time = '2026-11-05 12:30:00+03'`.
   Создать файл `touch /var/lib/postgresql/16/main/recovery.signal` (владелец postgres).
6. `systemctl start postgresql`, следить за `/var/log/postgresql/postgresql-16-main.log`: должны появиться строки `restored log file ...`, затем `archive recovery complete` и `database system is ready to accept connections`.
   Убрать из `postgresql.conf` строки `restore_command`/`recovery_target_*`.
7. Проверить данные (users/events/event_registrations, последняя запись), дальше — по `server-recovery.md` (код, `.env`, nginx, supervisor, cron, Kuma, домен).
8. Вернуть архивацию: `ALTER SYSTEM SET archive_mode='on'` и `archive_command`/`archive_timeout` (см. выше) → перезапуск; **новый ключ `pgwal`** (`ssh-keygen` для `postgres` → добавить в `/home/pgwal/.ssh/authorized_keys` на сервере бэкапов с `from="<новый IP>"`; то же `from=` обновить у `pgbackup` и `filesbackup`); сразу сделать базовую копию `sudo -u postgres /usr/local/bin/pg_basebackup_push.sh` и прогнать `pitr_restore_test.sh`.
- Если в цепочке WAL дыра (потерян сегмент), восстановление дойдёт только до дыры — поэтому следим за метрикой «WAL archive» и по воскресеньям появляется свежая база.
- Резервный узел (старый сервер, этап 2) и автопереключение — описаны отдельно, когда будут сделаны.
