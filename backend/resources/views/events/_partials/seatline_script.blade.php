{{-- resources/views/events/_partials/seatline_script.blade.php --}}
{{-- Общий скрипт подсчёта занятых мест/команд на карточке мероприятия (events/_card.blade.php, data-seatline). --}}
{{-- Подключать внутри <script> блока на любой странице, где рендерятся карточки events._card. --}}
// ===== Seats line =====
const seatLines = Array.from(document.querySelectorAll('[data-seatline]'));

// Шкала заполненности: 0-50% свободно (зелёный), 51-70% заполняется (оранж),
// 71-99% почти нет мест (красный), 100% мест нет (серый) — та же шкала, что на
// странице мероприятия (players.blade.php/scripts.blade.php).
function applySeatProgress(el, registered, max) {
	const barWrap  = el.querySelector('[data-seat-progress-wrap]');
	const bar      = el.querySelector('[data-seat-progress-bar]');
	const fullLbl  = el.querySelector('[data-seat-progress-full]');
	if (!bar) return;
	if (!(max > 0)) { if (barWrap) barWrap.style.display = 'none'; return; }

	const percent = Math.max(0, Math.min(100, (registered / max) * 100));
	bar.style.width = percent + '%';
	bar.classList.remove('bg-success', 'bg-warning', 'bg-danger', 'bg-full');
	if (percent >= 100) {
		bar.classList.add('bg-full');
	} else if (percent > 70) {
		bar.classList.add('bg-danger');
	} else if (percent > 50) {
		bar.classList.add('bg-warning');
	} else {
		bar.classList.add('bg-success');
	}
	if (fullLbl) fullLbl.style.display = percent >= 100 ? '' : 'none';
}

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
		applySeatProgress(el, 0, maxCard);
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
			applySeatProgress(el, 0, maxCard);
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
			applySeatProgress(el, tReg, tMax);
		} else {
			const apiMax       = Number(meta.total_capacity ?? meta.max_players ?? 0) || 0;
			const effectiveMax = apiMax > 0 ? apiMax : maxCard;
			const registeredTotal = Number(meta.registered_total ?? 0) || 0;
			if (leftEl)  leftEl.textContent  = String(registeredTotal);
			if (totalEl) totalEl.textContent = String(effectiveMax);
			// Турнир без данных по командам (напр. tournament_individual без настроенного лимита) —
			// не выдаём число игроков за число команд, честно подписываем юнит "игроков".
			if (isTournament && unitEl) unitEl.textContent = unitEl.dataset.unitPlayers ?? unitEl.textContent;
			applySeatProgress(el, registeredTotal, effectiveMax);
			if (!isTournament && effectiveMax > 0 && registeredTotal >= effectiveMax) markCardFull(el);
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

// ===== Состояние кнопки на карточке: «Записаться в резерв» (мест нет) и «Уже идёт» (началось, пока страница открыта) =====
const VP_RESERVE_LABEL = @json(__('events.btn_join_reserve'));
const VP_LIVE_LABEL    = @json(__('events.btn_live_now'));
const VP_FINISHED_LABEL = @json(__('events.msg_event_finished'));

function markCardFull(el) {
	const card = el.closest('.event-card');
	const area = card && card.querySelector('[data-join-area]');
	if (!area || area.dataset.groupMode === '1' || area.dataset.fullApplied === '1' || area.dataset.liveApplied === '1') return;
	const url = (area.dataset.eventUrl || '') + '#waitlist-section';
	const loginBtn = area.querySelector('.js-open-login-popup');
	if (loginBtn) {                       // гость: то же окно входа, но с честной подписью и возвратом в блок резерва
		loginBtn.textContent = VP_RESERVE_LABEL;
		loginBtn.setAttribute('data-return-url', url);
		area.dataset.fullApplied = '1';
		return;
	}
	const target = area.querySelector('.js-open-join') || area.querySelector('form[action*="/join"]');
	if (target) {                         // вошедший, ещё не записан: ведём на страницу события в блок резерва
		const a = document.createElement('a');
		a.href = url; a.className = 'btn btn-secondary w-100'; a.textContent = VP_RESERVE_LABEL;
		(target.closest('form') || target).replaceWith(a);
		area.dataset.fullApplied = '1';
	}
}

function watchEventStart() {
	const now = Date.now() / 1000;
	document.querySelectorAll('[data-join-area][data-starts-ts]').forEach(area => {
		const s = Number(area.dataset.startsTs) || 0, e = Number(area.dataset.endsTs) || 0;
		if (!s || now < s || area.dataset.liveApplied === '1') return;
		area.dataset.liveApplied = '1';
		if (e && now >= e) {
			area.innerHTML = '<div class="alert alert-info"></div>';
			area.firstChild.textContent = VP_FINISHED_LABEL;
		} else {
			const a = document.createElement('a');
			a.href = area.dataset.eventUrl || '#'; a.className = 'btn btn-secondary btn-live w-100'; a.textContent = VP_LIVE_LABEL;
			area.innerHTML = ''; area.appendChild(a);
		}
	});
}
watchEventStart();
setInterval(watchEventStart, 15000);
