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
- Класс form-select-dropdown даёт visibility:hidden — НЕ использовать для dropdown
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

## Индивидуальная запись на турнир (tournament_individual)
- `registration_mode = 'tournament_individual'` — игроки записываются по амплуа, не командами
- Чекбокс `tournament_individual_reg` в форме создания (step1.blade.php) в блоке `#tournament_settings_block`
- Role slots создаются как для обычной игры: позиции × teams_count; резерв = reserve_per_team × teams_count
- EventGameSettingsService: при `tournament_individual_reg=1` → `game_subtype = tournament_game_scheme`, `teams_count = tournament_teams_count`
- Guard: `tournament_individual` НЕ требует команду; waitlist включён (как обычная игра)
- players.blade.php: `$isIndividualTournament = format=tournament && registration_mode=tournament_individual`; кнопка "Распределить случайно" → POST /events/{id}/distribute-individual
- TournamentTeamDistributionService::distributeRandom() — round-robin по позициям, первый участник = капитан, не перезаписывает существующие команды
- **Гендерное распределение пар (team_size_min=2, т.е. пляжные пары 2x2)**: сначала формируются смешанные М+Ж пары (пока хватает обоих полов), остаток одного пола (+ игроки без указанного пола) — пары между собой тем же/любым полом; нечётный "хвост" — неполная пара из 1 игрока (`is_complete=false`, попадает в "⏳ Ищут партнёра", как и при обычном ручном создании). Метод `pairByGenderThenRandom()`; включается когда `event.tournamentSetting.team_size_min === 2` — для team_size!=2 (классика с разными амплуа на команду) используется прежний shuffle+round-robin без учёта пола, т.к. концепция "пары" неприменима к ролевым командам напрямую.
- **`is_complete` в distributeRandom() — раньше был хардкожен `true` для ЛЮБОЙ созданной команды**, включая неполные (напр. одиночный "хвост" при нечётном перекосе полов) → команда с 1 участником ошибочно показывалась как полная. Фикс: `is_complete = count($members) >= team_size_min` (когда team_size_min известен).
- **Баг (исправлен): ручное создание команды без явного капитана "съедало" место организатором.** `TournamentTeamController::store()` при пустом `captain_user_id` дефолтил капитана на `$request->user()` (самого организатора) — при отметке 2 игроков чекбоксами (`member_user_ids`) первый добавлялся нормально, второй падал `DomainException('Достигнут максимальный размер команды (2)')`, т.к. организатор+1 игрок уже заняли обе позиции team_size_min=2. Фикс: если `registration_mode=tournament_individual` и капитан не выбран явно, но есть `member_user_ids` — капитаном становится ПЕРВЫЙ отмеченный игрок (не организатор).
- **Редирект после ручного создания команды организатором** — раньше всегда уводил на `tournamentTeams.show` (страница только что созданной команды), неудобно при создании нескольких команд подряд. Теперь если создатель — организатор/админ (`$isOrganizerOrAdmin`), редирект возвращает на `tournament.setup?occurrence_id=...` (страница управления турниром); обычные игроки (самостоятельное создание своей команды) по-прежнему попадают на страницу команды. Аналогично поправлен `destroy()` (удаление команды) — раньше редиректил на `tournament.setup` БЕЗ `occurrence_id`, из-за чего страница могла подхватить не тот тур (актуально для событий с несколькими occurrences).
- **`distributeRandom()` теперь дополняет, а не требует "всё или ничего"**: раньше при наличии ХОТЯ БЫ одной существующей команды возвращал ошибку "Команды уже сформированы, сначала удалите". Теперь считает `remainingTeamsCount = tournament_teams_count - существующие команды`, берёт в выборку ТОЛЬКО игроков, ещё не состоящих ни в одной команде события/тура (через `EventTeamMember`), и формирует именно `remainingTeamsCount` новых команд, не трогая уже созданные (вручную или предыдущим запуском). Ошибка "уже сформированы" теперь означает только "свободных командных слотов не осталось".
- **Баг (исправлен): чекбокс `tournament_individual_reg` не влиял ни на что.** `EventCreateValidator` не содержал это поле в правилах → `Validator::validate()` (Laravel фильтрует по `getRules()`) всегда возвращал массив без этого ключа, даже если чекбокс был отмечен → `EventStoreService::store()` (`!empty($data['tournament_individual_reg'])`) всегда `false` → турнир создавался как `team_beach`/`team_classic` независимо от формы. Фикс: добавлено `'tournament_individual_reg' => ['nullable', 'boolean']` в правила валидатора. Если у турнира, созданного ДО фикса, `registration_mode` ошибочно `team_beach`/`team_classic` вместо `tournament_individual` — чинить вручную через `EventGameSettingsService` (см. код `EventStoreService::store()` строки 543-556 как образец: `event->registration_mode`, `EventTournamentSetting`, `EventRoleSlot` через `createGameSettings()`, `occurrence.max_players`).
- **Баг (исправлен): `TournamentTeamService::resolveTeamKindFromSettings()` не различал пляж/классику для `tournament_individual`** — при `registration_mode='tournament_individual'` всегда возвращал `classic_team` (match знал только `'team_beach' => 'beach_pair'`), из-за чего ручное создание команды (`TournamentTeamController::store()` без явного `team_kind`) на пляжном индивидуальном турнире падало с `DomainException('Для капитана классической команды нужно указать амплуа')`. Фикс: метод принимает `$direction` вторым параметром, `'tournament_individual' => $direction === 'beach' ? 'beach_pair' : 'classic_team'`.
- **Баг (исправлен): `TournamentTeamDistributionService::distributeRandom()` падал с NOT NULL на `event_teams.captain_user_id`** — `EventTeam::create([...])` не передавал `captain_user_id` в момент создания (проставлялся ПОСЛЕ, через `$team->save()` внутри цикла участников), а колонка `NOT NULL` → `QueryException` при первой же попытке распределения. Фикс: `captain_user_id => $members[0]['user_id']` сразу в payload `create()`.
- **Баг (исправлен): команды после `distributeRandom()` создавались, но были невидимы на странице setup** — `distributeRandom()` ставил `event_teams.status = 'confirmed'`, а `TournamentController::setup()` фильтрует `$teams` через `whereIn('status', ['draft','submitted','approved','ready'])` — `confirmed` в этот список не входит. Итог: команды реально формировались (с верными капитанами/составом), но блок "Команды" показывал "Нет подтверждённых команд", а список нераспределённых игроков — пусто (0), т.к. он считается независимо от статуса команды через `EventTeamMember`. Выглядело так, будто распределение не сработало или "стёрло" игроков. Фикс: `status => 'approved', confirmed_at => now()` — как у organizer-created команд с `autoApprove=true`.
- **`confirm()`/`alert()` в JS кнопки "Распределить случайно" заменены на swal** (стиль проекта, см. паттерн `admin/impersonate/index.blade.php`: `swal({title,text,icon,buttons:{cancel:{...},confirm:{...}},dangerMode:true}).then(fn(value){...})`). **Ловушка**: `@json(__('key', ['n' => ..., 'p' => $var->count(),]))` — вложенный многострочный массив-аргумент ВНУТРИ `@json(__(...))` ломает извлечение аргументов blade-директивы (компилятор не находит закрывающую `]`/`)`, страница падает `ViewException: Unclosed '[' ... does not match ')'`). Считать текст перевода заранее в `@php $x = __('key', [...]); @endphp`, затем `@json($x)` — одна простая переменная внутри `@json()`, без вложенных вызовов/массивов.
- **`TournamentTeamNamingService`** (`app/Services/TournamentTeamNamingService.php`) — автогенерация названия команды, когда организатор оставил поле пустым: пляж → фамилии участников через `/` (`Иванов/Петров`), классика → случайное короткое название из пула прилагательное+существительное (`Дикие Бобры`); проверка уникальности в рамках `event_id+occurrence_id` (там реальный unique-индекс `event_teams_event_id_occurrence_name_unique`) с ретраями. Используется и в `distributeRandom()`, и в `TournamentTeamController::store()` (когда `registration_mode=tournament_individual` и имя не задано).
- **Setup-страница турнира (`tournaments/setup.blade.php`) — блок "Команды" для `tournament_individual`**: заголовок меняется на "Команды/Игроки (:n)" (`tournaments.setup_teams_h2_individual`), под списком команд показывается блок "Не распределены по командам (:n)" — все, кто зарегистрирован на occurrence (`event_registrations`), но ещё не состоит ни в одном `EventTeamMember` этого события/тура (`TournamentController::setup()` считает `$unassignedPlayers` через `whereNotIn('user_id', assignedUserIds)`). Форма "Создать команду" в этом случае вместо обычного автокомплита по всем пользователям (`/api/users/search`) показывает капитана через ЛОКАЛЬНЫЙ JS-фильтр по уже отрендеренному списку нераспределённых игроков (без похода в сеть — список маленький и известен на этапе рендера) + чекбоксы остальных участников (`member_user_ids[]`); при выборе капитана его чекбокс в списке блокируется и снимается. `TournamentTeamController::store()` после создания команды добавляет отмеченных участников через `TournamentTeamService::addMemberByOrganizer()` в цикле (без новых роутов).

