# Локализация и выбор языка

_Вынесено из CLAUDE.md 2026-10-23 без изменения содержания. Полный исходник: docs/archive/CLAUDE.full-2026-10-23.md_

## Локализация (i18n)
- Сайт двуязычный: RU (по умолчанию) и EN
- Все строки интерфейса через `{{ __('файл.ключ') }}`, не хардкодом в blade
- При добавлении нового текста — обязательно добавлять перевод в `lang/ru/*.php` И `lang/en/*.php`
- Языковые файлы: `ui`, `auth`, `events`, `profile`, `locations`, `subscriptions`, `notifications`, `admin`, `tournaments`, `seasons`, `pages`, `welcome`
- Контент пользователей (названия событий, описания, имена, города) НЕ переводится
- HTML в переводах выводить через `{!! __('файл.ключ') !!}`
- Подстановки: `__('events.foo', ['name' => $value])` → ключ содержит `:name`
- Locale переключается через `SetLocale` middleware + `LocaleController`; кука/сессия хранят выбор пользователя
- **Язык по умолчанию — по Accept-Language (2026-10-04)**: `SetLocale::handle()` — приоритет: `users.locale` → `session('locale')` → автодетект `detectFromRequest()` → `config('app.locale')`. Автодетект смотрит ТОЛЬКО самый приоритетный язык клиента (`$request->getLanguages()[0]`): `ru` → RU, любой другой (включая uk/kk и `en-US,en;q=0.9,ru;q=0.8`) → EN; заголовка нет (боты, curl) → RU (чтобы не менять выдачу поисковикам). Результат НЕ сохраняется в сессию/профиль — явный выбор всегда приоритетнее. Нативные приложения (Capacitor WebView) шлют язык устройства в Accept-Language, их язык определяется этим же механизмом — отдельной логики в приложении нет. Идея «СНГ → RU» была отвергнута владельцем: критерий — системный язык, не география.
- **`users.locale` — NOT NULL с DB-default `'ru'`**: отличить «выбрал RU» от «не выбирал» у существующих пользователей нельзя, поэтому профиль приоритетнее автодетекта и старых пользователей он не затрагивает. Чтобы автодетект работал хотя бы для новых, `User::booted()` в `creating` ставит `locale = app()->getLocale()` (язык, реально показанный при регистрации; в консоли — `ru`). Без этого OAuth-регистрация из EN-браузера всегда давала `ru`.
- `<html lang>` в `components/voll-layout.blade.php` — `app()->getLocale()` (раньше жёстко `ru`). Остались жёсткие `lang="ru"` в `tournaments/tv.blade.php` и `events/registrations/pdf.blade.php` — не трогали.
- Проверка вживую: `curl -H "Accept-Language: en-US" https://volleyplay.club/events` → `<html lang="en">` и английский `<title>`; в тестах через `Request::create()` помнить, что Symfony подставляет дефолтный `Accept-Language: en-us,en;q=0.5` — для случая «без заголовка» делать `$r->headers->remove('Accept-Language')`. После правки middleware — `reload php8.3-fpm` (opcache).
