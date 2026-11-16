/**
 * Кнопка «Проверить настройки» у Telegram-канала/темы: POST на сервер, ответ {level: ok|warn|error, message}.
 * Разметка — partials/channel-check.blade.php (data-check-url, data-thread-input — селектор поля с номером темы).
 */
(function () {
	'use strict';
	var COLORS = { ok: '#2a8a3b', warn: '#b7791f', error: '#d33' };
	var ICONS = { ok: '✅ ', warn: '⚠️ ', error: '❌ ' };

	function csrf() {
		var m = document.querySelector('meta[name="csrf-token"]');
		return m ? m.getAttribute('content') : '';
	}

	document.addEventListener('click', function (e) {
		var btn = e.target.closest ? e.target.closest('.channel-check-btn') : null;
		if (!btn) return;
		e.preventDefault();
		var out = btn.parentNode.querySelector('.channel-check-result');
		var body = new FormData();
		if (btn.dataset.threadInput) {
			var inp = document.querySelector(btn.dataset.threadInput);
			if (inp) body.append('thread_id', inp.value);
		}
		var label = btn.textContent;
		btn.disabled = true;
		btn.textContent = btn.dataset.checking || '…';
		out.textContent = '';
		fetch(btn.dataset.checkUrl, {
			method: 'POST', body: body, credentials: 'same-origin',
			headers: { 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
		}).then(function (r) { return r.json().catch(function () { return {}; }); })
		.then(function (j) {
			var level = j.level || 'warn';
			out.style.color = COLORS[level] || '';
			out.textContent = (ICONS[level] || '') + (j.message || '—');
		}).catch(function () {
			out.style.color = COLORS.warn;
			out.textContent = '⚠️ ' + (btn.dataset.failed || 'Error');
		}).then(function () { btn.disabled = false; btn.textContent = label; });
	});
})();
