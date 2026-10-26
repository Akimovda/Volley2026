# Турниры, рейтинги, лиги и сезоны, игра со статистикой

_Вынесено из CLAUDE.md 2026-10-23 без изменения содержания. Полный исходник: docs/archive/CLAUDE.full-2026-10-23.md_

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
- **Тот же баг `@json(...)` срабатывает и БЕЗ `__(...)` внутри** (найдено 2026-09-11, `tournaments/setup.blade.php`, блок ручного распределения по командам) — `@json($collection->map(fn($p) => ['id'=>..., 'label'=>trim(...) ?: (...), 'positionLabel'=>$p->x ? __(...) : null, ...]))`: 3+ поля в массиве, где хотя бы у одного значения есть вложенные скобки (`trim()`/`??`/`__()`), уже достаточно, чтобы регэксп-экстрактор аргумента директивы обрезал выражение раньше срока (симптом другой — `syntax error, unexpected token ";", expecting "]"` или `Unclosed '[' does not match ')'`, зависит от того, где именно оборвёт). Порог непредсказуем (пара похожих полей компилируется нормально, третье добавленное поле — ломает) — не пытаться угадать «сколько вложенности можно», просто ВСЕГДА собирать `->map(...)` в `@php $x = ...; @endphp` и передавать в `@json($x)` простую переменную, если в замыкании `map()` больше одного поля с вызовом функции/тернарником.
- **`php artisan view:cache` НЕ ловит эту ошибку** — команда репортует "Blade templates cached successfully" даже когда скомпилированный PHP реально невалиден (compileString — чистая текстовая трансформация, без `php -l` на результате); ошибка проявляется только на живом запросе (500). Надёжная проверка ПЕРЕД тем как считать правку рабочей: `app('blade.compiler')->compileString(file_get_contents($path))` → записать результат во временный файл → `php -l` на нём. `php -l` на самом `.blade.php` файле не работает вообще (это не чистый PHP).
- **`TournamentTeamNamingService`** (`app/Services/TournamentTeamNamingService.php`) — автогенерация названия команды, когда организатор оставил поле пустым: пляж → фамилии участников через `/` (`Иванов/Петров`), классика → случайное короткое название из пула прилагательное+существительное (`Дикие Бобры`); проверка уникальности в рамках `event_id+occurrence_id` (там реальный unique-индекс `event_teams_event_id_occurrence_name_unique`) с ретраями. Используется и в `distributeRandom()`, и в `TournamentTeamController::store()` (когда `registration_mode=tournament_individual` и имя не задано).
- **Setup-страница турнира (`tournaments/setup.blade.php`) — блок "Команды" для `tournament_individual`**: заголовок меняется на "Команды/Игроки (:n)" (`tournaments.setup_teams_h2_individual`), под списком команд показывается блок "Не распределены по командам (:n)" — все, кто зарегистрирован на occurrence (`event_registrations`), но ещё не состоит ни в одном `EventTeamMember` этого события/тура (`TournamentController::setup()` считает `$unassignedPlayers` через `whereNotIn('user_id', assignedUserIds)`). Форма "Создать команду" в этом случае вместо обычного автокомплита по всем пользователям (`/api/users/search`) показывает капитана через ЛОКАЛЬНЫЙ JS-фильтр по уже отрендеренному списку нераспределённых игроков (без похода в сеть — список маленький и известен на этапе рендера) + чекбоксы остальных участников (`member_user_ids[]`); при выборе капитана его чекбокс в списке блокируется и снимается. `TournamentTeamController::store()` после создания команды добавляет отмеченных участников через `TournamentTeamService::addMemberByOrganizer()` в цикле (без новых роутов).
- **Кнопка "➕ Добавить игрока" на setup.blade.php (добавлено 2026-09-06)**: до этого у организатора для `tournament_individual` была только "Создать команду" — не было способа записать нового игрока на occurrence (чтобы он затем попал в пул для ручного/случайного распределения), не уходя на отдельную страницу `/events/{id}/registrations`. Кнопка переиспользует существующий роут `events.registrations.add` (`EventRegistrationsManagementController::addPlayer()`, те же проверки — права организатора, лимит слота позиции, гендерная квота), автокомплит по всем пользователям через `/api/users/search` (та же фабрика `makeAC()`, что и у "Добавить в состав" лиги — вызов добавлен туда же, в тот же JS-блок, id набор `add-player-search`/`add-player-id`/`add-player-dd`/`add-player-ac-wrap`). Список позиций считает `TournamentController::setup()` (`$availablePositions`, только для classic + reserve если настроен) — **обязан** браться из того же метода, что и валидация на сервере, иначе select разойдётся с `addPlayer()`.
- **`EventRoleSlotService::resolvePositions()`** — публичный метод (перенесён из приватного дубля в `EventRegistrationsManagementController`, было 3 отдельных вызова в `index()`/`addPlayer()`/`updatePosition()`), единая точка правды для списка позиций классики (`4x2`/`4x4`/`5x1` + либеро) по всему проекту. Используется и в `TournamentController::setup()` для формы "Добавить игрока".
- **Баг (исправлен 2026-09-06): при восстановлении отменённой регистрации через `addPlayer()` уведомлению игроку передавался `occurrenceId: null`** вместо реального occurrence — ссылка в уведомлении (`event_url`/`button_url`) вела на мероприятие без выбранного тура (`route('events.show')` без `?occurrence=`), хотя сама регистрация сохранялась с верным `occurrence_id`. Ветка "новая регистрация" уже передавала `$occurrenceId ?: null` корректно — несогласованность исправлена по этому образцу.

