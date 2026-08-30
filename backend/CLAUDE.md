# Volley2026 — Контекст проекта

## Язык
- Всегда отвечай на русском языке

## Стек
- Laravel 12, PHP 8.3, PostgreSQL, Blade (частично Vue), jQuery v3.7.1
- Telegram/VK/MAX бот интеграции
- Сервер: /var/www/volley-bot/backend (dev), /var/www/volleyplay/backend (prod)

## Локализация (i18n)
- Сайт двуязычный: RU (по умолчанию) и EN
- Все строки интерфейса через `{{ __('файл.ключ') }}`, не хардкодом в blade
- При добавлении нового текста — обязательно добавлять перевод в `lang/ru/*.php` И `lang/en/*.php`
- Языковые файлы: `ui`, `auth`, `events`, `profile`, `locations`, `subscriptions`, `notifications`, `admin`, `tournaments`, `seasons`, `pages`, `welcome`
- Контент пользователей (названия событий, описания, имена, города) НЕ переводится
- HTML в переводах выводить через `{!! __('файл.ключ') !!}`
- Подстановки: `__('events.foo', ['name' => $value])` → ключ содержит `:name`
- Locale переключается через `SetLocale` middleware + `LocaleController`; кука/сессия хранят выбор пользователя

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

## Окно регистрации (паттерн)
- Данные хранятся как UTC-метки: registration_starts_at, registration_ends_at, cancel_self_until
- При отображении формы редактирования — вычислять обратно из diff UTC-меток (как в occurrence_edit.blade.php)
- Формат: часы+минуты split (select h + select m) + hidden total_minutes + JS change→sync
- event_management_edit reg_starts: два поля reg_starts_days_before + reg_starts_hours_before; вычислять через timestamp diff: days=floor(diffSec/86400), hours=floor((diffSec%86400)/3600)
- НЕ использовать hardcoded old('field', 60) без $savedValue из модели
- step2 create: selects имеют name="reg_ends_h","reg_ends_m","cancel_lock_h","cancel_lock_m","reg_starts_d","reg_starts_h"
- EventOccurrenceService::buildRegistrationWindows() читает эти поля и вычисляет minutes (приоритет перед hidden полями)
- Scheduled expand: events:expand-recurring работает ежедневно в 03:10 и перезаписывает окна регистрации у всех future occurrences

## Occurrence override паттерн
- NULL в occurrence = наследуется от серии
- Значение записывается ТОЛЬКО при отличии от event
- editOccurrence передаёт effective-переменные через $eff() хелпер
- EventShowService::handle() накладывает overrides ПОСЛЕ Cache::remember
- **effectiveCancelSelfUntil() баг**: метод возвращает `occurrence.cancel_self_until ?? event.cancel_self_until`. Установить NULL только в occurrence недостаточно — fallback на event срабатывает автоматически. Чтобы снять ограничение для конкретной occurrence нужно обнулять ОБА поля: и occurrence, и event. Иначе истёкший event.cancel_self_until блокирует выход даже при occurrence = NULL. Тот же паттерн применим ко всем `effective*`-методам модели.
- **effectiveCancelSelfUntil() и повторяющиеся серии**: форма event_management/edit пересчитывает event.cancel_self_until от event.starts_at (первый тур, обычно в прошлом). При следующем сохранении event.cancel_self_until снова станет истёкшим → снова заблокирует выход. Долгосрочный фикс: добавить sentinel-значение (например far future) или хранить смещения (minutes_before) вместо абсолютных дат на уровне occurrence.
- **event_management/edit — не обновлял future occurrences серии**: `updateOrCreate` в `EventManagementController::update()` работал только с occurrence от `event.starts_at` (обычно прошлый тур). Все будущие туры серии игнорировались → cancel_self_until, окна регистрации не применялись. Фикс: после updateOrCreate для `is_recurring=true` обновлять все occurrences с `starts_at > now()` (не отменённые), пересчитывая поля от их `starts_at`. При этом если рассчитанный `cancel_self_until < now()` — писать NULL (дедлайн уже прошёл, не блокировать выход).

## PostgreSQL
- is_cancelled (boolean) — фильтровать через whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
- Добавлять explicit boolean casts в модели
- **whereRaw с OR** — ВСЕГДА оборачивать в скобки, иначе AND>OR нарушает логику

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

## Турниры (format=tournament) — карточка
- `tournament_teams_count` (events) — кол-во команд в турнире (НЕ использовать game_settings.max_players)
- `game_settings.subtype` = '2x2' → team_size = 2 (parse через regex `/^(\d+)x\d+$/`)
- Счётчик команд: EventRegistrationGuard добавляет tournament_teams_max/registered/remaining в meta
- Зарегистрированных команд: COUNT(DISTINCT group_key), fallback ceil(registered_total / team_size)
- Карточка: data-is-tournament="1", label " команд" через `<span data-seat-unit>`
- **Правило**: лимит команд читается с приоритетом `events.tournament_teams_count → event_tournament_settings.teams_count → effectiveGameSettings()->teams_count → 0` — не `event_game_settings.teams_count` первым, оно может отставать от реального лимита (тот же паттерн, что у king_beach).
- **Правило**: `buildAvailabilitySnapshot` условие расширено до `['team_classic','team_beach','team','tournament_individual']` — для individual-режима команды формируются ПОСЛЕ регистрации (до жеребьёвки `event_teams` пуст), нужен fallback `ceil(registered/team_size)` вместо прямого COUNT команд.
- **Трёхуровневый фолбэк лимита команд** (актуально и для team_*, и для tournament_individual): 1) `events.tournament_teams_count` / `event_tournament_settings.teams_count` / `event_game_settings.teams_count` (в этом приоритете); 2) если лимит команд нигде не настроен — `ceil(total_capacity / team_size)` (лимит ИГРОКОВ, если он есть); 3) если и лимита игроков нет — `tournament_teams_max` остаётся 0, JS (`seatline_script.blade.php`) в этом случае честно переключает подпись юнита на карточке с «команд» на «игроков» через `data-unit-teams`/`data-unit-players` на `<span data-seat-unit>` (`events.card_seats_players`, новый ключ) — не показывает число игроков под чужой подписью.
- **Счётчик мест на `/events` и на странице школы (`volleyball_school/show.blade.php`) — общий JS**: обе страницы используют `events/_card.blade.php`, реальные цифры подтягиваются JS-скриптом `events/_partials/seatline_script.blade.php` (`@include` внутри `<script>`) через `/occurrences/{id}/availability`. До 2026-07-15 этот скрипт был только на `/events` — страница школы показывала статичный плейсхолдер «0 из N» и никогда не обновляла его. Если добавляешь ещё одну страницу с карточками мероприятий — не забыть подключить этот партиал, иначе тот же баг повторится.

## Индивидуальная запись на турнир (tournament_individual)
- `registration_mode = 'tournament_individual'` — игроки записываются по амплуа, не командами
- Чекбокс `tournament_individual_reg` в форме создания (step1.blade.php) в блоке `#tournament_settings_block`
- Role slots создаются как для обычной игры: позиции × teams_count; резерв = reserve_per_team × teams_count
- EventGameSettingsService: при `tournament_individual_reg=1` → `game_subtype = tournament_game_scheme`, `teams_count = tournament_teams_count`
- Guard: `tournament_individual` НЕ требует команду; waitlist включён (как обычная игра)
- players.blade.php: `$isIndividualTournament = format=tournament && registration_mode=tournament_individual`; кнопка "Распределить случайно" → POST /events/{id}/distribute-individual
- TournamentTeamDistributionService::distributeRandom() — round-robin по позициям, первый участник = капитан, не перезаписывает существующие команды
- **Гендерное распределение пар (team_size_min=2, т.е. пляжные пары 2x2)**: сначала формируются смешанные М+Ж пары (пока хватает обоих полов), остаток одного пола (+ игроки без указанного пола) — пары между собой тем же/любым полом; нечётный "хвост" — неполная пара из 1 игрока (`is_complete=false`, попадает в "⏳ Ищут партнёра", как и при обычном ручном создании). Метод `pairByGenderThenRandom()`; включается когда `event.tournamentSetting.team_size_min === 2` — для team_size!=2 (классика с разными амплуа на команду) используется прежний shuffle+round-robin без учёта пола, т.к. концепция "пары" неприменима к ролевым командам напрямую.
- **Правило**: `is_complete = count($members) >= team_size_min` в `distributeRandom()` — раньше хардкожен `true` для любой команды, неполные "хвосты" (нечётный перекос полов) ошибочно считались полными.
- **Правило**: в `TournamentTeamController::store()` при пустом `captain_user_id` и наличии `member_user_ids` капитаном становится первый отмеченный игрок, не организатор — иначе организатор дефолтом занимал место в команде и упирался в лимит `team_size_min`.
- **Правило**: организатор/админ, создавший команду вручную через setup, редиректится на `tournament.setup?occurrence_id=...` (не на страницу команды) — удобно при создании нескольких команд подряд; игрок, создающий свою — на страницу команды. `destroy()` аналогично требует `occurrence_id` в редиректе (иначе подхватывается не тот тур серии).
- **`distributeRandom()` теперь дополняет, а не требует "всё или ничего"**: раньше при наличии ХОТЯ БЫ одной существующей команды возвращал ошибку "Команды уже сформированы, сначала удалите". Теперь считает `remainingTeamsCount = tournament_teams_count - существующие команды`, берёт в выборку ТОЛЬКО игроков, ещё не состоящих ни в одной команде события/тура (через `EventTeamMember`), и формирует именно `remainingTeamsCount` новых команд, не трогая уже созданные (вручную или предыдущим запуском). Ошибка "уже сформированы" теперь означает только "свободных командных слотов не осталось".
- **Правило**: `tournament_individual_reg` обязан быть в правилах `EventCreateValidator` (`nullable,boolean`) — иначе `Validator::validate()` фильтрует поле по `getRules()` и чекбокс молча не долетает до `EventStoreService::store()`, турнир создаётся как `team_beach`/`team_classic`. Ручная починка старых записей — через `EventGameSettingsService::createGameSettings()`.
- **Правило**: `resolveTeamKindFromSettings()` принимает `$direction` вторым параметром — `tournament_individual` резолвится в `beach_pair`/`classic_team` по направлению, иначе всегда падал на `classic_team` (ложный `DomainException` про амплуа на пляжных турнирах).
- **Правило**: `captain_user_id` обязателен сразу в payload `EventTeam::create()` (колонка NOT NULL) — не проставлять постфактум через `$team->save()`.
- **Правило**: `distributeRandom()` создаёт команды со `status='approved', confirmed_at=now()` (как organizer-created с `autoApprove=true`) — `'confirmed'` не входит в фильтр `TournamentController::setup()` (`whereIn(['draft','submitted','approved','ready'])`), иначе команды невидимы на странице.
- **`confirm()`/`alert()` в JS кнопки "Распределить случайно" заменены на swal** (стиль проекта, см. паттерн `admin/impersonate/index.blade.php`: `swal({title,text,icon,buttons:{cancel:{...},confirm:{...}},dangerMode:true}).then(fn(value){...})`). **Ловушка**: `@json(__('key', ['n' => ..., 'p' => $var->count(),]))` — вложенный многострочный массив-аргумент ВНУТРИ `@json(__(...))` ломает извлечение аргументов blade-директивы (компилятор не находит закрывающую `]`/`)`, страница падает `ViewException: Unclosed '[' ... does not match ')'`). Считать текст перевода заранее в `@php $x = __('key', [...]); @endphp`, затем `@json($x)` — одна простая переменная внутри `@json()`, без вложенных вызовов/массивов.
- **`TournamentTeamNamingService`** (`app/Services/TournamentTeamNamingService.php`) — автогенерация названия команды, когда организатор оставил поле пустым: пляж → фамилии участников через `/` (`Иванов/Петров`), классика → случайное короткое название из пула прилагательное+существительное (`Дикие Бобры`); проверка уникальности в рамках `event_id+occurrence_id` (там реальный unique-индекс `event_teams_event_id_occurrence_name_unique`) с ретраями. Используется и в `distributeRandom()`, и в `TournamentTeamController::store()` (когда `registration_mode=tournament_individual` и имя не задано).
- **Setup-страница турнира (`tournaments/setup.blade.php`) — блок "Команды" для `tournament_individual`**: заголовок меняется на "Команды/Игроки (:n)" (`tournaments.setup_teams_h2_individual`), под списком команд показывается блок "Не распределены по командам (:n)" — все, кто зарегистрирован на occurrence (`event_registrations`), но ещё не состоит ни в одном `EventTeamMember` этого события/тура (`TournamentController::setup()` считает `$unassignedPlayers` через `whereNotIn('user_id', assignedUserIds)`). Форма "Создать команду" в этом случае вместо обычного автокомплита по всем пользователям (`/api/users/search`) показывает капитана через ЛОКАЛЬНЫЙ JS-фильтр по уже отрендеренному списку нераспределённых игроков (без похода в сеть — список маленький и известен на этапе рендера) + чекбоксы остальных участников (`member_user_ids[]`); при выборе капитана его чекбокс в списке блокируется и снимается. `TournamentTeamController::store()` после создания команды добавляет отмеченных участников через `TournamentTeamService::addMemberByOrganizer()` в цикле (без новых роутов).

## Dark mode (body.dark)
- Inline style="color:..." нельзя переопределить CSS-классом
- Решение: добавить класс + text-shadow с белым glow в `body.dark .class`
- Уровень 7 ("Профи М.С.") = #212121 — чёрный → class `level-color-badge`; CSS: `body.dark .level-color-badge { text-shadow: 0 0 8px rgba(255,255,255,.85); }`