## Dark mode (body.dark)
- Inline style="color:..." нельзя переопределить CSS-классом
- Решение: добавить класс + text-shadow с белым glow в `body.dark .class`
- Уровень 7 ("Профи М.С.") = #212121 — чёрный → class `level-color-badge`; CSS: `body.dark .level-color-badge { text-shadow: 0 0 8px rgba(255,255,255,.85); }`

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

## Правила работы
- В конце каждой сессии обновляй этот файл: добавляй новые находки, баги, паттерны
- Коммить изменения CLAUDE.md вместе с кодом
- Не дублируй — обновляй существующие секции
- НЕ изучай структуру проекта заново каждый раз — вся информация уже в этом файле
- Сразу приступай к задаче, используя контекст из CLAUDE.md

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

## Рейтинговая система (OpenSkill + Elo)

### Архитектура (три параллельных рейтинга)
| Метрика | Таблица | Поле | Обновляется |
|---|---|---|---|
| WinRate | `player_career_stats` | `match_win_rate` | После каждого матча |
| Elo | `player_career_stats` | `elo_rating` | При завершении турнира |
| **OpenSkill μ/σ** | `player_career_stats` | `mu`, `sigma` | **После каждого матча** |
| Elo сезон | `tournament_season_stats` | `elo_season` | После каждого матча (ранее мёртвый — починен) |
| **OpenSkill сезон** | `tournament_season_stats` | `mu_season`, `sigma_season` | **После каждого матча** |

### OpenSkill — алгоритм (реализован сам, без пакетов)
- Сервис: `app/Services/TournamentOpenSkillService.php`
- Константы: `INITIAL_MU=25.0`, `INITIAL_SIGMA=8.333`, `BETA=4.1667`, `TAU=0.0833`
- **Conservative Rating (публичный рейтинг)**: `CR = max(0, mu - 3 * sigma)`
- Новичок: CR ≈ 0 (25 − 3×8.333); после 9 побед ~17–20; после 30+ игр стабилизируется
- Ядро: Gaussian update — победитель получает +Δμ, проигравший −Δμ; σ уменьшается у обоих
- Добавлен τ-drift (`TAU^2` к σ²) — предотвращает σ→0 и сохраняет динамику
- `normalCdf(x)` — Abramowitz & Stegun аппроксимация (max error 7.5e-8), без внешних пакетов

### Точка вызова OpenSkill
- **Инкрементально**: `TournamentStatsService::updateAfterMatch()` — вызывает `TournamentOpenSkillService::processMatchByIds()` после каждого матча
- **Сезонный**: `TournamentSeasonStatsService::updateForMatch()` — обновляет `mu_season`/`sigma_season` через тот же сервис
- **Ретропересчёт**: `php artisan tournament:recalculate-openskill` — сброс + прогон всех исторических матчей с топ-10 в консоли

### Баг: повторная обработка OpenSkill при каждом сохранении счёта (исправлен 2026-07-04)
- `TournamentStatsService::rebuildAll()` вызывается после **каждого** сохранения счёта любого матча турнира и внутри (`rebuildTournamentStats()`) прогоняет `updateAfterMatch()` по **ВСЕМ** завершённым матчам события, не только по только что введённому
- `updateAfterMatch()` → `processMatchByIds()` не идемпотентен: инкрементирует накопительные счётчики (`matches_together`, `unique_opponents`, mu/sigma и т.п.), а не пересчитывает их с нуля → при каждом новом счёте в турнире все ранее обработанные матчи накручивались повторно
- Симптом: `player_pair_stats.matches_together` растёт кратно реальному числу игр (напр. 12 при `total_matches=1`), `pair_stability = matches_together/total_matches*100` переполняет `DECIMAL(5,2)` → падает при вводе следующего счёта в этом турнире
- Фикс: `tournament_matches.stats_processed_at` (nullable timestamp) — `updateAfterMatch()` обрабатывает OpenSkill-часть только если `stats_processed_at IS NULL`, затем проставляет флаг; `resetScore()` (рескоринг) сбрасывает флаг в NULL; `TournamentOpenSkillService::rebuildAll()` (ретропересчёт) тоже проставляет флаг по каждому матчу
- Событийная часть (`PlayerTournamentStats` — win rate турнира) НЕ имеет этой проблемы — она удаляется и пересчитывается с нуля при каждом вызове (`rebuildTournamentStats()` делает `delete()` перед циклом), поэтому флагом не защищена и не должна быть
- После деплоя фикса на новую БД/окружение — один раз прогнать `php artisan tournament:recalculate-openskill`, если там уже копилась порча (симптом — `pair_stability`/другие производные метрики выглядят неправдоподобно, %>100 и т.п.)

### Починка elo_season (был мёртвым)
- `TournamentEloService::processSeasonMatch()` — новый метод, обновляет `elo_season` в `tournament_season_stats`
- Вызывается из `TournamentSeasonStatsService::updateForMatch()` и `rebuildForSeason()`
- До фикса: 72/72 записей = 1500 (дефолт). После: считается корректно

### Ограничение: legacy-данные
- Алгоритм берёт игроков через `event_team_members WHERE confirmation_status='confirmed'`
- Игроки без записей в `event_team_members` (legacy-матчи) имеют CR=0 — это ожидаемо, не баг
- То же ограничение у `elo_rating` — системная особенность данных

