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

function renderJumpSummary(data) {
    const caps       = Array.isArray(data.tracked_capabilities) ? data.tracked_capabilities : ['hr'];
    const hasJumps   = caps.includes('jumps');
    const i18n       = config.jumpI18n || {};
    const jumpsBlock = el('ble-sum-jumps-block');
    const noTrackEl  = el('ble-sum-jumps-not-tracked');

    if (!hasJumps) {
        if (noTrackEl)  noTrackEl.style.display  = '';
        if (jumpsBlock) jumpsBlock.style.display = 'none';
        return;
    }

    if (jumpsBlock) jumpsBlock.style.display = '';

    const countEl = el('ble-sum-jump-count');
    if (countEl) countEl.textContent = data.jump_count ?? '0';

    const trendEl = el('ble-sum-jump-trend');
    if (trendEl) {
        const trend = data.jump_trend || {};
        if (trend.first) {
            trendEl.textContent      = i18n.jump_first_session || '';
            trendEl.style.opacity    = '.6';
            trendEl.style.fontWeight = 'normal';
            trendEl.style.color      = '';
        } else if (trend.label && trend.delta != null) {
            const absDelta = Math.abs(trend.delta);
            const key  = trend.label === 'higher' ? 'jump_trend_higher' : 'jump_trend_lower';
            trendEl.textContent      = (i18n[key] || ':delta').replace(':delta', absDelta);
            trendEl.style.color      = trend.label === 'higher' ? '#4caf50' : '#f44336';
            trendEl.style.opacity    = '';
            trendEl.style.fontWeight = '600';
        } else {
            trendEl.textContent = '';
        }
    }

    const reachEl = el('ble-sum-jump-reach');
    if (reachEl && data.jump_max_height_cm != null) {
        const reach = data.direction === 'beach' ? config.reachBeachCm : config.reachClassicCm;
        if (reach) {
            const hitting = Math.round(reach + parseFloat(data.jump_max_height_cm));
            reachEl.textContent = (i18n.hitting_reach || '≈ :cm см').replace(':cm', hitting);
        }
    }
}