## /my/events, /my/bookings, /my/court-bookings (обмен ролей, 2026-07-15)
- **`/my/bookings`** (`player.my-bookings`) — личные записи игрока на мероприятия (текущие/архивные), для ВСЕХ авторизованных. Контроллер `PlayerDashboardController::myEvents()`, вид `player/my-bookings.blade.php` (переименован из `my-events.blade.php`, логика не менялась). Пункт меню «Мои брони» (`club.my_bookings`).
- **`/my/events`** (`organizer.my-events`) — НОВАЯ страница: упрощённый карточный список мероприятий организатора (название/дата/место + «Управление турниром» если `format=tournament` + «Управление регистрациями»). Контроллер `OrgDashboardController::myEvents()`, вид `dashboard/org_my_events.blade.php`. Доступ: `role IN ('organizer','admin')`, иначе 403 (тот же паттерн, что в `EventRegistrationsOverviewController`, НЕ через `Gate::is-organizer` — тот строго исключает admin).
- Дубль лейбла «Мои мероприятия» (organizer.my-events vs `events.create.event_management`) устранён 2026-07-15 в рамках реструктуризации меню организатора — см. следующий раздел, у `event_management` теперь свой текст «Управление мероприятиями» (`ui.org_events_management`).
- **`/my/court-bookings`** (`player.my-court-bookings`) — прямая бронь корта игроком (бывший Фаза 5 `/my/bookings`, переехал при свапе выше). Контроллер/вид не менялись (`PlayerCourtBookingController::myBookings()` → `player/bookings.blade.php`), только URL+route name+action-роуты (`.../cancel`, `.../pay`). Пункт меню «Брони кортов» (`club.my_court_bookings`) показывается ТОЛЬКО если `$user->courtBookings()->exists()` (relation добавлен в `User`) — то есть только если у игрока реально есть история бронирования корта.

## Меню Организатора — единая структура (2026-07-15)
- Рендерится ровно в ДВУХ местах (проверено grep по `org_menu_title`/«Таб: Организатор» — других мест нет): `components/voll-layout.blade.php` (шапка, колонка 3 «Дополнительное меню», сокращённый набор из 13 пунктов) и `profile/_menu.blade.php` (сайдбар, таб «Организатор», те же 13 + доп. пункты — staff/staff_logs, «Выданные абонементы» (`subscriptions.index`, ОТЛИЧАЕТСЯ от «Абонементы»=`subscription_templates.index` в канонической 13!), школа). Оба места гейтятся `role IN ['organizer','admin']`.
- Канонический порядок (одинаковый в обоих местах): Панель организатора → Панель арендатора → Управление мероприятиями → Управление регистрациями → Мои мероприятия → 🎪 Брони кортов → 📆 Создать мероприятие → 🪪 Абонементы → 🎟 Купоны → 🏆 Мои лиги и сезоны → 📣 Каналы уведомлений → 🌐 Виджет на сайт → ⭐ Организатор Pro.
- Эмодзи у 🏆/🌐/⭐ зашиты В ЗНАЧЕНИИ перевода (`ui.org_my_leagues/org_widget/org_pro`); у 🎪/📆/🪪/🎟/📣 — хардкод в blade рядом с `__()` (тот же паттерн, что раньше был у `club.bookings_title`) — при переводе на другой язык эмодзи не переводить, значение ключа остаётся без эмодзи.
- **«Панель арендатора» (`club.analytics`, переименована из «Аналитика») и «Брони кортов» (`club.bookings_title`) — гейт «арендодатель кортов»**: `$user->is_club_manager && $user->ownedLocations()->exists()`. В схеме БД НЕТ отдельного флага «корты выставлены на аренду» — сам контроллер `ClubBookingController` авторизует ещё шире (`is_club_manager || isAdmin()`, без проверки владения локацией вообще); `ownedLocations()->exists()` — чисто UX-фильтр в меню (не показывать тем, кто ещё не завёл ни одной локации). Это тот же паттерн, что `$user->courtBookings()->exists()` для пункта «Брони кортов» у игрока (прошлая сессия).

## Ключевые компоненты
- Карточка мероприятия: resources/views/events/_card.blade.php
- Меню профиля: resources/views/profile/_menu.blade.php
- Аватары: Spatie Media, collection='avatar', конверсия thumb
- Фото мероприятия: event_photos (JSON array Media IDs) в колонке events.event_photos (cast array); конверсия event_thumb; Swiper+чекбоксы → hidden input event_photos = JSON

## Боты
- Telegram dev: /opt/volley-telegram-bot/bot.py (порт 8092)
- Telegram prod: /opt/volleyplay-telegram-bot/bot.py (порт 8094)
- MAX dev: /opt/volley-max-bot/bot.py (порт 8091)
- VK dev: /opt/vk-bot/bot/index.php (PHP)

## MAX API — редактирование сообщений с фото
- PUT /messages удаляет фото если не передать photo_id обратно в attachments
- `MaxChannelPublisher::update()` извлекает photo_id из `previousMeta.saved_image_attachment`, затем из `raw.raw.message.body.attachments`
- После PUT сохраняет `saved_image_attachment` в meta для следующих обновлений

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

### Перенос ОТДЕЛЬНЫХ коммитов dev→prod (не полный merge)
- Dev и prod — РАЗНЫЕ клоны одного origin с несинхронизированными историями (dev обычно на много коммитов впереди `origin/main`). `git cherry-pick <SHA>` на проде падает («bad object»), если коммит есть только на dev и не запушен на origin — `git fetch origin` его не принесёт, объекта физически нет в базе прод-клона.
- Рабочий метод: на dev — `git format-patch -1 <SHA> --stdout > /tmp/x.patch`; на проде — `git am /tmp/x.patch` (сохраняет автора/сообщение, в отличие от `git apply`+ручной commit).
- **Ловушка — цепочка зависимостей файла**: если нужный коммит X — diff поверх ПРОМЕЖУТОЧНОГО коммита Y для того же файла (Y на dev позже полностью переписан коммитом X, но Y раньше не деплоился на prod) — патч X не применится («does not apply»). Проверять на dev: `git log <последний задеплоенный SHA для файла>..<X> --oneline -- <файл>` — накатывать через `git am` ВСЕ найденные коммиты по порядку, не только тот, что назвали явно.
- После накатки — `git diff HEAD~N..HEAD --stat` на проде, сверить список файлов с ожидаемым.
- Перед `git push origin production` (или `main`) — если ветка опережает origin сильно больше, чем на число только что накатанных коммитов, это нормально (там могут быть более ранние ещё не запушенные локальные коммиты) — но убедиться через `git log origin/production..production` что список ожидаемый, ничего чужого не улетит.

## Текущая версия: v1.9.7

### Деплой 2026-07-09 — три фикса закрыты (dev→prod)
Коммиты `25558e3`, `1551a65`, `56fc794`, `e1fb7a9`, смёржены в `production` (`6d630d1`). `composer install`/`npm`/`migrate` не требовались (без изменений composer.lock/миграций/Vite-JS). `config:cache`+`route:cache`+`view:cache`+`reload php8.3-fpm` выполнены.
1. **Гендер-квота при смене позиции организатором** (`EventRegistrationGuard::checkGenderQuotaForUser`, см. «Управление регистрациями») — проверено на бою (событие 380, occurrence 12253): реальная квота 4/4 женщин на `setter` блокирует корректным текстом. Находка: в этом конкретном событии `event_role_slots.setter.max_slots=4` **численно совпадает** с `gender_limited_max=4`, поэтому старый slot-лимит и так блокировал на этом же пороге — для чистой проверки именно новой гендерной защиты slot временно поднимался до 6 внутри тестовой транзакции (не в бою). Реальная брешь проявляется только когда slot-лимит НАСТРОЕН ШИРЕ гендерной квоты — обычная конфигурация организатора, не текущий частный случай события 380.
2. **`waitlist:cleanup-expired`** (см. «Лист ожидания») — задеплоена, `dailyAt('04:15')` подтверждён в `schedule:list`. Реальное удаление на проде выполнено 2026-07-09 09:33 UTC: 16 записей (occurrence starts_at 29 апр — 15 июня 2026), протокол dry-run зафиксирован перед удалением. `occurrence_waitlist` после — 0 строк.
3. **King Beach edit-форма + фикс GameCalculator-затирания** (см. «registration_mode — значения и приоритет») — проверено на бою (событие 392): форма рендерится с полями min/max (15/25), общие team-поля скрыты. Сохранение реальных данных проверялось **через транзакцию с откатом** (не прямым commit) — ручная пересборка ~40 полей формы для настоящего сохранения на живом платном турнире (взнос 1250₽, старт через 2 дня) сочтена неоправданным риском ради проверки, не относящейся к сути фикса; транзакция даёт те же гарантии без риска. Результат: max_players/min_players/registration_mode/role_slot не затираются.
- **Паттерн для будущих боевых проверок фиксов**: если тест подразумевает мутацию реальных данных (смена позиции регистрации, сохранение формы события) — оборачивать в `DB::beginTransaction()`/`DB::rollBack()` вместо прямого вызова, особенно если код по пути шлёт реальные уведомления (push/Telegram/VK) живым пользователям. Прямой (не откатываемый) вызов оправдан только для чтения (рендер страницы, SQL-проверки) или для операций, где организатор явно и осознанно просил именно боевое сохранение конкретной, полностью проверенной записи.

## Дашборд организатора и аналитика игроков

### Файлы
- Дашборд: `app/Http/Controllers/OrgDashboardController.php` → `resources/views/dashboard/org.blade.php`
- Аналитика игроков: `app/Http/Controllers/OrgPlayersController.php` → `resources/views/dashboard/org_players.blade.php`
- Маршруты: `/org/dashboard` (org.dashboard), `/org/players` (org.players), `/org/tournament-analytics` (org.tournament-analytics)

### Паттерн multi-period топ-игроков
- Один SQL-запрос с `COUNT(CASE WHEN er.created_at >= NOW() - INTERVAL '30 days' THEN 1 END)` для всех периодов одновременно
- Передавать всё в `@json($topPlayers)`, JS-переключатель фильтрует и сортирует массив по нужному полю
- Так избегаем 5 отдельных запросов к БД

### users.gender — значения 'm' / 'f'
- **КРИТИЧНО**: `users.gender` хранит `'m'` и `'f'`, НЕ `'male'`/`'female'`
- При `->keyBy('gender')` обращаться через `->get('m')` и `->get('f')`
- Аналогично при любых WHERE-условиях по полу

### Блоки страницы /org/players
- Сводка (4 карточки), топ активных с периодами (JS-табы), новые за 30 дней, риск оттока (≥3 визита + последний >60 дней назад), распределение по полу/уровням (Chart.js), часто в резерве (position='reserve')
- Чтобы добавить новый блок — добавлять запрос в `OrgPlayersController::index()` и секцию в blade

### Блок «Загрузка мероприятий» на /org/dashboard — баг с event_occurrence_stats (исправлен 2026-07-15)
- **Баг**: старый запрос `$occurrenceLoad` брал `SUM/AVG` по `event_occurrence_stats.registered_count` (LEFT JOIN) — это **кеш**, который не обновляется при массовых отменах через `EventRegistrationsManagementController::cancel()` (прямой `DB::table()->update()`, без вызова `EventOccurrenceStatsService`). У большинства occurrences строки в `event_occurrence_stats` вообще не было (`eos=NULL`) → `SUM`/`AVG` в PostgreSQL игнорируют NULL (не считают за 0) → «Всего записей» пустое, а не `0`, для событий без строки в кеше. Дополнительно: PostgreSQL `ORDER BY ... DESC` кладёт `NULL` **первыми** (не последними, вопреки интуиции) → `LIMIT 10` в первую очередь набирал мероприятия без данных, вытесняя из топа мероприятия с реальными записями (эффект «бар только у одного мероприятия из 4+»). Вместимость (`egs.max_players`) бралась без учёта типа турнира — для командных турниров (`team_classic`/`team_beach`) это состав ОДНОЙ команды, а не число команд, давая неверный %.
- **Фикс**: живой `COUNT` вместо кеша — `event_registrations` (с условиями как в `EventOccurrenceStatsService::getRegisteredCount()`) для обычных/`tournament_individual`/`king_beach`, `event_teams` (статусы `draft/ready/pending_members/submitted/confirmed/approved`, как в `EventRegistrationGuard`) для командных турниров. Вместимость — тот же приоритет `tournament_teams_count → event_tournament_settings.teams_count → event_game_settings.teams_count` для команд, `event_tournament_settings.total_players_max` для individual/king_beach, `event_game_settings.max_players` для обычных. Все три цифры (бар/«Всего записей»/«Средняя загрузка») по-прежнему из ОДНОГО SQL-запроса (`fromSub` — подзапрос считает per-occurrence `registered`/`capacity`, внешний `GROUP BY` агрегирует). Отменённые occurrences (`is_cancelled=true`) исключены из выборки — иначе события, где ВСЕ occurrences отменены, занимали место в `LIMIT 10` пустыми строками.
- Подробности диагностики и проверка на данных организатора 260 (dev) — `report_org_dashboard_load_2026-07-15.md` в корне backend.
- Дрейф самого `event_occurrence_stats` (упомянутый как «не пофикшено» в этом же коммите) закрыт полностью 2026-07-16 — см. следующий раздел.
- Превышение 100% загрузки, когда фактических регистраций больше `max_players` за счёт резерва (`reserve_players_max` не учтён в знаменателе) — отдельное наблюдение, не трогали, не относится к кеш-счётчикам.

## Выпил денормализованных кеш-счётчиков (2026-07-16)

### `event_role_slots.taken_slots` — write-пути удалены
- Колонка структурно не могла быть верной: НЕ occurrence-scoped (`UNIQUE(event_id, role)` — один счётчик на ВСЕ occurrences повторяющегося события сразу). Источник истины — live COUNT (`EventRoleSlotService::countActive()`/`hasFreeSlot()`/`tryTakeSlot()`, включая `WaitlistService::autoBookNext()`) — так было и раньше, колонка нигде не читалась для бизнес-решений.
- `tryTakeSlot()`/`syncRoleSlots()` больше не пишут `taken_slots`; `resyncTakenSlots()` удалён вместе с единственным вызовом (`EventRegistrationController::persistCancellation()`).
- `php artisan event-slots:resync` — команда осталась (файл не удалён), но теперь no-op с warning об устаревании.
- НЕ done: `DROP` колонки `event_role_slots.taken_slots` и партиального индекса `idx_event_role_slots_available` — отдельной миграцией после недели наблюдения.

### `event_occurrence_stats` — write-пути + таблица-кандидат на DROP
- Живых читателей было 2 (не ноль): `OrgDashboardController::$botEffect` и `WidgetPublicController::getEvents()` — оба переведены на live COUNT (`fromSub`+CASE / коррелированный скалярный подзапрос) ДО удаления write-путей.
- Убраны все вызовы `increment()`/`decrement()`: `EventRegistrationController`, `EventRegistrationsManagementController`, `BotAssistantService`, `WaitlistService::autoBookNext()`. Соседний `$wasActive`-гейт перед `WaitlistService::onSpotFreed()` сохранён — трогали только вызов статистики.
- `EventOccurrenceStatsService` теперь содержит только `getRegisteredCount()` (живой COUNT), используется `GET /occurrences/{id}/stats` — единственный оставшийся потребитель, сервис целиком не удалять.
- Удалён `app/Events/OccurrenceStatsUpdated.php` (мёртвый broadcast, слушателей не было).
- НЕ done: `DROP` таблицы `event_occurrence_stats` — отдельной миграцией после недели наблюдения.

