(function () {
    'use strict';

    var isCapacitor = !!(
        window.Capacitor &&
        window.Capacitor.isNativePlatform &&
        window.Capacitor.isNativePlatform()
    );
    var Plugins = isCapacitor ? (window.Capacitor.Plugins || {}) : {};

    // ─── Share ───────────────────────────────────────────────────────────────

    function share(opts) {
        if (isCapacitor && Plugins.Share) {
            Plugins.Share.share({
                title: opts.title || '',
                text: opts.text || '',
                url: opts.url || window.location.href,
                dialogTitle: 'Поделиться'
            }).catch(function (err) {
                if (String(err).indexOf('Share canceled') !== -1) return;
                console.warn('VolleyNative.share error:', err);
            });
            return;
        }
        // Fallback: copy to clipboard
        var url = opts.url || window.location.href;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(function () {
                swal({ title: 'Ссылка скопирована!', icon: 'success', timer: 1500, buttons: false });
            }).catch(function () {
                swal({ title: 'Ссылка', text: url });
            });
        } else {
            swal({ title: 'Ссылка', text: url });
        }
    }

    // ─── Calendar ────────────────────────────────────────────────────────────

    function toIcsDate(iso) {
        // "2025-06-15T18:00:00+03:00" → "20250615T150000Z" (UTC)
        var d = new Date(iso);
        var pad = function (n) { return n < 10 ? '0' + n : '' + n; };
        return d.getUTCFullYear() +
            pad(d.getUTCMonth() + 1) +
            pad(d.getUTCDate()) + 'T' +
            pad(d.getUTCHours()) +
            pad(d.getUTCMinutes()) +
            pad(d.getUTCSeconds()) + 'Z';
    }

    function downloadIcs(opts) {
        var lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//VolleyPlay//EN',
            'BEGIN:VEVENT',
            'DTSTART:' + toIcsDate(opts.startDate),
            'DTEND:' + toIcsDate(opts.endDate),
            'SUMMARY:' + (opts.title || '').replace(/[\r\n]+/g, ' '),
            'LOCATION:' + (opts.location || '').replace(/[\r\n]+/g, ' '),
            'DESCRIPTION:' + (opts.notes || '').replace(/[\r\n]+/g, ' '),
            'END:VEVENT',
            'END:VCALENDAR'
        ];
        var blob = new Blob([lines.join('\r\n')], { type: 'text/calendar;charset=utf-8' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'event.ics';
        document.body.appendChild(a);
        a.click();
        setTimeout(function () {
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }, 100);
    }

    function addToCalendar(opts) {
        if (isCapacitor && Plugins.VolleyCalendar) {
            Plugins.VolleyCalendar.addEvent({
                title: opts.title || '',
                location: opts.location || '',
                notes: opts.notes || '',
                startDate: opts.startDate || '',
                endDate: opts.endDate || ''
            }).then(function () {
                swal({ title: 'Добавлено в календарь!', icon: 'success', timer: 1500, buttons: false });
            }).catch(function (err) {
                console.warn('VolleyNative.addToCalendar error:', err);
                swal({ title: 'Ошибка', text: 'Не удалось добавить в календарь', icon: 'error' });
            });
            return;
        }
        // Fallback: download .ics
        downloadIcs(opts);
    }

    // ─── Haptic ──────────────────────────────────────────────────────────────

    function haptic(style) {
        if (!isCapacitor || !Plugins.Haptics) return;
        try {
            if (style === 'success' || style === 'warning' || style === 'error') {
                Plugins.Haptics.notification({ type: style });
            } else {
                Plugins.Haptics.impact({ style: style || 'medium' });
            }
        } catch (e) {}
    }

    // ─── Badge ───────────────────────────────────────────────────────────────

    function updateBadge(count) {
        if (!isCapacitor || !Plugins.Badge) return;
        try {
            if (count === 0) {
                Plugins.Badge.clear();
            } else {
                Plugins.Badge.set({ count: count });
            }
        } catch (e) {}
    }

    // ─── Public API ──────────────────────────────────────────────────────────

    window.VolleyNative = {
        isApp: isCapacitor,
        share: share,
        addToCalendar: addToCalendar,
        haptic: haptic,
        updateBadge: updateBadge
    };

    // ─── Preloader ───────────────────────────────────────────────────────────

    window.addEventListener('load', function () {
        if (isCapacitor && Plugins.Preloader) {
            setTimeout(function () {
                Plugins.Preloader.hide();
            }, 200);
        }
    });

    // ─── Pull-to-refresh ─────────────────────────────────────────────────────

    document.addEventListener('pull-to-refresh', function () {
        window.location.reload();
    });

    // ─── Auto-init ───────────────────────────────────────────────────────────

    document.addEventListener('DOMContentLoaded', function () {
        if (isCapacitor) {
            document.body.classList.add('is-app');
        }

        // Badge: запрашиваем только для авторизованных пользователей
        if (isCapacitor && Plugins.Badge && document.querySelector('meta[name="user-authenticated"]')) {
            fetch('/api/notifications/unread-count', {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            }).then(function (r) {
                return r.ok ? r.json() : null;
            }).then(function (data) {
                if (data && typeof data.count === 'number') {
                    updateBadge(data.count);
                }
            }).catch(function () {});
        }

        // Haptic на кнопки
        document.querySelectorAll('.btn-haptic').forEach(function (btn) {
            btn.addEventListener('click', function () { haptic('light'); });
        });
        document.querySelectorAll('.btn-alert').forEach(function (btn) {
            btn.addEventListener('click', function () { haptic('medium'); });
        });

        // Haptic при отправке форм join/leave
        document.querySelectorAll('form[action*="/join"], form[action*="/leave"]').forEach(function (form) {
            form.addEventListener('submit', function () { haptic('success'); });
        });
    });
})();
