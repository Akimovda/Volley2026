# Инцидент: привязка каналов Telegram и Max (2026-10-29)

Боты живут на сервере вне git: `/opt/volleyplay-telegram-bot` (порт 8094, `@VolleyEvents_bot`) и `/opt/volleyplay-max-bot` (порт 8093). Сервисы: `volleyplay-telegram-bot`, `volleyplay-max-bot` (рестарт — sudo). Дев-боты: `/opt/volley-telegram-bot` (8092), `/opt/volley-max-bot` (8091).

## Telegram — пустой вебхук
- Симптом: ссылки привязки игнорируются. В `getWebhookInfo` — `url: ""`, `pending_update_count: 149`; последний `WEBHOOK RAW` в `telegram-bot.log` — 2026-10-27 18:24.
- Причина (предположительно): токен перевыпущен (`.env` бота менялся 27.10 19:39), вебхук Telegram при этом сбрасывается, а заново его никто не ставил. Сам бот вебхук не регистрирует.
- Фикс: `setWebhook` на `https://volleyplay.club/telegram/webhook`, `allowed_updates` = `["message","edited_message","channel_post","callback_query","my_chat_member","chat_member"]` (ровно то, что разбирает `bot.py`). Токен — `TELEGRAM_BOT_TOKEN` из `.env` бота. Очередь (149) отработала без ошибок.
- После смены токена всегда: проверить `getWebhookInfo` и поставить вебхук. Ссылки привязки живут недолго (`Bind token expired`) — пользователям нужны новые.

## Max — `GET /chats` больше не работает
- Симптом: бот «не видит группы, где он админ». `GET /chats` → `404 method.not.found` на `platform-api.max.ru` и `platform-api2.max.ru`; по сторонним SDK (webtolk/max) метод объявлен устаревшим с июня 2026. `/me`, `/subscriptions`, `/chats/{id}`, `/chats/{id}/members`, `/members/me` работают.
- Фикс (`docs/bots/max-bot-2026-10-29.patch`, применён к `bot.py`; бэкап `bot.py.bak-2026-10-29`):
  - чаты накапливаются в `known_chats.json` из событий `bot_added` (с `added_by`), `bot_removed` и сообщений в группах;
  - список привязки: `GET /chats/{id}` (удаляем 403/404), `members/me` → пометка «бот не админ»; пользователь видит только чаты, куда он добавил бота либо где он админ/владелец (`/members?user_ids=`), чужие группы не показываются;
  - подписка Max расширена: `message_created`, `bot_started`, `bot_added`, `bot_removed` (`POST /subscriptions`).
- Ограничение: группа становится известной боту после `bot_added` или первого сообщения в ней. `known_chats.json` должен быть доступен на запись для `www-data` (chmod 666).
- Логи Max-бота: `max-bot.log` пишется только при ошибках.