### Дополнительные таблицы (OpenSkill v2)
| Таблица | Что хранит |
|---|---|
| `player_rating_history` | История μ/σ после каждого матча: mu_before/after, sigma_before/after, mu_delta (generated), match_id, event_id |
| `player_pair_stats` | Статистика пар: matches_together, wins_together, **direction**, **game_scheme** |
| `player_opponent_stats` | Статистика встреч: matches_against, wins_against |
| `player_career_stats` (новые поля) | mu_peak, mu_peak_date, unique_opponents, unique_partners, main_partner_id, main_partner_games, pair_stability, last_5_form, last_10_form, points_ratio |

- `player_pair_stats.direction` + `game_scheme` — добавлены в v1.9.7; **КРИТИЧНО**: при пересчёте `direction` и `game_scheme` сохраняются через ON CONFLICT UPDATE
- `rebuildAll()` в сервисе сбрасывает ВСЕ три таблицы истории (truncate) перед пересчётом
- `processMatchByIds` принимает `?int $eventId, ?int $matchId` — передавать всегда, иначе history не привязана к матчу

### Модели Player*
- `PlayerRatingHistory` — `app/Models/PlayerRatingHistory.php`; relation: `event()`, `user()`
- `PlayerPairStats` — `app/Models/PlayerPairStats.php`; methods: `winRate()`; constraint: `player1_id < player2_id`
- `PlayerOpponentStats` — `app/Models/PlayerOpponentStats.php`; methods: `winRate()`

### UI — страницы рейтинга
| URL | Файл | Описание |
|---|---|---|
| `/players/rating` | `resources/views/players/rating.blade.php` | Карьера + сезон; CR, Δ7д, μ, поиск, сортировка |
| `/players/teams` | `resources/views/players/teams.blade.php` | Связки/пары; фильтр по direction+game_scheme |
| `/pages/rating-info` | `resources/views/pages/rating_info.blade.php` | Объяснение OpenSkill: μ, σ, CR, примеры, форматы |
| `/user/{id}` | `resources/views/user/public.blade.php` | Позиция в рейтинге, график Chart.js, форма, партнёры, соперники |
- Контроллер: `PlayerRatingController` — методы `index()` (карьера/сезон) и `teams()`
- Навигация: ссылки «Рейтинг» и «Связки» добавлены в `voll-layout.blade.php`

### UserPublicController — новые данные профиля
Добавлены переменные: `$ratingHistory`, `$ratingPartners[beach|classic]`, `$ratingOpponents`, `$ratingPositions[beach|classic]`  
Chart.js скрипт вставляется **после** `</x-voll-layout>` — это нормально, браузер обрабатывает.

### i18n рейтинга
- `lang/ru/players.php` + `lang/en/players.php` — 45 ключей: `rating`, `teams_title`, `conservative_rating`, `mu`, `sigma`, `delta_7d`, `pair_stability`, `oz_op` и др.
- `tournaments.conservative_rating` / `tournaments.mu` / `tournaments.sigma` — старые ключи в `tournaments.*` (оставить для seasons/show)

### game_scheme — критичный баг парсинга
- **ЗАПРЕЩЕНО** определять team_size через regex `/^(\d+)x\d+$/` от game_scheme
  - `4x2` → regex даёт 4, реальный состав = 6; `5x1` → 5, реальный = 6
- **Правильно**: читать `event_tournament_settings.team_size_min`
- Допустимые значения `game_scheme`: classic → `4x4`, `4x2`, `5x1`, `5x1_libero`; beach → `2x2`, `3x3`, `4x4`
- Defaults в `EventGameSettingsService::getTournamentDefaults()`: `4x2`→min=6, `5x1`→min=6, `5x1_libero`→min=7+libero, `2x2`→min=2

### registration_mode — значения и приоритет (King Beach, добавлено 2026-07-08)
- Значения: `team_classic`, `team_beach`, `tournament_individual`, `king_beach`. Приоритет при создании события: **king_beach > tournament_individual > team_\*** — единая точка определения: `EventGameSettingsService::normalizeTournamentDefaults()` (создание) + продублирован в `EventStoreService::store()` (запись `event.registration_mode` до вызова normalize).
- **King Beach**: доступен только `direction=beach` + `tournament_game_scheme=2x2`. Индивидуальная регистрация как обычное мероприятие (`event_registrations`, НЕ `EventTeam`) — игроки не объединяются в команды на этапе записи. `king_beach_min_players`/`king_beach_max_players` (форма) пишутся напрямую в `EventGameSetting.min_players/max_players`, обходя расчёт `team_size × teams_count` в `EventGameSettingsService::createGameSettings()` (ранний branch по `registration_mode==='king_beach'`). `event.tournament_teams_count = 0` (нет команд — счётчик на карточке скрывается). Движок распределения по группам/раундам — `TournamentKingBeachService` (готов, не путать со слоем регистрации выше).
- Хелпер `Event::isIndividualRegistrationMode(?string $mode): bool` — `true` для `tournament_individual` И `king_beach` (места регистрации/waitlist должны обрабатывать оба режима одинаково); места ФОРМИРОВАНИЯ КОМАНД (`TournamentTeamDistributionService::distributeRandom` и др.) — строго `=== 'tournament_individual'`, king_beach туда не попадает (у него свой `distributeIntoGroups` на странице setup).
- **ГРАБЛИ**: формы РЕДАКТИРОВАНИЯ события перезаписывают `registration_mode` при КАЖДОМ сохранении (не только при явной смене режима) — `EventManagementController::update()` безусловно пересчитывал `tournament_individual ? ... : team_*` без проверки текущего режима, из-за чего king_beach-турнир тихо откатывался на `team_beach` при любой правке (даже смены описания/фото), т.к. на форме редактирования нет чекбокса king_beach. Уже пофикшено (`update()` сохраняет `king_beach`, если `tournament_individual_reg` явно не отмечен) — но при добавлении НОВОГО режима регистрации в будущем: grep ВСЕ места присваивания `registration_mode =` / `'registration_mode' =>` (не только чтения/сравнения) по всему `app/`, особенно в update-методах — иначе один из них молча затрёт новый режим.
- **Баг (исправлен): любое сохранение edit-формы затирало king_beach max_players/role_slots на GameCalculator-значение**. Блок пересинхронизации ролей в `update()` (после `$event->load('gameSettings')`) выполнялся безусловно для любого события с `gameSettings`: `$teams = max(2, min(teams_count ?? 2, 200))` — у king_beach `teams_count=0` → clamp до 2 → `GameCalculator::calculate('2x2', null, 2)` → `max_players=4` → перезаписывал реальное значение (например 20) на 4 при КАЖДОМ сохранении формы, независимо от того, трогал ли организатор количество игроков. Фикс: флаг `$staysKingBeach = $event->registration_mode === 'king_beach' && empty($data['tournament_individual_reg'])` (вычисляется ДО транзакции, передаётся в неё через `use`) — если true, вместо GameCalculator-пересчёта пишет `EventGameSetting.min_players/max_players` напрямую из `king_beach_min/max_players` + `syncRoleSlots(['player' => max])`; `EventTournamentSetting.team_size_min/total_players_max` тоже берутся напрямую (не из общей формулы `team_size_min+reserve`, у king_beach нет резерва/команд).
- **Поля min/max игроков на форме РЕДАКТИРОВАНИЯ** (`event_management_edit.blade.php`, `$isKingBeachEdit = $event->registration_mode === 'king_beach'`) — показываются ТОЛЬКО для уже-king_beach событий, вместо (не для них скрываемых через `@unless($isKingBeachEdit)`) полей «Кол-во команд»/«Состав команды»/«Запасных» (не имеют смысла у king_beach). Валидация в `update()` ДО транзакции, жёсткий запрет (не warning): `min>=4`, `max>=min`, при `gender_policy=mixed_5050` — **расширенное правило чётности**: ОБА поля (min И max) должны быть чётными (форма создания проверяла только max — тоже расширена, см. ниже), и live-COUNT защита: `max` нельзя опустить ниже максимума активных регистраций по всем ещё не начавшимся occurrences серии (`event_registrations JOIN event_occurrences WHERE starts_at >= now()`, GROUP BY occurrence_id, берём MAX — не позволяет обойти защиту через один малолюдный тур при переполненном другом).
- **Форма СОЗДАНИЯ king_beach — чётность min тоже добавлена** (`EventCreateValidator`, было только max): generic-проверка `mixed_5050 && $max>0` теперь пропускает king_beach (`!$isKingBeach`), т.к. king_beach получил свой блок с обеими проверками (min и max) и специфичными ключами `events.king_beach_min/max_players_parity_error` — раньше min не проверялся вообще, и был мёртвый неиспользуемый ключ `events.king_beach_parity_error` (заведён, но никогда не подключён к коду).

