# Вход и привязка через MAX

У MAX нет OAuth для сторонних сайтов (проверено по dev.max.ru, 2026-11), поэтому вход сделан **через бота по ссылке-приглашению**
(так же делают сторонние интеграции). Идентичность — `users.max_user_id` (идентификатор пользователя MAX); `users.max_chat_id` —
диалог с ботом для личных уведомлений (отдельная привязка, см. `MaxBindWebhookController`).

## Поток
1. Кнопка «Войти через MAX» (страница входа, попап) или «Привязать» в профиле → `GET /auth/max` (`Auth\MaxAuthController::page`):
   создаётся попытка `max_login_attempts` — токен для ссылки `https://max.ru/<MAX_BOT_ID>?start=LGN<32 символа>` (в базе только sha256),
   4-значный код (показывается ТОЛЬКО на этой странице), TTL 10 минут. Залогиненный пользователь → intent `link`, иначе `login`.
2. Пользователь жмёт «Старт» у бота → бот вызывает `POST /api/integrations/max/login/begin` (`Api\MaxLoginWebhookController::begin`,
   секрет `X-Bind-Secret`): сервер запоминает MAX-идентификатор, чат, имя и просит ввести код.
3. Пользователь вводит в чат код с сайта → бот вызывает `POST /api/integrations/max/login/code`: 3 попытки, затем отказ.
   **Код защищает от «пересылки ссылки жертве»**: злоумышленник не может войти, если жертва нажала «Старт» по его ссылке, — код видит только его браузер.
4. Браузер опрашивает `GET /auth/max/status/{id}` каждые 2 с (только браузер, начавший вход — привязка к сессии): при подтверждении
   сервер логинит пользователя (`finishLogin`) или привязывает MAX (`finishLink`).
   Поиск пользователя при входе: `users.max_user_id` → псевдоним `account_links` (после слияния) → `users.max_chat_id` (уже подключённые
   уведомления, проставляется `max_user_id`) → иначе создаётся новый (`max_<id>@max.local`, → `/profile/complete`).

## Где ещё учтён MAX
Профиль («Привязка входов»: привязать/отвязать, «текущий вход»), `AccountUnlinkController` (`/account/unlink/max`, последний способ входа не отвязать),
`UserMergeService` (поле `max_user_id`, псевдоним `account_links` провайдер `max`), `AccountDeleteRequestController`, админка (дашборд «Вход через MAX (всего)»,
список и карточка пользователя, дубли, поиск по `max_user_id`).

## Бот (вне этого репозитория)
Сервис `volleyplay-max-bot` (`/opt/volleyplay-max-bot/bot.py`, от www-data). Изменение — в `docs/max-bot-login.patch` (unified diff к версии до изменения):
обработка `bot_started` с токеном `LGN…` (35 символов) и сообщения из 4 цифр в личке. **Тот же патч нужно применить в репозитории бота (VolleyBot)**, иначе при
следующем деплое бота он затрется. Применение: `cd /opt/volleyplay-max-bot && patch -p1 < max-bot-login.patch` → `venv/bin/python -m py_compile bot.py` → `systemctl restart volleyplay-max-bot`.
