# Фронтенд: JS/CSS/Blade, WebView, ловушки вёрстки

_Вынесено из CLAUDE.md 2026-10-23 без изменения содержания. Полный исходник: docs/archive/CLAUDE.full-2026-10-23.md_

## JS файлы
- lib.js — везде, script.js — логика+swal, fas.js — fancybox+swiper, org.js — орг. панель
- swal: класс .btn-alert + data-атрибуты
- Fancybox = jQuery: jQuery.fancybox.open({src:'#id',type:'inline'}), НЕ standalone
- Safari: использовать jQuery.ajax (не fetch — CORS), polling 200мс (не input/keyup)
- Safari select bug: использовать change (не input) для <select> — input не срабатывает
- **`<datalist>` в WKWebView не работает** — подсказки не показываются никогда, независимо от способа заполнения (JS/server-side). Заменять на `<select>` или кастомный dropdown.
- **`<a href="#anchor">` для табов/кнопок — мина в WKWebView**: без `e.preventDefault()` паразитный scroll-to-anchor дёргает viewport при смене высоты контента. Для табов и действий использовать `<button>` или `href="javascript:void(0)"` + `e.preventDefault()`, НЕ якоря.
- **Bootstrap-классы-фантомы**: в проекте изредка встречаются Bootstrap-классы (`justify-content-between`, `align-items-center`, `text-muted` и др.) — в кастомном CSS их нет, молча не работают. Если flex-раскладка «не распределяется» — проверить, не Bootstrap ли класс. Лечится своим классом с явными свойствами (`section-title-row` и т.п.). CSS файлы: lib.css (кастомные утилиты) + style.css (компоненты). Bootstrap не подключён.
- **`.row2` (lib.css) убивает вертикальный gap между перенесёнными строками карточек, если используется вместе с `.row`** (класс `"row row2"`, найдено и исправлено 2026-07-16 на admin/dashboard и dashboard/org.blade.php): `.row {margin:0 -1rem -2rem}` даёт 2rem нижний отступ на перенос строки, а `.row2 {margin:0 -1rem}` (2-значный shorthand, объявлен НИЖЕ `.row` в файле) полностью перезаписывает margin, обнуляя vertical-компонент (`.row2 > * {padding:0 1rem}` аналогично обнуляет `padding-bottom`). Горизонтальный отступ не страдает (оба дают 1rem). Симптом: карточки метрик стоят вплотную по вертикали при переносе на 2+ строки (2 и более карточки в ряд на мобильном), при этом на строке из одного ряда (не переносится) дефект незаметен. `.row2` используется в 59+ файлах — глобально не трогали (риск регресса), в проблемных местах просто убрали модификатор `row2`, оставив `row`.
- Класс form-select-dropdown даёт visibility:hidden — НЕ использовать для dropdown
- **CSS-transition show/hide (`.form-select-dropdown--active`, opacity/visibility/transform) ломает скролл колесом/трекпадом в Safari** внутри `backdrop-filter`-контекста (`.ramka`/`.card-ramka` на `@media(min-width:992px)`) — событие `wheel` не долетает до списка, хотя курсор физически над ним. Рабочий паттерн (эталон — автокомплит «Добавить игрока» на `/events/{id}/teams/{id}`, `#ti-dd`): переключать `display:none`/`display:block` напрямую в JS, не через CSS-класс с transition. Исправлено в 4 дропдаунах поиска капитана на `tournaments/setup.blade.php`.
- **`#trainer_dd` в style.css был id-селектором и никогда не применялся** — реальный элемент имеет класс `trainer_dd` (`<div class="form-select-dropdown trainer_dd">`), не id. Список рос без `max-height`, наведение мышкой скроллило всю страницу. Исправлено на `.trainer_dd`.
- createCustomSelect оборачивает .form select → дропдаун обрезается если .card имеет overflow:hidden → добавлять style="overflow:visible" на карточку
- **PHP→JS передача данных — правило**: внутри `<script>` блока `{{ $var }}` вызывает `htmlspecialchars()` → `"` → `&quot;`, `&` → `&amp;` → SyntaxError в браузере (browser не декодирует HTML-entities внутри `<script>`). Правильно: `@json($var)` для строк/массивов/объектов; `{{ (int)$n }}` / `{{ (float)$n }}` для чисел (нет спецсимволов). `{{ json_encode($var) }}` — ЗАПРЕЩЕНО в JS-контексте, то же что `{{ $var }}` через e().
  ```js
  // плохо (ломается на спецсимволах):
  const sizes = {{ $mgmtSizes }};
  const type  = {{ json_encode($recType) }};  // &quot; → SyntaxError
  // хорошо:
  const sizes   = @json($mgmtSizes);
  const orgName = @json($event->title);   // безопасно даже с & " ' < >
  const ts      = {{ $evStartsAt->timestamp }};  // число — ок
  ```