## «Игра со статистикой» — матчи, счёт и статистика на обычных играх (2026-09-30)
- **Флаги** `events.collect_stats` (только `format=game`, классика и пляж; чекбокс в step1 создания и в `event_management_edit`) и `events.stats_rated` («Рейтинговое мероприятие», только вместе с collect_stats; по умолчанию false). Доступно всем организаторам (не Pro). Копируются при «создать на основе».
- **Движок переиспользован**, а не дублирован: `tournament_matches` / `match_player_stats` / `match_rally_events`, экраны ввода `tournament.matches.rally.form|score.form|player_stats.form`, красивые партиалы `match_stats_pretty`/`match_progress_fragment`. Доступ к вводу — `authorizeOrganizer` (владелец ИЛИ staff, `EventAccessService::canManageEvent`).
- **Служебная стадия** `TournamentStage::TYPE_FRIENDLY='friendly'` (один на occurrence, создаётся лениво в `FriendlyGameService::stageFor()`, конфиг `match_format` bo1/bo3/bo5 + `set_points`/`deciding_set_points`, редактируется на странице игры). НЕ входит в `TYPES`/`TYPE_TRAITS` — не предлагается в турнирных формах. Обязательна, т.к. `tournament_matches.stage_id NOT NULL`.
- **Составы на вечер меняются** → команды `EventTeam` создаются ПОД КАЖДЫЙ матч (имя «Красные · №3», уникально в рамках occurrence из-за unique-индекса; `meta.friendly=true`, `meta.color`), игроки — строго из подтверждённых `event_registrations` тура (включая добавленных организатором вручную — они тоже регистрации). Итоги вечера — **по игрокам** (`FriendlyGameService::leaderboard()`: победы → % побед → разница очков → очки в статистике), пьедестал = топ-3.
- **Страницы**: `/events/{id}/game?occurrence=` (`game.manage`, владелец/staff: составы сегментами «—/1/2», кнопки «разделить по уровню/случайно» (чистый JS), цвета, список матчей, «новый матч с теми же составами» `?from=`, формат матча) и публичная `/events/{id}/game/results?occurrence=` (`game.results`: пьедестал, таблица, матчи со статистикой и ходом матча; приватность — `EventVisibilityService`). Контроллер `FriendlyGameController`, сервис `FriendlyGameService`, вьюхи `resources/views/games/*`, lang `games.php` (RU/EN). На странице мероприятия (`events/show/description`): строки «Статистика матчей», «Рейтинговое мероприятие — да», кнопка «Результаты и статистика» (когда есть начатый матч/очки) и «Управление игрой» (владельцу/staff); на карточке — пилюля «Рейтинг» (`badge-rated`, только stats_rated).
- **Изоляция от турнирных последствий (критично)**: `TournamentController::score()` для friendly-стадии выходит в `afterFriendlyScore()` ДО пересчёта турнирной статистики/`checkStageCompletion`/перехода на setup; `redirectToSetup()` и back-ссылки в `score/score_rally/player-stats` возвращают на `game.manage`; `playerStatsSave` не агрегирует в PTS/career для нерейтинговых. **Elo/OpenSkill — полная переигровка ВСЕХ матчей платформы** (`TournamentEloService::rebuildAll`, `TournamentOpenSkillService::rebuildAll`, `RecalculateTournamentRatingsJob`) — поэтому везде, где матчи берутся для рейтингов/карьеры, добавлен скоуп `TournamentMatch::ratable()` (турнирные + friendly только при `events.stats_rated`). **Новый потребитель матчей для рейтингов обязан использовать `->ratable()`**, иначе обычные игры затянет в рейтинги. Для рейтинговых игр после счёта/удаления матча/смены флага в очередь уходит `RecalculateTournamentStatsJob`. Проверено в транзакции с откатом: нерейтинговая игра → 0 строк PTS и career не меняется; рейтинговая → строки и mu появляются; обратно → чисто.
- **Правки по тесту (2026-10-01)**: страница `/game` — блоки «Формат матча» и «Новый матч» объединены наверху; формы с `class="form"` (кастомные select, outline-кнопки `btn-outline`); под именем игрока — позиция регистрации (`FriendlyGameService::positions()`); после создания матча редирект на обычный ввод счёта (`score.form`, там баннер-агитация поочкового ввода), поочковый — вторая кнопка. **Пьедестал** (`leaderboard()['podium'] = ['type','items']`): если за вечер ≤3 разных составов (по набору игроков) — места занимают КОМАНДЫ (две команды → 1 и 2 место), иначе топ-3 игроков. Кнопка «Управление игрой» — в блоке «Регистрация участников» (`events/show/players`), иконка статистики — `menu-icon` `chart`.
- **Блок «Лучшие игроки по очкам»** (`leaderboard()['top_scorers']`, отдельно от пьедестала команд, и для вечера, и для серии): топ-3 по `match_player_stats.points_scored` (>0), рендер в `games/_board`.
- **Фаза 2 (2026-10-01)**: итоги по серии за период (`results?scope=series&period=all|30|90|365`, `FriendlyGameService::seriesLeaderboard()`, только игроки, без командного пьедестала); live-обновление публичной страницы — пока есть незавершённый матч, JS раз в 12 с подтягивает `#gm-live` (`data-live=1`) и подменяет, сохраняя раскрытые блоки; ссылки «Управление игрой» в таблице туров (`occurrences_table`, 📊) и в `/my/events` (`org_my_events`).

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
- **Страницы `/players/rating` и `/players/teams` (приведены к единому стилю 2026-10-02)**: направление (Пляжный/Классика), схема и сортировка — вкладки с подчёркиванием (`.filter-tabs`/`.filter-tab.active`, `<a>`, не кнопки); кнопки поиска («Обновить»/«Найти») — `btn btn-outline btn-small` (`btn-secondary` белый); числовые колонки таблиц — `text-center` и в `th`, и в `td`. Подпись схемы `5x1_libero` на странице — «5x1 с либеро» (`events.libero_word`); значение в URL/БД остаётся `5x1_libero`.
- **`/players/teams` — пляж и классика считаются РАЗНО**: `player_pair_stats` — попарная таблица (`updatePairStats()` пишет ВСЕ комбинации внутри команды: для команды из 6 человек 15 строк, `ON CONFLICT (player1_id, player2_id)` — одна строка на пару, direction/scheme перезаписываются последним матчем). Для пляжа (2 игрока) это и есть «связка» — колонка показывает двух игроков с аватарами (на ≤600px — каждый с новой строки). Для классики парная таблица бессмысленна, поэтому `PlayerRatingController::teamsClassic()` считает **команды по составу**: «команда» = одинаковый набор подтверждённых (`confirmation_status='confirmed'`) игроков `event_team_members` у `event_teams.team_kind='classic_team'` (≥2 игроков) в разных турнирах; статистика — завершённые матчи без тай-брейков по условию `TournamentMatch::ratable()` (`st.type <> 'friendly' OR ev.stats_rated`), агрегат одним SQL (`string_agg` состава → `GROUP BY roster`, подзапросы через `fromSub`/`joinSub`, `unionAll` home/away). Замена игрока = другая команда. Название — последней версии состава. Название команды — ссылка (`.team-roster-link`), по клику fancybox-окно с составом (аватар, ФИО, «капитан», амплуа) — данные отдаются как `@json($teamRosters)` (собираются в контроллере). Колонка называется «Связки/Команды» (`players.pair_or_team`) в обоих режимах.
- **Амплуа в окне состава** берётся из `position_code`, иначе из `role_code` (если это outside/opposite/middle/setter/libero/reserve); у большинства игроков в БД `position_code` пуст (только роли player/captain) — тогда амплуа в окне просто не показывается, это не баг.
- **Аватары в списках игроков**: `profile_photo_url` читает коллекцию `photos` по `avatar_media_id` (НЕ `avatar`!) — в циклах загружать `User::with('media')`, иначе N+1 (так сделано в `teams()`). `getFirstMediaUrl('avatar','thumb')` у пользователей возвращает пустую строку — не использовать.

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

