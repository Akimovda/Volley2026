import { BleClient } from '@capacitor-community/bluetooth-le';
import { KeepAwake } from '@capacitor-community/keep-awake';

const HR_SERVICE = '0000180d-0000-1000-8000-00805f9b34fb';
const HR_MEAS    = '00002a37-0000-1000-8000-00805f9b34fb';

const FLUSH_INTERVAL_MS  = 10_000;
const RECONNECT_MAX      = 10;
const RECONNECT_DELAY_MS = 3_000;

const state = {
    phase:      'idle', // idle | connecting | connected | recording | reconnecting | stopping | done
    deviceId:   null,
    dbDeviceId: null,
    sessionId:  null,
    t0:         0,
    buffer:     [],
    flushTimer: null,
    flushing:   false,
    reconnectAttempts: 0,
    reconnectTimer: null,
};

let config = {};

// ── Helpers ──────────────────────────────────────────────────────────────────

function csrfToken() {
    const m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.content : '';
}

function ajaxPost(url, data) {
    return new Promise((resolve, reject) => {
        window.jQuery.ajax({
            url,
            method:      'POST',
            contentType: 'application/json',
            data:        JSON.stringify(data),
            headers:     { 'X-CSRF-TOKEN': csrfToken() },
            xhrFields:   { withCredentials: true },
            success:     resolve,
            error:       (xhr) => reject(new Error(xhr.responseText || xhr.statusText)),
        });
    });
}

function zoneForBpm(bpm) {
    const zones = config.zones || {};
    for (let z = 5; z >= 1; z--) {
        if (bpm >= (zones['z' + z]?.low || 0)) return z;
    }
    return 0;
}

function formatDuration(sec) {
    const h = Math.floor(sec / 3600);
    const m = Math.floor((sec % 3600) / 60);
    const s = sec % 60;
    return h > 0
        ? `${h}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
        : `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
}

// ── UI ───────────────────────────────────────────────────────────────────────

const el = id => document.getElementById(id);

function setPhase(phase) {
    state.phase = phase;
    const phases = ['idle', 'connecting', 'connected', 'recording', 'reconnecting', 'stopping', 'done'];
    phases.forEach(p => {
        const block = el('ble-phase-' + p);
        if (block) block.style.display = (p === phase) ? '' : 'none';
    });
}

function renderLive(bpm, zone) {
    const bpmEl  = el('ble-bpm');
    const zoneEl = el('ble-zone');
    const barEl  = el('ble-zone-bar');

    if (bpmEl)  bpmEl.textContent = bpm;

    const zoneColors = { 0: '#ccc', 1: '#4caf50', 2: '#8bc34a', 3: '#ffc107', 4: '#ff9800', 5: '#f44336' };
    const zoneName   = zone > 0 ? (config.zoneNames?.['z' + zone] || 'Z' + zone) : '–';

    if (zoneEl)  zoneEl.textContent = zone > 0 ? `Z${zone} — ${zoneName}` : '–';
    if (barEl)   barEl.style.background = zoneColors[zone] || '#ccc';

    const t = Math.floor((Date.now() - state.t0) / 1000);
    const timerEl = el('ble-timer');
    if (timerEl) timerEl.textContent = formatDuration(t);
}

function renderSummary(data) {
    setPhase('done');
    const fields = {
        'ble-sum-avg':      data.avg_hr     ? `${data.avg_hr} уд/мин` : '–',
        'ble-sum-max':      data.max_hr     ? `${data.max_hr} уд/мин` : '–',
        'ble-sum-min':      data.min_hr     ? `${data.min_hr} уд/мин` : '–',
        'ble-sum-duration': data.duration_sec != null ? formatDuration(data.duration_sec) : '–',
        'ble-sum-load':     data.load_score != null   ? String(data.load_score)            : '–',
        'ble-sum-samples':  data.samples_count != null ? String(data.samples_count)        : '–',
    };
    Object.entries(fields).forEach(([id, val]) => {
        const e = el(id);
        if (e) e.textContent = val;
    });

    const calEl = el('ble-sum-calories');
    if (calEl) {
        if (data.calories_kcal != null) {
            calEl.textContent = `≈ ${data.calories_kcal} ккал`;
        } else {
            calEl.innerHTML = `<span style="opacity:.6;font-size:.85em">`
                + `<a href="${config.setWeightUrl || '/profile/athlete'}" style="color:inherit">`
                + (config.weightForCalories || 'Укажите вес в настройках')
                + `</a></span>`;
        }
    }

    const zonesEl = el('ble-sum-zones');
    if (zonesEl && data.time_in_zone) {
        const names = config.zoneNames || {};
        const colors = { z1: '#4caf50', z2: '#8bc34a', z3: '#ffc107', z4: '#ff9800', z5: '#f44336' };
        zonesEl.innerHTML = Object.entries(data.time_in_zone)
            .filter(([, sec]) => sec > 0)
            .map(([z, sec]) =>
                `<span style="display:inline-block;margin:2px 4px;padding:2px 8px;border-radius:12px;background:${colors[z]||'#ccc'};color:#fff;font-size:.85em">`
                + `${names[z] || z}: ${formatDuration(sec)}</span>`
            )
            .join('');
    }
}

// ── Flush buffer ─────────────────────────────────────────────────────────────