## Турнирная система v2.1
- Форматы classic: round robin, groups+playoff, single elimination, swiss
- Форматы beach: pool play, king of court, double elimination, thai, swiss
- WinRate на 4 уровнях: матч, турнир, серия, общий
- 8 новых таблиц: tournament_stages, tournament_groups, tournament_group_teams, tournament_matches, tournament_standings, player_tournament_stats, player_career_stats
- Полный план: tournament_plan_final.md (project files)

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
- **Уборка устаревших записей** (`waitlist:cleanup-expired`, `dailyAt('04:15')` в `routes/console.php`): occurrence_waitlist никогда не чистился после прохождения occurrence — 17 мусорных строк накопилось за апрель-июнь 2026. Удаляет записи где `occurrence.starts_at < now() - config('waitlist.cleanup_expired_days')` (default 7 дней запаса). `--dry-run` для проверки без удаления
- **Аудит других таблиц на такой же мусор** (привязанные к occurrence, никем не читаемые после прохождения тура): `event_channel_messages` (33/33 строки старше 7 дней на dev) — кандидат похожей проблемы, НЕ вычищается; отложено, нужно отдельное решение (хранит `external_message_id` для редактирования анонса, неясно нужна ли история). Остальные occurrence_id-таблицы — либо core-история, которую хранить нужно всегда (`event_registrations`, `event_teams`, `promotion_history`, `team_substitutions`, `tournament_stages`, `player_tournament_stats`, `event_occurrence_stats`, `court_bookings`), либо пока пустые (`activity_record_prompts`, `event_occurrence_trainers`)
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

### Таблицы
- `activity_sessions` — одна запись на тренировку: avg/max/min/hr, duration_sec, load_score, **calories_kcal** (decimal 7,2, null), time_in_zone (jsonb), samples_count, **jump_count** (int, default 0), **jump_avg_height_cm** / **jump_max_height_cm** (decimal 5,1, null), **tracked_capabilities** (jsonb, null)
- `activity_hr_samples` — сырые сэмплы (session_id, t_offset_sec, bpm); uq constraint → idempotent ingest
- **`activity_jump_events`** — прыжки (session_id FK cascade, t_offset_sec int, height_cm decimal 5,1 null, type varchar null); `$timestamps=false`; unique(session_id, t_offset_sec) → idempotent ingest
- `athlete_devices` — зарегистрированные BLE-устройства пользователя; поле `protocol` определяет capabilities
- `athlete_profiles` — resting_hr, max_hr, weight_kg (нужен для калорий), **reach_classic_cm** / **reach_beach_cm** (smallint null); классика — в обуви, пляж — босиком
- **`user_consents`** — append-only согласия: user_id, type ('health_activity'), document_version, locale, accepted_at; index (user_id, type)

### Конфиг (config/activity.php)
- `recording_open` (env ACTIVITY_RECORDING_OPEN, по умолч. false) — запись открыта только для админов
- `consent_version` (env ACTIVITY_CONSENT_VERSION, по умолч. '2026-06-21')
- **`device_capabilities`** — маппинг protocol → capabilities:
  - `ble_hrp` → `['hr']` (стандартный BLE HR-пояс, прыжки не меряет)
  - `healthkit` / `polar_sdk` / `health_connect` → `['hr', 'jumps']`
- `default_capabilities` → `['hr']`
- **КРИТИЧНО**: перед добавлением UI прыжков — всегда проверять `tracked_capabilities`, не `protocol` напрямую

### Capabilities-паттерн
- `AthleteDevice::capabilities()` → `config('activity.device_capabilities')[$this->protocol] ?? default`
- `ActivitySessionService::start()` фиксирует `tracked_capabilities = $device->capabilities()` в момент старта сессии; если устройство не задано — `default_capabilities`
- Возможности сессии неизменны после старта (устройство можно переключить, но сессия уже зафиксирована)
- Фильтр jumps-сессий в PostgreSQL: `whereRaw("tracked_capabilities::jsonb @> '\"jumps\"'")`

### Коэффициент высоты прыжка (jump_height_coeff)
- Конфиг: `activity.jump_height_coeff` — map protocol → float|null; `activity.jump_height_coeff_default = 0.55`
  - `healthkit` = 0.533, `health_connect` = 0.533, `polar_sdk` = null (откалибровать позже)
- Колонка: `athlete_profiles.jump_height_coeff` DECIMAL(4,3) NULL — личный коэффициент атлета
- Сервис: `AthleteProfileService::effectiveJumpCoeff(User, ?AthleteDevice): float`
  - Приоритет: личный (`athlete_profiles.jump_height_coeff` не NULL) → конфиг по протоколу (не null) → дефолт 0.55
- **POST /api/activity/sessions** возвращает `jump_height_coeff` в ответе рядом с `session_id` — клиент использует его для конвертации акселерометра в высоту
- Новый протокол? — добавлять в `config/activity.php` в оба ключа (`device_capabilities` + `jump_height_coeff`)

### Прыжки (C)
- `ActivitySessionService::ingestJumps($session, $jumps)` — идемпотентный приём батча через `insertOrIgnore` по uq(session_id, t_offset_sec)
- `finalize()` агрегирует jump_count/avg/max из `activity_jump_events` за раз (один SQL)
- **heightTrend($session)**: сравнивает `jump_avg_height_cm` с последними 5 завершёнными сессиями того же `user_id`+`direction`, у которых `tracked_capabilities @> 'jumps'`; HR-only сессии ИСКЛЮЧЕНЫ из тренда
  - Возвращает `['first' => true]` если нет предыдущих jumps-сессий
  - Или `['avg_prev' => float, 'delta' => float, 'label' => 'higher'|'lower']`