## Школы (volleyball_school)
- **Баг (исправлен 2026-07-15): 404 при сохранении чужой школы админом**. `VolleyballSchoolController::update()` для админа ищет школу по `$request->input('school_id', 0)` (`findOrFail(0)` → 404 при отсутствии поля), для обычного организатора — по `organizer_id`. В `edit.blade.php` не было скрытого поля `school_id` → админ, редактирующий школу другого организатора через `/volleyball_school/my/edit?id=X`, ловил 404 при сохранении (сама форма грузилась нормально). Фикс: `<input type="hidden" name="school_id" value="{{ $school->id }}">`.

## Уведомление жителей города о новом мероприятии (feature, добавлено 2026-07-17)
Полная документация — [claude_docs/claude_city_notify.md](claude_docs/claude_city_notify.md).
- Триггер: только `EventStoreService::store()` (новое событие), с дедупом `events.city_notified_at` + rate-limit 1/сутки на пользователя, чанками по `config('notifications.new_event_city_notify_chunk_size')`.
- Переключатель — в `profile.sec_notifications` на `profile/show.blade.php` (НЕ на странице каналов — та гейтится role organizer/admin/staff, обычным игрокам недоступна).
- Гейт `config('notifications.new_event_city_notify_enabled')` — выключается без деплоя.

### `.checkbox-item`/`.custom-checkbox` требуют предка с классом `.form`
- Все правила в `style.css` для чекбоксов/радио скопом `.form .checkbox-item`/`.form .custom-checkbox` (включая `.form .checkbox-item input {display:none}`). Без предка `.form` где-либо выше по DOM — виден голый браузерный чекбокс, кастомный тумблер полностью отсутствует (не «немного не так выглядит»). `.form` часто находится в родительском файле, который `@include`-ит партиал (`events/create.blade.php`, `tournaments/setup.blade.php` — весь `.form`-скоуп на уровне страницы).
- Исправлено (добавлена разметка `checkbox-item`+`custom-checkbox`, JS-селекторы на `<input>` не менялись): `admin/locations/edit.blade.php` (`direction-toggle`, `court_indoor`, `day-off-toggle`), `admin/users/show.blade.php` (`is_club_manager`), `profile/widget.blade.php` (`show_slots`/`show_location`), `tournaments/_partials/king_beach_stage.blade.php` (`kb-player-cb`).
- **Не трогали**: `resources/views/components/checkbox.blade.php` (`<x-checkbox>`, Jetstream/Tailwind) — отдельная дизайн-система.

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

## Баг промаха скролла к разделу дня на /events (найден и исправлен 2026-08)
Полное расследование — [claude_docs/claude_scroll_day_bug.md](claude_docs/claude_scroll_day_bug.md).
- Единая функция `stickyBottom()` — используется и для цели скролла, и для определения активного дня (раньше использовались две разные границы, отсюда промах).
- IntersectionObserver — только триггер пересчёта, выбор активного дня — прямой `getBoundingClientRect()` по всем секциям заново (сам IO даёт entries только по изменившимся элементам).
- После явного клика по чипу — окно `suppressObserverUntil` (1500мс), в течение которого фоновый пересчёт активного дня подавлен.
- Ждать конца скролла — через событие `'scrollend'` (не угадывать таймаутом).

## SEO (добавлено 2026-08-27)
Полная документация — [claude_docs/claude_seo.md](claude_docs/claude_seo.md). Открытый TODO — память `project_seo_city_landing_todo.md`.
- **КРИТИЧНО**: `public/robots.txt` — динамический роут (`Route::get('/robots.txt', ...)` в `routes/web.php`, выбор контента по `config('app.url')`), НЕ статический файл — иначе `git merge` откатит прод-версию на dev-заглушку (`Disallow: /`).
- Готовая SEO-инфраструктура — `voll-layout.blade.php` слоты `title/description/canonical/h1/h2/breadcrumbs` (112+ страниц), не изобретать заново.
- `/events` фильтрует `format`/`direction` реально на сервере — под конкретные комбинации отдаются уникальные title/h1/description/canonical (см. `events/index.blade.php`). **Ловушка**: `<x-slot name="canonical">{{ $var }}</x-slot>` двойное экранирование `&` → `&amp;amp;`, использовать `{!! $var !!}`.

## Правила работы
- В конце каждой сессии обновляй этот файл: добавляй новые находки, баги, паттерны
- Коммить изменения CLAUDE.md вместе с кодом
- Не дублируй — обновляй существующие секции
- НЕ изучай структуру проекта заново каждый раз — вся информация уже в этом файле
- Сразу приступай к задаче, используя контекст из CLAUDE.md
- **Отчёты по задачам (>50 строк итогов, вопросы к пользователю)** — файлом `report_*.md`/`deploy_*.md`/`diagnosis_*.md` в корень dev (`/var/www/volley-bot/backend`), НЕ в CLAUDE.md. Эти паттерны — в `.gitignore` (2026-07-16), **в git не коммитить никогда**, даже если явно попросят «закоммить отчёт» — сначала уточнить. Пользователь читает и удаляет сам; если такой файл пропал с диска между твоими действиями — это ожидаемо (пользователь прочитал и убрал), **не восстанавливать** из git-истории и не поднимать как проблему.

## Турнирная страница управления (setup.blade.php)
- Файл: `resources/views/tournaments/setup.blade.php` (~1660 строк)
- Partials: `resources/views/tournaments/_partials/`
- `group_crosstable.blade.php` — кросс-таблица; принимает: `$group`, `$groupMatches`, `$groupClean`, `$groupOutsiders`; ячейки победа=зелёный/поражение=красный/не сыгран=серый; итоги: М, В, Сеты, Очки, Мячи, Место
- Переключатель Список/Шахматка: `.ct-view-btn`, блоки `.ct-view-list`/`.ct-view-crosstable[data-group]`; localStorage `ct_view_pref`; показывается только при `$hasCrosstable`
- matches-цикл: `$stage->matches->groupBy('group_id')`; `$groupForCross = $stage->groups->firstWhere('id', $groupId)`; `$crossClean = $cleanStatsByGroup[$groupId] ?? []`
- **Блок "Команды" ($teams)**: для нелиговых турниров разделяется по `is_complete`: полные → основной состав, неполные → «⏳ Ищут партнёра» (пунктирная рамка). Лиговые турниры (event.season_id != null) показывают отдельный блок `$leagueTeams` с `$_activeTeams` / `$_reserveTeams`.
- **Два блока для лигового турнира**: на setup одновременно показываются `$leagueTeams` (лига, active/reserve) и `$teams` (все EventTeams по is_complete). Расхождение счётчиков = is_complete не совпадает с league.status. Если организатор добавляет игроков вручную через setup — пересчитать is_complete скриптом.
- **Имена игроков в таблицах**: везде использовать `trim(($m->user->last_name ?? '') . ' ' . ($m->user->first_name ?? '')) ?: '?'` — НЕ только `$m->user->last_name`. Применимо в setup.blade.php, group_crosstable.blade.php, tv.blade.php, public/show.blade.php.
- **Сворачиваемые блоки после жеребьёвки**: `$hasStages = $stages->isNotEmpty()` вычисляется в начале страницы. Блоки «Состав лиги» (`<div id="league-teams-body">`) и «Команды» (`<div id="teams-body">`) — `style="{{ $hasStages ? 'display:none' : '' }}"`, заголовок кликабелен (onclick toggle ▼/▶). Кнопки «Добавить в состав», «Страница сезона», «Синхронизировать» — `@if(!$hasStages)` (не рендерятся вообще).
- **Структура league-block**: кнопки «Добавить в состав», «Страница сезона», «Синхронизировать» находятся ВНЕ `@if($leagueTeams->count())` / `@else` / `@endif` — внутри `<div class="ramka">`. `league-teams-body` закрывается внутри `@if($leagueTeams->count())`, эти кнопки снаружи. Скрывать через `@if(!$hasStages)`, не через CSS.
- **sendTeamToWaitlist**: `TournamentTeamController::sendTeamToWaitlist()` — расформировывает команду и создаёт соло-пары (beach_pair) или добавляет в occurrence_waitlist (classic_team). Маршрут: `POST /events/{event}/teams/{team}/send-to-waitlist`.
- **Хлебная крошка → событие**: `route('events.show', $event)` БЕЗ occurrence ведёт на автовыбор ближайшей будущей occurrence. Всегда добавлять `?occurrence={{ $selectedOccurrence->id }}` — `EventShowService` читает параметр `?occurrence` (не `occurrence_id`!).

## Публичная страница турнира (public/show.blade.php) — вкладка «Результаты»
- Блоки `#match-stats-r-{id}` (статистика матча) и `#match-progress-r-{id}` (лента «ход матча») — оба `class="card mb-2"`, вложены внутрь `.card.p-3` карточки стадии. Собственный паддинг `.card` (2rem по бокам) поверх паддинга родителя визуально сужал оба блока относительно списка матчей над ними (тот — обычные `d-flex`-строки без паддинга, сидят вплотную к краям `p-3`) — исправлено 2026-07-16: единый override `padding-left/right:1.2rem` на оба ID-селектора уравнивает их ширину друг с другом на любом экране.
- **≤600px**: `.ramka` (2rem) + `.card.p-3` (3rem) паддингов родителей = 5rem — на узких экранах даже 1.2rem собственного паддинга оставляет мало места (аватары/имена в ленте, таблицы статистики). Оба блока компенсируют это `margin-left/right:-5rem` (паттерн изначально был только у ленты, см. коммит 52d83861 — при добавлении подобных вложенных карточек в других вкладках проверять по этому же образцу, не трогая `.ramka`/`.card.p-3` глобально).
- Проверка вёрстки без реального браузера: Chrome-бинарь уже установлен в `storage/app/chromium/<version>/chrome-linux64/chrome` (см. раздел «Карточка шаринга матча» ниже) — можно гонять `puppeteer` (в `node_modules`) с `--host-resolver-rules="MAP volley-bot.store 127.0.0.1"` вместо ручного `Host`-заголовка (Chrome запрещает подменять `Host` через `setExtraHTTPHeaders`).

## Рейтинговая система (OpenSkill + Elo)
Полная документация — [claude_docs/claude_rating_system.md](claude_docs/claude_rating_system.md).
- Три параллельных рейтинга: WinRate/Elo/OpenSkill μ,σ (`player_career_stats`) + сезонные Elo/OpenSkill (`tournament_season_stats`) — обновляются после каждого матча (кроме Elo турнирного — при завершении турнира)
- `TournamentOpenSkillService`: `CR = max(0, mu - 3*sigma)`, `INITIAL_MU=25.0`, `INITIAL_SIGMA=8.333`
- **`tournament_matches.stats_processed_at`** — защита от повторной обработки OpenSkill при каждом сохранении счёта (`rebuildAll()` гоняет ВСЕ матчи турнира, не идемпотентно) — обрабатывать только если NULL
- **game_scheme — ЗАПРЕЩЕНО** парсить team_size через regex `/^(\d+)x\d+$/` (даёт неверное число для `4x2`/`5x1`) — читать `event_tournament_settings.team_size_min`
- **registration_mode приоритет**: king_beach > tournament_individual > team_\* (`EventGameSettingsService::normalizeTournamentDefaults()`); `Event::isIndividualRegistrationMode()` = true для tournament_individual И king_beach; формы редактирования могут молча затереть king_beach обратно на team_\* — проверять `$staysKingBeach` перед пересинком
- UI: `/players/rating`, `/players/teams`, `/pages/rating-info`, `/user/{id}` (`PlayerRatingController`)

## Турнирная система v2.1
- Форматы classic: round robin, groups+playoff, single elimination, swiss
- Форматы beach: pool play, king of court, double elimination, thai, swiss
- WinRate на 4 уровнях: матч, турнир, серия, общий
- 8 новых таблиц: tournament_stages, tournament_groups, tournament_group_teams, tournament_matches, tournament_standings, player_tournament_stats, player_career_stats
- Полный план: tournament_plan_final.md (project files)

### Баг: «groups_playoff» — плей-офф не создавался, турнир завершался после групп (исправлен 2026-08-02, event 402)
Полная история расследования — [claude_docs/claude_tournament_bugs.md](claude_docs/claude_tournament_bugs.md).
- **Стадия `groups_playoff` НЕ самодостаточна** — плей-офф всегда отдельная запись `tournament_stages` (`type=single_elim`), иначе `checkStageCompletion()` считает турнир завершённым сразу после группового этапа. Фикс: `TournamentController::createStage()` при создании `groups_playoff` автоматически создаёт парную `single_elim`-стадию (status=pending).
- `advance_per_group=2` (не 1!) нужен для классической схемы «2 полуфинала → финал+3-4место» — иначе получится только финал без матча за 3-е место.
- Для турниров с РОВНО 2 группами есть альтернатива без полуфиналов — прямой кросс-посев по местам (`TournamentBracketService::generateGroupCrossover()`, роут `tournament.stages.advanceCrossover`).

