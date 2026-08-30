# Рейтинговая система (OpenSkill + Elo) — полная документация

Краткая версия — в CLAUDE.md, раздел "Рейтинговая система (OpenSkill + Elo)".

## Архитектура (три параллельных рейтинга)
| Метрика | Таблица | Поле | Обновляется |
|---|---|---|---|
| WinRate | `player_career_stats` | `match_win_rate` | После каждого матча |
| Elo | `player_career_stats` | `elo_rating` | При завершении турнира |
| **OpenSkill μ/σ** | `player_career_stats` | `mu`, `sigma` | **После каждого матча** |
| Elo сезон | `tournament_season_stats` | `elo_season` | После каждого матча (ранее мёртвый — починен) |
| **OpenSkill сезон** | `tournament_season_stats` | `mu_season`, `sigma_season` | **После каждого матча** |

## OpenSkill — алгоритм (реализован сам, без пакетов)
- Сервис: `app/Services/TournamentOpenSkillService.php`
- Константы: `INITIAL_MU=25.0`, `INITIAL_SIGMA=8.333`, `BETA=4.1667`, `TAU=0.0833`
- **Conservative Rating (публичный рейтинг)**: `CR = max(0, mu - 3 * sigma)`
- Новичок: CR ≈ 0 (25 − 3×8.333); после 9 побед ~17–20; после 30+ игр стабилизируется
- Ядро: Gaussian update — победитель получает +Δμ, проигравший −Δμ; σ уменьшается у обоих
- Добавлен τ-drift (`TAU^2` к σ²) — предотвращает σ→0 и сохраняет динамику
- `normalCdf(x)` — Abramowitz & Stegun аппроксимация (max error 7.5e-8), без внешних пакетов

## Точка вызова OpenSkill
- **Инкрементально**: `TournamentStatsService::updateAfterMatch()` — вызывает `TournamentOpenSkillService::processMatchByIds()` после каждого матча
- **Сезонный**: `TournamentSeasonStatsService::updateForMatch()` — обновляет `mu_season`/`sigma_season` через тот же сервис
- **Ретропересчёт**: `php artisan tournament:recalculate-openskill` — сброс + прогон всех исторических матчей с топ-10 в консоли

## Баг: повторная обработка OpenSkill при каждом сохранении счёта (исправлен 2026-07-04)
- `TournamentStatsService::rebuildAll()` вызывается после **каждого** сохранения счёта любого матча турнира и внутри (`rebuildTournamentStats()`) прогоняет `updateAfterMatch()` по **ВСЕМ** завершённым матчам события, не только по только что введённому
- `updateAfterMatch()` → `processMatchByIds()` не идемпотентен: инкрементирует накопительные счётчики (`matches_together`, `unique_opponents`, mu/sigma и т.п.), а не пересчитывает их с нуля → при каждом новом счёте в турнире все ранее обработанные матчи накручивались повторно
- Симптом: `player_pair_stats.matches_together` растёт кратно реальному числу игр (напр. 12 при `total_matches=1`), `pair_stability = matches_together/total_matches*100` переполняет `DECIMAL(5,2)` → падает при вводе следующего счёта в этом турнире
- Фикс: `tournament_matches.stats_processed_at` (nullable timestamp) — `updateAfterMatch()` обрабатывает OpenSkill-часть только если `stats_processed_at IS NULL`, затем проставляет флаг; `resetScore()` (рескоринг) сбрасывает флаг в NULL; `TournamentOpenSkillService::rebuildAll()` (ретропересчёт) тоже проставляет флаг по каждому матчу
- Событийная часть (`PlayerTournamentStats` — win rate турнира) НЕ имеет этой проблемы — она удаляется и пересчитывается с нуля при каждом вызове (`rebuildTournamentStats()` делает `delete()` перед циклом), поэтому флагом не защищена и не должна быть
- После деплоя фикса на новую БД/окружение — один раз прогнать `php artisan tournament:recalculate-openskill`, если там уже копилась порча (симптом — `pair_stability`/другие производные метрики выглядят неправдоподобно, %>100 и т.п.)

