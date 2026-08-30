# SEO — полная документация (сессия 2026-08-27)

Краткая версия — в CLAUDE.md, раздел "SEO".

## robots.txt — динамический роут, не статический файл
- **КРИТИЧНАЯ находка**: до 2026-08-27 `public/robots.txt` на ПРОДЕ был `Disallow: /` — весь сайт был полностью закрыт от индексации (наследие dev-заглушки, скопированной когда-то в прод). Это первопричина отсутствия органического трафика, важнее любых правок title/description.
- Фикс: `public/robots.txt` удалён из git, вместо него `Route::get('/robots.txt', ...)` в `routes/web.php` (в самом начале файла, до блока Home) — контент выбирается по `config('app.url')`: `volleyplay.club` → открыт (с explicit `Disallow` приватных разделов: admin/api/ajax/auth/login/my/account/dashboard/org/club/staff/settings/profile и т.д. + `Sitemap:` строка), любой другой домен (включая dev `volley-bot.store`) → `Disallow: /` целиком.
- **Почему не статический файл per-branch**: dev (`main`) и prod (`production`) — общий код, любой будущий `git merge origin/main` в prod откатил бы прод-версию файла обратно на dev-версию. Динамический роут одинаков в обеих ветках, поведение различается только через `.env` (`APP_URL`).

## Готовая SEO-инфраструктура в voll-layout — используй, не изобретай
- `resources/views/components/voll-layout.blade.php:632-653` — уже поддерживает `<x-slot name="title/description/canonical/h1/h2/t_description/d_description/breadcrumbs/image/style/script">`, включая готовую микроразметку `schema.org/BreadcrumbList` в `breadcrumbs`. Используется на 112+ из 116 страниц с `x-voll-layout`.
- До 2026-08-27 главная (`welcome.blade.php`) и `/events` (`events/index.blade.php`) ЭТИ слоты уже физически имели (не были «голыми» — ложная тревога в начале разбора; неверный grep-паттерн `title=` не матчит `x-slot name="title"`), но с generic-текстом без таргетинга под реальные поисковые запросы (Wordstat), плюс `events/index.blade.php` дублировал `description` = `title` (копипаста).
- **Только 4 страницы во всём проекте реально без `h1`-слота** (не связаны с SEO-приоритетом): `activity/record.blade.php`, `components/welcome.blade.php` (неиспользуемый дубль, не путать с `resources/views/welcome.blade.php`), `policy.blade.php`, `users/show.blade.php`.

## Динамический SEO по query-параметрам /events (не косметика — реальный SSR-фильтр)
- `EventIndexService` фильтрует `format`(game/tournament/training_game/training) и `direction`(beach/classic) по-настоящему на сервере (`$q->where('format',...)`/`$q->where('direction',...)`), не JS/AJAX-only — значит под конкретные комбинации можно отдавать уникальные title/h1/description/canonical без создания новых страниц.
- Реализовано в `events/index.blade.php` (@php-блок сразу после вычисления `$fFormat`/`$fDir`): `?format=tournament` → «Турниры по волейболу», `?direction=beach` → «Где поиграть в пляжный волейбол», `?format=tournament&direction=beach` → «Турниры по пляжному волейболу», иначе — базовый «Где поиграть в волейбол». Любая ДРУГАЯ комбинация фильтров (level/location/city/остальные format) — canonical падает на голый `/events` (не плодим дубли фасетной навигации в индексе).
- **Ловушка двойного HTML-экранирования canonical с `&`**: `<x-slot name="canonical">{{ $var }}</x-slot>` экранирует один раз при захвате слота, `voll-layout` рендерит `{{ trim($canonical) }}` — экранирует ЕЩЁ раз → `&amp;amp;` вместо `&amp;` при query-параметрах с `&`. Фикс — `{!! $var !!}` в x-slot (безопасно, если `$var` не содержит пользовательский ввод напрямую — здесь строится через `route()` с литералами).
- Эти URL не в основном списке навигации → добавлены явные внутренние ссылки с главной (`welcome.blade.php`, кнопки «Турниры по волейболу»/«Пляжный волейбол»/«Секции и школы волейбола») — без ссылок краулер их не найдёт, sitemap.xml их не покрывает автоматически (добавлены туда вручную как отдельные static-записи).

## Карта кластеров запросов → страницы (Wordstat, сессия 2026-08-27)
| Кластер | Страница | Статус |
|---|---|---|
| «турнир по волейболу» / «...2026» | `/events?format=tournament` | ✅ сделано |
| «турнир по пляжному волейболу» | `/events?format=tournament&direction=beach` | ✅ сделано |
| «поиграть в волейбол» / «где поиграть» | `/events` (база) | ✅ сделано |
| «поиграть в пляжный волейбол» | `/events?direction=beach` | ✅ сделано |
| «секция по волейболу» (+для детей/взрослых/девочек) | `/volleyball_school` | ✅ title/description/h1 обновлены |
| «волейбол в Москве» (+пляжный/секция/школа/для подростков в Москве) | — | ❌ НЕ сделано: нет реального `?city=` фильтра для АНОНИМНОГО посетителя. См. память `project_seo_city_landing_todo.md`. |
- Home (`welcome.blade.php`) — broad-таргетинг на все кластеры сразу, H1 сменён с голого «VOLLEY CLUB» на «VOLLEY CLUB — волейбол в вашем городе».
- JSON-LD `WebSite`+`SportsActivityLocation` добавлены на главную. `Event`/`SportsEvent` schema.org на страницах отдельных мероприятий (`events/show.blade.php`) — НЕ сделано, рекомендовано как следующий шаг.
- `GenerateSitemap.php` дополнен: 3 facet-URL `/events` (tournament/beach/tournament+beach) + все опубликованные `/volleyball_school/{slug}`.
