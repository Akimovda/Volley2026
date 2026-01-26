# Authentication & Identity — Volley

## 1) Цели авторизации
Volley использует **мульти‑провайдерную авторизацию** с возможностью **привязки** дополнительных способов входа к текущему аккаунту.

Поддерживаемые способы входа:
- **Telegram Login Widget** (основной)
- **VK ID** (альтернативный)
- **Yandex** (альтернативный)
- **Passkey/WebAuthn** (планируется)

Принцип: **1 пользователь = 1 аккаунт в системе**, но у аккаунта может быть несколько внешних идентификаторов.

---

## 2) Модель идентификации (User)
Ключевые поля:
- `telegram_id`, `telegram_username`
- `vk_id`, `vk_email`
- `yandex_id`, `yandex_email`, `yandex_phone` (опционально), `yandex_avatar` (опционально)
- `email` — служебный (может быть “fake”, т.к. внешние провайдеры не всегда возвращают email)
- `phone`, `first_name`, `last_name`, `patronymic` и пр. — анкета/профиль игрока

Отображение пользователя (`displayName()`):
1) `last_name + first_name` (если заполнены)
2) `@telegram_username` (если есть)
3) `User #{id}`

---

## 3) Единый контракт “login vs link”
Во всех провайдерах используется **одинаковая схема намерения**:

### Сессионные ключи (технические)
- `oauth_provider` = `telegram|vk|yandex`
- `oauth_intent` = `login|link`

### Принципы
- `redirect()` **НЕ трогает** `auth_provider` (чтобы UI не “прыгал”).
- `callback()`/успешный логин или привязка **записывает**:
  - `auth_provider` = `telegram|vk|yandex`
  - `auth_provider_id` = внешний id (если используем)

---

## 4) Telegram Login (основной)

### 4.1 Как работает
Используется **Telegram Login Widget**, который отправляет данные на `data-auth-url` (наш callback).

Валидации:
- проверка `hash` (HMAC-SHA256) по `bot_token`
- защита от replay: `auth_date` считается валидным в окне (например, до 24 часов)

### 4.2 Сценарии

#### A) Пользователь НЕ авторизован (login)
1) Поиск пользователя по `telegram_id`.
2) Если не найден — создаём нового пользователя:
   - `email` создаётся служебный (уникальный), потому что Telegram может не вернуть email
   - пароль задаём случайным (Fortify/Jetstream требуют наличие)
3) `Auth::login(...)`, `session()->regenerate()`
4) redirect: `intended('/events')`

#### B) Пользователь УЖЕ авторизован (link)
1) Проверяем, что `telegram_id` не принадлежит другому пользователю.
2) Пишем `telegram_id`, `telegram_username` (и опционально first/last при пустых полях).
3) Аватар: **только если у пользователя нет profile photo** (см. раздел “Аватары”).
4) redirect обратно на `/user/profile` с flash `status`.

---

## 5) VK ID (Socialite)

### 5.1 Что храним
- `vk_id` (уникальный)
- `vk_email` (если пришёл)

### 5.2 Сценарии
- login: найти по `vk_id`, иначе (если есть email) можно найти по `email` и “допривязать”, иначе создать нового
- link: привязать `vk_id` к текущему пользователю, запретить если `vk_id` уже у другого

---

## 6) Yandex (Socialite)

### 6.1 Что храним
- `yandex_id` (уникальный)
- опциональные поля: `yandex_avatar`, `yandex_phone`, `yandex_email` (если вы храните)

### 6.2 Сценарии
- login: найти по `yandex_id`, иначе создать нового с безопасным `email` (служебным)
- link: привязать `yandex_id` к текущему пользователю, запретить если `yandex_id` уже у другого

---

## 7) Роуты
Ожидаемые роуты (пример):
- `GET /auth/telegram/redirect` → подготовка intent (login/link) и редирект на страницу с виджетом
- `GET /auth/telegram/callback` → обработка данных виджета
- `GET /auth/vk/redirect` → Socialite redirect
- `GET /auth/vk/callback` → Socialite callback
- `GET /auth/yandex/redirect` → Socialite redirect
- `GET /auth/yandex/callback` → Socialite callback

---

## 8) Аватары (единая политика)
Правило одно: **аватар провайдера сохраняем только если у пользователя ещё нет своего**.

Хранилище:
- original: `avatars/original/{userId}/av-{userId}.{ext}`
- thumb:    `avatars/thumbs/{userId}/av-{userId}.jpg`

В базе (`users.profile_photo_path`) храним **только базовое имя**:
- `av-{userId}` (без расширения и без директорий)

URL собирается в модели `User`:
- если в `profile_photo_path` лежит “старый формат” с `/` → считаем это путём и отдаём как есть
- если лежит `av-{id}` → строим `avatars/thumbs/{id}/av-{id}.jpg`

---

## 9) Конфигурация провайдеров (важно)
### Telegram (Widget)
В `config/services.php` должен быть ключ:
- `services.telegram.bot_username` (username бота, без @)
- `services.telegram.bot_token`

### VK / Yandex (SocialiteProviders)
Ошибка вида “There is no services entry for vkid/yandex” означает:
- в `config/services.php` **нет** секций `vkid` и/или `yandex`
- или не заполнены env‑переменные

Минимально нужно добавить:
- `services.vkid.{client_id, client_secret, redirect}`
- `services.yandex.{client_id, client_secret, redirect}`

---

## 10) Текущее состояние
✔ Telegram Login — готов  
✔ VK Login — готов (при корректном services.php)  
✔ Yandex Login — готов (при корректном services.php)  
✔ Привязка провайдеров — готово  
✔ Avatar only if missing — готово  
⏳ Merge аккаунтов (UI) — в планах  

---

## 11) auth_provider в сессии (UX)
После успешного логина/привязки:
- `telegram` → `session(['auth_provider' => 'telegram'])`
- `vk` → `session(['auth_provider' => 'vk'])`
- `yandex` → `session(['auth_provider' => 'yandex'])`

Это используется на `/user/profile` для отображения “текущего способа входа” и статусов привязок.
