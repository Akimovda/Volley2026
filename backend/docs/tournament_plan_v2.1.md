# Volley2026 — Турнирная система
## Финальный план разработки v2.1

---

## 1. Форматы турниров

### 1.1 Классический волейбол (6x6)

| # | Формат | Описание | Команды | Матч |
|---|--------|----------|---------|------|
| 1 | Round Robin | Каждый с каждым, одна группа | 4–8 | Bo3 (25-25-15) |
| 2 | Группы + плей-офф | Пулы по 3–4 команды → сетка single elim | 8–24 | Группы: Bo3, финал: Bo5 опц. |
| 3 | Олимпийка | Single elimination, прямая сетка | 4–32 | Bo3, опц. матч за 3-е место |
| 4 | Швейцарская система | Туры с подбором равных соперников, без выбывания | 8–32 | Bo3 или Bo1 по настройке |

### 1.2 Пляжный волейбол (2x2 / 3x3 / 4x4)

| # | Формат | Описание | Пары | Матч |
|---|--------|----------|------|------|
| 1 | Pool Play + плей-офф | Стандарт FIVB, группы → сетка | 8–24 | Bo3 (21-21-15) |
| 2 | King of the Court | Ротация: победитель остаётся, проигравший уходит | 6–16 | Мини-сеты |
| 3 | Double Elimination | Верхняя/нижняя сетка, два поражения = вылет | 6–16 | Bo3 |
| 4 | Тайский формат | 2 параллельные игры на 1 корте, группы → дивизионы (золото/серебро) | 8–16 | До 15 очков, баланс 21 |
| 5 | Швейцарская система | Туры с подбором равных, без выбывания | 8–32 | Bo3 или Bo1 |

### 1.3 Смешанные турниры
Любой турнир может комбинировать форматы по стадиям. Каждая стадия имеет свой тип.

---

## 2. Формат матчей

### Классика: Bo3 (25-25-15), Bo5 для финалов
### Пляжка: Bo3 (21-21-15), Bo1 (21/25) для пулов
### Тайский: до 15, баланс на 21
### Швейцарская очки: 3/2/1/0 (за 2:0/2:1/1:2/0:2)

---

## 3. WinRate

### Метрики: Match WR, Set WR, Point Differential
### 4 уровня: матч → турнир → серия → общий
### Игрок: суммируем все турниры по всем командам
### Замены mid-турнир: считаем по факту (≥1 матч)
### Тай-брейк: личная встреча → Match WR → Set WR → Point Diff
### Elo Rating (фаза 2): старт 1500, отдельно классика/пляжка

---

## 4. Публичные страницы

- /tournaments/{id} — 7 табов (обзор, расписание, группы, сетка, результаты, статистика, фото)
- /tournaments/{id}/results — классификация + MVP + фотогалерея
- /locations/{id}-{slug} — блок турниров + мини-рейтинг топ-5
- /organizer/{id}/tournaments — список + сводная статистика + WinRate
- Профиль игрока — секция турниров + общий WinRate

---

## 5. Управление турниром

- Настройка стадий, жеребьёвка (ручная/случайная/по рейтингу)
- Мобильный UI ввода счёта
- Авто-обновление таблиц и продвижение по сетке
- Привязка к кортам и временным слотам
- Откат стадий (revert)

---

## 6. TV Mode
- /tournaments/{id}/tv — полноэкранный, polling 10-15 сек, QR-код

## 7. PDF
- Расписание, таблицы групп, bracket-сетка, итоги

## 8. Уведомления
- Начало турнира, следующий матч, результат, продвижение, итоги, фото
- Каналы: Telegram/VK/MAX

---

## 9. БД — 8 новых таблиц

tournament_stages (event_id, type, config JSON, status)
tournament_groups (stage_id, name, sort_order)
tournament_group_teams (group_id, team_id, seed)
tournament_matches (stage_id, group_id, round, bracket_position, teams, score JSON, sets, points, next_match_id, court, scheduled_at, status)
tournament_standings (stage_id, group_id, team_id, played/wins/losses, sets, points, rank)
player_tournament_stats (event_id, user_id, team_id, matches/wins, sets, points, WR)
player_career_stats (user_id, direction, totals, WR, elo_rating, best_placement)

---

## 10. Сервисы (12)
TournamentSetupService, TournamentMatchService, TournamentBracketService,
TournamentStandingsService, TournamentSwissService, TournamentKingService,
TournamentThaiService, TournamentStatsService, TournamentScheduleService,
TournamentPhotoService, TournamentPdfService, TournamentNotificationService

---

## 11. Фазы

1. Ядро: миграции + модели + setup/match/standings сервисы (4-5 дней)
2. Round Robin + Олимпийка: расписание + bracket + UI организатора (3-4 дня)
3. Публичные страницы: табы + SVG bracket + live polling + фото (4-5 дней)
4. WinRate: stats сервис + профиль игрока (3 дня)
5. Школа/организатор: блок турниров + рейтинги (2-3 дня)
6. TV Mode + PDF (2-3 дня)
7. Продвинутые форматы: швейцарская, double elim, king, тайский (4-5 дней)
8. Уведомления + полировка (2 дня)

MVP (фазы 1-4 + частично 6) = ~15 дней
Полная версия = ~26-31 день
