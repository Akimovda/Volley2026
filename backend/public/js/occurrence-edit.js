/**
 * occurrence-edit.js
 *
 * JS для страницы /events/{event}/occurrences/{occurrence}/edit.
 * Независим от events-create.js. Работает с именами полей без префикса "game_".
 *
 * Отвечает за:
 *   - Динамический список позиций при mixed_limited (зависит от subtype)
 *   - Показ/скрытие блока лимитов при смене gender_policy
 *   - Переход на mixed_open, если после смены subtype позиции невалидны (опц.)
 */
(function () {
    'use strict';

    // ========= Labels для позиций =========
    var POS_LABELS = {
        setter:   'Связующий (setter)',
        outside:  'Доигровщик (outside)',
        opposite: 'Диагональный (opposite)',
        middle:   'Центральный (middle)',
        libero:   'Либеро (libero)'
    };

    // ========= Позиции, доступные для subtype =========
    // В occurrence_edit libero входит в subtype (5x1 vs 5x1_libero),
    // отдельного libero_mode нет.
    function positionsForSubtype(subtype) {
        switch (subtype) {
            case '4x2':        return ['setter', 'outside'];
            case '4x4':        return ['setter', 'outside', 'opposite'];
            case '5x1':        return ['setter', 'outside', 'opposite', 'middle'];
            case '5x1_libero': return ['setter', 'outside', 'opposite', 'middle', 'libero'];
            default:           return [];
        }
    }

    // ========= DOM утилиты =========
    function qs(sel)  { return document.querySelector(sel); }
    function qsa(sel) { return document.querySelectorAll(sel); }

    function escHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function getOldPositions() {
        var hidden = document.getElementById('occ_positions_old_json');
        if (!hidden) return [];
        try {
            var arr = JSON.parse(hidden.value || '[]');
            return Array.isArray(arr) ? arr.map(String) : [];
        } catch (e) { return []; }
    }

    function getCurrentCheckedPositions() {
        var box = document.getElementById('occ_positions_box');
        if (!box) return [];
        var out = [];
        box.querySelectorAll('input[type="checkbox"][name="gender_limited_positions[]"]:checked')
           .forEach(function (cb) { out.push(String(cb.value)); });
        return out;
    }

    // ========= Построение чекбоксов позиций =========
    function buildPositionsCheckboxes() {
        var box = document.getElementById('occ_positions_box');
        if (!box) return;

        var subtypeEl = document.querySelector('select[name="subtype"]');
        var subtype   = subtypeEl ? String(subtypeEl.value || '') : '';
        var list      = positionsForSubtype(subtype);

        var currentMap = {};
        getCurrentCheckedPositions().forEach(function (k) { currentMap[k] = true; });

        var oldMap = {};
        getOldPositions().forEach(function (k) { oldMap[k] = true; });

        box.innerHTML = '';

        if (!list.length) {
            var hint = document.createElement('div');
            hint.className = 'f-13';
            hint.style.opacity = '.7';
            hint.textContent = 'Выберите подтип игры, чтобы отобразить доступные позиции.';
            box.appendChild(hint);
            return;
        }

        // Если есть уже отмеченные — сохраняем их. Иначе — восстанавливаем из old().
        var useCurrent = Object.keys(currentMap).length > 0;
        var source     = useCurrent ? currentMap : oldMap;

        list.forEach(function (key) {
            var label = document.createElement('label');
            label.className = 'checkbox-item';

            var cb = document.createElement('input');
            cb.type    = 'checkbox';
            cb.name    = 'gender_limited_positions[]';
            cb.value   = key;
            cb.checked = !!source[key];

            var fake = document.createElement('div');
            fake.className = 'custom-checkbox';

            var span = document.createElement('span');
            span.textContent = POS_LABELS[key] || key;

            label.appendChild(cb);
            label.appendChild(fake);
            label.appendChild(span);
            box.appendChild(label);
        });
    }

    // ========= Показ/скрытие блока лимитов =========
    function syncGenderLimitedWrap() {
        var sel  = document.getElementById('occ_gender_policy');
        var wrap = document.getElementById('occ_gender_limited_wrap');
        if (!sel || !wrap) return;

        var show = (sel.value === 'mixed_limited');
        wrap.style.display = show ? '' : 'none';

        if (show) buildPositionsCheckboxes();
    }

    // ========= Init =========
    document.addEventListener('DOMContentLoaded', function () {
        var sel       = document.getElementById('occ_gender_policy');
        var subtypeEl = document.querySelector('select[name="subtype"]');

        if (sel) {
            sel.addEventListener('change', syncGenderLimitedWrap);
        }

        if (subtypeEl) {
            subtypeEl.addEventListener('change', function () {
                // Если включён mixed_limited — пересобрать список позиций
                if (sel && sel.value === 'mixed_limited') {
                    buildPositionsCheckboxes();
                }
            });
        }

        // Начальная инициализация
        syncGenderLimitedWrap();
    });
})();