### Финальные дивизионы — выбор формата этапа (RR/single_elim/double_elim), добавлено 2026-08-27
- «Финальные группы по уровням» (Hard/Medium/Lite) после группового этапа теперь имеют ОДИН общий селект формата (`divisions_stage_type`: round_robin/single_elim/double_elim) + `divisions_format` (bo1/bo3) — не по одному на дивизион, т.к. все дивизионы играют в одинаковом формате. Конфиг хранится в `TournamentStage.config` родительской "скелетной" стадии (`finals_mode=divisions`) и копируется в конфиг каждой division-стадии при формировании (`TournamentController::formDivisionsCore()`).
- Bracket-типы (single_elim/double_elim) создаются через `TournamentBracketService::generateSingleElimination()/generateDoubleElimination()` — **эти методы НЕ проставляют `group_id` на матчах**, только `stage_id`; `TournamentGroup`/`TournamentGroupTeam` всё равно создаются (для хранения ростера/seed), но матчи фильтровать по `group_id` для bracket-стадий нельзя.
- `TournamentStatsService::extractBracketPlacements()` — общая логика извлечения мест из bracket-стадии (final+3rd-place, double_elim tiered, + seed-fallback tail для незавершённых мест), используется и в `calculateFinalClassification()`, и в новом `rankedTeamIdsForStage(TournamentStage)` (единая точка получения ранжированного списка team_id для стадии любого типа — round_robin через `TournamentStanding`, bracket через extractBracketPlacements).
- Кнопка «Сформировать группы»/«Сформировать сетку» на setup.blade.php — подпись динамическая, зависит от `$stage->cfg('divisions_stage_type')`.
- **Публичная страница турнира, вкладка «Группы» (`tournaments/public/show.blade.php`)**: для division-стадий типа single_elim/double_elim показывает матчи, сгруппированные по раундам через `TournamentStage::roundLabelFor()` (не пустой standings-блок как раньше) — секции «Верхняя сетка/Нижняя сетка/Гранд-финал» для double_elim (по `bracket_position`/`court`), карточка матча идентична round_robin-блоку (состав, счёт, кнопка 📊 статистики матча).
- Задеплоено на прод 2026-08-27 (см. «Перенос отдельных коммитов dev→prod» выше — потребовался промежуточный коммит `5401e024`, отсутствовавший на проде). Double_elim-ветка секций проверена на синтетических тестовых данных (без реальных player-stats — кнопка 📊 корректно не появляется, т.к. `match_player_stats` пусты).

### Ранжирование в группе (TournamentStandingsService::rankGroup)
1. Победы (rating_points) — desc
2. Набранные очки (clean points_scored) — desc, без матчей против аутсайдеров
3. Разница мячей (clean diff) — desc, без матчей против аутсайдеров
4. Личная встреча (h2h транзитивно среди tied tuple)
5. resolved_order из `tournament_tiebreaker_sets` (новый формат, N команд)
6. Legacy: попарный resolved tiebreaker из `tournament_tiebreakers`
7. Иначе — все команды tuple получают одинаковый rank, создаётся pending `TournamentTiebreakerSet`
- Аутсайдер = команда с 0 побед при played > 0; матчи против неё исключаются из критериев 2 и 3
- `TournamentTiebreakerSet`: 3 варианта resolve: `full_diff` | `match` (RR, points_to_win 1..30, two_point_margin) | `lottery`
- Метод `match`: создаются is_tiebreaker=true матчи; при submitScore последнего — `maybeResolveTiebreakerSet` авторезолвит
- is_tiebreaker=true → матч не учитывается в standings/cleanStats
- В standings показ `чистая / (полная)` если отличаются; аутсайдер — opacity 0.7, подпись «· аутсайдер»

### Thai (тайский формат)

**Статус:** НЕ используется как тип турнирной стадии. Убрать из выбора типа стадии в форме
создания (setup.blade — списки типов / валидация). Как стадия он нефункционален (заглушка).

**Почему TournamentThaiService НЕ удалять при чистке мёртвого кода:** тайский формат нужен
Дмитрию НЕ как стадия «группы → финал», а как будущий РЕЖИМ ВВОДА СЧЁТА — посменное
судейство: очко одной паре → смена пар на площадке → очко другой паре, все команды группы
играют параллельно на одной площадке со сменой после каждого очка. Это отдельная механика
ввода счёта, а не тип стадии.

**Осторожно:** текущий TournamentThaiService генерирует группы как round_robin — это НЕ
реализация посменного судейства, а старая заглушка. Сервис сохраняем как маркер намерения;
будущий режим посменного ввода счёта проектируется отдельно, не обязательно поверх этого
сервиса. Не принимать текущее содержимое сервиса за «почти готовое судейство».

## Лиги и Сезоны
- Иерархия: League (долгоживущая) -> Season (временной период) -> Events (туры/rounds)
- **ВАЖНО: две разные таблицы с похожими названиями:**
  - `leagues` — верхнеуровневая лига (ББЛ Лига, slug, logo, organizer_id); одна на всё время
  - `tournament_leagues` — дивизион ВНУТРИ сезона (id=9 = «Основной» сезона Весна, id=10 = «Основной» сезона Лето); НЕ путать с лигой
  - `tournament_seasons.league_id` → FK на `leagues.id`; `tournament_leagues.season_id` → FK на `tournament_seasons.id`
- Тур (round) = одно событие (Event/EventOccurrence) внутри сезона; привязка через `tournament_season_events.occurrence_id`
- `TournamentSeasonEvent`: season_id + league_id + event_id + occurrence_id — ключевая таблица для поиска правильного дивизиона по туру
- Терминология: в турнире — "Группа A/B/Hard/Medium/Lite", в сезоне — "Дивизион"
- Публичные URL: /l/{leagueSlug}/s/{seasonSlug}
- Контроллеры: LeagueController (CRUD+public+admin), TournamentSeasonController
- Промоушен: TournamentPromotionService; автосоздание: TournamentSeasonAutoCreateService
- **Поиск дивизиона по туру**: всегда через `TournamentSeasonEvent::where('occurrence_id', $occId)->first()` → `league_id`; НЕ через `$event->season->leagues->first()` (вернёт дивизион первого сезона события, даже если тур из другого сезона)
- План дивизионов: season_auto_pipeline_plan.md (project files)

## Система межлигового промоушена

### Архитектура
- `leagues.feeder_league_id` — FK на `leagues.id`; если задан, команды из фидерной лиги могут повышаться в основную
- `tournament_leagues.config` — настройки дивизиона: `eliminate_count`, `eliminate_to` (reserve/feeder/lower_division), `promote_count`, `promote_to` (upper_division/parent_league)
- `tournament_seasons.config` — настройки сезона: `auto_promotion` (bool), `promotion_trigger` (manual/after_tour), `queue_entry_enabled`, `queue_entry_slots`, `feeder_promote_slots`, `relegation_penalty` (saturday_07:00 / sunday_07:00 / monday_07:00)
- `promotion_history` — лог всех перемещений: action, status (completed/pending_confirmation/declined/expired), initiated_by (system/organizer/admin/user)

### TournamentPromotionService — два публичных API
- `process(TournamentSeason, occurrenceId, roundNumber, initiatedBy)` — вызвать после завершения тура; порядок: вылет → внутренний промоушен → из фидера → из очереди; записывает PromotionHistory и отправляет уведомления
- `processEvent(Event)` — legacy-путь для несезонных турниров; вызывается из `TournamentController::checkStageCompletion()` (только для event без season_id)
- `manualMove(season, leagueTeam, toDivision, status, initiatedBy)` — ручное перемещение организатором; пишет историю и уведомляет игрока
- `declineTransfer(leagueTeam, history)` — игрок отказывается от перевода; команда → в конец резерва

### Модели — хелперы
- `League::hasFeeder()`, `feederLeague()`, `parentLeagues()`, `isFeederFor()`
- `TournamentLeague::getEliminateCount()`, `getEliminateTo()`, `getPromoteCount()`, `getPromoteTo()`, `upperDivision()`, `lowerDivision()`
- `TournamentSeason::getPromotionTrigger()`, `isQueueEntryEnabled()`, `getFeederPromoteSlots()`, `getRelegationPenalty()`, `nextSeasonEvent()`

### Роуты управления промоушеном
| Роут | Метод | Описание |
|---|---|---|
| `POST /seasons/{season}/promote` | `seasons.promote` | Выполнить промоушен вручную |
| `POST /seasons/{season}/teams/{lt}/relegate` | `seasons.teams.relegate` | Вылет (reserve/feeder/lower_division) |
| `POST /seasons/{season}/teams/{lt}/transfer` | `seasons.teams.transfer` | Перевод в другой дивизион |
| `POST /seasons/{season}/teams/{lt}/activate` | `seasons.teams.activate` | Активация из резерва |
| `POST /promotions/{promotionHistory}/decline` | `promotions.decline` | Игрок отказывается от перевода |

### Кнопка "Выполнить промоушен" на seasons/edit
- Показывается если: есть хотя бы один `seasonEvent.status='completed'` И `season.isAutoPromotion()=false` И `occurrence_id` заполнен
- Передаёт `occurrence_id` и `round_number` последнего завершённого тура

### Отказ от перевода (игрок)
- Доступен 7 дней после перемещения (`promotion_history.created_at >= now()-7d`)
- Только для action: `relegated_to_feeder`, `promoted_to_upper`, `promoted_to_parent`
- Баннер показывается на публичной странице сезона (`seasons/show.blade.php`) для авторизованных

### Уведомления (тип promotion)
- Отправляются через `UserNotificationService::create()` из `sendPromotionNotification()`
- Код шаблона: `promotion`; каналы: in_app, telegram, vk, max
- Вызывается в: eliminateTeam, promoteTeam, promoteFromFeeder, fillFromQueue, manualMove
- Также: `reserve_spot_offered` (игрок из резерва лиги) и `reserve_spot_offered_organizer` — в `TournamentLeagueService`
- Все три кода есть в notification_templates (is_active=false — содержание задаётся динамически)

### getStandingsForRound — важный нюанс
- `tournament_standings.team_id` (не user_id!) — по нему ищем TournamentLeagueTeam
- standings берутся из **последней стадии** (orderByDesc sort_order) события данного occurrence_id
- Если occurrence не привязан к дивизиону через TournamentSeasonEvent — возвращает пустую коллекцию (не ошибка)

## Система абонементов и купонов
- Модели: SubscriptionTemplate, Subscription, SubscriptionUsage, CouponTemplate, Coupon
- Сервисы: SubscriptionService, CouponService
- Jobs: CheckExpiredSubscriptions, AutoBookingSubscriptionJob, AutoUnconfirmBookingJob
- Колонки в event_registrations: subscription_id, coupon_id, confirmed_at, auto_booked

## Уведомления организаторов (каналы)
- Привязка через ProfileNotificationChannelController; платформы: telegram, vk, max
- Telegram: группы/супергруппы/каналы; форум-темы: сохраняет message_thread_id в channel meta
- VK: беседы (peer_id >= 2000000000), bind_TOKEN в чат
- Таблицы: user_notification_channels, channel_bind_requests, event_notification_channels, event_channel_messages, channel_publish_logs
- Анонсы: PublishOccurrenceAnnouncementService -> OccurrenceAnnouncementMessageBuilder
- При создании event: анонс сразу для первой occurrence с `registration_starts_at <= now()` (EventStoreService.php:617)
- Будущие occurrences: `events:publish-pending-announcements` (routes/console.php, каждую минуту)
- При записи/отписке: `RefreshOccurrenceAnnouncementJob`; повторные publish() с тем же hash → skip
- **VK-анонсы статичны**: `VkChannelPublisher`/`VkWallPublisher` — `supportsUpdate()=false` и `supportsDelete()=false` у ОБОИХ (проверено 2026-07-17). Обновление постов работает только для Telegram и MAX — ни изменение мест/состава (`publish()` refresh), ни отмена (`markCancelled()`), ни финализация (`markFinalized()`) никогда не редактируют/не удаляют VK-сообщение; для VK это осознанно пропускается с логом (`cancel_update_not_supported`/`finalize_update_not_supported`), не баг.
- **`RefreshOccurrenceAnnouncementJob` не дёргался из «Управление регистрациями» (исправлено 2026-08-28, событие 421/occurrence 12526)**: `EventRegistrationsManagementController::addPlayer/updatePosition/cancel/destroy` пишут состав напрямую через `DB::table()->insert/update/delete()`, минуя `EventRegistrationController::persistRegistration()` (единственное место, где раньше был диспатч). Организатор добавляет игроков вручную со страницы регистраций → анонс в Telegram/VK застревает на составе на момент ПЕРВОЙ регистрации, дальнейшие добавления/отмены/смены позиции в чат не попадают, пока не сработает что-то другое (следующая самостоятельная запись игрока). Фикс — общий приватный `dispatchAnnounceUpdate(eventId, occurrenceId)` (тот же hasChannels-гейт, что в `EventRegistrationController`/`TournamentTeamController`/`EventManagementController`), вызывается после каждой успешной мутации во всех 4 методах.
- **Rich Messages в Telegram-анонсах (Bot API 10.1+, добавлено 2026-08-28)** — коллаж из нескольких фото (`event_photos`, до 10) и сворачиваемый `details`-блок для списка игроков/команд, вместо одного фото + плоского текста с обрезкой по `MAX_CAPTION_LENGTH=1024` (`TelegramChannelPublisher::truncateCaption`). Только Telegram — VK/MAX не тронуты (публикуют как раньше, новые поля `ChannelMessageData` игнорируют).
  - Схема блоков подтверждена ВЖИВУЮ реальными вызовами `sendRichMessage`/`editMessageText` (документация `core.telegram.org/bots/api` слишком велика для полной автоматической выгрузки, вторичные источники давали неполные/неточные детали): `{"type":"photo","photo":{"type":"photo","media":url}}`, коллаж — `{"type":"collage","blocks":[{"type":"photo","photo":{...}}, ...]}`, список — `{"type":"details","summary":"...","blocks":[{"type":"paragraph","text":"..."}],"is_open":false}`.
  - **Rich-параграф НЕ парсит HTML** (в отличие от `parse_mode=HTML` у classic `sendPhoto`/`sendMessage`) — `<b>title</b>` показывался бы буквально с тегами. Заголовок — отдельный bold-узел `{"type":"paragraph","text":{"type":"bold","text":title}}`, не HTML-строка. `TelegramChannelPublisher::buildRichMessagePayload()` отрезает известный HTML-префикс `<b>{title}</b>\n\n` от `textShort` перед вставкой в paragraph.
  - `ChannelMessageData` — новые опциональные поля: `imageUrls` (все фото для коллажа, `imageUrl`=первое — фолбэк для VK/MAX/classic-sendPhoto), `listTitle`/`listText` (список отдельно от текста — details-блок), `textShort` (текст БЕЗ списка, только для rich-параграфа — иначе список дублируется: раз в паре with $text, раз в details). `$text` всегда содержит список целиком (VK/MAX и Telegram-фолбэк при ошибке rich не теряют список).
  - Rich включается (`TelegramChannelPublisher::isRichEligible()`) когда `count(imageUrls)>1 ИЛИ listText непусто` — то есть практически ЛЮБОЙ анонс с хотя бы одним зарегистрированным игроком уходит как rich (список появляется при `regCount>0`), не только «большие составы». Проверено: пустой состав + 1 фото → `message_kind='photo'` (старое поведение не тронуто).
  - **Фолбэк при сбое** — `send()`/`update()` оборачивают вызов `sendRichMessage`/`editMessageText(rich_message)` в try/catch; при ЛЮБОЙ ошибке (в т.ч. транзиентный `cURL error 28` — воспроизведено вживую при тестировании) откатываются на classic `sendPhoto` (с полным `$text`, список внутри, старая обрезка по 1024) — анонс не пропадает, просто временно без коллажа/сворачивания.
  - `update()`: rich↔rich — `editMessageText` с параметром `rich_message` (не `text`, они взаимоисключающие). Смена kind (включая `null→rich`, когда `originalKind` неизвестен) — delete+resend, как и раньше для photo↔text.
  - Лимит `buildPlayerList()`/`buildTeamList()` поднят с 30 до 100 строк — rich-details спокойно тянет весь текст в рамках 32 768-символьного бюджета rich-сообщения.
