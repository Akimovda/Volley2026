/**
 * «+ Добавить город» — общий виджет для всех полей города (.city-autocomplete).
 * Подключается partials/city-add.blade.php только для Админ/Организатор/Staff (window.CITY_ADD задаёт сервер).
 * Поток: ссылка под полем → страна + название → поиск в GeoNames (GET /cities/suggest) → выбор варианта →
 * создание города (POST /cities) → город выбирается в host-поле автоматически (см. pickInHost).
 * Страница-хозяин не меняется: ждём, пока её собственный поиск покажет .city-item[data-id], и «нажимаем» его.
 */
(function () {
	'use strict';

	var C = window.CITY_ADD;
	if (!C || !C.suggestUrl || !C.storeUrl) return;
	var T = C.t || {};

	function norm(s) {
		s = String(s || '').toLowerCase().replace(/ё/g, 'е').trim();
		// латиница без диакритики (Türkiye → turkiye); кириллицу не трогаем (иначе «й» превратится в «и»)
		if (!/[а-я]/.test(s) && s.normalize) s = s.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
		return s;
	}
	function csrf() {
		var m = document.querySelector('meta[name="csrf-token"]');
		return m ? m.getAttribute('content') : '';
	}
	function el(tag, attrs, text) {
		var e = document.createElement(tag);
		if (attrs) Object.keys(attrs).forEach(function (k) { e.setAttribute(k, attrs[k]); });
		if (text != null) e.textContent = text;
		return e;
	}
	function request(url, opts) {
		opts = opts || {};
		opts.credentials = 'same-origin';
		opts.headers = Object.assign({ 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, opts.headers || {});
		return fetch(url, opts).then(function (r) {
			return r.json().catch(function () { return {}; }).then(function (j) { return { status: r.status, json: j || {} }; });
		});
	}
	function errText(code) {
		return T['err_' + code] || T.err_generic || 'Error';
	}
	function countryName(code) {
		var f = (C.countries || []).filter(function (c) { return c.code === code; })[0];
		return f ? f.name : code;
	}

	function init(container) {
		if (container.getAttribute('data-city-add-ready')) return;
		var hostInput = container.querySelector('input[type="text"]');
		if (!hostInput) return;
		container.setAttribute('data-city-add-ready', '1');

		var state = { country: C.defaultCountry || 'RU' };

		var wrap = el('div', { 'class': 'city-add-wrap', style: 'margin-top:.8rem;font-size:1.4rem' });
		var link = el('a', { href: '#', 'class': 'city-add-link' }, T.link || '+ Add a city');
		wrap.appendChild(link);

		var panel = el('div', { 'class': 'city-add-panel', style: 'display:none;margin-top:1rem;padding:1.4rem;border:1px solid rgba(128,128,128,.35);border-radius:1.2rem;max-width:40rem' });
		wrap.appendChild(panel);

		// страна: поле с подсказками (без <select> — нативные select внутри .form не отображаются)
		var cLabel = el('div', { style: 'font-size:1.2rem;opacity:.7;margin-bottom:.3rem' }, T.country || 'Country');
		var cInput = el('input', { type: 'text', 'class': 'city-add-country', autocomplete: 'off', placeholder: T.country_ph || '' });
		var cList = el('div', { 'class': 'city-add-country-list', style: 'display:none;max-height:18rem;overflow-y:auto;border:1px solid rgba(128,128,128,.35);border-radius:.8rem;margin-top:.3rem' });
		var nInput = el('input', { type: 'text', 'class': 'city-add-name', autocomplete: 'off', placeholder: T.name_ph || '', style: 'margin-top:.8rem' });
		var btn = el('button', { type: 'button', 'class': 'btn btn-secondary', style: 'margin-top:.8rem' }, T.find || 'Search');
		var msg = el('div', { 'class': 'city-add-msg', style: 'margin-top:.8rem;font-size:1.3rem' });
		var results = el('div', { 'class': 'city-add-results', style: 'margin-top:.6rem' });
		[cLabel, cInput, cList, nInput, btn, msg, results].forEach(function (n) { panel.appendChild(n); });

		container.insertAdjacentElement('afterend', wrap);

		function setMsg(text, kind) {
			msg.textContent = text || '';
			msg.style.color = kind === 'err' ? '#d33' : (kind === 'ok' ? '#2a8a3b' : '');
		}
		function setCountry(code) {
			state.country = code;
			cInput.value = countryName(code);
			cList.style.display = 'none';
		}
		function renderCountryList(q) {
			cList.innerHTML = '';
			var nq = norm(q);
			// поиск по всем странам мира: основное название, альтернативные (ru/en, короткие, синонимы) и код; совпадения с начала — выше
			var list = (C.countries || []).map(function (c) {
				var n = norm(c.name), al = norm(c.alt), rank = 9;
				if (!nq) rank = 5;
				else if (c.code.toLowerCase() === nq) rank = 0;
				else if (n.indexOf(nq) === 0) rank = 1;
				else if (al.split(' | ').some(function (x) { return x.indexOf(nq) === 0; })) rank = 2;
				else if (n.indexOf(nq) !== -1) rank = 3;
				else if (al.indexOf(nq) !== -1) rank = 4;
				return { c: c, rank: rank };
			}).filter(function (x) { return x.rank < 9; })
			.sort(function (a, b) { return a.rank - b.rank; })
			.map(function (x) { return x.c; }).slice(0, 12);
			list.forEach(function (c) {
				var b = el('button', { type: 'button', style: 'display:block;width:100%;text-align:left;padding:.7rem 1rem;background:none;border:0;cursor:pointer;font-size:1.4rem' }, c.name);
				b.addEventListener('click', function () { setCountry(c.code); });
				cList.appendChild(b);
			});
			cList.style.display = list.length ? 'block' : 'none';
		}
		cInput.addEventListener('focus', function () { cInput.select(); renderCountryList(''); });
		cInput.addEventListener('input', function () { renderCountryList(cInput.value); });
		document.addEventListener('click', function (e) {
			if (!panel.contains(e.target)) cList.style.display = 'none';
		});

		link.addEventListener('click', function (e) {
			e.preventDefault();
			var open = panel.style.display === 'none';
			panel.style.display = open ? 'block' : 'none';
			if (open) {
				// страна по умолчанию: страна уже выбранного города (если есть в подписи), иначе страна профиля
				setCountry(state.country);
				var typed = hostInput.value.replace(/\s*\(.*$/, '').trim();
				if (typed && !nInput.value) nInput.value = typed;
				nInput.focus();
			}
		});

		// выбрать город в host-поле: подставляем название в его поле поиска и «нажимаем» нужный результат
		function pickInHost(id, searchText, okMessage) {
			hostInput.focus();
			hostInput.value = searchText;
			hostInput.dispatchEvent(new Event('input', { bubbles: true }));
			hostInput.dispatchEvent(new Event('keyup', { bubbles: true }));
			var tries = 0;
			var timer = setInterval(function () {
				tries++;
				var item = container.querySelector('.city-item[data-id="' + id + '"]') ||
					document.querySelector('.city-item[data-id="' + id + '"]');
				if (item) {
					clearInterval(timer);
					item.click();
					panel.style.display = 'none';
					results.innerHTML = '';
					nInput.value = '';
					setMsg('', '');
				} else if (tries > 30) {
					clearInterval(timer);
					setMsg(T.added_hint || okMessage, 'ok');
				}
			}, 150);
		}

		function renderResults(items) {
			results.innerHTML = '';
			if (!items.length) { setMsg(T.none, 'err'); return; }
			setMsg(T.pick, '');
			items.forEach(function (it) {
				var title = it.name_ru ? (it.name_ru + ' · ' + it.name) : it.name;
				var sub = [it.region, it.country_name || it.country_code,
					it.population ? (it.population.toLocaleString('ru-RU') + ' ' + (T.people || '')) : null].filter(Boolean).join(' · ');
				var b = el('button', { type: 'button', 'class': 'btn btn-secondary', style: 'display:block;width:100%;text-align:left;margin-top:.5rem;white-space:normal;height:auto;padding:.9rem 1.2rem' });
				b.appendChild(el('div', { style: 'font-weight:600' }, title));
				b.appendChild(el('div', { style: 'font-size:1.2rem;opacity:.7' }, sub + (it.already_city_id ? ' — ' + (T.exists || '') : ' — ' + (T.add || ''))));
				b.addEventListener('click', function () {
					if (it.already_city_id) { pickInHost(it.already_city_id, it.name, T.added); return; }
					b.disabled = true;
					setMsg(T.adding, '');
					request(C.storeUrl, {
						method: 'POST',
						headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
						body: JSON.stringify({ geoname_id: it.geoname_id, country: it.country_code, query: nInput.value })
					}).then(function (r) {
						b.disabled = false;
						if (r.json && r.json.city) {
							setMsg(T.added, 'ok');
							pickInHost(r.json.city.id, r.json.city.name, T.added);
						} else {
							setMsg(errText((r.json && r.json.error) || 'generic'), 'err');
						}
					}).catch(function () { b.disabled = false; setMsg(errText('generic'), 'err'); });
				});
				results.appendChild(b);
			});
		}

		function search() {
			var q = nInput.value.trim();
			results.innerHTML = '';
			if (q.length < 2) { setMsg(errText('query'), 'err'); return; }
			setMsg(T.searching, '');
			request(C.suggestUrl + '?q=' + encodeURIComponent(q) + '&country=' + encodeURIComponent(state.country))
				.then(function (r) {
					var j = r.json || {};
					if (j.error) { setMsg(errText(j.error), 'err'); return; }
					renderResults(j.items || []);
				})
				.catch(function () { setMsg(errText('unavailable'), 'err'); });
		}
		btn.addEventListener('click', search);
		nInput.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); search(); } });
	}

	function boot() {
		document.querySelectorAll('.city-autocomplete').forEach(init);
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})();