- Высоту показываем как ТРЕНД, не абсолютные сантиметры
- **Hitting reach** (≈ reach + jump_max): вычисляется на стороне JS из `config.reachClassicCm`/`reachBeachCm` + `data.jump_max_height_cm` из finalize-ответа

### Согласие (A)
- `User::hasHealthConsent()` — проверяет наличие строки в `user_consents` с type='health_activity' И текущей `consent_version`
- При бампе `consent_version` в .env все пользователи должны переподписать (true → false)
- `POST /api/activity/consent` → `ActivityConsentController::store()` — идемпотентно, пишет только если нет записи с текущей версией
- Блейд `record.blade.php`: если `!hasHealthConsent` — показывает блок с чекбоксом ПЕРЕД кнопкой подключения; по отметке → AJAX POST к API → скрывает блок
- JS: `connectSensor()` в начале проверяет `config.hasHealthConsent`; без согласия — показывает блок, не идёт дальше

### Калории (B) — формула Keytel 2005
- Сервис: `ActivityCalorieService::keytelKcalPerMin(hr, weightKg, age, gender)`
  - M: EE(кДж/мин) = -55.0969 + 0.6309×hr + 0.1988×weight + 0.2017×age
  - F: EE(кДж/мин) = -20.4022 + 0.4472×hr - 0.1263×weight + 0.074×age
  - kcal/min = EE/4.184; max(0, value)
- `finalize()`: каждый сэмпл = 1 сек → ккал += keytelKcalPerMin(bpm,...)/60; если нет weight/birth_date/gender → calories_kcal=null
- **gender**: 'm'/'f' (не 'male'/'female')
- Итоги: если null → ссылка «Укажите вес в настройках»

### Маршруты API (auth:sanctum,web)
- `POST /api/activity/consent` — принять согласие
- `POST /api/activity/devices` — зарегистрировать BLE-устройство
- `POST /api/activity/sessions` — начать сессию
- `POST /api/activity/sessions/{id}/samples` — батчевый приём сэмплов (idempotent)
- `POST /api/activity/sessions/{id}/jumps` — батчевый приём прыжков (idempotent)
- `POST /api/activity/sessions/{id}/finalize` — завершить; возвращает avg/max/min/load/calories/zones + tracked_capabilities, direction, jump_count/avg/max, jump_trend

### JS (resources/js/ble-activity.js)
- Capacitor `@capacitor-community/bluetooth-le` + `@capacitor-community/keep-awake`
- Flush сэмплов каждые 10 сек; при разрыве — reconnect до 10 попыток
- Если не в Capacitor (браузер) — скрывает управление, показывает alert
- **`renderJumpSummary(data)`** — capability-aware: проверяет `data.tracked_capabilities.includes('jumps')`
  - `true` → показывает `#ble-sum-jumps-block` (счётчик + тренд + hitting reach)
  - `false` → показывает `#ble-sum-jumps-not-tracked` («Этот датчик не отслеживает прыжки»); «0 прыжков» НИКОГДА не показывается для HR-only датчиков