- **ShouldQueue Job — $queue через onQueue()**: `public string $queue = 'name'` конфликтует с `Queueable` трейтом (он объявляет `$queue` как nullable). Правильно: задавать в конструкторе через `$this->onQueue('broadcasts')`.
- **@json() в onclick-атрибуте — ЗАПРЕЩЕНО**: `@json()` выводит `"строка"` с двойными кавычками прямо внутрь `onclick="..."` → первая `"` обрывает HTML-атрибут → SyntaxError → обработчик не выполняется. Решение: строки передавать через `data-*` атрибуты (`{{ }}` экранирует), логику выносить в `<script>` блок через jQuery/addEventListener
- **swal с несколькими кнопками**: SweetAlert 1.x поддерживает объект `buttons` с произвольными ключами и `value`. Паттерн для выбора действия: одна hidden-форма + `input[name=x]`, значение которого устанавливается по нажатой кнопке перед `form.submit()`. PHP-данные передавать через `data-*` атрибуты на кнопке, читать в IIFE-скрипте. Пример: `buttons: { cancel: {text:'Отмена',value:null}, waitlist: {text:'В очередь',value:'waitlist'}, leave: {text:'Выйти',value:'leave',className:'swal-button--danger'} }`

## Blade
- @include передаёт все parent переменные автоматически
- Partials occurrence_edit: 13 штук в views/events/_partials/
- Trix editor: /assets/trix.css + /assets/trix.js (v2.1.15, локально)
- **@elseif внутри блока — критичный баг**: вставка `@elseif` посередине `@if($a) ... @elseif($b)` переносит весь код ДО нового @elseif в первую ветку, а весь код ПОСЛЕ — во вторую. Переменные объявленные в первой ветке (`@php $x = ...`) недоступны во второй → Undefined variable. При добавлении промежуточного @elseif — всегда вставлять его ПОСЛЕ полного закрытия текущей ветки (после всех @endif вложенных блоков).
- **`.card` (style.css) имеет `height:100%`** — если оборачивающая колонка почти пустая (список из 1-2 элементов в высокой flex/grid-колонке), карточка растягивается пустым белым полем на всю высоту колонки; граница по умолчанию `rgba(0,0,0,0.1)` почти не видна на белом фоне → выглядит как «безрамочный блок, вылезающий за пределы страницы». Для карточек-элементов списка (не тайлов сетки) переопределять `height:auto` инлайн-стилем.

## Форма создания события — data-show-if / data-hide-if
- Логика в `step2.blade.php` → `applyAllowRegShowIf()` (глобально через window)
- Синтаксис data-show-if: `field=val` (одно), `f1=v1,f2=v2` (AND), `f=v1|v2|v3` (OR внутри поля)
- Синтаксис data-hide-if: `f=v1,v2,v3` (OR по значениям), `f1=v1|f2=v2` (OR между полями)
- Поддерживаемые поля: allow_registration, registration_type, registration_mode, format
- Триггер: `$('form').on('change', '#registration_mode, #format', ...)` в create.blade.php

## Dark mode (body.dark)
- Inline style="color:..." нельзя переопределить CSS-классом
- Решение: добавить класс + text-shadow с белым glow в `body.dark .class`
- Уровень 7 ("Профи М.С.") = #212121 — чёрный → class `level-color-badge`; CSS: `body.dark .level-color-badge { text-shadow: 0 0 8px rgba(255,255,255,.85); }`

