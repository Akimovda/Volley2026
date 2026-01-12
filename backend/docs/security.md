# Security

Этот документ фиксирует принятые решения по безопасности для аутентификации (Telegram/VK ID/Yandex), привязки аккаунтов и полного удаления пользователя (purge).

## 1) Модель угроз (кратко)

Основные риски:
- Подмена/перехват OAuth callback (state/session mismatch, replay).
- Захват аккаунта через «привязку» провайдера к чужому пользователю.
- Дубли/коллизии идентификаторов провайдеров (vk_id / telegram_id / yandex_id).
- «Сиротские» данные в таблицах с `user_id` после purge.
- Утечки через логи/аудит (PII, токены).
- Файлы профиля остаются на диске после удаления пользователя.

Мы минимизируем риски через:
- Жёсткую уникальность provider-id на уровне БД.
- Разделение intent: `login` vs `link`.
- Проверки «provider уже привязан к другому аккаунту».
- PKCE/State и fallback stateless только на InvalidState.
- Полагание на FK + ON DELETE CASCADE/SET NULL для чистого purge.
- Удаление `profile_photo_path` при purge.

## 2) Провайдеры и флоу

### 2.1 Общие принципы
- **Никогда не меняем `session('auth_provider')` в redirect-методах.**
  Это поле — “как вошли в текущей сессии” и должно ставиться только **после успешного** login/link.
- В redirect кладём:
  - `oauth_provider` = `telegram|vk|yandex`
  - `oauth_intent` = `login|link` (если `Auth::check()` → `link`, иначе `login`)
- После успешного login:
  - `Auth::login($user, true)`
  - `session()->regenerate()`
  - `session('auth_provider') = <provider>`
  - `session('auth_provider_id') = <provider_user_id>`

### 2.2 Telegram
- Callback валидирует подпись Telegram (`hash_hmac` на bot token) и проверяет `auth_date` (anti-replay).
- В режиме `link` (когда пользователь уже залогинен):
  - проверяем, что `telegram_id` не привязан к другому user.
  - записываем `telegram_id`, `telegram_username`.
- В режиме `login`:
  - ищем пользователя по `telegram_id`, fallback — по “служебному email” (tg_*@telegram.local).
  - создаём пользователя при отсутствии.

### 2.3 VK ID (OAuth 2.1 + PKCE)
- Используется Socialite driver `vkid` + `scopes(['email'])`.
- Callback:
  - `Socialite::driver('vkid')->user()`, при `InvalidStateException` — `stateless()->user()` (fallback).
- В режиме `link`:
  - проверяем уникальность `vk_id` (не у другого user).
  - пишем `vk_id` и, если пришёл, `vk_email`.
- В режиме `login`:
  - ищем по `vk_id`, если есть email — fallback по `email`.
  - если нет email/коллизия — создаём безопасный уникальный email (vk_*@vk.local).

### 2.4 Yandex
- Callback аналогично: `user()` и fallback `stateless()` только на InvalidState.
- В режиме `link`:
  - привязываем `yandex_id` к текущему user (и опционально `yandex_avatar`, `yandex_phone`).
  - запрещаем, если `yandex_id` уже у другого user.
- В режиме `login`:
  - ищем по `yandex_id`, иначе создаём пользователя.
  - **Важно:** `users.email` у нас `NOT NULL`, поэтому если Yandex email не отдаёт —
    создаём служебный уникальный email (пример: `ya_<id>@yandex.local`).

## 3) Привязка аккаунтов: политика

Правило: **один provider-id может принадлежать только одному user**.
- `telegram_id`, `vk_id`, `yandex_id` — уникальные.
- Если новый пользователь вошёл через Yandex и пытается “привязать” Telegram/VK,
  которые уже привязаны к старому аккаунту — это корректно блокируется.
  Решение без «поломки» системы:
  - либо пользователь должен логиниться в **тот** аккаунт, где уже привязан Telegram/VK,
  - либо нужен отдельный controlled-flow “merge accounts” (с подтверждением владения обоими),
    который сейчас **умышленно отключён**.

## 4) Полное удаление (purge)

Цель purge:
1) удалить файл профиля (`profile_photo_path`), если есть;
2) удалить запись пользователя по id;
3) гарантированно удалить/обнулить все связанные записи во всех таблицах.

Стратегия:
- Используем **FK + ON DELETE CASCADE / SET NULL** как основной механизм очистки.
- Ручные `delete()` по таблицам **не масштабируются** (при появлении новых таблиц можно забыть).
- В коде purge мы делаем:
  - `Storage::disk('public')->delete($profile_photo_path)` (в try/catch)
  - `$user->forceDelete()`

Рекомендации:
- Для таблиц “истории/аудита” предпочтительнее `SET NULL`, чтобы сохранять историю,
  но это уже зависит от требований. Сейчас часть связей может быть CASCADE.

## 5) Логи и секреты
- Не логируем токены OAuth, `code_verifier`, `authorization_code`.
- В audit meta храним только безопасные поля (id, provider, outcome).
- `.env` не коммитим. Секреты — только через окружение.

## 6) Быстрые команды (операции)
- Очистка кешей после изменения провайдеров:
  - `php artisan optimize:clear`
  - `php artisan config:cache`
  - `composer dump-autoload -o` (если ставили пакеты/меняли автолоад)

- Проверить FK на users(id) и delete_rule:
  - (через tinker) запрос в `information_schema.referential_constraints`.