## Расширенная статистика матча (страница `/matches/{match}/stats`, 2026-10-26, dev, не закоммичено)
- Файлы: `MatchStatsPageController` (show/pdf), `MatchAdvancedStatsService` (build/analyseServe), `PlayerMatchListService` (профиль), вьюхи `resources/views/matches/stats*.blade.php` + `matches/stats/_*.blade.php` (стили `mx-*` в `_css.blade.php`, общий для страницы и PDF), `lang/*/match_stats.php`, тест `tests/Unit/MatchServeAnalysisTest.php`.
- Доступ: турнир — публично; friendly-стадия — `collect_stats` + `isPrivateEventRow/canViewPrivateEvent` (как `FriendlyGameController::results`). 404, если нет событий розыгрышей; PDF — только `completed`. «Кто ввёл» — только `EventAccessService::canManageEvent`.
- **Подача**: подаёт победитель предыдущего розыгрыша (side-out) ⇒ для розыгрыша №2+ первый подающий НЕ нужен; он нужен только для №1 (`match_set_starts` → `match_serve_marks` → якорь в самом №1, иначе №1 исключён). Якоря: `ace` (подавал победитель), `opp_serve_error` (подавал проигравший). Сет надёжен, только если нет противоречий и есть ≥1 подтверждение; иначе исключён из «подача/приём» с подписью причины.
- Прод (2026-10-26): из 1480 якорей 46 (3%) противоречат side-out; 41 из них — события до 01.10.2026 (ввод «из головы» с паузами по минутам между очками), после — 5 из 317 (1,6%). Сетов без противоречий 102 из 135; матчей: 31 полностью, 22 частично, 4 без пригодных сетов.
- Кэш: `build()` для completed — `Cache` по (match_id, last_seq, updated_at, макс. updated_at серв-отметок); PDF — файл `storage/app/match-stats-pdf/match-{id}-{PDF_TEMPLATE_VERSION}-{data}-{locale}.pdf` (при правке шаблона PDF поднять `PDF_TEMPLATE_VERSION`). Файлы создаёт www-data.
- В профиле (`user/public.blade.php`, блок `#player-matches` после «Турнирной статистики») — список матчей игрока; приватные friendly видит только админ.
