# Profile — анкета игрока и требования мероприятий

## 1) Что такое “профиль” в Volley
Профиль состоит из:
- базовых данных пользователя (имя/контакты)
- “анкеты игрока” (уровни классика/пляж, зоны, и т.д.)
- аватара (Jetstream profile photo)

Профиль редактируется на странице:
- `GET /profile/complete`
- сохранение доп.полей: `POST /profile/extra`

---

## 2) Важный принцип: НЕ блокировать логин
Проверка заполненности профиля **не выполняется при логине** (Telegram/VK/Yandex).

Она выполняется **только**:
- при попытке записи на мероприятие (`join`)

Если данных не хватает:
→ redirect на `/profile/complete` с параметрами, которые подскажут что заполнить.

Поддерживаемые форматы:
- новый: `required=phone,classic_level,...&event_id=...`
- legacy: `section=personal|classic|beach`

---

## 3) Автозапись после заполнения
Флоу:
1) Пользователь нажал “Записаться”
2) Сайт понял, что не хватает данных → отправил на `/profile/complete`
3) Пользователь заполнил → `POST /profile/extra`
4) После сохранения:
   - требования мероприятия проверяются ещё раз
   - если всё ок — происходит **автозапись**
   - ставится flash `status` (успех) или `error` (ошибка)



## 4) Привязка входов на /user/profile
Раздел “Привязка входов” показывает:
- текущий способ входа (по `session('auth_provider')`)
- статусы: Telegram/VK/Yandex привязан/не привязан
- если не все привязаны — показывает кнопки/виджеты привязки

Особенность Telegram:
- привязка делается **виджетом** (не обычной ссылкой)
- на странице профиля используется Telegram widget с `data-auth-url` → наш callback

---

## 6) Troubleshooting

### Telegram: “Bot username required”
Означает, что `data-telegram-login` пустой.
Проверьте:
- `.env`: `TELEGRAM_BOT_USERNAME=YourBot` (без @)
- `config/services.php`: `services.telegram.bot_username`
- затем `php artisan optimize:clear` (и при необходимости `php artisan config:cache`)

### VK/Yandex: “There is no services entry for vkid/yandex”
Означает, что в `config/services.php` нет секции `vkid` / `yandex`
или env‑переменные пустые.

### Yandex: `services.yandex.redirect = null`
Если redirect null — Yandex OAuth не сможет корректно работать.
Нужно заполнить `YANDEX_REDIRECT_URI`, например:
- `https://volley-bot.store/auth/yandex/callback`
и очистить/пересобрать конфиг.
