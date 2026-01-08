# Account Linking (Telegram <-> VK) через одноразовый код

## Идея
Пользователь в аккаунте A генерирует одноразовый код.
Потом входит вторым способом (Telegram/VK) и вводит код на странице привязки — второй провайдер привязывается к текущему аккаунту.

## Таблицы
### account_link_codes
Хранит одноразовые коды (в базе — только hash).

### account_link_audits
История действий по привязкам (кто/когда/что привязал).

## Роуты
- POST  /account/link-code        -> account.link_code.store  (генерация кода)
- GET   /account/link             -> account.link.show        (форма ввода кода)
- POST  /account/link             -> account.link.consume     (применить код)

## Где показывается UI
`resources/views/profile/show.blade.php`
Секция "Привязка Telegram / VK" отображается только если НЕ привязаны оба провайдера.

## auth_provider (каким способом вошли)
Мы сохраняем `session(['auth_provider' => 'telegram'|'vk'])`
в `TelegramAuthController` и `VkAuthController`.

## Быстрая проверка
1) Зайти через Telegram -> открыть /debug/session (временный роут) -> auth_provider=telegram
2) Зайти через VK -> /debug/session -> auth_provider=vk
3) Сгенерировать link-code в аккаунте A, войти вторым способом, применить код -> провайдер привязан
