/**
 * Восстановление ВСЕХ введённых настроек в форме создания мероприятия (черновик / возврат после ошибки валидации).
 * Сервер отдаёт window.__restoreValues = old() целиком; здесь значения применяются к полям по имени: переключатели, радио,
 * списки, числа, текст. Порядок: направление → формат (они перестраивают остальные поля), затем остальное, затем повторный
 * проход — для полей, которые появились/перестроились после первого. Поля, у которых уже верное значение, не трогаем.
 */
(function () {
	'use strict';
	var vals = window.__restoreValues;
	if (!vals || typeof vals !== 'object') return;
	var form = document.querySelector('form[data-initial-step]');
	if (!form) return;

	// служебные и обрабатываемые отдельным кодом поля
	var SKIP = {
		_token: 1, wizard_step: 1, draft_id: 1, event_photos: 1, description_html: 1, trainer_user_id: 1, trainer_user_label: 1,
		'trainer_user_ids': 1, city_id: 1, city_label: 1, timezone: 1, location_id: 1, court_id: 1, club_booking_choice: 1, booking_starts_at: 1
	};
	var FIRST = ['direction', 'format'];

	function fire(el) {
		el.dispatchEvent(new Event('input', { bubbles: true }));
		el.dispatchEvent(new Event('change', { bubbles: true }));
	}
	function toList(v) { return Array.isArray(v) ? v.map(String) : [String(v)]; }

	function applyOne(name, raw) {
		var isArr = /\[\]$/.test(name);
		var key = isArr ? name.replace(/\[\]$/, '') : name;
		if (SKIP[key] || SKIP[name]) return;
		if (!Object.prototype.hasOwnProperty.call(vals, key)) return;
		var v = vals[key], list = toList(v);
		var els = Array.prototype.slice.call(form.querySelectorAll('[name="' + name + '"]'));
		if (!els.length) return;

		els.forEach(function (el) {
			if (el.disabled || el.type === 'file' || el.type === 'submit' || el.type === 'button') return;
			if (el.type === 'hidden') return; // скрытые поля производные (минуты, id) — их пересчитывают сами контролы
			if (el.type === 'checkbox') {
				var want = isArr ? list.indexOf(String(el.value)) !== -1 : list.indexOf(String(el.value)) !== -1;
				if (el.checked !== want) { el.checked = want; fire(el); }
			} else if (el.type === 'radio') {
				var on = list.indexOf(String(el.value)) !== -1;
				if (on && !el.checked) { el.checked = true; fire(el); }
			} else if (el.tagName === 'SELECT') {
				if (el.multiple) {
					Array.prototype.forEach.call(el.options, function (o) { o.selected = list.indexOf(o.value) !== -1; });
				} else {
					var s = list[list.length - 1];
					if (el.value !== s && Array.prototype.some.call(el.options, function (o) { return o.value === s; })) { el.value = s; fire(el); }
				}
			} else {
				var t = list[list.length - 1];
				if (el.value !== t) { el.value = t; fire(el); }
			}
		});
	}

	function names() {
		var seen = {}, out = [];
		Array.prototype.forEach.call(form.elements, function (el) {
			if (el.name && !seen[el.name]) { seen[el.name] = 1; out.push(el.name); }
		});
		return out;
	}

	function pass() {
		FIRST.forEach(function (n) { applyOne(n); });
		names().forEach(function (n) { if (FIRST.indexOf(n) === -1) applyOne(n); });
	}

	function run() { try { pass(); } catch (e) { if (window.console) console.error('restore pass 1', e); } setTimeout(function () { try { pass(); } catch (e) { if (window.console) console.error('restore pass 2', e); } }, 350); }

	if (document.readyState === 'complete') run(); else window.addEventListener('load', run);
})();