- **`telegram_user_id` теперь сохраняется из вебхука `/start notify_<token>` (добавлено 2026-08-28)**: поле валидировалось как `required` в `TelegramNotifyWebhookController::complete()`, но никогда не писалось в БД (только `telegram_notify_chat_id`). Новая колонка `users.telegram_user_id` (string, nullable, index — НЕ unique, по прецеденту `telegram_notify_chat_id`). Это ТОЛЬКО фундамент на будущее (например, Ephemeral Messages, Bot API 10.2+, `receiver_user_id`) — саму фичу персональных сообщений в групповых чатах не делали: нет связи «игрок state в конкретной группе организатора», `telegram_id` (OAuth-логин, единственный queryable numeric id сегодня) покрывает лишь ~21% пользователей и никак не привязан к членству в группе. `TelegramAuthController`/`telegram_id`/логика логина не тронуты.

### Пометка отмены/удаления в канале (feature, добавлено 2026-07-09)
- **Сценарий A (occurrence отменена, is_cancelled=true, ещё существует)**: `PublishOccurrenceAnnouncementService::markCancelled()` — редактирует уже отправленный пост (пересобирает актуальный текст через `builder->build()`, приклеивает баннер `«❌ ОТМЕНЕНО 😢»` сверху). Диспатчится через `MarkAnnouncementCancelledJob::dispatch($occurrenceId)->onQueue('default')->afterCommit()` из **6 мест** отмены occurrence: `EventManagementController::destroyOccurrence()` (single), `update()` (с активными регистрациями), `destroy()` (mode=series и default/cancel), `bulkDelete()` (cancel mode), `CancelEventsByQuorum` (автоотмена по недобору). **5 из 6 путей используют `DB::table()->update()`**, не Eloquent — Observer на `EventOccurrence` НЕ сработал бы для них, поэтому диспатч явный в каждой точке, не через модельные события.
- **Сценарий B (occurrence/event физически удалены)**: `PublishOccurrenceAnnouncementService::deletePosts(array $messages)` — принимает **plain-array примитивов** (`event_id`,`occurrence_id`,`channel_id`,`platform`,`external_chat_id`,`external_message_id`,`event_title`,`starts_at_text`), НЕ Eloquent-модели и НЕ occurrence — к моменту выполнения джоба `event_channel_messages` уже физически удалена каскадом (`event_occurrences.event_id` и `event_channel_messages.occurrence_id`/`event_id` — все три `cascadeOnDelete()`). Паттерн: `collectChannelMessagesForOccurrences()`/`collectChannelMessagesForEvent()` (приватные хелперы в `EventManagementController`) собирают массив **ДО** `->delete()`, затем `DeleteChannelPostsJob::dispatch($messages)->afterCommit()` — 3 точки: `destroyOccurrence()` force-режим, `update()` (occurrences без регистраций — физически удаляются как мусор), `safeForceDeleteEvent()` (каскад на всё событие).
- **`deletePosts()` — цепочка фоллбеков**: пробует `publisher->delete()` → при неудаче (или `!supportsDelete()`) откатывается на `update()` с УПРОЩЁННЫМ текстом (оригинал недоступен, occurrence уже физически удалена — только title+дата, собранные ДО delete) → при неудаче и этого — `Log::warning`, без исключений наружу (удаление события не должно ломаться из-за канала).
- **`ChannelPublisher` — добавлены `delete()`/`supportsDelete()`** в интерфейс. Матрица поддержки: Telegram — `true`, реально работает (Bot API `deleteMessage`, бот с правом `can_delete_messages` в канале может удалить своё сообщение без 48ч-лимита — тот лимит про чужие сообщения в обычных чатах). MAX — `true`, но НЕ проверено на реальном канале (по аналогии с REST-style `update()`: `PUT .../messages?message_id=`, для delete — `DELETE` на тот же путь; **у MAX редактирование ограничено 24 часами** — после этого `update()` тоже упадёт, fallback на warning будет частым, не редким случаем). VK (`VkWallPublisher`, `VkChannelPublisher`) — `false`, `wall.delete` с community-токеном даёт тот же error 27, что и `wall.edit` (см. `reference_vk_publishing_limits.md` в памяти) — метод реализован (на случай user-token в будущем), но отключён.
- **Сценарий C**: `PublishOccurrenceRegistrationOpenJob::handle()` проверяет `is_cancelled`/`cancelled_at` перед `publish()` — джоб мог встать в очередь ДО отмены (диспетчер `events:publish-pending-announcements` фильтрует на момент постановки, не на момент выполнения), без проверки анонс ушёл бы для уже мёртвого события.
- **Тестирование без реальных вызовов внешних API**: `Http::fake([...])` перехватывает Telegram/MAX HTTP-клиенты — безопасно на dev, где настроены РЕАЛЬНЫЕ боевые каналы (`user_notification_channels` с реальным `TELEGRAM_BOT_TOKEN`). `Http::assertSent()`/`assertNothingSent()` требуют PHPUnit `Registry` (падают вне реального теста) — вне PHPUnit использовать `Http::recorded()` (просто данные, без ассертов) и проверять вручную.
- **`Http::fake()` — "залипание" при повторном вызове для того же URL-паттерна**: если паттерн один раз выбросил исключение через closure/`Http::failedConnection()` ИЛИ вернул ответ, на котором сработал `->throw()`, **повторный** `Http::fake([тот же паттерн => новый ответ])` в ТОМ ЖЕ PHP-процессе не перекрывает — старое поведение (включая исключение) продолжает срабатывать. Подтверждено экспериментально (Laravel 12), не зависит от способа фейка (closure и `Http::response()` с ошибочным статусом — оба залипают одинаково). Для тестов сценария "сначала упало, потом получилось" — либо разносить по отдельным PHP-процессам, либо готовить "уже упавшую" запись прямым INSERT в БД и держать в процессе только ОДИН `Http::fake()` (для успешного ретрая).

### Финализация анонсов при завершении мероприятия (feature, добавлено 2026-07-17)
- **Сценарий D**: occurrence реально завершилась (`starts_at + duration_sec < now`) — правим уже опубликованный пост: кнопка «Записаться!» → «🏁 Мероприятие завершено» (`events.channel_announcement_finalized_button`), строка «Осталось мест: X из Y» / «Команд: X/Y» → «🏁 Мероприятие завершено!» (`events.channel_announcement_finalized_line`). Ссылка кнопки не меняется — по-прежнему ведёт на страницу события (там результаты/статистика турнира).
- **`OccurrenceAnnouncementMessageBuilder::build()`** принимает опцию `finalized` (bool) — меняет только buttonText и итоговую строку в блоке «Мест/Команд»; подсчёт `$registered`/`$teamsRegistered`/`reserveTeamIds` не пропускается (нужен дальше для секции «Список игроков/команд»).
- **`PublishOccurrenceAnnouncementService::markFinalized()`** — по образцу `markCancelled()`: ранний return если `$occurrence->isCancelled()` (у отмены своя семантика, не пересекается с финализацией). Идемпотентность — на уровне записи: реальная колонка `event_channel_messages.announcement_finalized_at` (не meta-флаг, в отличие от `cancelled_marked_at` у markCancelled) — тот же паттерн, что `reminded_24h_at`/`reminded_2h_at` у court bookings. Публикатор без `supportsUpdate()` (VK) — пропускается с логом `finalize_update_not_supported`.
- **Регрессия, которую поймали при тестировании**: `publish()` (обычный refresh-триггер при записи/отмене) НЕ знал о финализации — если что-то дёргает `RefreshOccurrenceAnnouncementJob` для уже завершённого и финализированного occurrence (например, организатор отменяет чью-то регистрацию задним числом на прошедшее мероприятие), пересобранный текст (без `finalized=true`) даёт новый hash → перезаписал бы кнопку обратно на «Записаться!». Добавлен guard в начале блока `if ($record->exists)`: `if ($record->announcement_finalized_at !== null) { log('skip','already_finalized'); continue; }` — ДО проверки hash.
- **Команда `events:finalize-announcements`** (`everyFiveMinutes`, routes/console.php) — ищет `event_channel_messages` с `announcement_finalized_at IS NULL` у occurrences, где `starts_at + make_interval(secs => duration_sec) <= now()`, не отменённых, и **в пределах `config('channels.finalize_announcement_max_age_hours')`** (default 6ч, `config/channels.php`) — без этого cutoff первый же деплой начал бы редактировать сотни старых постов разом (Telegram rate limits + странно выглядит подписчикам). Диспатчит `MarkAnnouncementFinalizedJob` по одному на occurrence (все каналы обрабатывает сервис внутри), как и `CancelEventsByQuorum`/`MarkAnnouncementCancelledJob`.
- **Ловушка при тестировании через queue worker**: класс сервиса добавлен, но уже запущенный `queue:work` процесс (`appuser ... queue:work database --queue=default,broadcasts`) держит СТАРЫЙ снимок классов в памяти (не только opcache PHP-FPM, тот же эффект у любого long-running PHP-процесса) → `Call to undefined method markFinalized()` при первом прогоне джобы, несмотря на то что метод реально есть в файле. Фикс — `php artisan queue:restart` (не требует sudo, посылает graceful-сигнал; supervisor сам перезапускает воркер новым процессом) ПОСЛЕ добавления новых методов в сервисы, которые вызываются из уже смёрженных в очередь джобов.

## Ретрай неудачных доставок уведомлений (feature, добавлено 2026-07-09)
- **Проблема**: `NotificationDeliverySender::sendById()` ловит ошибку канала внутри себя (`markFailed()`) и не пробрасывает исключение — `SendNotificationDeliveryJob` (обёртка) считается "успешно выполненным" с точки зрения очереди → Laravel-ретрай (`tries=3`) никогда не срабатывает, `failed_jobs` остаётся пустым, транзиентный сетевой сбой (cURL 28/7/6, 5xx) съедает уведомление навсегда.
- **`notification_deliveries`** — новые поля: `attempts` (int, default 0), `next_retry_at` (timestampTz, nullable), `is_retryable` (boolean, nullable — null=ещё не классифицировано, актуально для строк ДО миграции). `dedupe_key` (unique) остался как есть — ретрай **обновляет существующую строку**, не создаёт новую.
- **`NotificationDeliverySender::classifyRetryable(string $error): bool`** — единая точка классификации. `cURL error \d+` в начале строки → транзиент (сеть/соединение, ответ от API не получен вообще). Известные постоянные HTTP-отказы (`chat not found`, `bot can't initiate conversation`, `user is deactivated`, `bot was blocked by the user`, `chat.denied`, `dialog.suspended`) → постоянная. Неизвестное → транзиент (безопаснее дать пару попыток, чем молча похоронить нераспознанную ошибку).
- **`attempts` считает только РЕТРАИ, не исходную попытку**: `sendById(int $deliveryId, bool $isRetry = false)` — исходный вызов из `SendNotificationDeliveryJob` идёт с `isRetry=false` (attempts не трогается, остаётся 0), вызовы из `notifications:retry-failed` — с `isRetry=true` (attempts инкрементится). Backoff по НОВОМУ значению attempts: 0→+1мин, 1→+5мин, 2→+30мин; на attempts=3 (третий по счёту ретрай тоже упал) — исчерпано, `next_retry_at=null`, `Log::warning`. Итого 1 исходная + 3 ретрая = 4 попытки на доставку.
- **`notifications:retry-failed`** (`everyFiveMinutes`, `withoutOverlapping`): выборка `status='failed' AND is_retryable=true AND attempts<3 AND next_retry_at<=now() AND created_at > now()-N часов` (потолок `config('notifications.retry_max_age_hours')`, default 6 — уведомление "игрок записался" через сутки бессмысленно). Для каждой — `$sender->sendById($id, isRetry: true)` (сам умеет `status IN (pending,failed)`, логику отправки дублировать не нужно). `--dry-run`.
- **Деактивация канала при постоянной ошибке** — НЕ трогаем `telegram_id`/`vk_notify_user_id`/`max_chat_id` (это устойчивые идентификаторы, бот может быть разблокирован пользователем в любой момент, ID при этом не меняется). Вместо этого — булев флаг `users.telegram_notifications_enabled`/`vk_notifications_enabled` (новые, default true) + уже существовавший `max_notifications_enabled` (заводился при бинде/анбинде MAX, но **никогда не читался как гейт** — декоративный баг, теперь исправлен). Все три подключены в `UserNotificationService::normalizeChannels()`.
- **Открытый вопрос (не решён)**: выход из блокировки флага не автоматизирован — боты на голый `/start` без payload не дёргают бэкенд, хука сброса флага нет. Сброс только вручную через БД (`UPDATE users SET telegram_notifications_enabled=true WHERE id=...`, аналогично для `vk_`/`max_`). Варианты на будущее: доработать `bot.py` под голый `/start`, кнопка «Проверить уведомления» в профиле, или периодический ре-тест.
- **Баг (исправлен): `sendTelegram()`/`normalizeChannels()` слали на `telegram_id` (OAuth-логин) вместо `telegram_notify_chat_id` (реальный chat_id, появляется только после `/start notify_<token>`)** — Telegram не разрешает боту первым писать пользователю без диалога → гарантированный `chat not found`. VK/MAX уже были корректны. UI и раньше показывал реальный статус подключения верно — баг был чисто в бэкенд-гейте отправки.
- Деактивация каналов не применяется задним числом к уже существующим `failed`-записям — флаг выставляется только при НОВОЙ постоянной ошибке.

