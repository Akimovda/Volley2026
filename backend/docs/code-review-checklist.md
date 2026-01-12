
---

## Чеклист для Code Review (`docs/code-review-checklist.md`)

```md
# Code Review Checklist

## A) Безопасность и Auth
- [ ] Любая привязка провайдера (`link`) запрещает takeover: provider-id не может быть у другого user.
- [ ] В redirect-методах **не пишем** `session('auth_provider')`. Только intent: `oauth_provider`, `oauth_intent`.
- [ ] После успешного login: `session()->regenerate()` (anti session fixation).
- [ ] OAuth callback: корректно обрабатываем `InvalidStateException` (fallback `stateless()` только в этом случае).
- [ ] Не логируем секреты (tokens, authorization_code, code_verifier, client_secret).
- [ ] Telegram: проверка подписи + anti-replay по `auth_date`.

## B) Данные и ограничения БД
- [ ] У `users.email` соблюдён NOT NULL (если провайдер не отдаёт email — генерируем служебный).
- [ ] Уникальные поля провайдеров: `telegram_id`, `vk_id`, `yandex_id` (nullable + unique).
- [ ] Для новых таблиц с `user_id` добавлен FK на `users(id)` с правильным `ON DELETE` (обычно CASCADE).
- [ ] Для “истории/аудита” осознанно выбрано: CASCADE или SET NULL.

## C) Purge / удаление пользователя
- [ ] Purge удаляет `profile_photo_path` (и делает это безопасно: try/catch).
- [ ] Purge полагается на FK cascade, не на ручные списки таблиц.
- [ ] После purge не остаются “orphan rows” (проверка через tinker/SQL).
- [ ] Purge не позволяет удалить самого себя (admin self-protect).

## D) Миграции
- [ ] Миграция идемпотентна: правильно именованы индексы/constraints, down() корректный.
- [ ] Для Postgres нет конфликтов имён constraints.
- [ ] В production `php artisan migrate --force` используется осознанно.

## E) UI/UX (профиль/привязка)
- [ ] Кнопки привязки показываются корректно для всех провайдеров (telegram/vk/yandex).
- [ ] Ошибки “уже привязан к другому аккаунту” отображаются как `session('error')`/`session('status')` без утечки деталей.

## F) Логи, аудит, тесты
- [ ] Для критичных действий есть audit запись (purge/link/login failures при необходимости).
- [ ] Feature-тесты покрывают: login, link, duplicate-provider prevention, purge.
- [ ] Нет изменений в tracked `.gitignore`/storage/debugbar/случайных файлов (проверка `git status`).

## G) Качество кода
- [ ] Ясные названия переменных (`intent`, `provider`, `providerId`).
- [ ] Нет дублирования логики (по возможности — вынос в сервис/trait).
- [ ] Все изменения сопровождаются обновлением docs (security/db-schema).