## Trix — `trix-paste` это уведомление ПОСТФАКТУМ, не хук «до вставки» (исправлено 2026-07-17)
Полное расследование — [claude_docs/claude_trix_paste.md](claude_docs/claude_trix_paste.md).
- `trix-paste` — DOM-событие ПОСЛЕ того, как Trix уже вставил сырой HTML; `preventDefault()` внутри него ничего не отменяет.
- Паттерн-баг (был в 4 файлах): `e.preventDefault(); editor.insertHTML(clean);` — вставляет очищенную копию ПОВЕРХ уже вставленного сырого HTML → двойная вставка.
- **Фикс**: `editor.undo()` ПЕРЕД повторной вставкой:
  ```js
  document.addEventListener('trix-paste', function(e) {
      var paste = e.paste;
      if (paste.html) {
          var clean = paste.html.replace(/<(?!\/?(br|p|b|i|u|strong|em|a |ul|ol|li))[^>]+>/gi, '');
          e.target.editor.undo();
          e.target.editor.insertHTML(clean);
      }
  });
  ```
- Исправлено в: `admin/locations/edit.blade.php`, `admin/locations/create.blade.php`, `volleyball_school/create.blade.php`, `volleyball_school/edit.blade.php`.

## Дубль города/региона для городов федерального значения (СПб, Москва) — исправлено 2026-07-17
- В `cities` у городов федерального значения `region` дословно равен `name` (`id=266 Санкт-Петербург/Санкт-Петербург`, `id=403 Москва/Москва`). Паттерн вывода `"{city->name}, {city->region}"` / `"{city->name} ({city->region})"` использовался **в 13+ местах** без проверки на совпадение → дубль в шапке (`/locations/{id}`), в карточках, в профилях, в автокомплитах городов.
- **Единый хелпер** в `app/Models/City.php`: `City::displayRegion(?string $name, ?string $region): ?string` (статик, для «сырых» `stdClass`-строк из `DB::table('cities')`) + accessor `getRegionDisplayAttribute()`/`->region_display` (для Eloquent `City`-инстансов: `$location->city`, `$user->city`, `$school->cityModel`, `$currentCity` и т.п.) — оба возвращают `null`, если `mb_strtolower(trim($region)) === mb_strtolower(trim($name))`, иначе `$region` как есть.
- `CitySearchController::search()` (эндпоинт `/cities/search`, единый для ВСЕХ городских автокомплитов на сайте — `city.js`, `events-create.js`, инлайн-JS в `event_management_edit.blade.php`) добавляет `region_display` в каждый элемент JSON-ответа — клиентский JS использует готовое поле, не дублирует сравнение самостоятельно.
- Исправлено ~16 мест, включая `locations/show.blade.php`, `event_management_edit.blade.php` (PHP-префилл + JS `renderResults`), `events/_partials/create/step2.blade.php`, `public/assets/city.js`, `public/js/events-create.js` и другие карточки/профили/автокомплиты городов.
- **Не трогал**: `welcome.blade.php` строка с `$isoCodes[$city->region]` — функциональный ключ для флага страны, не отображение.

## Sticky-блоки под фикс-шапкой — единая утилита window.getFixedHeaderBottom() (регресс исправлен 2026-08)
Полная история регрессов (включая баг меню-гармошки) — [claude_docs/claude_sticky_header_menu.md](claude_docs/claude_sticky_header_menu.md).
- **Правило**: любой sticky/fixed-элемент под шапкой обязан брать отступ через `window.getFixedHeaderBottom(extra)` (`public/assets/script.js`) — НЕ хардкодить `top`, т.к. в `.is-app` высота шапки зависит от `env(safe-area-inset-top)`.
- Sticky-блок должен подписываться не только на `resize`/`orientationchange`/`load`, но и на кастомное событие `vp:header-resize` (Telegram WebApp сообщает safe area асинхронно).
- Десктопный поповер меню — `position:absolute` (не `fixed`) внутри `.fix-header` — работает без JS-пересчёта `top`, т.к. `.fix-header` (position:fixed) — containing block для absolute-потомков.