## Рассылка организатора участникам (broadcast)
- Роуты: `GET/POST /events/{event}/registrations/broadcast` → `EventRegistrationsManagementController::broadcastForm/broadcastSend`
- Кнопка 💬 на `/events/registrations/manage` (overview.blade.php), только для не-турнирных
- Job: `BroadcastToRegistrantsJob` (очередь `broadcasts`, tries=3, backoff=30); отправляет через `UserNotificationService::create()` с type=`organizer_broadcast`
- Фильтр получателей: `is_cancelled=false AND cancelled_at IS NULL AND status='confirmed'`; резерв = `position='reserve'` (чекбокс include_reserve, по умолчанию вкл)
- Охват (tg/vk/max/push) считается по $userIds **до** dispatch() — фиксируется в flash и показывается в отчёте
- `is_active=false` для шаблона organizer_broadcast — create() уходит в fallback и использует переданные title/body
- Blade: `resources/views/events/registrations_broadcast.blade.php` — стиль сайта (ramka/card/btn/checkbox-item), NOT Tailwind

## Шаблоны уведомлений (notification_templates)
- Таблица `notification_templates`: code (unique), channel (nullable = общий), name, title_template, body_template, is_active
- Сервис: `NotificationTemplateService` — `findActiveTemplate(code, channel)`; рендер: `NotificationTemplateRenderer`
- Страница управления: `/admin/notification-templates` → `AdminNotificationTemplateController`
- `is_active=false` + пустые шаблоны = контент задаётся динамически в коде (шаблон не применяется)
- **Добавление нового типа**: 1) создать миграцию по образцу `2026_05_28_100001_add_followed_and_waitlisted_notification_templates.php`; 2) добавить code в `$groups` массив в `resources/views/admin/notification_templates/index.blade.php`
- **Аудит**: типы уведомлений в коде — `UserNotificationService` (игроки), `TournamentNotificationService` (турниры), `NotifyOrganizerWaitlistJob` + `NotifyOrganizerRegistrationJob` (организаторы); сравнивать с `DB::table('notification_templates')->pluck('code')`
- Текущие группы на странице: Регистрация, Лист ожидания, Приглашения, Мероприятия, Платежи, Турниры, Лиги и сезоны, Социальное, Уведомления организатору, Администрирование

## Laravel 12 schedule
- ВСЕ scheduled команды в `routes/console.php` (`Schedule::command(...)`), НЕ в Console/Kernel.php
- `bootstrap/app.php` → `->withSchedule(...)` — наследие (одна команда), новые туда НЕ добавляем
- Проверка: `php artisan schedule:list`
- **Паттерн dedupe для повторяющихся команд**: если команда запускается часто (каждые 5 мин) и выбирает записи по временному окну, НЕ использовать бизнес-поле (scored_at, completed_at) как флаг «уже обработано» — добавлять отдельную колонку `notified_*_at` (nullable timestamp). Пример: `tournament_matches.notified_upcoming_at` — команда `tournament:notify-upcoming` фильтрует `whereNull('notified_upcoming_at')`, после отправки записывает `now()`. Без этого одна запись попадает в каждый запуск пока окно перекрывается.

## Платежи и кошельки
- PaymentService — создаёт Payment при записи (методы: cash, online, wallet)
- YookassaService — онлайн-платежи, вебхук: YookassaWebhookController
- VirtualWallet — wallet_id per (user_id, organizer_id), баланс в минорных единицах (balance_minor / 100)
- WalletTransaction — типы: credit / debit; PaymentSetting — payment_hold_minutes (дефолт 15)
- OrganizerSubscriptionService — подписки организаторов (не путать с абонементами игроков)

## Премиум-подписки игроков
- Модель: PremiumSubscription, сервис: PremiumService
- Планы: trial (7д), month (30д), quarter (90д), year (365д)
- Поля уведомлений: weekly_digest, notify_level_min/max, notify_city_id
- Контроллер: PremiumController, настройки: PremiumSettingsController

### Авто-запись на мероприятия (Premium, добавлено 2026-08-24)
- Модель `PremiumAutoBooking` (таблица `premium_auto_bookings`: user_id, event_id, position; unique(user_id,event_id)) — «джоб»: привязан к СЕРИИ (event_id), не к конкретному occurrence, срабатывает при открытии регистрации на КАЖДЫЙ будущий тур, пока не удалён. До 5 штук на пользователя (`PremiumAutoBookingController::MAX_JOBS`).
- Доступны только индивидуальные форматы (обычные + `tournament_individual` + `king_beach`); `team_classic`/`team_beach` исключены и в поиске, и в `store()` (там своя логика формирования команд, не подходит под простую позицию).
- UI — новый блок на `/premium/settings` (`premium/settings.blade.php`): поиск мероприятия по ID или названию локации (`GET /premium/auto-bookings/search-events`, эталонный autocomplete-паттерн + учёт приватности через `EventVisibilityService`), позиции подтягиваются вместе с результатами поиска (без отдельного запроса) и заполняют `<select>` через `createCustomSelect()` destroy+recreate (CLAUDE.md — перезаполнение `<option>` не подхватывается кастомной обёрткой само по себе).
- При создании (`store()`) — `EventRegistrationGuard::checkStaticEligibility()` (новый публичный метод, БЕЗ проверки окна регистрации/занятости — окно ещё не открыто, это нормально) проверяет возраст/личные данные/уровень/жёсткий гендерный допуск позиции по ближайшей будущей occurrence серии. Не проходит — джоб не сохраняется, ошибка пользователю.
- Триггер — `premium:auto-booking` (`everyFiveMinutes`, тот же паттерн окна `[registration_starts_at ∈ now-5мин..now]`, что и `subscriptions:auto-booking`) → `PremiumAutoBookingJob($occurrenceId)`. Бронирование — advisory lock (`pg_advisory_xact_lock(occurrence_id, crc32(position))`) + `EventRoleSlotService::tryTakeSlot()`, как в `EventRegistrationController::persistRegistration()` (в отличие от `AutoBookingSubscriptionJob`, который эту защиту не использует). Платные мероприятия — обычная логика `PaymentService::createForRegistration()`, оплата отдельно от подтверждения участия.
- **Приоритет абонемента**: `SubscriptionService::hasUsableAutoBookingSubscription()` — если у игрока есть активный абонемент с авто-записью на это же мероприятие, `PremiumAutoBookingJob` пропускает пользователя (не бронирует) независимо от порядка выполнения команд `subscriptions:auto-booking`/`premium:auto-booking` в один тик планировщика — абонемент отрабатывает свою же job.
- **Подтверждение участия за 5 часов**: `event_registrations.premium_auto_booking_id` (FK, nullOnDelete) + `premium_auto_confirm_deadline_at` (= `now()+5h` на момент бронирования, НЕ привязано к `created_at` — важно при реактивации старой отменённой регистрации, где `created_at` был бы датой из прошлого). Подтверждение — существующий генерик-роут `POST /registrations/{registration}/confirm` (`routes/web.php`, уже использовался для абонементов), без изменений. UI подтверждения — блок на `events/show/players.blade.php` (`@elseif($myReg->premium_auto_booking_id && !$myReg->confirmed_at)`, по образцу подписочного блока рядом).
- Команда `premium:expire-auto-bookings` (`everyFiveMinutes`) отменяет неподтверждённые (`confirmed_at IS NULL`, `premium_auto_confirm_deadline_at <= now()`) через Eloquent `save()` (не Query Builder — иначе `EventRegistrationObserver` не вызовет `WaitlistService::onSpotFreed()`), уведомляет игрока.
- **Баг (исправлен 2026-08-30): игрока выписывало повторно после самостоятельной перезаписи**. `EventRegistrationController::persistRegistration()` при `$existing` (реактивация СТАРОЙ, ранее отменённой строки `event_registrations` для того же `user_id+occurrence_id` — обычный путь при повторной самостоятельной записи) сбрасывал только `status/is_cancelled/cancelled_at/position/payment_*`, но НЕ трогал `premium_auto_booking_id`/`premium_auto_confirm_deadline_at`/`confirmed_at`/`subscription_id`/`subscription_usage_id`/`auto_booked`/`coupon_id`/`coupon_discount_pct`, оставшиеся от ПРЕДЫДУЩЕЙ жизни этой же строки (авто-запись премиум/по абонементу). Сценарий: игрок не подтвердил премиум-авто-запись → `premium:expire-auto-bookings` отменил регистрацию → игрок сам записался снова (реактивировал ту же строку) → `premium_auto_booking_id`/просроченный `premium_auto_confirm_deadline_at` остались висеть → следующий прогон `premium:expire-auto-bookings` (каждые 5 мин) тут же матчил эту строку заново (`is_cancelled` уже `false`, `confirmed_at` всё ещё `null`, дедлайн в прошлом) и отменял её повторно — хотя игрок записался сам, без всякой авто-записи. Фикс — в блоке `if ($existing)` явно обнулять все 8 полей авто-записи/абонемента/купона перед `save()`, трактуя реактивацию как полностью новую самостоятельную запись. **Не исправлено попутно** (отдельная, более мелкая проблема, не была в жалобе пользователя): если игрок при такой реактивации всё же передаёт `subscription_id`/`coupon_code` (форма записи это умеет), логика применения абонемента/купона (`Приоритет 1/2/3`) целиком находится в ветке создания НОВОЙ строки (после `return;` для `$existing`) — на реактивированной строке абонемент/купон молча не применится.
- `PremiumService::expireAll()` при истечении подписки удаляет и `PremiumAutoBooking` (по образцу уже существовавшего удаления `PlayerFollow`) — фича только для активного премиума.
- i18n — новый namespace `lang/{ru,en}/premium.php` (`auto_booking_*` ключи); confirm-текст — `events.sp_confirm_premium` (RU+EN, соседние `sp_confirm_subscription`/`sp_confirm_or_cancel`/`sp_btn_confirm`/`sp_confirmed` были локализованы только на RU — не трогал, не в скоупе).

## Лист ожидания (Waitlist)
- Модель: OccurrenceWaitlist (таблица occurrence_waitlist), сервис: WaitlistService
- Триггер: EventRegistrationObserver::deleted/updated → WaitlistService::onSpotFreed
- **Индивидуальная регистрация (НЕ турниры)** — `autoBookNext`: АВТОМАТИЧЕСКИ записывает первого подходящего; очередь: премиум первыми, затем по `sort_order`, затем `created_at`
  - **КРИТИЧНО**: PostgreSQL не поддерживает FOR UPDATE на nullable стороне LEFT JOIN → использовать EXISTS subquery вместо leftJoin для сортировки по premium
  - Лимит: 20 итераций на один вызов; при превышении — warning в лог
  - Платные: PaymentService::createForRegistration; **releaseExpired использует Eloquent save()** (не Query Builder) — иначе Observer не срабатывает
  - auto_booked=true в event_registrations, поле НЕ в $fillable — через свойства+save
- **Турниры** — старая логика (`notifyNext` + CheckWaitlistNotificationJob); UI записи в waitlist на турнир заблокирован
- **checkWaitlistGate** (`EventRegistrationGuard`): блокирует только если в очереди есть участник, который РЕАЛЬНО может занять одну из свободных основных позиций:
  - Проверяется пересечение `waitlist.positions` (empty = любая) с `freeMainKeys` (free_positions без reserve)
  - При `mixed_limited`: применяется гендерный фильтр — если ограниченный пол не может взять ни одну свободную позицию, он не считается блокирующим
  - **Гендерное окно**: если окно ограниченного пола ещё не открылось (`genderWindowOpensAt` > now) — этот участник не считается блокирующим, место доступно остальным. Вычисляется из `occurrence.starts_at - gender_limited_reg_starts_days_before`
  - Если `hasBlockingOthers=false` → gate не блокирует, даже при наличии людей в очереди
  - Если `hasBlockingOthers=true` и есть свободный reserve → разрешает только reserve (waitlist_only не ставится)
  - Если `hasBlockingOthers=true` и reserve нет → `waitlist_only=true`, ошибка; турниры пропускаются
  - **Типичный баг**: autoBookNext не нашёл никого для освободившегося слота (гендер/позиции), место осталось пустым, но очередь не очищается → gate некорректно блокировал. Фикс: смотреть в checkWaitlistGate на eligibility, а не просто на exists()