## Починка elo_season (был мёртвым)
- `TournamentEloService::processSeasonMatch()` — новый метод, обновляет `elo_season` в `tournament_season_stats`
- Вызывается из `TournamentSeasonStatsService::updateForMatch()` и `rebuildForSeason()`
- До фикса: 72/72 записей = 1500 (дефолт). После: считается корректно

## Ограничение: legacy-данные
- Алгоритм берёт игроков через `event_team_members WHERE confirmation_status='confirmed'`
- Игроки без записей в `event_team_members` (legacy-матчи) имеют CR=0 — это ожидаемо, не баг
- То же ограничение у `elo_rating` — системная особенность данных

## Дополнительные таблицы (OpenSkill v2)
| Таблица | Что хранит |
|---|---|
| `player_rating_history` | История μ/σ после каждого матча: mu_before/after, sigma_before/after, mu_delta (generated), match_id, event_id |
| `player_pair_stats` | Статистика пар: matches_together, wins_together, **direction**, **game_scheme** |
| `player_opponent_stats` | Статистика встреч: matches_against, wins_against |
| `player_career_stats` (новые поля) | mu_peak, mu_peak_date, unique_opponents, unique_partners, main_partner_id, main_partner_games, pair_stability, last_5_form, last_10_form, points_ratio |

- `player_pair_stats.direction` + `game_scheme` — добавлены в v1.9.7; **КРИТИЧНО**: при пересчёте `direction` и `game_scheme` сохраняются через ON CONFLICT UPDATE
- `rebuildAll()` в сервисе сбрасывает ВСЕ три таблицы истории (truncate) перед пересчётом
- `processMatchByIds` принимает `?int $eventId, ?int $matchId` — передавать всегда, иначе history не привязана к матчу

## Модели Player*
- `PlayerRatingHistory` — `app/Models/PlayerRatingHistory.php`; relation: `event()`, `user()`
- `PlayerPairStats` — `app/Models/PlayerPairStats.php`; methods: `winRate()`; constraint: `player1_id < player2_id`
- `PlayerOpponentStats` — `app/Models/PlayerOpponentStats.php`; methods: `winRate()`

## UI — страницы рейтинга
| URL | Файл | Описание |
|---|---|---|
| `/players/rating` | `resources/views/players/rating.blade.php` | Карьера + сезон; CR, Δ7д, μ, поиск, сортировка |
| `/players/teams` | `resources/views/players/teams.blade.php` | Связки/пары; фильтр по direction+game_scheme |
| `/pages/rating-info` | `resources/views/pages/rating_info.blade.php` | Объяснение OpenSkill: μ, σ, CR, примеры, форматы |
| `/user/{id}` | `resources/views/user/public.blade.php` | Позиция в рейтинге, график Chart.js, форма, партнёры, соперники |
- Контроллер: `PlayerRatingController` — методы `index()` (карьера/сезон) и `teams()`
- Навигация: ссылки «Рейтинг» и «Связки» добавлены в `voll-layout.blade.php`

## UserPublicController — новые данные профиля
Добавлены переменные: `$ratingHistory`, `$ratingPartners[beach|classic]`, `$ratingOpponents`, `$ratingPositions[beach|classic]`
Chart.js скрипт вставляется **после** `</x-voll-layout>` — это нормально, браузер обрабатывает.

## i18n рейтинга
- `lang/ru/players.php` + `lang/en/players.php` — 45 ключей: `rating`, `teams_title`, `conservative_rating`, `mu`, `sigma`, `delta_7d`, `pair_stability`, `oz_op` и др.
- `tournaments.conservative_rating` / `tournaments.mu` / `tournaments.sigma` — старые ключи в `tournaments.*` (оставить для seasons/show)

## game_scheme — критичный баг парсинга
- **ЗАПРЕЩЕНО** определять team_size через regex `/^(\d+)x\d+$/` от game_scheme
  - `4x2` → regex даёт 4, реальный состав = 6; `5x1` → 5, реальный = 6
- **Правильно**: читать `event_tournament_settings.team_size_min`
- Допустимые значения `game_scheme`: classic → `4x4`, `4x2`, `5x1`, `5x1_libero`; beach → `2x2`, `3x3`, `4x4`
- Defaults в `EventGameSettingsService::getTournamentDefaults()`: `4x2`→min=6, `5x1`→min=6, `5x1_libero`→min=7+libero, `2x2`→min=2

