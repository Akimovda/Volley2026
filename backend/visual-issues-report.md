# Диагностика 4 визуальных проблем — voll-layout / шапка / баннеры / hero

Дата: 2026-06-23  
Среда: dev (`volley-bot.store`), файл: `resources/views/components/voll-layout.blade.php`

---

## 1. ШАПКА — полная карта иконок по средам

### Структура `fix-header-btn` (строки ~214–290)

```
fix-header-btn
├── @if(!$isErrorPage)
│   ├── fix-header-users
│   │   └── fix-header-btn-user  ← профиль (auth) или стрелка-вход (guest)
│   └── fix-header-btn-mail-wrap  ← колокол
│   @endif
├── fix-header-btn-hamm           ← бургер — ВСЕГДА (нет @if)
└── fix-header-btn-theme          ← тема — ВСЕГДА (нет @if)
```

### CSS-скрытие `fix-header-btn-theme`

- `.is-app .fix-header-btn-theme { display: none !important }` — в inline-стиле voll-layout.blade.php (строка 74)
- Для `.tg-webapp` правила нет — тема **всегда видна** в TG-браузере

### Матрица по средам

| Иконка | app (`is-app`) | Safari | tg-webapp |
|---|---|---|---|
| Профиль (auth) / стрелка-вход (guest) | ✅ | ✅ | ✅ |
| Колокол | ✅ | ✅ | ✅ |
| Бургер/меню | ✅ | ✅ | ✅ |
| Тема (sun/moon) | ❌ скрыт CSS | ✅ | ✅ |

### Откуда в TG-браузере «лишние» иконки

**«вход-стрелка»** = `.icon-login-svg` — это `@else`-ветка в `fix-header-btn-user`, показывается для неавторизованных пользователей. Если пользователь открыл страницу в TG-браузере без сессии — видит стрелку вместо профиля. Это не tg-webapp-специфичный баг, просто сессия из нативного приложения не переносится в браузер.

**«тема + луна»** — это ОДИН элемент `fix-header-btn-theme` с двумя SVG внутри одного `<span>`:
- `.theme-icon.sun` — видна по умолчанию (opacity: 1, scale: 1)
- `.theme-icon.moon` — скрыта по умолчанию (opacity: 0, scale: 0)
- При `body.dark` — sun прячется, moon видна

В tg-webapp `body.dark` ставится только из `localStorage.getItem('theme') === 'dark'`. Свойство `tg.colorScheme` (системная тема Telegram) **не читается** → если Telegram в тёмной теме, но localStorage не выставлен вручную, рендерится sun. Если localStorage='dark' — moon. Оба одновременно видны только в момент CSS-перехода при смене темы.

### Что нужно для желаемого поведения

- **app (3 иконки):** уже работает — `.is-app .fix-header-btn-theme { display: none }`
- **Safari/TG-браузер (3 иконки + тема):** уже работает
- **Стрелка-вход в TG:** вопрос авторизации/сессии, не CSS

### Возможные доработки

1. Синхронизировать тему с `tg.colorScheme` в блоке `tg-webapp`:
   ```javascript
   if (tg.colorScheme === 'dark') document.body.classList.add('dark');
   ```
2. Если нужно скрыть тему-кнопку и в TG-браузере — добавить:
   ```css
   .tg-webapp .fix-header-btn-theme { display: none !important; }
   ```

---

## 2. БАННЕР «VolleyClub — Открыть» (in_app_telegram.jpg)

### Что это

Это **наш собственный JS-баннер**, НЕ нативный `<meta name="apple-itunes-app">` (этого тега в коде нет).

**Файл:** `resources/views/components/voll-layout.blade.php`, строки ~109–165.

### Логика показа

```javascript
(function() {
    var ua = navigator.userAgent;
    if (ua.includes('VolleyPlayApp')) return;  // ← только app скипает

    var isAndroid = ua.includes('Android');
    var isIOS = (ua.includes('iPhone') || ua.includes('iPad')) && !isAndroid;
    if (!isAndroid && !isIOS) return;

    // НЕТУ проверки window.Telegram?.WebApp !

    var STORE_URL = isAndroid
        ? 'https://www.rustore.ru/catalog/app/club.volleyplay.app'
        : 'https://apps.apple.com/app/id6764748613';
    // ... создаёт div с кнопкой «Открыть» и ×
})();
```

### Почему баннер виден в Telegram

Проверка только на `VolleyPlayApp`. Telegram на iOS/Android имеет iOS/Android UA → условие проходит → баннер показывается в Telegram in-app browser. Именно это видно на `in_app_telegram.jpg`.

### Логика «один раз»

- localStorage: `appbanner_hidden_until` (iOS) / `rustore_banner_hidden_until` (Android)
- При нажатии × → скрывается на **7 дней**: `Date.now() + 7 * 24 * 60 * 60 * 1000`
- При повторном открытии: `if (hidden && Date.now() < parseInt(hidden, 10)) return;`

