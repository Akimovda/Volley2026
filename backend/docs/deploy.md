# Деплой dev→prod

_Вынесено из CLAUDE.md 2026-10-23 без изменения содержания. Полный исходник: docs/archive/CLAUDE.full-2026-10-23.md_

## Деплой (dev к prod)
- Прод находится на ветке `production` (НЕ `main`)
- Деплой: merge origin/main в production; проверить: `git merge-base --is-ancestor <hash> HEAD && echo "уже в проде"`

```
cd /var/www/volleyplay/backend
git fetch origin && git merge origin/main --no-edit
composer install --no-dev --optimize-autoloader
npm install --legacy-peer-deps && npm run build
php artisan livewire:publish --assets
php artisan migrate --force
php artisan config:clear && php artisan config:cache
php artisan route:clear && php artisan route:cache
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
