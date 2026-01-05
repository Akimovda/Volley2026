# Events — модель мероприятий и логика записи

## 1. Назначение

Мероприятия (Events) — ключевая сущность проекта Volley.
Запись на мероприятие является **триггером проверки профиля пользователя**.

Принцип:
> ❌ Не проверять профиль при логине  
> ✅ Проверять профиль при действии (запись на мероприятие)

---

## 2. Модель Event

Таблица `events`:

| Поле | Тип | Описание |
|-----|-----|----------|
| id | bigint | ID мероприятия |
| title | string | Название |
| requires_personal_data | boolean | Требуются персональные данные |
| classic_level_min | smallint \| null | Минимальный уровень классики |
| beach_level_min | smallint \| null | Минимальный уровень пляжа |
| created_at / updated_at | timestamps | Системные поля |

Модель: `App\Models\Event`

---

## 3. Регистрация на мероприятие

### Роут
```http
POST /events/{event}/join

Контроллер:
EventRegistrationController@store

Алгоритм
Пользователь нажимает «Записаться»
Проверяются требования мероприятия:
персональные данные
уровень классики
уровень пляжа
Если данных не хватает:
формируется список required
redirect → /profile/complete?required=...&event_id=...
данные сохраняются в session
Если требования выполнены:
создаётся запись в event_registrations
redirect → /events с flash-сообщением
4. EventRegistrationRequirements
Вся бизнес-логика требований вынесена в сервис:
App\Services\EventRegistrationRequirements
Методы:
missing(User $user, Event $event): array
ensureEligible(User $user, Event $event): void
Контроллеры не содержат логики требований, только оркестрацию.
5. Отмена записи
Роут

DELETE /events/{event}/leave
Контроллер:
EventRegistrationController@destroy

Поведение:
удаляет запись из event_registrations
очищает pending-состояние в session
возвращает пользователя на /events
6. UI страницы мероприятий
Страница:
GET /events
Контроллер:
EventsController@index

Отображение карточки:
название мероприятия
бейджи требований
состояния:
«Записаться»
«Уже записан»
«Отменить запись»
Состояние «Уже записан» определяется по таблице event_registrations.
7. Принципы
Одна запись = один пользователь + одно мероприятие
Нет дублей (unique(event_id, user_id))
Проверки выполняются только при действии
UX ориентирован на поток пользователя, а не на блокировки

---

# 2️⃣ `docs/profile.md` — структура профиля и анкеты

```md
# Profile — структура профиля и анкеты

## 1. Разделение ответственности

Профиль пользователя разделён на два логических блока:

1. **Базовый профиль (Jetstream)**
2. **Анкета игрока (Volley)**

Это сделано намеренно, чтобы:
- не ломать Fortify/Jetstream
- иметь контроль над бизнес-полями

---

## 2. Базовый профиль (Jetstream)

Управляется Jetstream / Fortify.

Редактируется на `/user/profile`.

Поля:
- name
- email
- photo

Логика сохранения:
UpdateUserProfileInformation

❗ Эти поля **НЕ участвуют напрямую** в требованиях мероприятий.

---

## 3. Анкета игрока (Extra Profile)

Анкета реализована отдельно.

### Поля:
- first_name
- last_name
- phone
- classic_level
- beach_level

### Роут:
```http
POST /profile/extra
ProfileExtraController@update

4. Проверка профиля
Проверка заполненности профиля:
не выполняется при логине
выполняется при попытке записи на мероприятие
Если данных не хватает:
пользователь перенаправляется на /profile/complete
список required сохраняется в session
нужные поля подсвечиваются в UI
Поддержка:
required=field1,field2
legacy section=personal|classic|beach
5. Автозапись после заполнения профиля
После сохранения анкеты:
4. Проверка профиля
Проверка заполненности профиля:
не выполняется при логине
выполняется при попытке записи на мероприятие
Если данных не хватает:
пользователь перенаправляется на /profile/complete
список required сохраняется в session
нужные поля подсвечиваются в UI
Поддержка:
required=field1,field2
legacy section=personal|classic|beach
5. Автозапись после заполнения профиля
После сохранения анкеты:

---

# 3️⃣ `docs/dev.md` — dev/build, Vite, стили

```md
# Development — сборка, стили, окружение

## 1. Frontend сборка

Проект использует **Vite**.

### Dev-режим
```bash
npm run dev

запускает Vite dev-server
используется для локальной разработки
процесс останавливается Ctrl + C
Production build
npm run build
собирает ассеты в public/build
используется на сервере
не требует запущенного dev-сервера
После build рекомендуется:
php artisan optimize:clear
2. Стили проекта
Архитектура
resources/css/app.css — точка входа
resources/css/volley.css — стили проекта
Подключение:
@import './volley.css';
Принципы стилизации
HTML (Blade) — без инлайнов
Tailwind используется только базово (Jetstream)
Проектные стили — через классы:
.v-card
.v-alert
.v-btn
.v-badge
.v-required
3. UX-состояния через session
Используются session-ключи:
pending_event_join
pending_profile_required
status (flash)
Это позволяет:
сохранять контекст пользователя
не хранить состояние в URL
обеспечивать чистый UX
4. Полезные команды
php artisan route:list
php artisan optimize:clear
php -l file.php
5. Принципы разработки
Логика — в сервисах
Контроллеры — тонкие
Проверки — по действию
UX важнее формальной строгости

---

## ✅ Как создать файлы на сервере

```bash
nano docs/events.md
nano docs/profile.md
nano docs/dev.md


