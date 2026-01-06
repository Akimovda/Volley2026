# Profile — поля, видимость, редактирование

## Поля профиля

### Публичные (видно всем)
- last_name, first_name (отображение имени)
- gender: `m` / `f` (Мужчина / Женщина)
- height_cm: 40–230

### Sensitive (видимость ограничена)
- patronymic
- phone

### Прочие (по текущей логике проекта)
- birth_date
- city_id
- classic_level, beach_level

## Правила видимости

Sensitive-поля (patronymic/phone) показываем только если:
- это сам пользователь, **или**
- роль viewer: admin / organizer / staff

Реализация: Gate `view-sensitive-profile`.

## Правила редактирования

- gender, height_cm: может менять **сам игрок** и (при наличии UI/роутов) admin/organizer
- "зафиксированные" поля после заполнения игроком: меняет только admin  
  Реализация: Gate `edit-protected-profile-fields`

## Публичные страницы
- `/users` — публичный каталог игроков, фильтрация только по видимым полям
- `/user/{id}` — публичная карточка игрока
