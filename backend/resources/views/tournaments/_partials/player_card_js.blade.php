{{-- Общие кирпичики карточки игрока для пульта: кружок 56 px (фото или инициалы на цвете команды) и поле «№» с мгновенным сохранением
     в event_jersey_numbers (тот же эндпоинт, что на экране расстановки классики). Подключается ДО lineup_rally_js / beach_serve_js. --}}
<script>
window.PlayerCardUi = window.PlayerCardUi || (function () {
	/* кружок: фото или инициалы на цвете команды; lib — фиолетовый (либеро) */
	function avatar(p, color, text, lib) {
		var a = document.createElement('span');
		a.className = 'ls-av' + (lib ? ' ls-av--lib' : '');
		a.style.background = lib ? '#7C3AED' : (color || '#2967BA');
		a.style.color = lib ? '#fff' : (text || '#fff');
		if (p.photo) { var im = document.createElement('img'); im.src = p.photo; im.alt = ''; im.dataset.ini = p.initials || '?'; im.dataset.bg = color || '#2967BA'; im.dataset.fg = text || '#fff'; a.style.background = 'transparent'; a.appendChild(im); }
		else { a.textContent = p.initials || '?'; }
		return a;
	}
	/* поле «№»: правка сразу сохраняется; расстановку/выбор не сбрасывает. onSaved(number|null) — обновить локальные данные */
	function numInput(url, setNumber, uid, value, onSaved, placeholder) {
		var i = document.createElement('input');
		i.type = 'text'; i.className = 'ls-num'; i.setAttribute('inputmode', 'numeric'); i.maxLength = 2;
		i.placeholder = placeholder || '№'; i.value = value != null ? value : '';
		bindNum(i, url, setNumber, uid, onSaved);
		return i;
	}
	function bindNum(i, url, setNumber, uid, onSaved) {
		i.addEventListener('click', function (e) { e.stopPropagation(); });
		i.addEventListener('input', function () { i.value = i.value.replace(/[^0-9]/g, ''); });
		i.addEventListener('change', function () {
			var v = i.value.trim();
			i.classList.remove('ls-num--ok'); i.classList.remove('ls-num--err');
			if (!url) { return; }
			RallyLive.send(url, {set_number: setNumber, user_id: uid, number: v}, {json: true, retry: true}, function () {
				if (onSaved) { onSaved(v === '' ? null : parseInt(v, 10)); }
				i.classList.add('ls-num--ok'); setTimeout(function () { i.classList.remove('ls-num--ok'); }, 1200);
			}, function (m) { i.classList.add('ls-num--err'); i.title = m; });
		});
	}
	return {avatar: avatar, numInput: numInput, bindNum: bindNum};
})();
</script>
