# Интеграция NotificationsScreen плагина

## Файлы

- `NotificationsScreenPlugin.swift` — Capacitor-плагин (регистрирует метод `open`)
- `NotificationsViewController.swift` — нативный экран уведомлений

## Шаги в Xcode

### 1. Добавить файлы в проект

Скопировать оба файла в папку `App/` Xcode-проекта и добавить в target `App`.

### 2. Зарегистрировать плагин в AppDelegate

```swift
// AppDelegate.swift или CAPPlugins.m
import Capacitor

// В методе application(_:didFinishLaunchingWithOptions:):
// Capacitor auto-discovers plugins through reflection, но если нужна явная регистрация:
// bridge.registerPlugin(NotificationsScreenPlugin.self)
```

Либо — стандартный Capacitor 6+ способ через `capacitor.config.ts`:
```typescript
// capacitor.config.ts — не требуется для Swift-плагинов, обнаружение автоматическое
```

### 3. Объявить JS-тип (TypeScript, опционально)

```typescript
// src/plugins/NotificationsScreen.ts
import { registerPlugin } from '@capacitor/core';

export interface NotificationsScreenPlugin {
  open(): Promise<void>;
}

export const NotificationsScreen = registerPlugin<NotificationsScreenPlugin>('NotificationsScreen');
```

### 4. JS-сторона (уже реализована в capacitor-native.js)

```javascript
// VolleyNative.openNotifications() — вызывает Plugins.NotificationsScreen.open()
// Перехват клика на .fix-header-btn-mail-wrap добавлен в DOMContentLoaded
```

## Поток данных

```
Пользователь тапает колокольчик
  → capacitor-native.js перехватывает клик (capture phase)
  → e.preventDefault() + e.stopImmediatePropagation()
  → VolleyNative.openNotifications()
  → Plugins.NotificationsScreen.open()
  → NotificationsScreenPlugin.open(_:)
  → NotificationsViewController (pageSheet)
    → getCookies из WKWebView.httpCookieStore
    → GET /api/notifications (с Cookie-заголовком)
    → Отображение списка
  → Тап по ячейке → POST /api/notifications/{id}/read → webView.evaluateJavaScript("window.location.href = '...'")
  → Свайп влево → DELETE /api/notifications/{id}
  → "Прочитать все" → POST /api/notifications/read-all
```

## API сервера

| Метод | URL | Описание |
|-------|-----|----------|
| GET | `/api/notifications?page=1&per_page=20` | Список уведомлений |
| POST | `/api/notifications/{id}/read` | Отметить прочитанным |
| POST | `/api/notifications/read-all` | Все прочитаны |
| DELETE | `/api/notifications/{id}` | Удалить |
| GET | `/api/notifications/unread-count` | Счётчик непрочитанных |

Все маршруты защищены `auth:sanctum,web` (принимают web-сессию через Cookie).