## /events в нативных приложениях (`html.is-app`) — без заголовка и дат (2026-09-29)
- В приложениях (UA `VolleyPlayApp`/`SVSVolleyApp` → класс `is-app` на `<html>`) на `/events` скрыты `h1` и `h2` (диапазон дат) через CSS в слоте `style` `events/index.blade.php`; в HTML они остаются (SEO для браузера не тронут). `.title-description` скрыт отдельным правилом в `style.css`.
- Резерв места под них тоже убран (`style.css`, `html.is-app .events-page .top-section`): `padding-top = env(safe-area-inset-top) + var(--fh-content-h, 5.6rem) + 2rem`, `padding-bottom:0`, `.top-section-txt {padding:0}`. `--fh-content-h` пишет JS на странице (`syncStickyOffset`).
- **Ловушка**: запас 2rem нельзя уменьшать. `initStickyCollapse()` (`events/index.blade.php`) ставит `.is-scrolled` (сворачивает фильтр, остаются только чипы дат), если `mobSticky.getBoundingClientRect().top <= computed top + 1`, а sticky `top` в app = `safe-area + --fh-content-h + 12px`. При запасе 1rem (10px) лента «прилипала» уже на scroll=0 и фильтр был схлопнут с первого показа. Запас должен быть строго больше 12px+1px.

## Баг промаха скролла к разделу дня на /events (найден и исправлен 2026-08)
Полное расследование — [claude_docs/claude_scroll_day_bug.md](claude_docs/claude_scroll_day_bug.md).
- Единая функция `stickyBottom()` — используется и для цели скролла, и для определения активного дня (раньше использовались две разные границы, отсюда промах).
- IntersectionObserver — только триггер пересчёта, выбор активного дня — прямой `getBoundingClientRect()` по всем секциям заново (сам IO даёт entries только по изменившимся элементам).
- После явного клика по чипу — окно `suppressObserverUntil` (1500мс), в течение которого фоновый пересчёт активного дня подавлен.
- Ждать конца скролла — через событие `'scrollend'` (не угадывать таймаутом).

## Флюидный корневой размер шрифта на узких экранах (2026-09-29)
- `style.css`, `html` в `@media (max-width:480px)`: `font-size: clamp(51%, calc(58% - (430px - 100vw) / 65), 58%)`. 430px+ = 58% (9.28px, эталон iPhone Pro Max), ~360px и уже = 51% (8.16px), между — плавно. Весь интерфейс в `rem`, поэтому масштабируется целиком (текст, отступы, иконки). Проценты, не px — системная настройка шрифта уважается.
- Причина: «шрифт одинаковый, но на Redmi A7 Pro карточки выглядят перегруженно» — экран ~360 CSS-px против 430 у Pro Max; это геометрия, не баг WebView (см. память `project_android_webview_font_scale`). Подстройка — пол `51%` и делитель `65` (больше делитель = мягче падение). Нативные приложения грузят тот же сайт, отдельного CSS нет; `html.is-app` шрифт не трогает.
- **Ловушка деплоя**: приложения ходят на прод (`volleyplay.club`) — проверка «на устройстве» бессмысленна, пока правка не на проде (dev-коммит пользователь не увидит).
- Подтверждено пользователем на Redmi после деплоя: «стало гораздо лучше».

## Инструмент скриншотов `tools/shot.mjs` (2026-10-18)
- Проверка вёрстки dev-сайта глазами: `node tools/shot.mjs /events --vp 390x844,1280x800 --theme both [--app] [--measure ".btn"] [--full] [--click sel] [--hide sel] [--cookie name=val]`, справка — `--help`. PNG по умолчанию в `/tmp/shots` (вне репозитория), смотреть инструментом Read.
- Сам: puppeteer-core + Chrome из `storage/app/chromium/`, host → 127.0.0.1, каждый кадр в отдельном browser context, Twemoji, автозакрытие баннера «установить приложение», `.phpdebugbar` скрыт, ночь = `body.dark`, предупреждение о горизонтальном overflow и JS-ошибки в выводе. Закрытые страницы — только с cookie демо-пользователя dev (см. раздел про тестовые данные для скриншотов); прод и реальные сессии не трогать.
- Рядом: `tools/shot-diff.mjs` (pixelmatch, до/после), `tools/a11y.mjs` (axe-core, контраст), `tools/blade-lint.php` (компиляция Blade + `php -l`). devDependencies `pixelmatch`/`pngjs`/`@axe-core/puppeteer` — на проде `npm install --legacy-peer-deps` их тоже поставит, это безвредно.
- **Проектные настройки Claude Code** — `.claude/settings.json` (в git): разрешения на эти инструменты и хук `PostToolUse` на `Edit|Write` (`tools/hook-lint.php`: `.blade.php` → blade-lint, `.php` → `php -l`; при ошибке код 2 и текст попадает мне). Скилл `visual-check` — `.claude/skills/visual-check/SKILL.md` (порядок проверки + ловушки вёрстки проекта). Личные разрешения остаются в `.claude/settings.local.json` (в git не идёт).
- Свёрстанное «на глаз» не считать готовым: после правки CSS/blade прогнать на 360 и 390 px в обеих темах.