- Цвет тренда: зелёный (#4caf50) если higher, красный (#f44336) если lower, серый при first
- `direction` берётся из `data.direction` (finalize-ответ), reach — из `config.reachBeachCm` или `config.reachClassicCm`
- `window.__activityConfig` расширен: `reachClassicCm`, `reachBeachCm`, `jumpI18n` (объект с ключами из `lang/*/activity.php`)

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
- При ручной отмене записи ограниченного игрока через скрипт: установить `is_cancelled=true`, `cancelled_at=now()`, `status='cancelled'`; вызвать `EventOccurrenceStatsService::decrement()`, `WaitlistService::onSpotFreed()`, `UserNotificationService::createRegistrationCancelledByOrganizerNotification()`
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

## Клубный модуль — таймлайн локации (TimelineService)
- Файл: `app/Services/TimelineService.php`; рендер: `resources/views/club/_partials/timeline.blade.php` — общий partial, подключается и на `locations/show.blade.php` (владелец локации), и на `club/bookings.blade.php` (список локаций владельца; select локации если >1, дефолт первая). НЕ дублировать разметку/JS таймлайна в других местах — только `@include`
- `timeline.blade.php` принимает `$locations` (Collection, непустая) + опционально `$showAddButton` (по умолчанию true; на club/bookings передаётся false, т.к. кнопка "Добавить бронь" уже есть в тулбаре страницы). URL таймлайна строится в JS как `/locations/{id}/timeline` без `route()` — паттерн роута не требует slug, безопасно шаблонизировать на клиенте при переключении локации
- **`EventOccurrence.ends_at` НЕ существует как колонка** — вычисляется accessor'ом `getEndsAtLocalAttribute()` из `starts_at + duration_sec`. При ручном создании occurrence (тесты/скрипты) обязательно указывать `duration_sec`, иначе `ends_at_local` вернёт `null` и occurrence молча выпадет из `TimelineService::fetchDirectionSlots()` (там `if (!$startsLocal || !$endsLocal) continue;`) — без исключения, без лога, просто 0 слотов
- **`EventOccurrence.timezone` / `Event.timezone`** — если не задать явно при создании (тесты/скрипты), в БД остаётся дефолт `'UTC'`, а `starts_at_local`/`ends_at_local` считают локальное время ОТНОСИТЕЛЬНО ЭТОГО поля (`$this->timezone ?: $this->event?->timezone ?: 'UTC'`), НЕ относительно локации/`effectiveTimezone()`. Забытый `timezone` → все local-time расчёты (клампинг таймлайна, аналитика загрузки) молча съезжают на разницу с UTC вместо честной ошибки
- **Событие вне рабочих часов направления ломает сетку**: JS считал `top`/`height` блока от РЕАЛЬНОГО `starts_at`/`ends_at`, а `dayStart`/`dayEnd` сетки — от `opens_at`/`closes_at`. Если событие начинается раньше `opens_at` (напр. турнир в 07:00 при открытии в 08:00) — `top` уходил в отрицательные px, блок вылезал над сеткой и перекрывал заголовки кортов. Фикс: клампить `startMin`/`endMin` в `[dayStart, dayEnd]` перед расчётом позиции; событие целиком вне часов — не рендерить (`return` из forEach); если реальное время обрезано — показывать пометку «с HH:MM» / «до HH:MM» внутри блока (ключи `club.timeline_clamped_from/until`)
- **Турнир без court_booking_id красит ВСЕ корты направления** — это осознанное поведение Фазы 2 для событий без явной привязки к корту, НО для турниров (`format=tournament`) есть точная привязка на уровне матчей: `tournament_matches.court` — это **имя корта строкой** (не court_id!), проставляется при жеребьёвке/расстановке. `TimelineService::tournamentCourtIds()` собирает distinct `court` по матчам стадий данной `occurrence_id`, сопоставляет по имени с `location_courts.name` направления и, если нашлись совпадения, показывает событие ТОЛЬКО на этих кортах (по одному слоту на court_id) вместо заливки всего направления. Если у турнира нет матчей с проставленным `court` (старые данные / ещё не жеребьёвка) — падает обратно на legacy-поведение (все корты направления)
- `tournament_groups.courts` (JSON массив имён кортов) и `tournament_stages.config->courts` — это ПЛАН/настройка стадии (что выбрал организатор при создании), а не факт; источник истины для таймлайна — `tournament_matches.court` (реальное назначение конкретного матча)
- **Режим «Список» в блоке таймлайна** — раньше был декоративной кнопкой (`showList()` просто скрывал `#timelinePanel`, ничего не рендерил; дефолтные CSS-классы кнопок делали «Список» визуально активным при пустом экране). Теперь `state.view` ('list'|'timeline') управляет тем, что показывать внутри всегда видимого `#timelinePanel`; список строится из тех же данных `day()`, что и дневная сетка (`directions→courts→slots`), с дедупликацией по `occurrence_id`/`booking_id` (события без court_id дублируются по всем кортам направления в исходных данных)
- **`.booking-modal-content` (модалка добавить/редактировать бронь)** — max-width задавала ширину КОНТЕЙНЕРА (68rem на десктопе), но `.form input/select{width:100%}` внутри `.fancybox-content` (inline-block, shrink-to-fit) заставляет браузер использовать max-width как preferred width блока → контейнер всегда ровно max-width шириной, даже если реальным полям нужно меньше. Уменьшен до 48rem (~480px); на `max-width:768px` — `max-width:100%;width:100%`, дальше ограничивает сам `.fancybox-content` (`max-width:94%` + `padding` в rem, уже масштabируется вниз через `html{font-size}` брейкпоинты)
- **Надёжный способ задать размер fancybox-модалки — `opts.baseClass`**: задавать `max-width`/`max-height` на вложенном контент-диве (`.booking-modal-content`) ненадёжно — при `width:100%`-полях внутри и `display:inline-block` у `.fancybox-content` браузер то берёт max-width вложенного блока как preferred width родителя (слишком широко), то не пересчитывает высоту после смены состояния формы без явного `.update()` (пустое место снизу). Правильный путь: `jQuery.fancybox.open({..., opts:{baseClass:'my-modal-class', ...}})` — fancybox добавляет этот класс на `.fancybox-container` (проверено в исходнике fas.js: `.addClass(r.baseClass)`), дальше стилизовать сам `.fancybox-content` через `.fancybox-container.my-modal-class .fancybox-content{max-width:...;max-height:90vh;overflow-y:auto}` — размер жёстко привязан к реально видимому окну, а не к косвенному дочернему блоку. Применено для `booking-modal-fancybox` (добавление/редактирование брони)
- **`refreshFancyboxSize()` (`inst.update()`) нужно звать при КАЖДОМ изменении, влияющем на высоту контента модалки**, не только при очевидных (тумблер клиента, чекбокс повторения) — `fillCourts()` (пересборка чекбоксов кортов при смене локации/направления, разное число кортов = разная высота блока) исходно не вызывала `refreshFancyboxSize()`, хотя явно меняла высоту формы уже открытой модалки
- **Заголовок колонки корта в таймлайне (`.timeline-court-header`) — длинные названия переносились на 2 строки** и наезжали на сетку под шапкой, т.к. высота шапки (24px, используется в JS-константе `HEADER_OFFSET=50`) рассчитана на одну строку. Фикс: `white-space:nowrap;overflow:hidden;text-overflow:ellipsis` + `title`-атрибут с полным названием на элементе — высота шапки не меняется (осталась 24px), значит `HEADER_OFFSET` в JS менять не нужно

## Клубный модуль — аналитика (ClubAnalyticsService)
- Роут: `GET /club/analytics` → `ClubAnalyticsController::index`; доступ как у `ClubBookingController` (`is_club_manager || isAdmin()`); меню-пункт «Аналитика» показывается строже — только `is_club_manager && ownedLocations()->exists()` (3 места: `dashboard/org.blade.php`, `profile/_menu.blade.php`, `components/voll-layout.blade.php` — рядом с «Брони кортов» в каждом)
- Сервис: `app/Services/ClubAnalyticsService.php`. Ключевое архитектурное решение — **переиспользовать `TimelineService::day()`** для числителя загрузки (часы занятости), а не писать вторую независимую реализацию привязки турнира к корту/резолва "событие без court_booking_id = вся сетка направления". `forLocation()` крутит цикл по каждому календарному дню периода, дергая `day()` и клампя каждый slot в `[opens_at, closes_at]` направления (та же логика клампинга, что в JS-рендере таймлайна). `pending`-брони исключаются из числителя (ещё не гарантированная занятость), `confirmed`/`paid` — считаются
- **Знаменатель** (доступные часы) — сумма `closes_at - opens_at` по факту вызовов `day()` за все дни периода (не выходные не учитываются, `is_day_off` уже фильтруется внутри `day()`, направление в этот день просто не появляется в результате)
- **Выручка** — отдельный агрегирующий SQL-запрос (НЕ через day()-цикл, т.к. price_total не требует клампинга по рабочим часам): `SUM(price_total) GROUP BY court_id, payment_mode` за период, только `confirmed`/`paid`. `payment_mode='prepaid'` → бакет "оплачено онлайн", `on_site`/`trusted` → "на месте". На практике **все ручные брони владельца клуба (`storeManual`) всегда получают `payment_mode=on_site`** независимо от выбранного статуса (confirmed/paid) — `prepaid` появляется только у самостоятельных броней игрока платформы с `trust_level=prepaid_only` (Фаза 3); онлайн-эквайринг ещё не подключён (Фаза 4), так что бакет "оплачено онлайн" будет пустым почти на всех текущих клубах — ожидаемо, не баг
- **"Средняя загрузка"** направления/центра — невзвешенное среднее `occupancy_pct` по кортам (не отношение суммарных часов), простое и предсказуемое определение "средней загрузки кортов" из ТЗ
- **Performance-компромисс**: цикл по дням периода вызывает `TimelineService::day()` — метод НЕ переиспользует уже загруженные relations модели `$location` (внутри всегда свежий `$location->directions()->...->get()`), т.е. на "Год" (365 дней) это 365 повторных запросов направлений+кортов+рабочих часов + events/bookings запросы на каждый день/корт. Для месяца/квартала это ощутимо не медленно, для года/полугодия может быть заметно на медленной БД — если станет реальной проблемой, следующий шаг оптимизации: заменить цикл на арифметику по дням недели (знаменатель) + один batched SQL по броням/событиям с группировкой по локальной календарной дате (числитель), без похода в TimelineService
- **Тестирование сервиса**: `php artisan tinker` не работает (см. общее правило) — тестировать через `DB::beginTransaction()` + создание синтетических Location/LocationDirection/LocationWorkingHour/LocationCourt/CourtBooking + `DB::rollBack()` в `finally`, скрипт вида `/tmp/test_*.php` с `bootstrap/app.php`. Подтверждено вручную: корт 8:00-23:00 (15ч/день, без выходных), 10 броней по 2ч за месяц (31 день) → 20ч / 465ч = 4.3% — сошлось с сервисом день-в-день

## Клубный модуль — Фаза 5: прямая бронь корта игроком
- **Вход** — кнопка «🏐 Забронировать корт» на `locations/show.blade.php` (рядом с адресом, НЕ в блоке таймлайна — тот виден только владельцу). Условие показа: `$location->owner_id` задан И есть хотя бы одно активное направление — вычисляется один раз в `@php` в начале файла (`$canBookCourt`), там же теперь общий `$location->load(['directions'=>...])` (раньше грузился только внутри `$canManageTimeline`, теперь переиспользуется для обоих случаев — один запрос вместо двух)
- **Анонимным** — кнопка ведёт на `route('login', ['return' => $location->public_url])`. **КРИТИЧНО**: в проекте НЕТ формы логина по email/паролю (`resources/views/auth/login.blade.php` — только OAuth-кнопки Apple/VK/Yandex/Google/Telegram + биометрия). Параметр называется `return` (НЕ `return_to`!) — читается в login.blade.php как `request()->query('return')`, приоритет над `session('url.intended')`. Используется тот же паттерн, что и в соцкнопках логина
- **`CourtBookingService::createByPlayer()`** — НЕ использует `ClubOrganizerTrust` (тот только для организаторов, настраивается владельцем per-organizer). Игрок всегда получает `status=pending, payment_mode=on_site, expires_at=null` независимо от того, что вернул бы trust-лукап — так и задумано до подключения YooKassa (следующая фаза). Доп. проверки внутри метода: локация клубная (`owner_id` задан), не более 30 дней вперёд, не более 3 активных pending-броней ОДНОГО игрока **на локацию** (не на корт — `whereHas('court.direction', fn($q)=>$q->where('location_id',...))`)
- **`GET /locations/{id}/booking-windows` (`Ajax\CourtBookingWindowsController` + `CourtAvailabilityService::windowsForDuration()`) уже privacy-safe "из коробки"** — этот endpoint изначально сделан для формы создания события (organizer подбирает время под платформенную бронь, `events/_partials/create/step2.blade.php` + `public/js/events-create.js` — блок `club_booking_grid`, паттерн визуала переиспользован для игрока) и НИКОГДА не возвращал детали занятых слотов (ни имён, ни названий) — только список СВОБОДНЫХ окон с ценой. Переиспользован для игрока без изменений; проверено тестом (создана бронь с "секретным" названием и именем клиента — в ответе endpoint'а не встречается ни то, ни другое, занятый слот просто отсутствует в списке свободных)
- **Уведомления игроку при confirm/reject — их НЕ было**: `CourtBookingService::confirm()`/`reject()` только меняют статус, `ClubBookingController::confirm()`/`reject()` не вызывали `UserNotificationService` вообще (в отличие от `update()`/`cancel()`, которые это уже делали). Добавлены `UserNotificationService::createCourtBookingConfirmedNotification()` / `createCourtBookingRejectedNotification()` (без миграции/`notification_templates` — по прямому прецеденту соседних `court_booking_changed`/`court_booking_cancelled`, у которых тоже нет template-записи, работают через fallback title/body в `create()`) + вызовы в контроллере после успешного `confirm()`/`reject()`
- **`locations.booking_cancel_hours`** (миграция `2026_07_06_000001_add_cancel_hours_to_locations`, default 24) — за сколько часов до начала игрок ещё может отменить бронь сам. Поле только в `admin/locations/{id}/edit` (admin-only роут, `can:is-admin`) — club-owner self-service страницы настроек нет, задача явно указывала именно эту форму. `CourtBookingService::cancelByUser()` теперь проверяет `now() < starts_at - booking_cancel_hours`, иначе `club.cancel_deadline_error`
- **`GET /my/bookings` (`player.my-bookings`, `PlayerCourtBookingController::myBookings`)** — активные (`pending`+`confirmed`+`paid`, `ends_at >= now()`) и история (остальное) брони ТЕКУЩЕГО игрока (`user_id`), НЕ владельца локации (это отдельная страница от `/club/bookings`). Пункт меню «Мои брони» добавлен БЕЗ условия (всегда виден авторизованным) в 3 места: `profile/_menu.blade.php` (внутри ДВУХ разных `<nav>`-блоков — org/admin-ветка и обычный пользователь, у них разное форматирование/отступы, `replace_all` не схватывает оба сразу — проверять оба вручную), `components/voll-layout.blade.php` (верхнее меню, рядом с «Мои мероприятия»)

## Карточка шаринга матча (Browsershot) — размещение Chrome
- `TournamentController::shareCard()` (`GET /tournament-matches/{match}/share-card`) генерирует PNG 1200x630 (retina ×2 → 2400x1260) через `spatie/browsershot`, кеш на диске `storage/app/public/share-cards/match-{id}.png`, инвалидация по `$match->updated_at` vs `filemtime()`
- **КРИТИЧНО — Chrome НЕЛЬЗЯ ставить через `npx puppeteer browsers install chrome` от имени `appuser`**: он ложится в `~/.cache/puppeteer` = `/home/appuser/.cache/puppeteer`, а PHP-FPM работает под `www-data`. `/home/appuser` имеет права `750` (rwxr-x---, группа `appuser`) — `www-data` не входит в группу `appuser`, поэтому не может даже зайти в директорию, независимо от прав самого бинарника. Симптом: Browsershot падает с `Could not find Chrome... your cache path is incorrectly configured (which is: /var/www/.cache/puppeteer)` — путь в ошибке не совпадает с реальным `~/.cache/puppeteer`, это сбивает с толку (ошибка от лица www-data, у которого HOME=/var/www)
- **Правильное место**: `storage/app/chromium/<version>/chrome-linux64/chrome` — `storage/app` уже `www-data:www-data` + setgid (`drwxrwsr-x`), а `appuser` состоит в группе `www-data` (проверено: `groups appuser` → `appuser sudo www-data users`), так что `appuser` может туда копировать, `www-data` — читать/исполнять. Установка: `npx puppeteer browsers install chrome` (в любом временном месте) → `cp -r <cache>/chrome/<version> storage/app/chromium/` → `chmod -R g+rX storage/app/chromium`
- Контроллер ищет бинарник через `glob(storage_path('app/chromium/*/chrome-linux64/chrome'))` — версия не хардкодится, при обновлении Chrome просто кладём новую папку рядом
- `storage/app/.gitignore` содержит `*` — бинарник Chrome (~277MB) НЕ коммитится и НЕ деплоится через git; на каждом окружении (dev/prod) нужно повторить установку в `storage/app/chromium/` вручную
- Подтверждено тестом на dev: генерация ~1.6-2.3с, кеш-хит ~0.2с, инвалидация по `updated_at` работает, файл создаётся с владельцем `www-data:www-data`

## Клубный модуль — Фазы 4+6: оплата брони через ЮKassa + уведомления в боты
- **КРИТИЧНАЯ находка перед реализацией**: организаторский флоу оплаты ЮKassa за мероприятия (`payment_method='yoomoney'`) был ТОЛЬКО наполовину реализован — `PaymentService::createForRegistration()` создавал локальную запись `Payment` (pending+expires_at), но НИГДЕ не было кода, который реально вызывает `YooKassa\Client::createPayment()` с ключами организатора (`payment_settings.yoomoney_shop_id/secret_key`) — `yoomoney_confirmation_url` никогда не заполнялся. Единственный РЕАЛЬНО работающий вызов ЮKassa API в проекте — `YookassaService::createAdPayment()` для рекламных событий, и тот использует ключи ПЛАТФОРМЫ (`PlatformPaymentSetting`, одна запись на всех), а не организатора. `PaymentController::yoomoneyWebhook()` также не верифицировал платёж через API (комментарий `"можно добавить проверку"` — недоделанный TODO), в отличие от ad-event вебхука, который переспрашивает статус через `getPaymentInfo()`. Для брони корта эта логика написана с нуля (см. ниже), заодно исправлена верификация вебхука для ОБОИХ путей (event-registration и court-booking) — теперь оба обязаны подтвердить статус через `YookassaService::verifyPayment()` (реальный API-вызов), не доверяя телу вебхука напрямую
- **`payments.court_booking_id`** (миграция `2026_07_06_000002`, nullable FK) — по аналогии с существующими `team_id`/`team_member_id`: одна и та же таблица `payments`/`Payment` модель обслуживает и мероприятия, и команды, и теперь брони кортов. `Payment.organizer_id` для брони = `location.owner_id` (не «организатор события») — так резолвится `PaymentSetting` в вебхуке и во всех местах возврата
- **`YookassaService`** — добавлены методы поверх существующего `makeClient()` (платформенный, для ad-event): `makeClientFor(PaymentSetting)` — клиент на ключах ЛЮБОГО организатора/владельца локации; `createBookingPayment()` — реальный `createPayment()` с `metadata.type=court_booking`; `verifyPayment()` — переспрос статуса через `getPaymentInfo()` (тот же паттерн, что и `handleWebhook()` для ad-event, теперь переиспользован для всех yoomoney-платежей); `createRefund()` — НАСТОЯЩИЙ возврат через SDK `Client::createRefund()` (в SDK `yoomoney/yookassa-sdk-php` v3.13.1 есть, ранее не использовался нигде в проекте)
- **`PaymentService::refund()` (старый метод, для мероприятий) — это НЕ настоящий возврат**, а зачисление на `VirtualWallet` (внутренний кошелёк платформы). Для брони корта это неприменимо (игрок реально платил картой через ЮKassa владельца локации) — добавлен отдельный `PaymentService::refundBooking()`, вызывающий `YookassaService::createRefund()` и переводящий `Payment.status='refunded'` по факту реального возврата
- **`CourtBookingService::createByPlayer()`** — теперь проверяет `canPrepayOnline($location->owner_id)` (= `payment_settings.payment_for_rentals && isYoomoneyReady()`): если true → `payment_mode=prepaid`, TTL 30 мин (как у organizer-флоу с `prepaid_only` trust); если false → как раньше, `on_site` без TTL. Organizer-флоу (`create()`, `prepaid_only` trust) уже создавал prepaid-брони и раньше, но платёж не создавался нигде — теперь и организатор, и игрок оплачивают через ОДИН и тот же `POST /my/bookings/{booking}/pay` (`PlayerCourtBookingController::pay()`), т.к. `/my/bookings` фильтрует по `user_id`, а не по способу создания брони — бронь организатора (созданная при публикации события) тоже попадает в его личный `/my/bookings`
- **Платёж создаётся ЛЕНИВО (по клику «Оплатить»), не сразу при создании брони**: `PaymentService::createForBooking()` — идемпотентен (переиспользует существующий pending-платёж с непустым `confirmation_url`, если не истёк, вместо создания дубля в ЮKassa при повторном клике/возврате на страницу)
- **Возврат при отмене — ВАЖНО: сам API-вызов ЮKassa делается ПОСЛЕ коммита отмены брони, не внутри той же транзакции**: если `createRefund()` бросит исключение (гейт недоступен/невалидные ключи), бронь ВСЁ РАВНО должна отмениться — игрока/владельца нельзя блокировать сбоем платёжного шлюза. И `cancelByUser()`, и `cancel()` (клуб) сначала коммитят статус `cancelled` в отдельной транзакции, затем пытаются вернуть деньги в `try/catch` (при ошибке — `Log::error`, `refunded=false`, но отмена уже состоялась). Проверено тестом: возврат с фейковыми ключами кидает `TypeError` (SDK строго типизирует `$login` как `?int`, т.е. shop_id обязан быть числовой строкой) — бронь всё равно отменяется
- **Политика возврата — на уровне ЛОКАЦИИ, не организатора**: `locations.refund_policy` (`full`/`none`, default `full`) + `refund_deadline_hours` (default 24) — миграция `2026_07_06_000003`, форма в `admin/locations/{id}/edit` рядом с `booking_cancel_hours` (тот же admin-only паттерн). Отмена КЛУБОМ оплаченной брони — возврат ВСЕГДА, `refund_policy` не проверяется (клуб отменил — деньги возвращаются). Отмена ИГРОКОМ — возврат только если `policy=full` И `now() <= starts_at - refund_deadline_hours`; иначе просто отмена без возврата (в UI перед подтверждением — предупреждение через `CourtBookingService::refundWouldApply()`, вызывается из blade напрямую построчно, не оптимизировано под большие списки, но список личных броней короткий)
- **`court_bookings.reminded_24h_at`/`reminded_2h_at`** (миграция `2026_07_06_000004`) — дедупликация напоминаний, тот же паттерн, что и `tournament_matches.notified_upcoming_at` (см. раздел «Laravel 12 schedule» выше): бизнес-поле НЕ подходит как флаг «уже напомнили», нужна отдельная колонка-таймстамп
- **Новый scheduler `remind-court-bookings`** (`routes/console.php`, `everyFifteenMinutes`) — окно `now()+N часов ± 7.5 мин` (половина шага расписания, чтобы каждая бронь попала ровно в один прогон, без пропусков и дублей на границах). Существующий `expire-court-bookings` переписан с массового `update()` на поштучный `->each()` — нужно было вызвать уведомление на каждую истёкшую бронь (`createCourtBookingExpiredNotification`), массовый Query Builder update этого не позволяет
- **5 новых типов уведомлений** в `UserNotificationService` (`court_booking_requested/paid/expired/refunded/reminder`), все с `channels: ['in_app','telegram','vk','max']` — БЕЗ миграции/`notification_templates`, по тому же прецеденту, что и 4 существующих `court_booking_*` (fallback title/body в `create()`). **Поправка к устаревшему предположению**: 4 старых уведомления (`changed/cancelled/confirmed/rejected`) уже И ТАК уходили в боты (не только in_app) — только 5 НОВЫХ событий (заявка/оплата/истечение/возврат/напоминание) реально отсутствовали в коде (`CourtBookingService::notifyOwner()` была буквальной заглушкой с логом, без вызова `UserNotificationService`)
- **`payment_settings.yoomoney_secret_key` хранится в открытом виде** (не `encrypt()`), в отличие от `platform_payment_settings.yoomoney_secret_key`, который шифруется в `AdminPlatformPaymentController`. Существующая асимметрия в коде, не трогал (не в скоупе задачи) — читается как есть, без `decrypt()`
- **На dev/prod нет ни одного реального yoomoney_shop_id/secret_key** (ни у платформы, ни у организаторов) — боевой платёж протестировать негде. Тестировал через `DB::beginTransaction()`+фейковые ключи: реальный API-вызов кидает исключение на попытке аутентификации (ожидаемо и корректно обрабатывается через `catch (\Throwable)` — в т.ч. `TypeError`, не только `Exception`), вебхук с фейковым payload не помечает платёж оплаченным, пока `verifyPayment()` не подтвердит через API (что и требовалось — не доверять телу вебхука)