async function flushOnce() {
    if (!state.buffer.length || !state.sessionId) return;
    const batch = state.buffer.splice(0);
    try {
        await ajaxPost(`/api/activity/sessions/${state.sessionId}/samples`, { samples: batch });
    } catch {
        state.buffer.unshift(...batch); // keep for next attempt (idempotent ingest)
    }
}

function startFlushLoop() {
    state.flushTimer = setInterval(async () => {
        if (state.flushing) return;
        state.flushing = true;
        await flushOnce();
        state.flushing = false;
    }, FLUSH_INTERVAL_MS);
}

function stopFlushLoop() {
    clearInterval(state.flushTimer);
    state.flushTimer = null;
}

// ── BLE notifications ─────────────────────────────────────────────────────────

async function startNotifications() {
    await BleClient.startNotifications(state.deviceId, HR_SERVICE, HR_MEAS, (value) => {
        const flags = value.getUint8(0);
        const bpm   = (flags & 0x1) ? value.getUint16(1, true) : value.getUint8(1);
        if (bpm < 30 || bpm > 240) return;
        const t = Math.floor((Date.now() - state.t0) / 1000);
        state.buffer.push({ t, bpm });
        renderLive(bpm, zoneForBpm(bpm));
    });
}

// ── Reconnect ─────────────────────────────────────────────────────────────────

function onDisconnect() {
    if (state.phase === 'stopping' || state.phase === 'done') return;
    setPhase('reconnecting');
    scheduleReconnect();
}

function scheduleReconnect() {
    if (state.reconnectAttempts >= RECONNECT_MAX) {
        setPhase('disconnected');
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
            scheduleReconnect();
        }
    }, RECONNECT_DELAY_MS);
}

// ── Main actions ──────────────────────────────────────────────────────────────

async function recordConsent() {
    return new Promise((resolve, reject) => {
        window.jQuery.ajax({
            url:         '/api/activity/consent',
            method:      'POST',
            contentType: 'application/json',
            data:        JSON.stringify({}),
            headers:     { 'X-CSRF-TOKEN': csrfToken() },
            xhrFields:   { withCredentials: true },
            success:     () => { config.hasHealthConsent = true; resolve(); },
            error:       reject,
        });
    });
}

async function connectSensor() {
    // Проверяем согласие — если нет, показываем блок и не идём дальше
    if (!config.hasHealthConsent) {
        const consentBlock = el('ble-consent-block');
        if (consentBlock) consentBlock.style.display = '';
        const errEl = el('ble-consent-error');
        if (errEl) errEl.style.display = '';
        return;
    }

    setPhase('connecting');
    try {
        await BleClient.initialize({ androidNeverForLocation: true });
        const device = await BleClient.requestDevice({ services: [HR_SERVICE] });

        let dbDeviceId = null;
        try {
            const r = await ajaxPost('/api/activity/devices', {
                ble_identifier: device.deviceId,
                name:           device.name || 'HR Sensor',
                protocol:       'ble_hrp',
            });
            dbDeviceId = r.device_id;
        } catch (e) {
            console.warn('[BLE] device registration failed:', e);
        }

        await BleClient.connect(device.deviceId, () => onDisconnect());

        state.deviceId   = device.deviceId;
        state.dbDeviceId = dbDeviceId;
        state.reconnectAttempts = 0;

        setPhase('connected');

        const nameEl = el('ble-device-name');
        if (nameEl) nameEl.textContent = device.name || device.deviceId;

    } catch (e) {
        console.error('[BLE] connect failed:', e);
        setPhase('idle');
        const errEl = el('ble-connect-error');
        if (errEl) { errEl.textContent = e.message || 'Ошибка подключения'; errEl.style.display = ''; }
    }
}

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

// ── Public init ───────────────────────────────────────────────────────────────

window.initBleActivity = function (cfg) {
    config = cfg || {};

    if (!window.Capacitor || !window.Capacitor.isNativePlatform()) {
        setPhase('idle');
        const notAppEl = el('ble-not-app');
        if (notAppEl) notAppEl.style.display = '';
        const controlsEl = el('ble-controls');
        if (controlsEl) controlsEl.style.display = 'none';
        return;
    }

    setPhase('idle');

    const btnConnect = el('ble-btn-connect');
    const btnStart   = el('ble-btn-start');
    const btnStop    = el('ble-btn-stop');
    const btnDone    = el('ble-btn-done');

    if (btnConnect) btnConnect.addEventListener('click', () => connectSensor());
    if (btnStart)   btnStart.addEventListener('click',   () => startSession());
    if (btnStop)    btnStop.addEventListener('click',    () => stopSession());
    if (btnDone)    btnDone.addEventListener('click',    () => { window.location.href = '/profile/athlete'; });

    const consentCheckbox = el('ble-consent-checkbox');
    if (consentCheckbox) {
        consentCheckbox.addEventListener('change', async function () {
            const errEl = el('ble-consent-error');
            if (!this.checked) return;
            try {
                await recordConsent();
                const block = el('ble-consent-block');
                if (block) block.style.display = 'none';
                if (errEl) errEl.style.display = 'none';
            } catch (e) {
                console.error('[BLE] consent failed:', e);
                this.checked = false;
                if (errEl) errEl.style.display = '';
            }
        });
    }
};