## Скачивание файлов (PDF/Excel/CSV/TXT) в нативных приложениях (2026-10-12)
- **Правило: любая ссылка на скачиваемый файл/отчёт ОБЯЗАНА иметь атрибут `data-file-download`** (`<a href="{{ route('...') }}" data-file-download class="btn">`). Для ссылок, чей `href` проставляется JS-ом, — `link.setAttribute('data-file-download','')` (в самом `href` допустим `#` — обработчик его пропускает). Без атрибута в приложении (Capacitor WebView) файл откроется прямо в окне WebView без кнопок «назад»/«сохранить» (iOS) либо вообще не скачается (`Content-Disposition: attachment` Android WebView игнорирует). Это не автоматическое правило «на все выгрузки» — новая кнопка отчёта без атрибута сломана в приложении.
- Обработчик — IIFE в конце `public/assets/script.js` (capture-фаза на `document`): в приложении (`html.is-app` или `window.Capacitor`) делает `fetch` с cookie, берёт имя из `Content-Disposition`, отдаёт файл через `navigator.share({files})` (iOS: «Сохранить в Файлы», AirDrop и т.д.). Если после загрузки жест пользователя «протух» (`NotAllowedError`) — swal «Файл готов» с кнопкой «Сохранить» (свежий тап). Нет Web Share для файлов (часть Android WebView) → `window.open(url,'_system')` — системный браузер скачивает сам. В обычном браузере ссылка работает как раньше. Старые per-page обработчики `window.open(..., '_system')` удалены — не возвращать.
- Подключено (2026-10-12): `/subscriptions` (Excel+PDF), аудитория организатора `/org/players` (PDF+CSV), регистрации мероприятия (PDF+TXT), турнир (расписание, результаты, статистика матча — `tournament.pdf.*`, `tournament.matches.pdf_stats`), Excel результатов на `teams/stats`. **Не подключено (ссылок в шаблонах не нашли)**: `seasons.excel` (`TeamStatsController::exportSeasonStats`), `TournamentTvController` PDF.
- Ограничение: надёжное сохранение на Android WebView возможно только нативными плагинами Capacitor Filesystem+Share — их в `package.json` нет (нужна пересборка приложения в mobile-репозитории); обработчик сам подхватит `Capacitor.Plugins.Share`, но запись файла через Filesystem пока не реализована. **Проверено пользователем в приложении 2026-10-12: «всё работает как надо»** (на каком именно устройстве — iOS/Android — не уточнялось; Android без Web Share для файлов идёт через `_system`).
- Поиск пропущенных мест: `grep -rnE "streamDownload|->download\(|Content-Disposition" app` → сопоставить с роутами → искать ссылки на них в `resources/views` и JS. Одного grep по `route('...pdf|export...')` недостаточно — так и пропустили `teams/stats`.

## Паттерн поиска игроков (autocomplete)
Эталон: `resources/views/events/show/players.blade.php` (блоки `invite-ac-*` и `group-invite-ac-*`).
- Обёртка: `position:relative`, БЕЗ `overflow:hidden`; дропдаун через класс `form-select-dropdown--active` (НЕ через `form-select-dropdown` — он даёт visibility:hidden)
- JS в IIFE; guard `if (!input) return;`; debounce 250мс, минимум 2 символа
- API: `GET /api/users/search?q=QUERY` → `{ items: [{id, label, name}] }`; использовать `item.label || item.name`
- Chips (мульти): `name="to_user_ids[]"` + `data-invite-hidden="ID"` для удаления; выбранные — opacity 0.4
- Safari: заменять fetch на jQuery.ajax при CORS-проблемах; credentials:'same-origin' обычно достаточно

## Smart App Banner и RuStore баннер
- iOS Smart App Banner: `<meta name="apple-itunes-app" content="app-id=6764748613">` в voll-layout.blade.php; обёрнут в @if(!VolleyPlayApp)
- Android RuStore: JS-блок после `<body>`; UA=Android && !VolleyPlayApp; закрытие → localStorage 7 дней; MutationObserver на body.classList для dark mode
