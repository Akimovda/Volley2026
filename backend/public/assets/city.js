document.addEventListener('DOMContentLoaded', function() {

	const CITY_CONFIG = {
		showCountry: true,        // Показывать страну в выпадающем списке
		showRegion: true,         // Показывать регион в выпадающем списке
		inputShowCountry: true,   // Показывать страну в инпуте после выбора
		inputShowRegion: true     // Показывать регион в инпуте после выбора
	};
	
	
	// ---------- City autocomplete (стилизованный под селект) ----------
	const cityWrap = document.getElementById('city-autocomplete');
	const cityInput = document.getElementById('city_search');
	const cityId = document.getElementById('city_id');
	const dd = document.getElementById('city_dropdown');
	const results = document.getElementById('city_results');
	
	
	function extractCityNameForSearch(fullLabel) {
		if (!fullLabel) return '';
		
		// Просто берем всё до первой скобки
		const matches = fullLabel.match(/^([^(]+)/);
		return matches ? matches[1].trim() : fullLabel.trim();
	}						
	
	
	
	
	
	function escapeHtml(s) {
		return String(s || '')
		.replace(/&/g, '&amp;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;')
		.replace(/"/g, '&quot;')
		.replace(/'/g, '&#039;');
	}
	
	function showDropdown() {
		if (!dd) return;
		dd.classList.add('active');
	}
	
	function hideDropdown() {
		if (!dd) return;
		dd.classList.remove('active');
	}
	
	function clearResults() {
		if (results) results.innerHTML = '';
	}
	
	function renderGroup(title, items) {
		const html = [];
		html.push('<div class="city-group">' + escapeHtml(title) + '</div>');
		
		items.forEach(item => {
			// Формируем подпись под городом (страна и регион)
			const subParts = [];
			if (CITY_CONFIG.showCountry && item.country_code) {
				subParts.push(escapeHtml(item.country_code));
			}
			if (CITY_CONFIG.showRegion && item.region) {
				subParts.push(escapeHtml(item.region));
			}
			const subText = subParts.length ? ' • ' + subParts.join(' • ') : '';
			
			html.push(
				'<button type="button" class="city-item" ' +
				'data-id="' + escapeHtml(item.id) + '" ' +
				'data-name="' + escapeHtml(item.name) + '" ' +
				'data-country="' + escapeHtml(item.country_code || '') + '" ' +
				'data-region="' + escapeHtml(item.region || '') + '">' +
				'<div class="city-item-name">' + escapeHtml(item.name) + '</div>' +
				(subText ? '<div class="city-item-sub">' + subText + '</div>' : '') +
				'</button>'
			);
		});
		
		return html.join('');
	}
	
	let lastReqId = 0;
	
	function debounce(fn, ms) {
		let t = null;
		return function (...args) {
			clearTimeout(t);
			t = setTimeout(() => fn.apply(this, args), ms);
		};
	}
	
	async function fetchCities(q) {
		if (!cityWrap) return null;
		const url = cityWrap.getAttribute('data-search-url');
		if (!url) return null;
		
		const reqId = ++lastReqId;
		
		const u = new URL(url, window.location.origin);
		u.searchParams.set('q', q || '');
		u.searchParams.set('limit', '30');
		
		const r = await fetch(u.toString(), {
			headers: { 'Accept': 'application/json' },
			credentials: 'same-origin'
		});
		
		if (reqId !== lastReqId) return null;
		if (!r.ok) return null;
		return await r.json();
	}
	
	function applySelected(id, name, countryCode, region) {
		if (cityId) cityId.value = id ? String(id) : '';
		
		if (cityInput) {
			let displayName = name;
			
			const parts = [];
			if (CITY_CONFIG.inputShowCountry && countryCode) {
				parts.push(countryCode);
			}
			if (CITY_CONFIG.inputShowRegion && region) {
				parts.push(region);
			}
			
			if (parts.length) {
				displayName = name + ' (' + parts.join(', ') + ')';
			}
			
			cityInput.value = displayName || '';
		}
		
		hideDropdown();
	}
	
	// Функция для обновления отображения при загрузке
	function updateDisplayFromSelected() {
		if (!cityInput || !cityId) return;
		
		const selectedId = cityId.value;
		if (!selectedId) return;
		
		// Если уже есть значение в city_search, не меняем его
		if (cityInput.value.trim()) return;
		
		// Пробуем найти выбранный город в уже загруженных данных
		// или делаем запрос для получения информации о городе по ID
		// Пока просто оставляем как есть
	}
	
	function groupByCountry(list) {
		const groups = { RU: [], KZ: [], UZ: [], OTHER: [] };
		(list || []).forEach(x => {
			const cc = (x.country_code || '').toUpperCase();
			if (cc === 'RU') groups.RU.push(x);
			else if (cc === 'KZ') groups.KZ.push(x);
			else if (cc === 'UZ') groups.UZ.push(x);
			else groups.OTHER.push(x);
		});
		return groups;
	}
	
	if (cityWrap && cityInput && cityId && dd && results) {
		if (cityInput.disabled) {
			cityInput.classList.add('city-search-input--disabled');
			} else {
			const runSearch = debounce(async (searchTerm) => {
				const q = searchTerm || extractCityNameForSearch(cityInput.value);
				
				if (q.length < 2) {
					clearResults();
					if (q.length === 0) {
						hideDropdown();
						} else {
						showDropdown();
						results.innerHTML = '<div class="city-message">Введите ещё символы…</div>';
					}
					return;
				}
				
				clearResults();
				showDropdown();
				results.innerHTML = '<div class="city-message">Поиск…</div>';
				
				const data = await fetchCities(q);
				if (!data) {
					results.innerHTML = '<div class="city-message">Не удалось загрузить список.</div>';
					return;
				}
				
				const items = Array.isArray(data) ? data : (data.items || []);
				if (!items.length) {
					results.innerHTML = '<div class="city-message">Ничего не найдено.</div>';
					return;
				}
				
				const g = groupByCountry(items);
				
				let html = '';
				if (g.RU.length) html += renderGroup('Россия', g.RU);
				if (g.KZ.length) html += renderGroup('Казахстан', g.KZ);
				if (g.UZ.length) html += renderGroup('Узбекистан', g.UZ);
				if (g.OTHER.length) html += renderGroup('Другие страны', g.OTHER);
				
				results.innerHTML = html;
				
				results.querySelectorAll('.city-item').forEach(btn => {
					btn.addEventListener('click', () => {
						const id = btn.getAttribute('data-id');
						const name = btn.getAttribute('data-name') || btn.querySelector('.city-item-name')?.textContent || '';
						const countryCode = btn.getAttribute('data-country');
						const region = btn.getAttribute('data-region');
						applySelected(id, name, countryCode, region);
					});
				});
			}, 220);
			
			cityInput.addEventListener('input', () => {
				// Пользователь меняет текст - сбрасываем city_id
				cityId.value = '';
				runSearch();
			});
			
			// При фокусе - НЕ меняем визуальное отображение
			cityInput.addEventListener('focus', () => {
				// Просто запускаем поиск по чистому названию
				const searchTerm = extractCityNameForSearch(cityInput.value);
				if (searchTerm.length >= 2) {
					// Запоминаем текущий city_id перед поиском
					const currentCityId = cityId.value;
					
					
					
					// Если ничего не нашли и был выбран город - восстановим?
					// Но это уже внутри runSearch
				}
			});
			
			document.addEventListener('click', (e) => {
				if (!cityWrap.contains(e.target)) {
					hideDropdown();
				}
			});
			
			cityInput.addEventListener('keydown', (e) => {
				if (e.key === 'Escape') hideDropdown();
			});
			
			// При загрузке проверяем, если есть выбранный город но пустой инпут
			updateDisplayFromSelected();
		}
	}
});