{{-- resources/views/events/_partials/seatline_script.blade.php --}}
{{-- Общий скрипт подсчёта занятых мест/команд на карточке мероприятия (events/_card.blade.php, data-seatline). --}}
{{-- Подключать внутри <script> блока на любой странице, где рендерятся карточки events._card. --}}
// ===== Seats line =====
const seatLines = Array.from(document.querySelectorAll('[data-seatline]'));

async function loadSeatLine(el) {
	const occId        = el.dataset.occurrenceId;
	const regEnabled   = el.dataset.registrationEnabled === '1';
	const maxCard      = Number(el.dataset.maxPlayers ?? 0) || 0;
	if (maxCard <= 0) return;

	const leftEl  = el.querySelector('[data-left]');
	const totalEl = el.querySelector('[data-total]');
	if (totalEl) totalEl.textContent = String(maxCard);

	if (!regEnabled) {
		if (leftEl) leftEl.textContent = '0';
		return;
	}

	try {
		const res = await fetch(`/occurrences/${occId}/availability`, {
			method: 'GET',
			headers: { 'Accept': 'application/json' },
			credentials: 'same-origin',
		});
		let data = null;
		try { data = await res.json(); } catch (e) {}

		const meta = data?.meta || data?.data?.meta || null;
		if (!data || !meta) {
			if (leftEl) leftEl.textContent = '0';
			return;
		}

		const isTournament = el.dataset.isTournament === '1';
		const unitEl = el.querySelector('[data-seat-unit]');
		if (isTournament && meta.tournament_teams_max > 0) {
			const tMax = Number(meta.tournament_teams_max);
			const tReg = Number(meta.tournament_teams_registered ?? 0) || 0;
			if (leftEl)  leftEl.textContent  = String(tReg);
			if (totalEl) totalEl.textContent = String(tMax);
			if (unitEl)  unitEl.textContent  = unitEl.dataset.unitTeams ?? unitEl.textContent;
		} else {
			const apiMax       = Number(meta.total_capacity ?? meta.max_players ?? 0) || 0;
			const effectiveMax = apiMax > 0 ? apiMax : maxCard;
			const registeredTotal = Number(meta.registered_total ?? 0) || 0;
			if (leftEl)  leftEl.textContent  = String(registeredTotal);
			if (totalEl) totalEl.textContent = String(effectiveMax);
			// Турнир без данных по командам (напр. tournament_individual без настроенного лимита) —
			// не выдаём число игроков за число команд, честно подписываем юнит "игроков".
			if (isTournament && unitEl) unitEl.textContent = unitEl.dataset.unitPlayers ?? unitEl.textContent;
		}
	} catch (e) {}
}

if (seatLines.length) {
	const concurrency = 3;
	let i = 0;
	async function worker() {
		while (i < seatLines.length) { const idx = i++; await loadSeatLine(seatLines[idx]); }
	}
	for (let k = 0; k < concurrency; k++) worker();
}