## registration_mode — значения и приоритет (King Beach, добавлено 2026-07-08)
- Значения: `team_classic`, `team_beach`, `tournament_individual`, `king_beach`. Приоритет при создании события: **king_beach > tournament_individual > team_\*** — единая точка определения: `EventGameSettingsService::normalizeTournamentDefaults()` (создание) + продублирован в `EventStoreService::store()` (запись `event.registration_mode` до вызова normalize).
- **King Beach**: доступен только `direction=beach` + `tournament_game_scheme=2x2`. Индивидуальная регистрация как обычное мероприятие (`event_registrations`, НЕ `EventTeam`) — игроки не объединяются в команды на этапе записи. `king_beach_min_players`/`king_beach_max_players` (форма) пишутся напрямую в `EventGameSetting.min_players/max_players`, обходя расчёт `team_size × teams_count` в `EventGameSettingsService::createGameSettings()` (ранний branch по `registration_mode==='king_beach'`). `event.tournament_teams_count = 0` (нет команд — счётчик на карточке скрывается). Движок распределения по группам/раундам — `TournamentKingBeachService` (готов, не путать со слоем регистрации выше).
- Хелпер `Event::isIndividualRegistrationMode(?string $mode): bool` — `true` для `tournament_individual` И `king_beach` (места регистрации/waitlist должны обрабатывать оба режима одинаково); места ФОРМИРОВАНИЯ КОМАНД (`TournamentTeamDistributionService::distributeRandom` и др.) — строго `=== 'tournament_individual'`, king_beach туда не попадает (у него свой `distributeIntoGroups` на странице setup).
- **ГРАБЛИ**: формы редактирования перезаписывают `registration_mode` при каждом сохранении — `EventManagementController::update()` пересчитывал `tournament_individual ? ... : team_*` без проверки текущего режима, king_beach тихо откатывался на `team_beach`. Фикс сохраняет `king_beach`, если `tournament_individual_reg` не отмечен явно — но при добавлении НОВОГО режима регистрации: grep ВСЕ места `registration_mode =`/`'registration_mode' =>` по `app/`, особенно update-методы, иначе один из них молча затрёт новый режим.
- **Правило**: `update()` king_beach-события обязан проверять `$staysKingBeach = registration_mode==='king_beach' && empty($data['tournament_individual_reg'])` — иначе безусловный пересинк ролей клампит `teams_count=0→2` и `GameCalculator` затирает реальный `max_players` при каждом сохранении формы, даже без изменения числа игроков.
- **Поля min/max игроков на форме РЕДАКТИРОВАНИЯ** (`event_management_edit.blade.php`, `$isKingBeachEdit = $event->registration_mode === 'king_beach'`) — показываются ТОЛЬКО для уже-king_beach событий, вместо (не для них скрываемых через `@unless($isKingBeachEdit)`) полей «Кол-во команд»/«Состав команды»/«Запасных» (не имеют смысла у king_beach). Валидация в `update()` ДО транзакции, жёсткий запрет (не warning): `min>=4`, `max>=min`, при `gender_policy=mixed_5050` — **расширенное правило чётности**: ОБА поля (min И max) должны быть чётными (форма создания проверяла только max — тоже расширена, см. ниже), и live-COUNT защита: `max` нельзя опустить ниже максимума активных регистраций по всем ещё не начавшимся occurrences серии (`event_registrations JOIN event_occurrences WHERE starts_at >= now()`, GROUP BY occurrence_id, берём MAX — не позволяет обойти защиту через один малолюдный тур при переполненном другом).
- **Форма СОЗДАНИЯ king_beach — чётность min тоже добавлена** (`EventCreateValidator`, было только max): generic-проверка `mixed_5050 && $max>0` теперь пропускает king_beach (`!$isKingBeach`), т.к. king_beach получил свой блок с обеими проверками (min и max) и специфичными ключами `events.king_beach_min/max_players_parity_error` — раньше min не проверялся вообще, и был мёртвый неиспользуемый ключ `events.king_beach_parity_error` (заведён, но никогда не подключён к коду).