### Фикс

Добавить проверку TG перед показом баннера:

```javascript
if (ua.includes('VolleyPlayApp')) return;
if (window.Telegram && window.Telegram.WebApp && window.Telegram.WebApp.initData) return;
```

---

## 3. БАННЕР «есть приложение» — полный аудит мест показа

Из поиска по `openInApp`, `есть приложение`, `открыть в приложении`, `открыть в нём`, `app-banner`, `smart-app-banner` по всем blade-файлам и JS:

**Найдено ровно одно место** — JS-баннер в `voll-layout.blade.php` (описан выше в пункте 2).

Никаких других отдельных баннеров-призывов «открыть в приложении» в views нет. Упоминания в `pages/about.blade.php` и `pages/help.blade.php` — это просто статический текст в разделах «о приложении», не интерактивные баннеры.

### Итого

| Файл | Тип | Условие показа | «Один раз» |
|---|---|---|---|
| `voll-layout.blade.php:109` | Custom JS div | iOS или Android, не VolleyPlayApp | ✅ localStorage 7 дней |

Вывод: система баннера одна, условие скрытия корректное (7 дней), проблема только в отсутствии исключения для Telegram WebApp.

---

## 4. КНОПКА «Записать тренировку» уходит под hero при скролле

### Расположение кнопки

`activity/index.blade.php:87` — обычный блочный элемент внутри `{{ $slot }}`:

```html
@if($canRecord)
<div class="mb-2">
    <a href="{{ route('activity.record') }}" class="btn w-100" style="min-height:44px;font-size:1.7rem">
        {{ __('activity.record_btn') }}
    </a>
</div>
@endif
```

Slot рендерится внутри `.main-container`, который идёт строго ПОСЛЕ `<section class="top-section">` в layout. Отрицательных отступов и `position: absolute` на кнопке нет.

### Корневая причина — z-index конфликт в style.css

```css
/* строка 3257 */
.top-section .container {
    position: relative;
    z-index: 2;        /* ← создаёт stacking context */
}

/* строка 3368 */
.main-container {
    background: linear-gradient(to bottom, #e7f0ff, #fff);
    padding: 0 0 4rem 0;
    /* z-index НЕ задан, position НЕ задан */
}
```

`.top-section .container` с `z-index: 2` создаёт stacking context, который при GPU-compositing (особенно в iOS WebView/Capacitor при скролле) рендерится поверх `.main-container`. На мобильном viewport с большой hero-картинкой (до 50rem на mobile) эффект усиливается.

Дополнительно: hero-картинка на mobile (`max-width: 767px`) имеет `max-height: 50rem` — очень высокая секция, и `.main-container` сразу под ней без верхнего отступа.

### Все похожие места с absolute/z-index рядом с hero/контентом

| Место (style.css) | Что делает | Риск |
|---|---|---|
| `.top-section .container { z-index: 2 }` (строка 3257) | Hero поверх следующего блока | **Первопричина** |
| `z-index: 1000` (строка 924) | Неизвестный элемент с высоким z-index | Требует ревью |
| `.fix-header { position: fixed }` | Фиксированная шапка | Перекрывает контент если нет top-padding на странице |
| `.is-app #app-back-btn { position: fixed; z-index: 9999 }` | Кнопка «назад» в приложении | ОК, предназначено |
| `.users-filter { position: relative; z-index: 10 }` (строка 3830) | Фильтр пользователей | ОК |

### Фикс

Вариант А (предпочтительный) — поднять z-index main-container:
```css
.main-container {
    position: relative;
    z-index: 3;
}
```

Вариант Б — убрать z-index с top-section (если он там не нужен функционально):
```css
.top-section .container {
    position: relative;
    /* z-index: 2; ← убрать */
}
```

Проверить: нужен ли `z-index: 2` на `.top-section .container` для каких-то наложений внутри hero (AOS-анимации, абсолютные элементы). Если нет — Вариант Б чище.

---

## Сводка — что фиксить и где

| # | Проблема | Файл | Правка |
|---|---|---|---|
| 1а | Тема не синхронизирована с Telegram dark mode | `voll-layout.blade.php` ~строка 175 | Добавить `if (tg.colorScheme === 'dark') body.classList.add('dark')` |
| 1б | (Опционально) скрыть тему в tg-webapp | `voll-layout.blade.php` ~строка 74 | `.tg-webapp .fix-header-btn-theme { display:none!important }` |
| 2 | Баннер показывается в Telegram browser | `voll-layout.blade.php` ~строка 109 | Добавить проверку `window.Telegram?.WebApp` |
| 4 | Кнопка под hero при скролле | `public/assets/style.css` строки 3257 / 3368 | `position:relative; z-index:3` на `.main-container` |
