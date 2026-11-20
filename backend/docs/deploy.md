# Деплой dev→prod

_Вынесено из CLAUDE.md 2026-10-23 без изменения содержания. Полный исходник: docs/archive/CLAUDE.full-2026-10-23.md_

## Деплой (dev к prod)
- **С 2026-11-20: на проде только `git merge --ff-only origin/main`, git и artisan — от `appuser`.** До этого production шла merge-коммитами поверх main (в main их не было, отличие — 2 строки комментариев от разрешения конфликта 28.10); история production влита в main (3fb430ea), теперь production — предок main. Если `--ff-only` не проходит — на проде кто-то закоммитил/смержил руками: не мержить обычным merge, разобраться.
- **git от root на проде портит владельца**: файлы worktree и `.git/objects`/`.git/index` становятся root:root, после чего `appuser` не может `fetch`/`merge` (20.11 так было после выкаток от root: 425 объектов + 17 файлов, возвращены `chown appuser:appuser`). Даже `git status` от root переписывает `.git/index`. Для чтения от root — `git -c safe.directory=... log/diff`, без status.
- Прод находится на ветке `production` (НЕ `main`)
- Деплой: merge origin/main в production; проверить: `git merge-base --is-ancestor <hash> HEAD && echo "уже в проде"`

```
cd /var/www/volleyplay/backend
sudo -u appuser git fetch origin && sudo -u appuser git merge --ff-only origin/main   # только fast-forward (решение владельца 2026-11-20)
composer install --no-dev --optimize-autoloader
npm install --legacy-peer-deps && npm run build
php artisan livewire:publish --assets
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:clear && php artisan view:cache
sudo supervisorctl restart volleyplay-queue:* volleyplay-reverb
sudo systemctl reload php8.3-fpm   # ← обязательно при изменении PHP-классов/config
```

**npm ci не работает на проде**: `@capacitor-community/keep-awake@6` требует `@capacitor/core >=7`, а проект на `@capacitor/core@6` → peer dep conflict. Всегда использовать `npm install --legacy-peer-deps`.

**Правило (добавлено 2026-09-28, после инцидента с брендами — см. ниже): если в деплое есть НОВАЯ миграция И новый код, который выполняется на КАЖДОМ запросе (глобальный middleware/провайдер) и трогает создаваемую этой миграцией таблицу/колонку — гнать `git merge` → `migrate --force` → `config:cache`/`route:cache`/`view:cache` одним блоком БЕЗ пауз на промежуточные проверки.** `git merge` меняет файлы на диске мгновенно; реальные запросы могут прилететь в секунды между merge и завершением `migrate` — если код уже ссылается на новую таблицу, это 500 на ВСЕМ сайте до конца `migrate`. Аналогично `route:cache`/`config:cache` — если отложить их «на потом, после первой проверки», конкретные страницы, зависящие от новых роутов/конфига, будут падать в этот разрыв, даже если главная страница уже отвечает 200.

### Перенос ОТДЕЛЬНЫХ коммитов dev→prod (не полный merge)
- Dev и prod — РАЗНЫЕ клоны одного origin с несинхронизированными историями (dev обычно на много коммитов впереди `origin/main`). `git cherry-pick <SHA>` на проде падает («bad object»), если коммит есть только на dev и не запушен на origin — `git fetch origin` его не принесёт, объекта физически нет в базе прод-клона.
- Рабочий метод: на dev — `git format-patch -1 <SHA> --stdout > /tmp/x.patch`; на проде — `git am /tmp/x.patch` (сохраняет автора/сообщение, в отличие от `git apply`+ручной commit).
- **Ловушка — цепочка зависимостей файла**: если нужный коммит X — diff поверх ПРОМЕЖУТОЧНОГО коммита Y для того же файла (Y на dev позже полностью переписан коммитом X, но Y раньше не деплоился на prod) — патч X не применится («does not apply»). Проверять на dev: `git log <последний задеплоенный SHA для файла>..<X> --oneline -- <файл>` — накатывать через `git am` ВСЕ найденные коммиты по порядку, не только тот, что назвали явно.
- После накатки — `git diff HEAD~N..HEAD --stat` на проде, сверить список файлов с ожидаемым.
- Перед `git push origin production` (или `main`) — если ветка опережает origin сильно больше, чем на число только что накатанных коммитов, это нормально (там могут быть более ранние ещё не запушенные локальные коммиты) — но убедиться через `git log origin/production..production` что список ожидаемый, ничего чужого не улетит.

## routes:check-usage и мёртвый код (2026-10-24)
- Команда пропускает файлы/каталоги с пометкой `delete …` в пути (`views/delete event_templates`, `events/delete choose_create…`) — выведенный из эксплуатации код, недостижим. Оставшиеся «не в route:list» (2026-10-24) — тоже недостижимые остатки Jetstream/старых фич (`navigation-menu`/teams/api-tokens, `auth/register`, `account/link`, `integrations/vk_community_select`, `EventTemplateController`: маршрутов на них нет, `/dashboard` закомментирован), в логах прода 0 обращений. Живой 500 — только если появится маршрут/рендер на такой view: тогда чинить.
