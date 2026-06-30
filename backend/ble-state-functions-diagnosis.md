# Диагностика: state, startSession, stopSession, flush, onDisconnect

## 1. state — полная структура (строки 11–22)

```js
const state = {
    phase:      'idle', // idle | connecting | connected | recording | reconnecting | stopping | done
    deviceId:   null,   // BLE MAC/UUID (ble_identifier)
    dbDeviceId: null,   // athlete_devices.id из БД
    sessionId:  null,   // ID созданной сессии
    t0:         0,      // Date.now() в момент старта
    buffer:     [],     // несброшенные HR-сэмплы
    flushTimer: null,   // setInterval handle
    flushing:   false,  // guard от параллельных flush
    reconnectAttempts: 0,
    reconnectTimer: null,
};
```

## 2. startSession() (строки 499–524)

```js
async function startSession() {
    const occurrenceId = config.occurrenceId || el('ble-occurrence-select')?.value || null;

    let sessionId;
    try {
        const res = await ajaxPost('/api/activity/sessions', {
            occurrence_id: occurrenceId ? parseInt(occurrenceId, 10) : null,
            device_id:     state.dbDeviceId,
        });
        sessionId = res.session_id;
    } catch (e) {
        console.error('[BLE] session start failed:', e);
        alert(config.errorNoSession || 'Ошибка создания сессии');
        return;
    }

    state.sessionId = sessionId;
    state.t0        = Date.now();
    state.buffer    = [];

    try { await KeepAwake.keepAwake(); } catch {}

    await startNotifications();
    startFlushLoop();
    setPhase('recording');
}
```

## 3. stopSession() (строки 526–549)

```js
async function stopSession() {
    setPhase('stopping');
    stopFlushLoop();
    clearTimeout(state.reconnectTimer);

    // final flush
    state.flushing = true;
    await flushOnce();
    state.flushing = false;

    try { await BleClient.stopNotifications(state.deviceId, HR_SERVICE, HR_MEAS); } catch {}
    try { await BleClient.disconnect(state.deviceId); } catch {}
    try { await KeepAwake.allowSleep(); } catch {}

    if (!state.sessionId) { setPhase('idle'); return; }

    try {
        const summary = await ajaxPost(`/api/activity/sessions/${state.sessionId}/finalize`, {});
        renderSummary(summary);
    } catch (e) {
        console.error('[BLE] finalize failed:', e);
        setPhase('done');
    }
}
```

Заметка: `finalize` отправляет пустой объект `{}` — `active_energy_kcal` и `steps` не передаются.
Для BLE-датчиков ожидаемо (нет HealthKit), но `steps` всегда будет 0.

## 4. flushOnce() + startFlushLoop() (строки 192–213)

```js
async function flushOnce() {
    if (!state.buffer.length || !state.sessionId) return;
    const batch = state.buffer.splice(0);   // атомарно вынимает весь буфер
    try {
        await ajaxPost(`/api/activity/sessions/${state.sessionId}/samples`, { samples: batch });
    } catch {
        state.buffer.unshift(...batch);     // возвращает назад при ошибке (idempotent)
    }
}

function startFlushLoop() {
    state.flushTimer = setInterval(async () => {
        if (state.flushing) return;         // guard от параллельных вызовов
        state.flushing = true;
        await flushOnce();
        state.flushing = false;
    }, FLUSH_INTERVAL_MS);                  // FLUSH_INTERVAL_MS = 10_000
}

function stopFlushLoop() {
    clearInterval(state.flushTimer);
    state.flushTimer = null;
}
```

## 5. localStorage / sessionStorage

Нет ни одного обращения. Состояние хранится только в памяти через объект `state`.

## 6. onDisconnect() + scheduleReconnect() (строки 231–253)

```js
function onDisconnect() {
    if (state.phase === 'stopping' || state.phase === 'done') return;
    setPhase('reconnecting');
    scheduleReconnect();
}

function scheduleReconnect() {
    if (state.reconnectAttempts >= RECONNECT_MAX) {   // RECONNECT_MAX = 10
        setPhase('disconnected');                      // БАГ: см. ниже
        return;
    }
    state.reconnectTimer = setTimeout(async () => {
        state.reconnectAttempts++;
        try {
            await BleClient.connect(state.deviceId, () => onDisconnect());
            await startNotifications();
            state.reconnectAttempts = 0;
            setPhase('recording');
        } catch {
            scheduleReconnect();   // рекурсивно через RECONNECT_DELAY_MS = 3_000 мс
        }
    }, RECONNECT_DELAY_MS);
}
```

## Баги, обнаруженные при диагностике

### Баг 1: фаза 'disconnected' не существует в setPhase()

`setPhase('disconnected')` вызывается при исчерпании 10 попыток реконнекта,
но фазы `'disconnected'` нет в массиве `phases` внутри `setPhase()`:

```js
const phases = ['idle', 'connecting', 'connected', 'recording', 'reconnecting', 'stopping', 'done'];
```

Результат: все блоки получают `display:none`, экран становится пустым.
Пользователь не видит никакого сообщения об ошибке и не может ничего сделать.

### Баг 2: steps всегда 0 для BLE-сессий

`stopSession()` отправляет `finalize({})` без `steps`.
BLE-датчики не имеют шагомера, это ожидаемо — но значение в БД всегда 0,
а не null, что может вводить в заблуждение при аналитике.

## Файлы

- JS: `resources/js/ble-activity.js`
- Константы: `FLUSH_INTERVAL_MS = 10_000`, `RECONNECT_MAX = 10`, `RECONNECT_DELAY_MS = 3_000`