- **WaitlistService::join() — autoBookNext при вступлении**: после добавления в очередь запускается autoBookNext для каждой основной позиции (setter/outside/...) где слот уже свободен. Аналогично reserve (существовало раньше). Если у вступившего закрыто гендерное окно — autoBookNext пропустит его (not eligible); авто-бук произойдёт при открытии окна через ProcessWaitlistGenderWindows
- **sort_order** (колонка occurrence_waitlist): порядок в очереди, управляемый организатором; при join() присваивается max+1; при ручной расстановке (↑↓) — swap с соседом. autoBookNext сортирует: premium → sort_order → created_at
- **Управление листом ожидания организатором** (`EventWaitlistManagementController`): добавить игрока (autocomplete + позиции), удалить, изменить позиции (+ autoBookNext для свободных слотов), переместить ↑/↓. Маршруты: `/events/{event}/waitlist/management/...`. Блок на странице `/events/{id}/registrations?occurrence=X`
- **Race condition — advisory lock, единая точка правды**: реальная защита от гонки на последний слот — `pg_advisory_xact_lock(occurrence_id, roleKey)`, где `roleKey = $position ? (crc32($position) & 0x7fffffff) : 0`. Лок берётся в ДВУХ местах, формула ОБЯЗАНА совпадать дословно, иначе локи не встретятся: `EventRegistrationController::persistRegistration()` (прямая запись — лок первым, до `lockForUpdate()` на `event_registrations`) и `WaitlistService::autoBookNext()` (лок первым, до `lockForUpdate()` на `occurrence_waitlist`; `$position` — один на весь вызов, из `onSpotFreed()`, НЕ меняется по кандидатам в цикле). `EventRoleSlotService::tryTakeSlot()` сам по себе НЕ содержит лока — вся защита на вызывающей стороне (докстринг метода это прямо говорит); `taken_slots` в защите никогда не участвовал (чисто зеркало живого COUNT) и с 2026-07-16 больше не пишется вообще — см. раздел «Выпил кеш-счётчиков» ниже.
- **Пляжка — `event_role_slots` тоже используется**: у пляжки есть роль `role='player'` (не только у классики setter/outside/...) — форма записи шлёт `position='player'`, значит прямая запись на пляжку идёт через ТОТ ЖЕ `tryTakeSlot()` + advisory lock, что и классика. Не путать с `WaitlistService::autoBookNext()`'s beach-веткой — та считает вместимость отдельным inline COUNT против `max_players` (не через role slots), и до фикса не имела лока вообще.
- **Баг (исправлен): `autoBookNext()` не брал `pg_advisory_xact_lock`** — синхронизировался с другими вызовами `autoBookNext()` через `lockForUpdate()` на `occurrence_waitlist`, но НЕ с прямой записью игрока (`persistRegistration()`), которая использует другой ресурс. Оба пути могли одновременно пройти проверку вместимости на последний слот. Фикс: тот же advisory lock, той же формулой, первым в транзакции (до `lockForUpdate()` на waitlist) — единый порядок захвата исключает дедлок между путями. Плюс defensive-проверка вместимости в `persistRegistration()` для пляжки на случай пустого `position` (сегодня веб-форма всегда шлёт `player`, но API/мобильное приложение могут этого не повторить) — под тем же locком (`roleKey=0`), живой COUNT против `max_players`, не разделяя reserve и основной пул (так же, как это делает `EventRegistrationGuard::checkCapacityAndPositions()` для beach — `reserve` там не исключается из общего числа).
- **Тестирование гонок**: обычная транзакция+rollback НЕ подходит (одно подключение = одна очередь запросов, гонки физически нет). Рабочий паттерн — `proc_open()` из runner-скрипта запускает N воркеров как ОТДЕЛЬНЫЕ OS-процессы (каждый = своё подключение к БД) на синтетические данные с ровно 1 оставшимся слотом; после теста синтетика удаляется вручную (`DELETE`), не через rollback. Подтверждено: 5 конкурентных join на 1 слот (classic и beach) → ровно 1 успех, COUNT не превышает max; `autoBookNext()` vs прямая запись на один слот → никогда оба не проходят одновременно.
- **Уборка устаревших записей** (`waitlist:cleanup-expired`, `dailyAt('04:15')` в `routes/console.php`): occurrence_waitlist никогда не чистился после прохождения occurrence — 17 мусорных строк накопилось за апрель-июнь 2026. Удаляет записи где `occurrence.starts_at < now() - config('waitlist.cleanup_expired_days')` (default 7 дней запаса). `--dry-run` для проверки без удаления
- **Аудит других таблиц на такой же мусор** (привязанные к occurrence, никем не читаемые после прохождения тура): `event_channel_messages` (33/33 строки старше 7 дней на dev) — кандидат похожей проблемы, НЕ вычищается; отложено, нужно отдельное решение (хранит `external_message_id` для редактирования анонса, неясно нужна ли история). Остальные occurrence_id-таблицы — либо core-история, которую хранить нужно всегда (`event_registrations`, `event_teams`, `promotion_history`, `team_substitutions`, `tournament_stages`, `player_tournament_stats`, `court_bookings`), либо пока пустые (`activity_record_prompts`, `event_occurrence_trainers`). `event_occurrence_stats` из этого списка исключена — см. раздел «Выпил кеш-счётчиков» ниже, это не core-история, а выведенный из эксплуатации кеш.
- **Чекбоксы позиций в waitlist-форме**: reserve входит в `$posLabels` (если reserveMax>0 или есть legacy-записи) → в `@foreach` пропускать `@if($k==='reserve') @continue @endif` и рендерить отдельно через `@if(isset($posLabels['reserve']))`, иначе будет дубль
- **Взаимное исключение**: нельзя встать в waitlist если уже в составе (OccurrenceWaitlistController::store()); нельзя в состав если уже в waitlist (EventRegistrationController::storeOccurrence())
- Слоты: классика — event_role_slots (setter/outside/middle/opposite/libero/reserve); пляжка — один слот role='player'
- `getSlots` кешируется — два вызова в blade = один SQL запрос
- **players.blade.php — гейт видимости разделён на два независимых блока** (`$showWaitlistJoinForm` для игрока, `$showWaitlistViewer` для организатора/админа). Раньше был один общий `$showWaitlist` с `!$isRegistered` — организатор, играющий в своём же мероприятии, не видел список очереди вообще (весь `<div id="waitlist-section">` скрывался). `$showWaitlistViewer = !$isTournament && !$eventStarted && $isOrganizer && $waitlistCount > 0` — не зависит от `$isRegistered`; внутри общей карточки join-форма и список очереди — под своими собственными `@if`, не вложены друг в друга.

## Команды (EventTeam)
- Модель: EventTeam — принадлежит event_id + occurrence_id; team_kind: classic_team | beach_pair
- Участники: EventTeamMember, приглашения: EventTeamInvite, аудит: EventTeamMemberAudit, заявки: EventTeamApplication
- Контроллеры: TournamentTeamController, TournamentTeamInviteController
- **Статус approved/submitted без application**: `autoApprove=true` (при создании) и `confirmEventReserveSpot()` (подтверждение резерва) выставляют `status='approved'` напрямую без создания `EventTeamApplication`. В blade перед `@elseif($canManage)` обязательно нужен `@elseif(in_array($team->status, ['approved','submitted'], true))` — иначе команда покажет «состав не готов» вместо статуса заявки.
- **Передача капитанства**: `TournamentTeamService::transferCaptain()` — обновляет captain_user_id, role_code/team_role обоих участников, пишет аудит `'captain_transferred'` с reason='manual_transfer', уведомляет нового капитана. Маршрут: `POST /events/{event}/teams/{team}/transfer-captain`. Кнопка 👑 показывается капитану рядом с каждым подтверждённым участником (не капитаном).
- **Выход с добавлением в waitlist**: `leaveTeam` контроллера принимает `add_to_waitlist` (bool). Для `beach_pair`: создать новую команду через `TournamentTeamService::createTeam()` (имя = «Фамилия И.», autoApprove из application_mode), а НЕ добавлять в occurrence_waitlist. Для classic_team — `WaitlistService::join()` с позицией участника (читать ДО удаления). Если бросает исключение — ловить и добавлять в success-сообщение.
- **leaveTeam beach_pair — партнёр уже в другой команде**: при выходе капитана из beach_pair передача капитанства партнёру может оставить его в двух командах. Перед передачей проверять `EventTeam::where('event_id')->where('occurrence_id')->whereHas('members', user_id = partner)->exists()` — если да, то расформировывать команду вместо передачи.
- **joinRequest — alreadyInTeam без occurrence_id**: проверка «уже в другой команде» фильтрует по `event_id` без `occurrence_id` → участники из других туров той же серии (прошлые туры) блокировали вход в текущий. Фикс: добавить `.where('occurrence_id', $team->occurrence_id)` в whereHas.
- **is_complete не обновляется при ручном добавлении**: если организатор добавляет участников через setup (AddMemberByOrganizer), `recheckTeamCompleteness` может не вызываться → `is_complete` остаётся false при 2 confirmed участниках. Симптом: команда с 2 игроками не попадает в "основной состав" на setup. Фикс: пересчитать через `DB::table('event_teams')->update(['is_complete' => true])` для команд где `confirmed_members >= team_size_min`.
- **Кнопка 🏆 в occurrences_table.blade.php**: одиночный турнир (`!$event->is_recurring`) → `/events/{id}/tournament/setup` (без occurrence_id); повторяющийся турнир → `/events/{id}/tournament/setup?occurrence_id={occ_id}`. Реализовано через `{{ $event->is_recurring ? '?occurrence_id=' . (int)$occ->id : '' }}`.
- **Подсчёт "N из M команд" + "K в резерве" — единственный канонический метод: `TournamentTeamService::countRegisteredTeams(eventId, occurrenceId, seasonId=null)`**. Резерв бывает двух взаимоисключающих видов на один occurrence: лиговый (`tournament_league_teams.status='reserve'`, когда есть `season_id` — команда физически существует и играет, но выведена из зачёта дивизиона) и событийный (`event_teams.reserve_position` — лист ожидания сверх `tournament_teams_count`, механизм `TournamentTeamService::eventTournamentIsFull()`/`createTeam()`). До 2026-07-16 три места считали независимо и по-разному (см. мини-диагностику event 395: дашборд и seatline-счётчик показывали 5 из 4 = 125%, т.к. не исключали событийный резерв; верно считала только страница события) — теперь три потребителя: `EventRegistrationGuard::check()` (ранний return для team_classic/team_beach — это и есть реальный путь `/occurrences/{id}/availability`, `buildAvailabilitySnapshot()` для этих же режимов недостижима, там осталась только для tournament_individual/legacy `'team'`), `OrgDashboardController` (SQL не может звать PHP — условие продублировано дословно в `$registeredExpr`, менять синхronно), `events/show/players.blade.php`. **При добавлении нового места, где нужен подсчёт команд — использовать этот метод, не писать свой запрос.**

## Сохранённые команды игрока (UserTeam)
- Таблицы: `user_teams` + `user_team_members` (role_code, position_code)
- Сервис: `UserTeamValidationService` — validateForEvent() + checkTeamSize()
- Контроллер: `UserTeamController` CRUD на `/user/teams/{team}`
- `TournamentTeamController::saveToProfile()` — EventTeam → UserTeam (только капитан)
- `TournamentTeamController::fromSaved()` — EventTeam из UserTeam, рассылает invites
- При ошибках валидации → редирект на `/user/teams/{team}/edit?event_id=X` с session('team_validation_errors')

## Позиция reserve в регистрациях
- `resolvePositions()` НЕ включает 'reserve' — добавляется отдельно в index()/addPlayer()/updatePosition()
- Источник лимита: event_role_slots.role='reserve' ИЛИ game_settings.reserve_players_max (fallback)
- Если reserveMax=0 но есть legacy-записи с position='reserve' → показываем без лимита
- **Счётчик вместимости** (`EventRegistrationsManagementController::index`): `maxPlayers += reserveMax` перед расчётом `freeSeats` — иначе activeRegs (включает запасных) > maxPlayers (только основные) → freeSeats=0 при наличии свободных мест

## Android WebView — скачивание файлов
- `Content-Disposition: attachment` молча игнорируется Android WebView
- Фикс: `if (window.Capacitor && window.Capacitor.getPlatform() === 'android') { window.open(url, '_system'); }`
- Применено в: registrations/index.blade.php (PDF/TXT), tournaments/public/show.blade.php (PDF)

## event_management_edit — город и timezone
- Поле timezone: `<input type="hidden" name="timezone" id="mgmt_timezone_hidden">`
- City autocomplete → AJAX `/ajax/cities/meta` обновляет timezone + `/ajax/locations/by-city` фильтрует локации

## Редактирование повторяющейся серии — будущие occurrences

### Когда показывается диалог
- SweetAlert показывается ТОЛЬКО при изменении полей расписания: `starts_at`, `recurrence_type`, `recurrence_weekdays`, `recurrence_interval`, `recurrence_end_type/until/count`
- При смене описания, локации, фото, оплаты, настроек регистрации — форма отправляется без вопросов
- JS сравнивает текущие значения с `origSchedule` (снимок из PHP на момент загрузки страницы)
- Диалог не показывается если `is_recurring_edit` checkbox снят (серия отключается)

### Параметр future_occurrences_action
- `keep` (по умолчанию) — будущие occurrences не трогаются; ExpandJob добавит новые по обновлённому расписанию поверх существующих (дублей нет — защита через `uniq_key`)
- `cancel` — умная логика в контроллере:
  - **Occurrence с активными регистрациями** → `is_cancelled=true` + уведомление участникам (`notifyUsersAboutCancelledEvent`)
  - **Occurrence без регистраций** → `DELETE` (мусор, убирается навсегда)
  - После очистки — dispatch `ExpandEventOccurrencesJob` создаёт новые по обновлённому расписанию

### Что применяется ко всем будущим occurrence при сохранении серии (без диалога)
Контроллер всегда обновляет все future occurrences: `location_id`, `duration_sec`, `allow_registration`, `max_players`, `age_policy`, `is_snow`, окна регистрации (пересчёт от starts_at каждой occurrence).

### Что НЕ хранится в occurrence — берётся из events при отображении
`title`, `description_html`, `event_photos`, `is_paid`, `price_*` — в `event_occurrences` есть эти колонки, но заполняются только при editOccurrence (override). Если NULL — `EventShowService` подставляет значение из parent `events`. Изменение в серии сразу видно на всех турах.

### uniq_key — защита от дублей
Каждая occurrence: `uniq_key = "event:{id}:{YmdHis UTC}"`. `OccurrenceExpansionService` при каждом запуске делает `updateOrCreate` по этому ключу — дублей не создаёт никогда, даже при повторных запусках ExpandJob.

## Красивая статистика матча (match_stats_pretty)
- Файл: `resources/views/tournaments/_partials/match_stats_pretty.blade.php`; CSS: `.ms-*` в конце `public/assets/style.css`. Старый `match_stats_table.blade.php` НЕ удалён (откат)
- Подключается в `tournaments/public/show.blade.php` в ДВУХ местах — **оба под кнопкой-тогглом "📊 Статистика матча"**:
  - вкладка `tab=groups` (не `overview`! в overview есть только список "Последние результаты" без статистики) — блок "Матчи групповой стадии"
  - вкладка `tab=results` — список завершённых матчей стадии
  - Важно: вкладка `tab=stats` вообще не показывает статистику конкретного матча — там только турнирный топ игроков (`getTopPlayers`)