function renderSummary(data) {
    setPhase('done');

    renderJumpSummary(data);

    const fields = {
        'ble-sum-avg':      data.avg_hr      ? `${data.avg_hr} уд/мин` : '–',
        'ble-sum-max':      data.max_hr      ? `${data.max_hr} уд/мин` : '–',
        'ble-sum-min':      data.min_hr      ? `${data.min_hr} уд/мин` : '–',
        'ble-sum-duration': data.duration_sec  != null ? formatDuration(data.duration_sec) : '–',
        'ble-sum-load':     data.load_score    != null ? String(data.load_score)            : '–',
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
        const names  = config.zoneNames || {};
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
    if (!config.hasHealthConsent) {
        const consentBlock = el('ble-consent-block');
        if (consentBlock) consentBlock.style.display = '';
        const errEl = el('ble-consent-error');
        if (errEl) errEl.style.display = '';
        return;
    }

    // Нет привязанных устройств — показываем подсказку
    if (!config.pairedDevices || !config.pairedDevices.length) {
        const hintEl = el('ble-no-device-hint');
        if (hintEl) hintEl.style.display = '';
        return;
    }

    setPhase('connecting');
    const paired = config.pairedDevices[0];
    try {
        await BleClient.initialize({ androidNeverForLocation: true });
        await BleClient.connect(paired.ble_identifier, () => onDisconnect());

        state.deviceId          = paired.ble_identifier;
        state.dbDeviceId        = paired.db_device_id;
        state.reconnectAttempts = 0;

        setPhase('connected');

        const nameEl = el('ble-device-name');
        if (nameEl) nameEl.textContent = paired.name || paired.ble_identifier;

    } catch (e) {
        console.error('[BLE] connect failed:', e);
        setPhase('idle');
        const errEl = el('ble-connect-error');
        if (errEl) { errEl.textContent = e.message || 'Ошибка подключения'; errEl.style.display = ''; }
    }
}

// ── Device manager (settings page) ───────────────────────────────────────────

function bindDeleteButton(btn, dmCfg) {
    if (!btn) return;
    btn.addEventListener('click', async function () {
        const id = this.dataset.id;
        if (!id) return;
        try {
            await new Promise((resolve, reject) => {
                window.jQuery.ajax({
                    url:       `/api/activity/devices/${id}`,
                    method:    'DELETE',
                    headers:   { 'X-CSRF-TOKEN': csrfToken() },
                    xhrFields: { withCredentials: true },
                    success:   resolve,
                    error:     (xhr) => reject(new Error(xhr.statusText)),
                });
            });
            const card = this.closest('[data-device-id]');
            if (card) card.remove();
            const list = document.getElementById('ble-device-list');
            if (list && !list.querySelector('[data-device-id]')) {
                const msg = document.createElement('div');
                msg.className = 'f-14 cd3 mb-1';
                msg.id = 'ble-no-devices-msg';
                msg.textContent = dmCfg.noDevicesText || '';
                list.appendChild(msg);
            }
        } catch (e) {
            console.error('[BLE] delete device failed:', e);
        }
    });
}

async function connectAndRegister(dmCfg) {
    const statusEl = document.getElementById('ble-add-device-status');
    const btnAdd   = document.getElementById('ble-btn-add-device');

    function setStatus(msg, type) {
        if (!statusEl) return;
        statusEl.textContent = msg;
        statusEl.className = `alert alert-${type} mb-1`;
        statusEl.style.display = '';
    }

    if (btnAdd) btnAdd.disabled = true;
    setStatus(dmCfg.connectingText || '…', 'info');

    try {
        await BleClient.initialize({ androidNeverForLocation: true });
        const device = await BleClient.requestDevice({ services: [HR_SERVICE] });

        await BleClient.connect(device.deviceId, () => {});

        const r = await ajaxPost('/api/activity/devices', {
            ble_identifier: device.deviceId,
            name:           device.name || 'HR Sensor',
            protocol:       'ble_hrp',
        });

        try { await BleClient.disconnect(device.deviceId); } catch {}

        if (statusEl) statusEl.style.display = 'none';

        const listEl = document.getElementById('ble-device-list');
        const noMsg  = document.getElementById('ble-no-devices-msg');
        if (noMsg) noMsg.remove();
        if (listEl) {
            const card = document.createElement('div');
            card.className = 'card mb-1';
            card.dataset.deviceId = r.device_id;
            card.innerHTML =
                '<div style="display:flex;align-items:center;gap:10px">'
                + '<div style="flex:1"><div class="b-600">' + (device.name || 'HR Sensor') + '</div></div>'
                + '<button class="btn btn-sm btn-outline-danger ble-device-delete" data-id="' + r.device_id + '" type="button">'
                + (dmCfg.removeText || 'X') + '</button>'
                + '</div>';
            listEl.appendChild(card);
            bindDeleteButton(card.querySelector('.ble-device-delete'), dmCfg);
        }
    } catch (e) {
        console.error('[BLE] connectAndRegister failed:', e);
        setStatus(e.message || 'Ошибка', 'danger');
    } finally {
        if (btnAdd) btnAdd.disabled = false;
    }
}

window.initBleDeviceManager = function (dmCfg) {
    if (!window.Capacitor || !window.Capacitor.isNativePlatform()) return;

    const section = document.getElementById('ble-devices-section');
    if (section) section.style.display = '';

    const btnAdd = document.getElementById('ble-btn-add-device');
    if (btnAdd) btnAdd.addEventListener('click', () => connectAndRegister(dmCfg));

    document.querySelectorAll('.ble-device-delete').forEach(btn => bindDeleteButton(btn, dmCfg));

    const checkbox = document.getElementById('ble-consent-checkbox-settings');
    if (checkbox) {
        checkbox.addEventListener('change', async function () {
            const errEl = document.getElementById('ble-consent-error-settings');
            if (!this.checked) return;
            try {
                await recordConsent();
                const block = document.getElementById('ble-consent-block-settings');
                if (block) block.style.display = 'none';
                const btn = document.getElementById('ble-btn-add-device');
                if (btn) { btn.disabled = false; btn.style.opacity = ''; }
            } catch (e) {
                console.error('[BLE] consent failed:', e);
                this.checked = false;
                if (errEl) errEl.style.display = '';
            }
        });
    }
};

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

    setPhase('idle');

    if (!window.Capacitor || !window.Capacitor.isNativePlatform()) {
        const notAppEl = el('ble-not-app');
        if (notAppEl) notAppEl.style.display = '';
        const btnConnect = el('ble-btn-connect');
        if (btnConnect) {
            btnConnect.disabled = true;
            btnConnect.style.opacity = '0.5';
            btnConnect.style.cursor = 'not-allowed';
        }
    }

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
