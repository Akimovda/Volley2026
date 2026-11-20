#!/usr/bin/env bash
# Доменные тесты на ЛОКАЛЬНОЙ тестовой БД PostgreSQL (см. docs/auto-quorum.md, раздел «Тесты»).
#   tools/test-pgsql.sh                      — все тесты из phpunit.pgsql.xml (Unit + доменные Feature)
#   tools/test-pgsql.sh --filter Registration
#   tools/test-pgsql.sh rebuild              — пересоздать схему volley_test: дамп схемы + миграции + справочник брендов
# Настройки берутся из backend/.env.testing (в git не коммитится).
# Предохранитель: запуск только при DB_HOST=127.0.0.1/localhost и DB_DATABASE=volley_test.
#
# ВАЖНО: стандартные тесты Jetstream (RefreshDatabase) на pgsql делают migrate:fresh и ломают volley_test —
# поэтому здесь используется отдельный конфиг phpunit.pgsql.xml, где их нет.
set -euo pipefail
cd "$(dirname "$0")/.."

[ -f .env.testing ] || { echo "Нет backend/.env.testing — см. docs/auto-quorum.md" >&2; exit 1; }
set -a; . ./.env.testing; set +a
export APP_ENV=testing

case "${DB_HOST:-}" in 127.0.0.1|localhost|::1) ;; *) echo "СТОП: DB_HOST=${DB_HOST:-} — разрешён только localhost" >&2; exit 2;; esac
[ "${DB_DATABASE:-}" = "volley_test" ] || { echo "СТОП: DB_DATABASE=${DB_DATABASE:-} — разрешена только volley_test" >&2; exit 2; }
[ ! -f bootstrap/cache/config.php ] || { echo "СТОП: bootstrap/cache/config.php перекроет .env.testing — удалите кеш конфига" >&2; exit 2; }

echo "Тестовая БД: ${DB_CONNECTION}://${DB_USERNAME}@${DB_HOST}:${DB_PORT}/${DB_DATABASE}"

if [ "${1:-}" = "rebuild" ]; then
    export PGPASSWORD="$DB_PASSWORD"
    PSQL=(psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" -d "$DB_DATABASE" -q -v ON_ERROR_STOP=1)
    "${PSQL[@]}" -c 'DROP SCHEMA public CASCADE; CREATE SCHEMA public;'
    # дамп схемы (без \restrict — мета-команд нет в старых psql)
    grep -v '^\\\(un\)\?restrict' database/schema/pgsql-schema.sql | "${PSQL[@]}" -v ON_ERROR_STOP=0 >/dev/null
    # Дамп новее своей таблицы migrations: часть миграций падает с «already exists» — отмечаем их выполненными.
    for f in database/migrations/*.php; do
        m=$(basename "$f" .php)
        [ -n "$("${PSQL[@]}" -Atc "select 1 from migrations where migration='$m'")" ] && continue
        if ! out=$(php artisan migrate --force --path="$f" 2>&1); then
            if echo "$out" | grep -qE '42701|42P07|42710|already exists'; then
                "${PSQL[@]}" -c "insert into migrations(migration,batch) values('$m',99)"
                echo "SKIP (уже в дампе) $m"
            else
                echo "FAIL $m"; echo "$out" | grep -m3 SQLSTATE; exit 3
            fi
        fi
    done
    # справочные данные, без которых не работают HTTP-запросы (бренд по умолчанию)
    exec php artisan db:seed --class=BrandSeeder --force
fi

exec php vendor/bin/phpunit -c phpunit.pgsql.xml "$@"