- Блоки: хедер (капитаны в кружках 48px + fallback инициалы команды на брендовом фоне синий/оранжевый #2967BA/#E7612F, счёт по сетам с приглушением проигранных очков), герои матча (по `points_scored` desc, потом `kills`, аватар 72px), сравнительные бары (атака=kills, блок=blocks, подача=aces, ошибки подачи=serve_errors отдельно, ошибки=attack_errors+block_errors+reception_errors суммарно), таблицы игроков (сортировка по points_scored desc, мини-аватар 24px)
- **Игрового номера в БД нет нигде** (`event_team_members` не имеет такого поля) — вместо бейджа с номером используется бейдж с позицией (Св/Дг/Дн/Ц/Либ) из `position_code`/`role_code`; если позиция не резолвится — бейдж просто не рисуется, не выдумываем номер
- Разделы "Либеро"/"Запасные" в таблице игроков рендерятся только если у команды реально есть участник с `position_code=libero` или `role_code=reserve` — иначе единый список
- Аватары игроков — `$user->profile_photo_url` (уже с fallback на ui-avatars.com), для капитана в хедере — отдельная проверка `avatar_media_id` + наличие `thumb`-конверсии (чтобы отличить «есть реальное фото» от общего fallback и показать вместо него инициалы команды на брендовом фоне)
- Тестирование без браузера (headless-окружение, нет chromium/playwright): временный `UPDATE tournament_matches SET status='completed', sets_home=.., score_home=..` на тестовый матч + `curl -sk https://127.0.0.1/... -H "Host: volley-bot.store"` (volley-bot.store слушает только 443, порт 80 отдаёт левый vhost с "ok") + откат UPDATE обратно к исходным значениям сразу после проверки

## Миграция volleyplay.club
- Dev: volley-bot.store, Prod: volleyplay.club
- Два Telegram бота: dev (VolleyEvent_bot, порт 8092), prod (VolleyEvents_bot, порт 8094)
- НЕ держать оба бота в одном канале

## Паттерн поиска игроков (autocomplete)
Эталон: `resources/views/events/show/players.blade.php` (блоки `invite-ac-*` и `group-invite-ac-*`).
- Обёртка: `position:relative`, БЕЗ `overflow:hidden`; дропдаун через класс `form-select-dropdown--active` (НЕ через `form-select-dropdown` — он даёт visibility:hidden)
- JS в IIFE; guard `if (!input) return;`; debounce 250мс, минимум 2 символа
- API: `GET /api/users/search?q=QUERY` → `{ items: [{id, label, name}] }`; использовать `item.label || item.name`
- Chips (мульти): `name="to_user_ids[]"` + `data-invite-hidden="ID"` для удаления; выбранные — opacity 0.4
- Safari: заменять fetch на jQuery.ajax при CORS-проблемах; credentials:'same-origin' обычно достаточно

## Google OAuth
- Провайдер: Laravel Socialite driver('google'); конфиг: config/services.php (GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET)
- Колонка: users.google_id; контроллер: GoogleAuthController; отвязка: AccountUnlinkController::google
- Кнопки OAuth в ДВУХ местах: login.blade.php + voll-layout.blade.php (попап шапки) — обновлять оба
- Apple скрывается на Android; Google скрывается на Apple (iPhone/iPad/Macintosh)

## Smart App Banner и RuStore баннер
- iOS Smart App Banner: `<meta name="apple-itunes-app" content="app-id=6764748613">` в voll-layout.blade.php; обёрнут в @if(!VolleyPlayApp)
- Android RuStore: JS-блок после `<body>`; UA=Android && !VolleyPlayApp; закрытие → localStorage 7 дней; MutationObserver на body.classList для dark mode

## Apple Sign In — диагностика
- `invalid_request` от Apple = code пустой (WKWebView race condition); `invalid_grant` = credentials OK, код невалиден (норма)
- Конфиг: client_id=club.volleyplay.app.signin, team_id=V762R44QWF, key_id=A78BY2CKKQ; ключ: storage/app/apple/AuthKey_A78BY2CKKQ.p8
- AppleAuthController: guard для пустого code (ранний выход + лог) + session()->save() в redirect()
- Долгосрочный fix: @capacitor-community/apple-sign-in (нативный ASAuthorizationAppleIDProvider)

## BLE-трекинг пульса (Activity)
Полная схема таблиц/конфига/API/JS/формул — [claude_docs/claude_activity_ble.md](claude_docs/claude_activity_ble.md).
- Таблицы: `activity_sessions`, `activity_hr_samples`, `activity_jump_events` (t_offset_ms, не sec!), `athlete_devices`, `athlete_profiles`, `user_consents`
- **КРИТИЧНО**: capabilities (hr/jumps) проверять через `tracked_capabilities` (зафиксировано на старте сессии), не через `protocol` напрямую
- Калории — формула Keytel 2005 (`ActivityCalorieService`); **gender** значения `'m'`/`'f'`, не `'male'/'female'`
- **`load_score` (decimal:2 cast) — отдаётся строкой, `"0.00"` truthy в PHP** — всегда `(float)$val > 0`, не голая truthy-проверка
- `sync_status` — вычисляемый accessor (`ActivitySession::getSyncStatusAttribute()`), НЕ колонка в БД

## Push-уведомления (APNs) и Face ID

### Push-уведомления
- Таблица: device_tokens (user_id, platform ios/android, token unique, is_active bool); модель: DeviceToken, сервис: PushNotificationService
- APNs — прямая реализация через curl HTTP/2 + JWT (ES256), БЕЗ внешних пакетов (laravel-notification-channels/apn несовместим с Laravel 12 + PHP 8.3)
- HTTP 410 = токен устарел → is_active=false; HTTP 400 BadDeviceToken → is_active=false
- Конфиг: config/apn.php; переменные: APN_KEY_ID, APN_TEAM_ID, APN_BUNDLE_ID, APN_PRIVATE_KEY, APN_PRODUCTION; .p8 → storage/app/apns/AuthKey.p8
- 4 типа с push: registration_created, event_reminder, event_cancelled, friend_joined_event
- **ВАЖНО**: Xcode/debug → sandbox-токены; TestFlight/App Store → production-токены; APN_PRODUCTION=false только для dev
- Prod-приложение → volleyplay.club → prod DB; при тестировании с Xcode: токены в prod DB, отправлять через prod-сервер с APN_PRODUCTION=false
- APNs возвращает 200 OK на sandbox но не доставляет если entitlements некорректны — проверять aps-environment:development

### Face ID и Biometric
- users.biometric_token (string 64, nullable, unique), скрыт в $hidden
- Генерация: Str::random(64); НЕ crypto.randomUUID() (недоступен в WKWebView до iOS 15.4)
- BiometricController (Api/): register/login/revoke/webLogin
- Web endpoints: POST /auth/biometric-login (исключён из CSRF в bootstrap/app.php), POST /auth/biometric-register
- deleteCredentials только при 422; при 419/500 — сохранять credentials
- SESSION_SAME_SITE=none, SESSION_SECURE_COOKIE=true

### Universal Links
- AASA: public/.well-known/apple-app-site-association; appID: V762R44QWF.club.volleyplay.app
- nginx: location ^~ /.well-known/ ДО блока `deny all`; после изменений nginx — restart (не reload)

## Impersonation (вход от имени пользователя)
- Контроллер: `Admin/ImpersonationController`; middleware: `BlockInImpersonation` (алиас `block.impersonation`)
- Session key: `impersonator_id`; leave() — БЕЗ `can:is-admin`; логирует через DB::table('admin_audits') с actor_user_id = impersonator_id
- Заблокированные действия: отвязка OAuth, удаление аккаунта, платежи, biometric-register
- Нельзя войти от имени другого администратора
- **AuthenticateSessionWithImpersonation** (`config/jetstream.php` → `auth_session`): синхронизирует `password_hash_<driver>` в session при активной impersonation — без этого расхождение hash → session()->flush() → потеря impersonator_id

## Прогрессивное заполнение профиля (v2)
- Уровень 1 (доступ): `User::isProfileComplete()` — только first_name, last_name, phone; middleware `EnsureProfileCompleted`
- Уровень 2 (при записи): `User::getMissingFieldsForEvent()` — age_policy→birth_date, direction+levels→уровни, gender_policy→gender; редирект на `/profile/complete?missing=...&return_to=...`
- Блокировка полей после заполнения (protectedOnce): first_name, last_name, patronymic, phone, city_id, birth_date, gender, classic_level, beach_level; в `ProfileUpdateGuard::selfEdit()` и `ProfileExtraController`
- OAuth при создании: НЕ заполняют first_name/last_name/phone/gender; пользователь заполнит через profile/complete
- **users.gender значения**: `'m'` и `'f'` (не 'male'/'female') — при фильтрах/keyBy использовать однобуквенные коды
- profile/complete.blade.php постит на `route('profile.extra.update')` (ProfileExtraController)

## Дубли пользователей и очистка неактивных аккаунтов
- Поиск дублей: `UserMergeService::findDuplicates()` — по телефону + по first_name+last_name (case-insensitive)
- Таблица стаффа: `organizer_staff` (колонка staff_user_id); роли: users.role IN ('admin','superadmin','organizer')
- `CheckUserDuplicatesJob` — ежедневно 04:00, уведомляет в Telegram (TELEGRAM_BOT_TOKEN, TELEGRAM_ADMIN_CHAT_ID)
- `PurgeInactiveUsersJob` — ежедневно 04:30; критерии: profile_completed_at IS NULL + нет регистраций/платежей/баланса + не bot/admin/organizer/staff
- Artisan: `users:check-duplicates`, `users:purge-inactive [--dry-run]`

### UserMergeService::merge() — что переносится
- OAuth поля (unique): telegram_id, vk_id, yandex_id, apple_id, google_id — из secondary только если у primary пусто; сначала обнуляются у secondary
- Профиль (если пусто у primary): phone, first_name, last_name, patronymic, birth_date, city_id, gender, height_cm, classic_level, beach_level
- user_beach_zones (дедупликация по zone), user_classic_positions (по position)
- Также: event_registrations, payments, virtual_wallets, subscriptions, coupons, premium_subscriptions, user_notification_channels, occurrence_waitlist, event_team_members/invites/applications, friendships, статистика, device_tokens
- НЕ переносятся: event_team_member_audits, notification_deliveries, page_views, account_delete_requests

## Ограничения пользователей (user_restrictions)
- Таблица: `user_restrictions` (user_id, scope, ends_at, event_ids, reason, created_by)
- `scope='events'` + `event_ids=[367,...]` — запрет записи на конкретные мероприятия; `ends_at=null` = пожизненно
- Middleware: `EnsureUserNotRestricted` (алиас `user.restricted`) — блокирует `events.join` И `occurrences.join`
- **КРИТИЧНО**: middleware проверяет только эти два маршрута; `events.registrations.add` (добавление организатором) намеренно не блокируется — организатор может добавить игрока вручную несмотря на ограничение
- **Баг (исправлен в f411a30)**: изначально middleware блокировал только `events.join` (legacy), но не `occurrences.join` (новый). Все записи через UI шли через `occurrences.join` → ограничение не работало. Игрок мог обойти запрет простой записью на сайте.
- При ручной отмене записи ограниченного игрока через скрипт: установить `is_cancelled=true`, `cancelled_at=now()`, `status='cancelled'`; вызвать `WaitlistService::onSpotFreed()`, `UserNotificationService::createRegistrationCancelledByOrganizerNotification()` (⚠️ `EventOccurrenceStatsService::decrement()` вызывать больше НЕ нужно — метод удалён 2026-07-16, см. раздел «Выпил кеш-счётчиков» ниже)
- `banEvents` делает **жёсткий DELETE** существующих регистраций (не soft cancel) — при снятии ограничений записи не восстанавливаются автоматически

## Управление регистрациями — toggle cancel/restore (EventRegistrationsManagementController::cancel)
- Три поля статуса отмены в `event_registrations`: `cancelled_at`, `is_cancelled`, `status` — все три должны быть синхронны
- **Баг (исправлен в 470e2ac)**: метод `cancel()` определял текущее состояние только по `cancelled_at`, игнорируя `is_cancelled` и `status`. При рассогласовании полей (напр. `status='cancelled'` + `is_cancelled=false` + `cancelled_at=null`) toggle шёл в неверную сторону.
- Рассогласование возникает при двойном клике или параллельных запросах. Симптом: страница управления считает запись активной (фильтр `is_cancelled=false`), а страница мероприятия исключает её (`status='cancelled'`) → расхождение счётчиков мест.
- Фикс: `$isCancelled = !empty($row->cancelled_at) || !empty($row->is_cancelled) || $row->status === 'cancelled';` — любого «отменённого» признака достаточно для restore.
- **Баг (исправлен): назначение/смена `position` организатором не проверяла гендерную квоту**. `cancel()` (восстановление с новой позицией), `updatePosition()`, `addPlayer()` (новая запись и восстановление отменённой) проверяли только `role.max_slots` живым COUNT — но не вызывали `EventRegistrationGuard::applyGenderPolicy()` (`only_male`/`only_female`/`mixed_5050`/`mixed_limited`). Организатор мог вручную посадить игрока на позицию, где гендерная квота уже исчерпана (напр. 5-ю женщину на `setter` при `gender_limited_max=4`).
- Фикс: `EventRegistrationGuard::checkGenderQuotaForUser(User, EventOccurrence, ?excludeRegistrationId)` — публичная обёртка над `applyGenderPolicy()` (переиспользуется и в `checkEligibility()`, чтобы не дублировать). Жёсткий запрет (не предупреждение) — квота настроена осознанно, обход = сломанный формат игры. Вызывается во всех трёх местах перед записью `position` в БД; `excludeRegistrationId` исключает саму перемещаемую регистрацию из подсчёта уже занятых мест. Сообщение об ошибке — `events.gender_quota_position_full` (ru/en), формируется внутри `applyGenderPolicy()` (mixed_limited ветка) с названием позиции + счётчиком `N из M`.

## SQL-безопасность
- whereRaw с OR — ВСЕГДА в скобках: `->whereRaw('(a IS NULL OR a = false)')`, иначе AND>OR захватывает чужие строки
- `AdminUserController::deleteOrPurge`: hard delete 30+ таблиц без транзакции — задача переход на soft delete (анонимизация PII + deleted_at). См. память project_soft_delete_users.md

## Клубный модуль (Timeline, аналитика, брони кортов, оплата ЮKassa)
Полная документация — [claude_docs/claude_club_module.md](claude_docs/claude_club_module.md). См. также память `project_club_module_phases.md`.
- **TimelineService** (`app/Services/TimelineService.php`) — единый partial `club/_partials/timeline.blade.php`, не дублировать. `EventOccurrence.ends_at`/`timezone` — не колонки/не заданы по умолчанию в тестовых occurrence → молча теряются слоты, см. файл.
- **ClubAnalyticsService** — переиспользует `TimelineService::day()` для числителя загрузки; выручка — отдельный SQL по `payment_mode`.
- **Фаза 5** (бронь корта игроком) — `CourtBookingService::createByPlayer()`, `route('login', ['return' => ...])` (параметр `return`, не `return_to`!), `/my/bookings`.
- **Карточка шаринга матча (Browsershot)** — Chrome ставить ТОЛЬКО в `storage/app/chromium/` (не `~/.cache/puppeteer` — туда `www-data` не имеет доступа).
- **Фазы 4+6** (оплата ЮKassa за бронь + уведомления) — `payments.court_booking_id`, `YookassaService::makeClientFor()/verifyPayment()/createRefund()`, `locations.refund_policy`/`refund_deadline_hours`.
