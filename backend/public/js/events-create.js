
// === Скрытие запасных при 2x2 ===
function toggleReserveFields() {
    var schemeEl = document.getElementById('tournament_game_scheme');
    var reserveWrap = document.getElementById('reserve_players_wrap');
    var totalWrap = document.getElementById('total_players_wrap');

    if (!schemeEl || !reserveWrap || !totalWrap) return;

    var scheme = schemeEl.value;
    var hide = (scheme === '2x2');

    reserveWrap.style.display = hide ? 'none' : '';
    totalWrap.style.display = hide ? 'none' : '';

    if (hide) {
        var reserveInput = document.getElementById('tournament_reserve_players_max');
        var totalInput = document.getElementById('tournament_total_players_max');
        if (reserveInput) reserveInput.value = '0';
        if (totalInput) totalInput.value = '';
    }
}

// public/js/events-create.js
// Trix: запрет загрузки файлов/картинок
document.addEventListener("trix-file-accept", function (event) {
	event.preventDefault();
});

(function () {
	// ========== 1. ВСЕ ОБЪЯВЛЕНИЯ ПЕРЕМЕННЫХ ==========
	var dirEl = null;
	var fmtEl = null;
	var registrationModeEl = null;
	var tournamentSchemeEl = null;
	var tournamentMinEl = null;
	var tournamentReserveEl = null;
	var tournamentTotalEl = null;
	var tournamentLiberoEl = null;
	var tournamentRatingSumEl = null;
	var cityWrap = null;
	var cityInput = null;
	var cityDd = null;
	var cityResults = null;
	var cityHiddenId = null;
	var tzHidden = null;
	var stepBlocks = null;
	var btnNext = null;
	var btnBack = null;
	var stepNumEl = null;
	var percentEl = null;
	var barEl = null;
	var pill1 = null;
	var pill2 = null;
	var pill3 = null;
	var wizardStepHidden = null;
	var trainerBlock = null;
	var levelsClassic = null;
	var levelsBeach = null;
	var gameSubtype = null;
	var gameMinEl = null;
	var gameMaxEl = null;
	var liberoModeBlock = null;
	var liberoModeSelect = null;
	var gameDefaultsHint = null;
	var gameMinHint = null;
	var gameMaxHint = null;
	var genderPolicyEl = null;
	var limitedSideWrap = null;
	var limitedMaxWrap = null;
	var limitedPositionsWrap = null;
	var genderMaxEl = null;
	var positionsBox = null;
	var positionsOldJson = null;
	var positionsClearBtn = null;
	var legacyAllowGirls = null;
	var agePolicyEl = null;
    var childAgeWrap = null;
    var childAgeMinEl = null;
    var childAgeMaxEl = null;
	var legacyGirlsMax = null;
	var gender5050Hint = null;
	var recEl = null;
	var recFields = null;
	var recType = null;
	var recInterval = null;
	var monthsWrap = null;
	var recurrenceHint = null;
	var regTimingBox = null;
	var regStartsInp = null;
	var regEndsInp = null;
	var cancelInp = null;
	var noRegStub = null;
	var climateBlock = null;
	var teamsEl = null;
	var liberoEl = null;
	var tournamentTeamsCountEl = null;
	
	// ========== 2. ПОЛУЧЕНИЕ DOM-ЭЛЕМЕНТОВ ==========
	dirEl = document.getElementById('direction');
	fmtEl = document.getElementById('format');
	registrationModeEl = document.getElementById('registration_mode');
	
	tournamentSchemeEl = document.getElementById('tournament_game_scheme');
	tournamentMinEl = document.getElementById('tournament_team_size_min');
	tournamentReserveEl = document.getElementById('tournament_reserve_players_max');
	tournamentTotalEl = document.getElementById('tournament_total_players_max');
	tournamentLiberoEl = document.getElementById('tournament_require_libero');
	tournamentRatingSumEl = document.getElementById('tournament_max_rating_sum');
	tournamentTeamsCountEl = document.getElementById('tournament_teams_count');
	
	cityWrap = document.getElementById('event-city-autocomplete');
	cityInput = document.getElementById('event_city_q');
	cityDd = document.getElementById('event_city_dropdown');
	cityResults = document.getElementById('event_city_results');
	cityHiddenId = document.getElementById('event_city_id');
	tzHidden = document.getElementById('event_timezone_hidden');
	
	trainerBlock = document.getElementById('trainer_block');
	levelsClassic = document.getElementById('levels_classic');
	levelsBeach = document.getElementById('levels_beach');
	gameSubtype = document.getElementById('game_subtype');
	gameMinEl = document.getElementById('game_min_players');
	gameMaxEl = document.getElementById('game_max_players');
	liberoModeBlock = document.getElementById('libero_mode_block');
	liberoModeSelect = document.getElementById('game_libero_mode');
	gameDefaultsHint = document.getElementById('game_defaults_hint');
	gameMinHint = document.getElementById('game_min_hint');
	gameMaxHint = document.getElementById('game_max_hint');
	
	genderPolicyEl = document.getElementById('game_gender_policy');
	
	childAgeWrap = document.getElementById('child_age_wrap');
    childAgeMinEl = document.querySelector('input[name="child_age_min"]');
    childAgeMaxEl = document.querySelector('input[name="child_age_max"]');
	
	limitedSideWrap = document.getElementById('gender_limited_side_wrap');
	limitedMaxWrap = document.getElementById('gender_limited_max_wrap');
	limitedPositionsWrap = document.getElementById('gender_limited_positions_wrap');
	genderMaxEl = document.getElementById('game_gender_limited_max');
	positionsBox = document.getElementById('gender_positions_box');
	positionsOldJson = document.getElementById('gender_positions_old_json');
	positionsClearBtn = document.getElementById('gender_positions_clear');
	legacyAllowGirls = document.getElementById('game_allow_girls_legacy');
	legacyGirlsMax = document.getElementById('game_girls_max_legacy');
	gender5050Hint = document.getElementById('gender_5050_hint');
	
	recEl = document.getElementById('is_recurring');
	recFields = document.getElementById('recurrence_fields');
	recType = document.getElementById('recurrence_type');
	recInterval = document.getElementById('recurrence_interval');
	monthsWrap = document.getElementById('months_wrap');
	recurrenceHint = document.getElementById('recurrence_hint');
	
	regTimingBox = document.getElementById('reg_timing_box');
	regStartsInp = document.getElementById('reg_starts_days_before');
	regEndsInp = document.getElementById('reg_ends_minutes_before');
	cancelInp = document.getElementById('cancel_lock_minutes_before');
	noRegStub = document.getElementById('no_registration_stub');
	climateBlock = document.getElementById('climate_block');
	teamsEl = document.getElementById('teams_count');
	liberoEl = document.getElementById('game_libero_mode');
	
	// steps
	stepBlocks = qsa('[data-step]');
	btnNext = qsa('[data-next]');
	btnBack = qsa('[data-back]');
	stepNumEl = document.getElementById('wizard_step_num');
	percentEl = document.getElementById('wizard_percent');
	barEl = document.getElementById('wizard_bar');
	pill1 = document.getElementById('pill_1');
	pill2 = document.getElementById('pill_2');
	pill3 = document.getElementById('pill_3');
	wizardStepHidden = document.getElementById('wizard_step');
	
	// ========== 3. ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ ==========
	function hasClass(el, c) { return el && el.classList && el.classList.contains(c); }
	function addClass(el, c) { if (el && el.classList) el.classList.add(c); }
	function removeClass(el, c) { if (el && el.classList) el.classList.remove(c); }
	function toggleClass(el, c, on) { if (!el || !el.classList) return; if (on) el.classList.add(c); else el.classList.remove(c); }
	
	function qs(sel, root) { return (root || document).querySelector(sel); }
	function qsa(sel, root) { return (root || document).querySelectorAll(sel); }
	
	function trim(s) { return String(s || '').replace(/^\s+|\s+$/g, ''); }
	
	function escHtml(s) {
		return String(s || '')
		.replace(/&/g, '&amp;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;')
		.replace(/"/g, '&quot;')
		.replace(/'/g, '&#039;');
	}
	
	function debounce(fn, ms) {
		var t = null;
		return function(){
			var args = arguments;
			clearTimeout(t);
			t = setTimeout(function(){ fn.apply(null, args); }, ms);
		};
	}
	
	// ========== 3.1 HELPER ДЛЯ AGE POLICY ==========
	function getAgePolicyValue() {
		var checked = document.querySelector('input[name="age_policy"]:checked');
		if (checked) return String(checked.value || '').trim();
		
		var single = document.querySelector('[name="age_policy"]');
		return single ? String(single.value || '').trim() : '';
	}
	
	function getVisibilityNodes() {
		return document.querySelectorAll('[data-show-if], [data-hide-if]');
	}
	
	function matchVisibilityRule(rule) {
		if (!rule) return true;
		
		var parts = rule.split(',');
		
		for (var j = 0; j < parts.length; j++) {
			var pair = parts[j].split('=');
			var field = trim(pair[0] || '');
			var values = String(pair[1] || '').split('|');
			
			if (!field) continue;
			
			var input =
			document.querySelector('[name="' + field + '"]:checked') ||
			document.querySelector('[name="' + field + '"]') ||
			document.getElementById(field);
			
			if (!input) continue;
			
			var v = input.type === 'checkbox'
			? (input.checked ? '1' : '0')
			: String(input.value || '');
			
			if (values.indexOf(v) === -1) {
				return false;
			}
		}
		
		return true;
	}
	
	function updateVisibility() {
		var nodes = getVisibilityNodes();
		
		for (var i = 0; i < nodes.length; i++) {
			var el = nodes[i];
			
			var showRule = el.getAttribute('data-show-if');
			var hideRule = el.getAttribute('data-hide-if');
			
			var showOk = showRule ? matchVisibilityRule(showRule) : true;
			var hideOk = hideRule ? matchVisibilityRule(hideRule) : false;
			
			var visible = showOk && !hideOk;
			
			toggleClass(el, 'hidden', !visible);
		}
	}
	
	function setSelectToFirstAllowed(selectEl) {
		if (!selectEl) return;
		var opts = selectEl.querySelectorAll('option');
		for (var i = 0; i < opts.length; i++) {
			if (!opts[i].disabled && !opts[i].hidden && String(opts[i].value || '') !== '') {
				selectEl.value = opts[i].value;
				return;
			}
		}
	}
	
	function clearGenderLimitedData() {
		if (genderMaxEl) genderMaxEl.value = '';
		
		var radios = qsa('input[name="game_gender_limited_side"]');
		for (var i = 0; i < radios.length; i++) radios[i].checked = false;
		
		if (positionsBox) {
			var cbs = positionsBox.querySelectorAll('input[type="checkbox"][name="game_gender_limited_positions[]"]');
			for (var j = 0; j < cbs.length; j++) cbs[j].checked = false;
		}
		
		if (positionsOldJson) positionsOldJson.value = '[]';
	}
	
	function ensureDefaultFormat(reason) {
		if (!fmtEl) return;
		
		if (!trim(fmtEl.value || '')) {
			fmtEl.value = 'game';
		}
	}


	// ========== 4. ОСНОВНЫЕ ФУНКЦИИ ==========
	
	function setActivePills(step) {
		var pills = [
			{ el: pill1, s: 1 },
			{ el: pill2, s: 2 },
			{ el: pill3, s: 3 }
		];
		for (var i = 0; i < pills.length; i++) {
			var p = pills[i];
			if (!p.el) continue;
			removeClass(p.el, 'is-active');
			removeClass(p.el, 'is-done');
			if (p.s < step) addClass(p.el, 'is-done');
			else if (p.s === step) addClass(p.el, 'is-active');
		}
	}
	
	function stepPercent(step) {
		if (step === 1) return 33;
		if (step === 2) return 66;
		return 100;
	}
	
	function setBarColor(step) {
		var c = '#111827';
		if (step === 1) c = '#2967BA';
		if (step === 2) c = '#E7612F';
		if (step === 3) c = '#10b981';
		if (barEl) barEl.style.backgroundColor = c;
	}
	
	function showStep(step) {
		for (var i = 0; i < stepBlocks.length; i++) {
			var b = stepBlocks[i];
			var isActive = Number(b.getAttribute('data-step')) === step;
			toggleClass(b, 'hidden', !isActive);
			toggleClass(b, 'is-active', isActive);
		}
		if (stepNumEl) stepNumEl.textContent = String(step);
		
		if (wizardStepHidden) wizardStepHidden.value = String(step);
		
		var pct = stepPercent(step);
		if (barEl) barEl.style.width = pct + '%';
		if (percentEl) percentEl.textContent = pct + '%';
		setActivePills(step);
		setBarColor(step);
		try { window.scrollTo({ top: 0, behavior: 'smooth' }); } catch (e) { window.scrollTo(0, 0); }
	}
	
	function getCurrentStep() {
		for (var i = 0; i < stepBlocks.length; i++) {
			if (!hasClass(stepBlocks[i], 'hidden')) return Number(stepBlocks[i].getAttribute('data-step')) || 1;
		}
		return 1;
	}
	
	function syncFormatOptions() {
		if (!fmtEl) return;
		
		var direction = dirEl ? String(dirEl.value || '') : '';
		
		var allowClassic = { game: 1, training: 1, training_game: 1, tournament: 1, camp: 1 };
		var allowBeach = { game: 1, training: 1, training_game: 1, coach_student: 1, tournament: 1, camp: 1 };
		
		var opts = fmtEl.querySelectorAll('option');
		for (var i = 0; i < opts.length; i++) {
			var v = String(opts[i].value || '');
			if (!v) continue;
			var ok = (direction === 'beach') ? !!allowBeach[v] : !!allowClassic[v];
			opts[i].disabled = !ok;
			opts[i].hidden = !ok;
		}
		
		var cur = String(fmtEl.value || '');
		var curOpt = fmtEl.querySelector('option[value="' + cur + '"]');
		var curAllowed = curOpt && !curOpt.disabled && !curOpt.hidden;
		
		if (!curAllowed) {
			var gameOpt = fmtEl.querySelector('option[value="game"]');
			if (gameOpt && !gameOpt.disabled && !gameOpt.hidden) fmtEl.value = 'game';
			else setSelectToFirstAllowed(fmtEl);
		}
	}
	
	function syncRegistrationModeOptions() {
		if (!registrationModeEl) return;
		
		var direction = dirEl ? String(dirEl.value || '') : '';
		
		var opts = registrationModeEl.querySelectorAll('option');
		for (var i = 0; i < opts.length; i++) {
			var opt = opts[i];
			var allowedDirections = String(opt.getAttribute('data-direction') || '').split(/\s+/);
			var ok = allowedDirections.indexOf(direction) !== -1;
			
			opt.disabled = !ok;
			opt.hidden = !ok;
		}
		
		var cur = String(registrationModeEl.value || '');
		var curOpt = registrationModeEl.querySelector('option[value="' + cur + '"]');
		var curAllowed = curOpt && !curOpt.disabled && !curOpt.hidden;
		
		if (!curAllowed) {
			registrationModeEl.value = 'single';
		}
	}
	
	function getSubtypeMeta() {
		var cfg = window.volleyballConfig || {};
		
		var direction = dirEl ? dirEl.value : '';
		var subtype = gameSubtype ? gameSubtype.value : '';
		
		if (!cfg[direction] || !cfg[direction].subtypes) return null;
		if (!cfg[direction].subtypes[subtype]) return null;
		
		return cfg[direction].subtypes[subtype];
	}
	function getTournamentBasePlayersByScheme(scheme) {
    	if (scheme === '2x2') return 2;
    	if (scheme === '3x3') return 3;
    	if (scheme === '4x4') return 4;
    	if (scheme === '4x2') return 6;
    	if (scheme === '5x1') return 6;
    	if (scheme === '5x1_libero') return 7;
    	return 0;
	}
    
    function updateTournamentTotalPlayers() {
    	if (!tournamentSchemeEl || !tournamentReserveEl || !tournamentTotalEl) return;
		
    	var scheme = String(tournamentSchemeEl.value || '');
    	var basePlayers = getTournamentBasePlayersByScheme(scheme);
    	var reserve = Number(tournamentReserveEl.value || 0);
		
    	if (!basePlayers) return;
    	if (isNaN(reserve) || reserve < 0) reserve = 0;
		
    	tournamentTotalEl.value = String(basePlayers + reserve);
	}
	function syncTournamentSchemeOptions() {
		if (!tournamentSchemeEl) return;
		
		var direction = dirEl ? String(dirEl.value || '') : '';
		var format = fmtEl ? String(fmtEl.value || '') : '';
		
		if (format !== 'tournament') return;
		
		var allowClassic = {
			'4x4': 1,
			'4x2': 1,
			'5x1': 1,
			'5x1_libero': 1
		};
		
		var allowBeach = {
			'2x2': 1,
			'3x3': 1,
			'4x4': 1
		};
		
		var opts = tournamentSchemeEl.querySelectorAll('option');
		
		for (var i = 0; i < opts.length; i++) {
			var v = String(opts[i].value || '');
			if (!v) continue;
			
			var ok = (direction === 'beach') ? !!allowBeach[v] : !!allowClassic[v];
			opts[i].disabled = !ok;
			opts[i].hidden = !ok;
		}
		
		var cur = String(tournamentSchemeEl.value || '');
		var curOpt = tournamentSchemeEl.querySelector('option[value="' + cur + '"]');
		var curAllowed = curOpt && !curOpt.disabled && !curOpt.hidden;
		
		if (!curAllowed) {
			tournamentSchemeEl.value = (direction === 'beach') ? '2x2' : '5x1';
		}
	}
	
	function applyTournamentDefaults() {
        if (!tournamentSchemeEl || !fmtEl) return;
        if (String(fmtEl.value || '') !== 'tournament') return;
		
        var direction = dirEl ? String(dirEl.value || '') : '';
        var scheme = String(tournamentSchemeEl.value || '');
		
        var defaults = null;
		
        if (direction === 'beach') {
            if (scheme === '2x2') defaults = { base: 2 };
            if (scheme === '3x3') defaults = { base: 3 };
            if (scheme === '4x4') defaults = { base: 4 };
			} else {
            if (scheme === '4x4') defaults = { base: 4 };
            if (scheme === '4x2') defaults = { base: 6 };
            if (scheme === '5x1') defaults = { base: 6 };
            if (scheme === '5x1_libero') defaults = { base: 7 };
		}
		
        if (!defaults) return;
		
        // основной состав
        if (tournamentMinEl) {
            tournamentMinEl.value = String(defaults.base);
		}
		
        // total = base + reserve
        function recalcTotal() {
            var reserve = Number(tournamentReserveEl ? tournamentReserveEl.value : 0) || 0;
            var total = defaults.base + reserve;
			
            if (tournamentTotalEl) {
                tournamentTotalEl.value = String(total);
			}
		}
		
        recalcTotal();
		
        if (tournamentReserveEl) {
            tournamentReserveEl.addEventListener('input', recalcTotal);
		}
	}
	
	function applyGameDefaults() {
		var meta = getSubtypeMeta();
		if (!meta) return;
		
		if (gameMinEl && !gameMinEl.value)
		gameMinEl.value = meta.min_players;
		
		if (gameMaxEl && !gameMaxEl.value)
		gameMaxEl.value = meta.max_players;
	}
	
	var lastClassic = { subtype: '', min: '', max: '' };
	var lastBeach = { subtype: '', min: '', max: '' };
	
	function cacheGameState() {
		var direction = dirEl ? String(dirEl.value || '') : '';
		var format = fmtEl ? String(fmtEl.value || '') : '';
		
		var st = gameSubtype ? trim(gameSubtype.value || '') : '';
		var mn = gameMinEl ? trim(gameMinEl.value || '') : '';
		var mx = gameMaxEl ? trim(gameMaxEl.value || '') : '';
		
		if (direction === 'classic') {
			if (st) lastClassic.subtype = st;
			if (mn !== '') lastClassic.min = mn;
			if (mx !== '') lastClassic.max = mx;
			} else if (direction === 'beach') {
			if (st) lastBeach.subtype = st;
			if (mn !== '') lastBeach.min = mn;
			if (mx !== '') lastBeach.max = mx;
		}
	}
	
	function restoreGameStateIfEmpty() {
		var direction = dirEl ? String(dirEl.value || '') : '';
		var format = fmtEl ? String(fmtEl.value || '') : '';
		
		var cache = (direction === 'beach') ? lastBeach : lastClassic;
		
		if (gameSubtype && trim(gameSubtype.value || '') === '' && cache.subtype) {
			gameSubtype.value = cache.subtype;
		}
	}
	
	function syncGameSubtypeOptions() {
		if (!gameSubtype) return;
		
		var direction = dirEl ? dirEl.value : '';
		var format = fmtEl ? fmtEl.value : '';
		var isGame = (format === 'game');
		
		cacheGameState();
		
		var current = trim(gameSubtype.value || '') ||
		((dirEl && dirEl.value === 'beach') ? lastBeach.subtype : lastClassic.subtype);
		var opts = [];
		
		if (direction === 'classic') {
			opts = [
				{ v: '4x4', t: '4×4' },
				{ v: '4x2', t: '4×2' },
				{ v: '5x1', t: '5×1' }
			];
			} else {
			opts = [
				{ v: '2x2', t: '2×2' },
				{ v: '3x3', t: '3×3' },
				{ v: '4x4', t: '4×4' }
			];
		}
		
		gameSubtype.innerHTML = '';
		for (var i = 0; i < opts.length; i++) {
			var o = document.createElement('option');
			o.value = opts[i].v;
			o.textContent = opts[i].t;
			if (opts[i].v && opts[i].v === current) o.selected = true;
			gameSubtype.appendChild(o);
		}
		ensureDefaultSubtypeIfEmpty();
	}
	
	function syncGenderPolicyOptions() {
		if (!genderPolicyEl) return;
		
		var direction = dirEl ? String(dirEl.value || '') : '';
		var policy = trim(genderPolicyEl.value || '');
		
		if (!policy) {
        	genderPolicyEl.value = 'mixed_open';
        	policy = trim(genderPolicyEl.value || '');
		}
		
		var allowClassic = { mixed_open: 1, only_male: 1, only_female: 1, mixed_limited: 1 };
		var allowBeach = { mixed_open: 1, only_male: 1, only_female: 1, mixed_5050: 1 };
		
		var opts = genderPolicyEl.querySelectorAll('option');
		for (var i = 0; i < opts.length; i++) {
			var v = String(opts[i].value || '');
			if (!v) continue;
			var ok = (direction === 'beach') ? !!allowBeach[v] : !!allowClassic[v];
			opts[i].disabled = !ok;
			opts[i].hidden = !ok;
		}
		
		if (direction === 'beach') {
			var opt5050 = genderPolicyEl.querySelector('option[value="mixed_5050"]');
			if (!opt5050) {
				opt5050 = document.createElement('option');
				opt5050.value = 'mixed_5050';
				opt5050.textContent = 'Микс 50/50 М/Ж';
				genderPolicyEl.appendChild(opt5050);
			}
			opt5050.disabled = false;
			opt5050.hidden = false;
		}
		
		var cur = String(genderPolicyEl.value || '');
		var curOpt = genderPolicyEl.querySelector('option[value="' + cur + '"]');
		var curAllowed = curOpt && !curOpt.disabled && !curOpt.hidden;
		
    	if (!curAllowed) {
        	genderPolicyEl.value = 'mixed_open';
		}
		
		if (direction === 'beach') {
			clearGenderLimitedData();
			if (limitedSideWrap) addClass(limitedSideWrap, 'hidden');
			if (limitedMaxWrap) addClass(limitedMaxWrap, 'hidden');
			if (limitedPositionsWrap) addClass(limitedPositionsWrap, 'hidden');
		}
		
		syncGenderLimitedBlocks();
		syncGender5050Hint();
	}
	
	var POS_LABELS = {
		setter: 'Связующий (setter)',
		outside: 'Доигровщик (outside)',
		opposite: 'Диагональный (opposite)',
		middle: 'Центральный (middle)',
		libero: 'Либеро (libero)'
	};
	
	function syncGender5050Hint() {
		if (!gender5050Hint) return;
		
		var policy = genderPolicyEl ? trim(genderPolicyEl.value || 'mixed_open') : 'mixed_open';
		var show = (policy === 'mixed_5050');
		
		toggleClass(gender5050Hint, 'hidden', !show);
		
		if (show) {
			gender5050Hint.textContent = 'Для 50/50 max_players должен быть чётным.';
			} else {
			gender5050Hint.textContent = '';
		}
	}
	
	function positionsForSubtype() {
		var st = gameSubtype ? trim(gameSubtype.value) : '';
		var libero = liberoModeSelect ? trim(liberoModeSelect.value || 'with_libero') : 'with_libero';
		
		if (st === '4x2') return ['setter', 'outside'];
		if (st === '4x4') return ['setter', 'outside', 'opposite'];
		if (st === '5x1') return (libero === 'with_libero')
		? ['setter', 'outside', 'opposite', 'middle', 'libero']
		: ['setter', 'outside', 'opposite', 'middle'];
		return [];
	}
	
	function getOldSelectedPositions() {
		try {
			var raw = positionsOldJson ? (positionsOldJson.value || '[]') : '[]';
			var arr = JSON.parse(raw);
			if (!arr || !arr.length) return [];
			var out = [];
			for (var i = 0; i < arr.length; i++) out.push(String(arr[i]));
			return out;
			} catch (e) {
			return [];
		}
	}
	
	function getCurrentSelectedPositions() {
		if (!positionsBox) return [];
		var cbs = positionsBox.querySelectorAll('input[type="checkbox"][name="game_gender_limited_positions[]"]:checked');
		var out = [];
		for (var i = 0; i < cbs.length; i++) out.push(String(cbs[i].value));
		return out;
	}
	
	function buildPositionsCheckboxes() {
		if (!positionsBox) return;
		
		var list = positionsForSubtype();
		var cur = getCurrentSelectedPositions();
		var old = getOldSelectedPositions();
		
		var curMap = {};
		for (var i = 0; i < cur.length; i++) curMap[cur[i]] = true;
		
		var oldMap = {};
		for (var j = 0; j < old.length; j++) oldMap[old[j]] = true;
		
		positionsBox.innerHTML = '';
		
		if (!list.length) {
			var div = document.createElement('div');
			div.className = 'text-xs text-gray-500';
			div.textContent = 'Сначала выбери подтип игры (и режим либеро для 5×1), чтобы показать список позиций.';
			positionsBox.appendChild(div);
			return;
		}
		
		for (var k = 0; k < list.length; k++) {
			var key = list[k];
			
			var label = document.createElement('label');
			label.className = 'checkbox-item';
			
			var cb = document.createElement('input');
			cb.type = 'checkbox';
			cb.name = 'game_gender_limited_positions[]';
			cb.value = key;
			
			var hasCur = (cur.length > 0);
			cb.checked = hasCur ? !!curMap[key] : !!oldMap[key];
			
			var customCheckbox = document.createElement('div');
			customCheckbox.className = 'custom-checkbox';
			
			var span = document.createElement('span');
			span.textContent = POS_LABELS[key] || key;
			
			label.appendChild(cb);
			label.appendChild(customCheckbox);
			label.appendChild(span);
			positionsBox.appendChild(label);
		}
	}
	
	function clearPositionsSelection() {
		if (!positionsBox) return;
		var all = positionsBox.querySelectorAll('input[type="checkbox"][name="game_gender_limited_positions[]"]');
		for (var i = 0; i < all.length; i++) all[i].checked = false;
	}
	
	function updateLegacyMappingOnly() {
		if (!legacyAllowGirls || !legacyGirlsMax) return;
		
		var policy = genderPolicyEl ? trim(genderPolicyEl.value || 'mixed_open') : 'mixed_open';
		
		if (policy === 'only_male') {
			legacyAllowGirls.value = '0';
			legacyGirlsMax.value = '';
			return;
		}
		
		legacyAllowGirls.value = '1';
		
		if (policy === 'mixed_limited') {
			var sideEl = qs('input[name="game_gender_limited_side"]:checked');
			var side = sideEl ? trim(sideEl.value || 'female') : 'female';
			legacyGirlsMax.value = (side === 'female') ? String(trim(genderMaxEl ? genderMaxEl.value : '')) : '';
			} else {
			legacyGirlsMax.value = '';
		}
	}
	
	function syncGenderLimitedBlocks() {
		var policy = genderPolicyEl ? trim(genderPolicyEl.value || 'mixed_open') : 'mixed_open';
		var isLimited = (policy === 'mixed_limited');
		
		if (limitedSideWrap) toggleClass(limitedSideWrap, 'hidden', !isLimited);
		if (limitedMaxWrap) toggleClass(limitedMaxWrap, 'hidden', !isLimited);
		if (limitedPositionsWrap) toggleClass(limitedPositionsWrap, 'hidden', !isLimited);
		
		if (isLimited) buildPositionsCheckboxes();
		updateLegacyMappingOnly();
		
		syncGender5050Hint();
	}
	
	function syncMonthsVisibility() {
		if (!monthsWrap || !recType) return;
		monthsWrap.style.display = (recType.value === 'monthly') ? '' : 'none';
	}
	
	function syncRecFieldsVisibility() {
		if (!recEl || !recFields) return;
		
		if (recEl.checked) {
			recFields.style.display = '';
			} else {
			recFields.style.display = 'none';
			// Сбрасываем только тип, интервал не трогаем
			if (recType) recType.value = '';
			// recInterval не сбрасываем — остается 1
		}
		
		syncMonthsVisibility();
	}
	
	function getAllowRegistrationValue() {
		var el = qs('input[name="allow_registration"]:checked');
		if (!el) return 1;
		return Number(el.value) === 1 ? 1 : 0;
	}
	
	function clearRecurrenceInputs() {
		if (recEl) recEl.checked = false;
		if (recType) recType.value = '';
		if (recInterval) recInterval.value = '1';
		
		var monthCbs = qsa('input[name="recurrence_months[]"]');
		for (var i = 0; i < monthCbs.length; i++) monthCbs[i].checked = false;
		
		var legacy = qs('input[name="recurrence_rule"]');
		if (legacy) legacy.value = '';
	}
	
	function enforceRegistrationRules() {
		var allowReg = getAllowRegistrationValue();
		
		if (noRegStub) toggleClass(noRegStub, 'hidden', allowReg === 1);
		if (recEl) {
			if (allowReg === 0) {
				clearRecurrenceInputs();
				recEl.disabled = true;
				if (recFields) recFields.style.opacity = '0.45';
				if (recurrenceHint) recurrenceHint.style.display = '';
				} else {
				recEl.disabled = false;
				if (recFields) recFields.style.opacity = '1';
				if (recurrenceHint) recurrenceHint.style.display = 'none';
			}
		}
		if (regTimingBox) {
			var on = (allowReg === 1);
			regTimingBox.style.opacity = on ? '1' : '0.45';
			
			if (regStartsInp) regStartsInp.disabled = !on;
			if (regEndsInp) regEndsInp.disabled = !on;
			if (cancelInp) cancelInp.disabled = !on;
		}
		syncRecFieldsVisibility();
	}
	
	function ensureDefaultSubtypeIfEmpty() {
		if (!gameSubtype) return;
		
		var direction = dirEl ? String(dirEl.value || '') : '';
		var format = fmtEl ? String(fmtEl.value || '') : '';
		
		var st = trim(gameSubtype.value || '');
		if (st && gameSubtype.querySelector('option[value="' + st + '"]')) return;
		
		gameSubtype.value = (direction === 'beach') ? '2x2' : '4x2';
	}
	
	function bindGameCacheListeners() {
    	if (gameSubtype) gameSubtype.addEventListener('change', cacheGameState);
    	if (gameMinEl) gameMinEl.addEventListener('input', cacheGameState);
    	if (gameMaxEl) gameMaxEl.addEventListener('input', cacheGameState);
		
    	if (tournamentSchemeEl) {
    		tournamentSchemeEl.addEventListener('change', function () {
    			if (tournamentReserveEl) tournamentReserveEl.value = '';
    			applyTournamentDefaults();
    			updateVisibility();
			});
		}
		
    	if (tournamentReserveEl) {
    		tournamentReserveEl.addEventListener('input', function () {
    			updateTournamentTotalPlayers();
			});
		}
	}
	
	// ========== 5. ВАЛИДАЦИЯ ==========
	function validateStep(step) {
		function focusEl(el) { try { if (el && el.focus) el.focus(); } catch (e) { } }
		function val(el) { return el ? trim(el.value) : ''; }
		function need(cond, msg, el) {
			if (!cond) { alert(msg); focusEl(el); return false; }
			return true;
		}
		function num(el) { return Number(val(el)); }
		function has(el) { return val(el) !== ''; }
		
		function checkMinMaxPair(minName, maxName, label) {
			var minEl = qs('select[name="' + minName + '"]') || qs('input[name="' + minName + '"]');
			var maxEl = qs('select[name="' + maxName + '"]') || qs('input[name="' + maxName + '"]');
			
			if (!has(minEl) || !has(maxEl)) return true;
			
			var a = Number(val(minEl));
			var b = Number(val(maxEl));
			
			if (!isNaN(a) && !isNaN(b) && b < a) {
				alert(label + ': "До (max)" не может быть меньше "От (min)".');
				focusEl(maxEl);
				return false;
			}
			return true;
		}
		
		// ✅ ИСПРАВЛЕНО: используем getAgePolicyValue()
		var agePolicy = getAgePolicyValue();

        if (agePolicy === 'child') {
            var minVal = Number(childAgeMinEl ? childAgeMinEl.value : '');
            var maxVal = Number(childAgeMaxEl ? childAgeMaxEl.value : '');
        
            if (!childAgeMinEl || !childAgeMinEl.value || !childAgeMaxEl || !childAgeMaxEl.value) {
                alert('Укажи допустимый возраст детей.');
                if (childAgeMinEl) childAgeMinEl.focus();
                return false;
            }
        
            if (!Number.isFinite(minVal) || !Number.isFinite(maxVal)) {
                alert('Возраст детей должен быть числом.');
                if (childAgeMinEl) childAgeMinEl.focus();
                return false;
            }
        
            if (minVal < 6 || minVal > 17 || maxVal < 6 || maxVal > 17) {
                alert('Возраст детей должен быть в диапазоне от 6 до 17 лет.');
                if (childAgeMinEl) childAgeMinEl.focus();
                return false;
            }
        
            if (minVal > maxVal) {
                alert('Минимальный возраст не может быть больше максимального.');
                if (childAgeMinEl) childAgeMinEl.focus();
                return false;
            }
        }
		
		function validateGameClassic() {
			if (!need(gameSubtype && val(gameSubtype), 'Выбери подтип игры (4×4 / 4×2 / 5×1).', gameSubtype)) return false;
			if (!need(gameMaxEl && val(gameMaxEl), 'Укажи максимум участников для игры.', gameMaxEl)) return false;
			
			if (has(gameMinEl) && has(gameMaxEl)) {
				var minP = num(gameMinEl);
				var maxP = num(gameMaxEl);
				if (!isNaN(minP) && !isNaN(maxP) && maxP < minP) {
					alert('Макс. участников не может быть меньше Мин. участников.');
					focusEl(gameMaxEl);
					return false;
				}
			}
			
			var policy = genderPolicyEl ? trim(genderPolicyEl.value || 'mixed_open') : 'mixed_open';
			if (policy === 'mixed_limited') {
				var side = qs('input[name="game_gender_limited_side"]:checked');
				if (!need(!!side, 'Выбери, кого ограничиваем (М или Ж).', null)) return false;
				
				// ✅ ИСПРАВЛЕНО: правильная проверка genderMaxValue
				var genderMaxValue = val(genderMaxEl);
				if (!need(genderMaxEl && genderMaxValue, 'Укажи максимум мест для ограничиваемых.', genderMaxEl)) return false;
				
				var maxPlayersInput = document.getElementById('game_max_players');
				var maxPlayers = maxPlayersInput ? parseInt(maxPlayersInput.value) : 0;
				
				var genderMaxNum = Number(genderMaxValue);
				if (!need(genderMaxNum >= 1, 'Минимум ограничиваемых мест - 1', genderMaxEl)) return false;
				
				if (maxPlayers > 0 && !need(genderMaxNum <= maxPlayers, 'Максимум ограничиваемых мест не может превышать ' + maxPlayers + '.', genderMaxEl)) return false;
				
				var picked = getCurrentSelectedPositions();
				if (!need(picked.length > 0, 'Выбери минимум одну позицию для ограничения.', positionsBox)) return false;
			}
			updatePreview();
			return true;
		}
		
		function validateGameBeach() {
			if (!need(gameSubtype && val(gameSubtype), 'Выбери подтип игры (2×2 / 3×3 / 4×4).', gameSubtype)) return false;
			if (!need(gameMaxEl && val(gameMaxEl), 'Укажи максимум участников для игры.', gameMaxEl)) return false;
			
			var policy = genderPolicyEl ? trim(genderPolicyEl.value || 'mixed_open') : 'mixed_open';
			if (policy === 'mixed_5050') {
				var mp = Number(val(gameMaxEl));
				if (isNaN(mp) || mp < 2) {
					alert('Для 50/50 минимум 2 участника.');
					try { gameMaxEl.focus(); } catch (e) { }
					return false;
				}
				if (mp % 2 !== 0) {
					alert('Для 50/50 max_players должен быть чётным.');
					try { gameMaxEl.focus(); } catch (e) { }
					return false;
				}
			}
			updatePreview();
			return true;
		}
		
		function readNonNegative(el, msg) {
			if (!el) return true;
			var raw = val(el);
			if (raw === '') { alert(msg); focusEl(el); return false; }
			var n = Number(raw);
			if (isNaN(n) || n < 0) { alert(msg + ' (значение должно быть ≥ 0)'); focusEl(el); return false; }
			return true;
		}
		
		// ---- Step 1 ----
		if (step === 1) {
			var direction = dirEl ? dirEl.value : '';
			var format = fmtEl ? fmtEl.value : '';
			
			if (format === 'tournament') {
            	if (!need(tournamentSchemeEl && val(tournamentSchemeEl), 'Выбери схему турнира.', tournamentSchemeEl)) return false;
            	if (!need(tournamentReserveEl && val(tournamentReserveEl), 'Укажи максимум запасных игроков.', tournamentReserveEl)) return false;
				
            	var scheme = String(val(tournamentSchemeEl));
            	var basePlayers = getTournamentBasePlayersByScheme(scheme);
            	var tReserve = Number(val(tournamentReserveEl));
            	var expectedTotal = basePlayers + tReserve;
            	var tTotal = Number(val(tournamentTotalEl));
            	var tMin = Number(val(tournamentMinEl));
				
            	if (!basePlayers) {
            		alert('Некорректная схема турнира.');
            		focusEl(tournamentSchemeEl);
            		return false;
				}
				
            	if (isNaN(tReserve) || tReserve < 0) {
            		alert('Максимум запасных не может быть отрицательным.');
            		focusEl(tournamentReserveEl);
            		return false;
				}
				
            	if (isNaN(tMin) || tMin !== basePlayers) {
            		if (tournamentMinEl) tournamentMinEl.value = String(basePlayers);
            		alert('Основной состав команды должен соответствовать выбранной схеме.');
            		focusEl(tournamentMinEl);
            		return false;
				}
				
            	if (isNaN(tTotal) || tTotal !== expectedTotal) {
            		if (tournamentTotalEl) tournamentTotalEl.value = String(expectedTotal);
            		alert('Максимальный размер команды пересчитан автоматически.');
            		focusEl(tournamentTotalEl);
            		return false;
				}
				
            	return true;
			}
			
			if (direction === 'classic') {
				if (!validateGameClassic()) return false;
				} else if (direction === 'beach' && format === 'game') {
				if (!validateGameBeach()) return false;
			}
			
			if (!checkMinMaxPair('classic_level_min', 'classic_level_max', 'Уровень Classic')) return false;
			if (!checkMinMaxPair('beach_level_min', 'beach_level_max', 'Уровень Beach')) return false;
			
			return true;
		}
		
		// ---- Step 2 ----
		if (step === 2) {
			ensureCityFromUrl();
			
			if (cityHiddenId && Number(cityHiddenId.value || 0) <= 0) {
				alert('Выбери город.');
				try { if (cityInput) cityInput.focus(); } catch (e) { }
				return false;
			}
			
			var startEl = qs('input[name="starts_at_local"]');
			
			if (startEl && startEl.value) {
				var start = new Date(startEl.value);
				var max = new Date();
				max.setFullYear(max.getFullYear() + 1);
				
				if (start > max) {
					alert('Дата мероприятия не может быть позже чем через 1 год.');
					startEl.focus();
					return false;
				}
			}
			
			
			var loc = document.getElementById('location_id');
			if (!need(loc && val(loc), 'Выбери локацию.', loc)) return false;
			
			var durationSec = document.getElementById('duration_sec');
			if (!durationSec || !durationSec.value || durationSec.value == '0') {
				alert('Укажи продолжительность мероприятия.');
				if (durationSec) durationSec.focus();
				return false;
			}	
			// Проверка на минимальную продолжительность (10 минут = 600 секунд)
			var durationSecValue = Number(durationSec.value);
			if (durationSecValue < 600) {
				alert('Продолжительность мероприятия должна быть не менее 10 минут.');
				if (durationSec) durationSec.focus();
				return false;
			}			
			
			if (recEl && recEl.checked) {
				if (!need(recType && val(recType), 'Выбери тип повторения\n(ежедневно/еженедельно/ежемесячно).', recType)) return false;
				if (!need(recInterval && val(recInterval), 'Укажи интервал повторения.', recInterval)) return false;
				
				// Дополнительная проверка на 0
				var intervalVal = Number(val(recInterval));
				if (recEl && recEl.checked && (!recInterval.value || intervalVal < 1)) {
					alert('Интервал повторения должен быть больше 0.');
					if (recInterval) recInterval.focus();
					return false;
				}
				
				// Проверка для еженедельного повторения
				var recTypeValue = val(recType);
				if (recTypeValue === 'weekly') {
					var weekdaysChecked = document.querySelectorAll('input[name="recurrence_weekdays[]"]:checked');
					if (!weekdaysChecked || weekdaysChecked.length === 0) {
						alert('Для еженедельного повторения выбери хотя бы один день недели.');
						return false;
					}
				}
				
				// Проверка окончания повторения
				var endType = document.querySelector('input[name="recurrence_end_type"]:checked');
				if (!endType || !endType.value) {
					alert('Выбери условие окончания повторения (до даты или по количеству).');
					return false;
				}
				
				if (endType.value === 'until') {
					var untilDate = document.querySelector('input[name="recurrence_end_until"]');
					if (!untilDate || !untilDate.value) {
						alert('Укажи дату окончания повторения.');
						if (untilDate) untilDate.focus();
						return false;
					}
					} else if (endType.value === 'count') {
					var count = document.querySelector('input[name="recurrence_end_count"]');
					var countVal = Number(count ? count.value : 0);
					if (!count || !count.value || countVal < 1) {
						alert('Укажи количество повторений (минимум 1).');
						if (count) count.focus();
						return false;
					}
				}
			}
			
			if (getAllowRegistrationValue() === 1) {
				if (!readNonNegative(regStartsInp, 'Укажи начало регистрации (дней до).')) return false;
				if (!readNonNegative(regEndsInp, 'Укажи окончание регистрации (минут до).')) return false;
				if (!readNonNegative(cancelInp, 'Укажи запрет отмены (минут до).')) return false;
			}
			
			return true;
		}
		
		
		// ---- Step 3 ----
		if (step === 3) {
            var paidEl = document.getElementById('is_paid');
            var isPaid = paidEl ? !!paidEl.checked : false;
        
            var price = qs('input[name="price_amount"]');
            var currency = qs('select[name="price_currency"]');
        
            if (isPaid) {
                var raw = price ? String(price.value || '').trim() : '';
                var normalized = raw.replace(',', '.');
                var amount = Number(normalized);
        
                if (!raw || Number.isNaN(amount)) {
                    alert('Укажи стоимость');
                    if (price) price.focus();
                    return false;
                }
        
                if (amount < 10 || amount > 500000) {
                    alert('Стоимость должна быть от 10 до 500 000');
                    if (price) price.focus();
                    return false;
                }
        
                if (!currency || !String(currency.value || '').trim()) {
                    alert('Укажи валюту');
                    if (currency) currency.focus();
                    return false;
                }
            }
			
			// Проверка файла обложки
			var coverFile = qs('input[name="cover_upload"]');
			if (coverFile && coverFile.files && coverFile.files.length > 0) {
				var file = coverFile.files[0];
				var fileName = file.name;
				var fileExt = fileName.split('.').pop().toLowerCase();
				var allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
				
				if (!allowedExts.includes(fileExt)) {
					alert('Допустимые форматы изображений: \n JPG, PNG, WebP');
					coverFile.value = ''; // очищаем поле
					coverFile.focus();
					return false;
				}
				
				// Проверка размера файла (опционально, например 5MB)
				var maxSize = 5 * 1024 * 1024; // 5MB
				if (file.size > maxSize) {
					alert('Размер файла не должен превышать 5MB.');
					coverFile.value = '';
					coverFile.focus();
					return false;
				}
			}
			
			return true;
		}
		
		return true;
	}
	
	// ========== 6. ОБРАБОТЧИКИ КНОПОК ==========
	for (var iN = 0; iN < btnNext.length; iN++) {
		btnNext[iN].addEventListener('click', function () {
			var step = getCurrentStep();
			if (!validateStep(step)) return;
			showStep(Math.min(3, step + 1));
		});
	}
	
	for (var iB = 0; iB < btnBack.length; iB++) {
		btnBack[iB].addEventListener('click', function () {
			var step = getCurrentStep();
			showStep(Math.max(1, step - 1));
		});
	}
    
	// ========== 7. LOCATION PREVIEW ==========
	var sel = document.getElementById('location_id');
	var wrap = document.getElementById('location_preview');
	var img = document.getElementById('location_preview_img');
	var noimg = document.getElementById('location_preview_noimg');
	var nameEl = document.getElementById('location_preview_name');
	var metaEl = document.getElementById('location_preview_meta');
	var mapWrap = document.getElementById('location_preview_map_wrap');
	var mapEl = document.getElementById('location_preview_map');
	
	function updatePreview() {
		if (!sel) return;
		
		var opt = null;
		if (sel.selectedIndex >= 0) opt = sel.options[sel.selectedIndex];
		
		if (!opt || !opt.value) {
			if (wrap) addClass(wrap, 'hidden');
			if (mapEl) mapEl.src = '';
			return;
		}
		
		var name = opt.getAttribute('data-name') || '';
		var city = opt.getAttribute('data-city') || '';
		var address = opt.getAttribute('data-address') || '';
		var thumb = opt.getAttribute('data-thumb') || '';
		var lat = trim(opt.getAttribute('data-lat') || '');
		var lng = trim(opt.getAttribute('data-lng') || '');
		
		if (wrap) removeClass(wrap, 'hidden');
		if (nameEl) nameEl.textContent = name;
		
		var metaParts = [];
		if (city) metaParts.push(city);
		if (address) metaParts.push(address);
		if (metaEl) metaEl.textContent = metaParts.join(' • ');
		
		
		if (thumb && img && noimg) {
			img.src = thumb;
			removeClass(img, 'hidden');
			addClass(noimg, 'hidden');
			} else if (img && noimg) {
			img.src = '';
			addClass(img, 'hidden');
			removeClass(noimg, 'hidden');
		}
		
		var hasCoords = (lat !== '' && lng !== '' && !isNaN(Number(lat)) && !isNaN(Number(lng)));
		if (mapWrap && mapEl) {
			if (hasCoords) {
				mapWrap.style.display = '';
				var latNum = Number(lat);
				var lngNum = Number(lng);
				
				var theme = localStorage.getItem('theme') === 'dark' ? 'dark' : 'light';
								
				// Яндекс.Карты через iframe (статическая карта с меткой)
				mapEl.src = 'https://yandex.ru/map-widget/v1/?ll=' + lngNum + ',' + latNum + 
				'&z=15&pt=' + lngNum + ',' + latNum + ',pm2rdm' + '&theme=' + theme;
				        mapEl.classList.add('w-100', 'lazy-map', 'iframe-map');
        mapEl.style.height = '32rem';
        mapEl.style.border = '0';
        mapEl.style.borderRadius = '1rem';
				} else {
				mapWrap.style.display = 'none';
				mapEl.src = '';
			}
		}		
	}
	
	if (sel) sel.addEventListener('change', updatePreview);
	updatePreview();
	
	// ========== 8. PAID UX ==========
	var paidEl2 = document.getElementById('is_paid');
	var priceWrap = document.getElementById('price_wrap');
	
	function togglePaid() {
		if (!paidEl2 || !priceWrap) return;
		
		if (paidEl2.checked) {
			priceWrap.classList.remove('hidden');
			} else {
			priceWrap.classList.add('hidden');
		}
	}
	
	if (paidEl2) paidEl2.addEventListener('change', togglePaid);
	togglePaid();
	
	// ========== 9. REGISTRATION RULES ==========
	var allowRegs = qsa('input[name="allow_registration"]');
	for (var ar = 0; ar < allowRegs.length; ar++) {
		allowRegs[ar].addEventListener('change', enforceRegistrationRules);
	}
	
	// ========== 10. GENDER POLICY EVENT LISTENERS ==========
	if (genderPolicyEl) genderPolicyEl.addEventListener('change', syncGenderLimitedBlocks);
	
	var sideRadios = qsa('input[name="game_gender_limited_side"]');
	for (var sr = 0; sr < sideRadios.length; sr++) {
		sideRadios[sr].addEventListener('change', syncGenderLimitedBlocks);
	}
	
	if (genderMaxEl) genderMaxEl.addEventListener('input', updateLegacyMappingOnly);
	if (positionsClearBtn) positionsClearBtn.addEventListener('click', clearPositionsSelection);
	
	if (gameSubtype) gameSubtype.addEventListener('change', function () {
		if (genderPolicyEl && trim(genderPolicyEl.value || '') === 'mixed_limited') buildPositionsCheckboxes();
	});
	
	if (liberoModeSelect) liberoModeSelect.addEventListener('change', function () {
		if (genderPolicyEl && trim(genderPolicyEl.value || '') === 'mixed_limited') buildPositionsCheckboxes();
	});
	
	// ========== 11. RECURRENCE UI ==========
	if (recEl) recEl.addEventListener('change', syncRecFieldsVisibility);
	if (recType) recType.addEventListener('change', syncMonthsVisibility);
	
	
	// ========== 13. CITY AUTOCOMPLETE ==========
	// ========== CITY AUTOCOMPLETE (стилизованный под селект с подгрузкой локаций) ==========
	(function() {
		
		
		if (!cityWrap || !cityInput || !cityHiddenId || !cityDd || !cityResults) return;
		
		const CITY_CONFIG = {
			showCountry: true,        // Показывать страну в выпадающем списке
			showRegion: true,         // Показывать регион в выпадающем списке
			inputShowCountry: true,   // Показывать страну в инпуте после выбора
			inputShowRegion: true     // Показывать регион в инпуте после выбора
		};
		
		function extractCityNameForSearch(fullLabel) {
			if (!fullLabel) return '';
			// Берем всё до первой скобки
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
			if (!cityDd) return;
			cityDd.classList.remove('hidden');
			cityDd.classList.add('active');
		}
		
		function hideDropdown() {
			if (!cityDd) return;
			cityDd.classList.add('hidden');
			cityDd.classList.remove('active');
		}
		
		function clearResults() {
			if (cityResults) cityResults.innerHTML = '';
		}
		
		// Функции для работы с локациями
		function setLocationsLoading(on) {
			const loc = document.getElementById('location_id');
			if (!loc) return;
			loc.disabled = !!on;
			if (on) loc.innerHTML = '<option value="">Загрузка локаций…</option>';
		}
		
		function fillLocationsSelect(items) {
			const loc = document.getElementById('location_id');
			if (!loc) return;
			
			// Очищаем селект
			loc.innerHTML = '<option value="">— выбрать локацию —</option>';
			
			if (!items || !items.length) {
				loc.innerHTML = '<option value="">Нет локаций для выбранного города</option>';
				loc.disabled = false;
				
				// Перерисовываем кастомный селект
				if (typeof window.safeRerenderEl === 'function') {
					setTimeout(function() {
						window.safeRerenderEl('#location_id');
					}, 50);
				}
				return;
			}
			
			// Заполняем новыми локациями
			for (let i = 0; i < items.length; i++) {
				const it = items[i] || {};
				const id = Number(it.id || 0);
				if (!id) continue;
				
				const opt = document.createElement('option');
				opt.value = String(id);
				opt.textContent = (it.name ? String(it.name) : ('#' + id));
				
				// Сохраняем все данные в data-атрибуты
				opt.setAttribute('data-name', it.name || '');
				opt.setAttribute('data-address', it.address || '');
				opt.setAttribute('data-city', (cityInput ? (cityInput.value || '') : ''));
				opt.setAttribute('data-lat', it.lat || '');
				opt.setAttribute('data-lng', it.lng || '');
				opt.setAttribute('data-thumb', it.thumb || '');
				
				loc.appendChild(opt);
			}
			
			loc.disabled = false;
			loc.value = ''; // Не выбираем локацию автоматически
			
			// Перерисовываем кастомный селект
			if (typeof window.safeRerenderEl === 'function') {
				setTimeout(function() {
					window.safeRerenderEl('#location_id');
				}, 50);
			}
			
			// Если есть функция обновления превью
			try { if (typeof updatePreview === 'function') updatePreview(); } catch(e) {}
		}
		
		function fetchJson(url, cb) {
			const xhr = new XMLHttpRequest();
			xhr.open('GET', url, true);
			xhr.setRequestHeader('Accept', 'application/json');
			xhr.onreadystatechange = function() {
				if (xhr.readyState !== 4) return;
				if (xhr.status < 200 || xhr.status >= 300) return cb(null);
				try { cb(JSON.parse(xhr.responseText)); } catch(e) { cb(null); }
			};
			xhr.send();
		}
		
		function loadLocationsByCity(cityId) {
			const baseUrl = cityWrap.getAttribute('data-locations-url') || '';
			if (!baseUrl) return;
			
			setLocationsLoading(true);
			
			const url = baseUrl + '?city_id=' + encodeURIComponent(cityId);
			
			fetchJson(url, function(data) {
				setLocationsLoading(false);
				
				if (!data || data.ok !== true) {
					fillLocationsSelect([]);
					return;
				}
				
				fillLocationsSelect(data.items || []);
				
				const loc = document.getElementById('location_id');
				if (loc && loc.options.length === 2) {
					loc.selectedIndex = 1;
					try { if (typeof updatePreview === 'function') updatePreview(); } catch(e) {}
				}
				try { if (typeof updatePreview === 'function') updatePreview(); } catch(e) {}
			});
		}
		
		function loadCityMeta(cityId) {
			const baseUrl = cityWrap.getAttribute('data-city-meta-url') || '';
			if (!baseUrl) return;
			
			const tzHidden = document.getElementById('event_timezone_hidden');
			if (!tzHidden) return;
			
			const url = baseUrl + '?city_id=' + encodeURIComponent(cityId);
			fetchJson(url, function(data) {
				if (!data || data.ok !== true) return;
				if (tzHidden && data.timezone) {
					tzHidden.value = String(data.timezone);
				}
			});
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
				
				// Добавляем часовой пояс если есть
				const tzText = item.timezone ? ' • ' + escapeHtml(item.timezone) : '';
				
				html.push(
					'<button type="button" class="city-item" ' +
					'data-id="' + escapeHtml(item.id) + '" ' +
					'data-name="' + escapeHtml(item.name) + '" ' +
					'data-country="' + escapeHtml(item.country_code || '') + '" ' +
					'data-region="' + escapeHtml(item.region || '') + '" ' +
					'data-timezone="' + escapeHtml(item.timezone || '') + '">' +
					'<div class="city-item-name">' + escapeHtml(item.name) + '</div>' +
					(subText ? '<div class="city-item-sub">' + subText + tzText + '</div>' : '') +
					'</button>'
				);
			});
			
			return html.join('');
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
		
		function applySelected(id, name, countryCode, region, timezone) {
			if (cityHiddenId) cityHiddenId.value = id ? String(id) : '';
			
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
			
			// Сохраняем часовой пояс - исправлено!
			const tzHidden = document.getElementById('event_timezone_hidden');
			if (tzHidden) {
				if (timezone) {
					tzHidden.value = timezone;
					} else {
					// Если timezone не пришел из данных, загружаем через API
					loadCityMeta(id);
				}
			}
			
			hideDropdown();
			
			// Загружаем локации для выбранного города
			loadLocationsByCity(id);
			
		}
		
		let lastReqId = 0;
		
		function debounce(fn, ms) {
			let t = null;
			return function(...args) {
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
			
			try {
				const r = await fetch(u.toString(), {
					headers: { 'Accept': 'application/json' },
					credentials: 'same-origin'
				});
				
				if (reqId !== lastReqId) return null;
				if (!r.ok) return null;
				return await r.json();
				} catch(e) {
				return null;
			}
		}
		
		// Основная функция поиска
		const runSearch = debounce(async () => {
			const searchTerm = extractCityNameForSearch(cityInput.value);
			
			if (searchTerm.length < 2) {
				clearResults();
				if (searchTerm.length === 0) {
					hideDropdown();
					} else {
					showDropdown();
					cityResults.innerHTML = '<div class="city-message">Введите ещё символы…</div>';
				}
				return;
			}
			
			clearResults();
			showDropdown();
			cityResults.innerHTML = '<div class="city-message">Поиск…</div>';
			
			const data = await fetchCities(searchTerm);
			if (!data) {
				cityResults.innerHTML = '<div class="city-message">Не удалось загрузить список.</div>';
				return;
			}
			
			const items = Array.isArray(data) ? data : (data.items || []);
			if (!items.length) {
				cityResults.innerHTML = '<div class="city-message">Ничего не найдено.</div>';
				return;
			}
			
			const g = groupByCountry(items);
			
			let html = '';
			if (g.RU.length) html += renderGroup('Россия', g.RU);
			if (g.KZ.length) html += renderGroup('Казахстан', g.KZ);
			if (g.UZ.length) html += renderGroup('Узбекистан', g.UZ);
			if (g.OTHER.length) html += renderGroup('Другие страны', g.OTHER);
			
			cityResults.innerHTML = html;
			
			// Добавляем обработчики на кнопки
			cityResults.querySelectorAll('.city-item').forEach(btn => {
				btn.addEventListener('click', () => {		
					const id = btn.getAttribute('data-id');
					const name = btn.getAttribute('data-name');
					const countryCode = btn.getAttribute('data-country');
					const region = btn.getAttribute('data-region');
					const timezone = btn.getAttribute('data-timezone');
					
					applySelected(id, name, countryCode, region, timezone);
					
				});
			});
		}, 220);
		
		// Обработчик ввода
		cityInput.addEventListener('input', () => {
			// Пользователь меняет текст - сбрасываем city_id и показываем dropdown
			if (cityHiddenId) cityHiddenId.value = '';
			runSearch(); // runSearch сам покажет dropdown если есть 2+ символа
		});
		
		// Закрытие при клике вне
		document.addEventListener('click', (e) => {
			if (!cityWrap.contains(e.target)) {
				hideDropdown();
			}
		});
		
		// Закрытие по Escape
		cityInput.addEventListener('keydown', (e) => {
			if (e.key === 'Escape') hideDropdown();
		});
		
		// Если есть предварительно выбранный город, загружаем локации
		if (cityHiddenId && cityHiddenId.value) {
			loadLocationsByCity(cityHiddenId.value);
		}
	})();
	
	// ========== 14. TRAINER AUTOCOMPLETE ==========
	(function () {
		var trainerInput = document.getElementById('trainer_search');
		var dd = document.getElementById('trainer_dd');
		var clearBtn = document.getElementById('trainer_clear');
		var chips = document.getElementById('trainer_chips');
		var legacyId = document.getElementById('trainer_user_id_legacy');
		var trainerLabelHidden = document.getElementById('trainer_user_label');
		var fmtEl2 = document.getElementById('format');
		
		if (!trainerInput || !dd || !chips) return;
		window.addTrainerChip = addChip;
		
		function trim2(s) { return String(s || '').replace(/^\s+|\s+$/g, ''); }
		
		function showDd2() {
			dd.classList.add('form-select-dropdown--active');
		}
		
		function hideDd2() {
			dd.classList.remove('form-select-dropdown--active');
		}
		
		function clearDd2() { dd.innerHTML = ''; }
		
		function currentIds() {
			var ins = chips.querySelectorAll('input[data-trainer-hidden]');
			var out = [];
			for (var i = 0; i < ins.length; i++) out.push(Number(ins[i].value));
			return out;
		}
		
		function syncLegacyFirst() {
			var ids = currentIds();
			if (legacyId) legacyId.value = ids.length ? String(ids[0]) : '';
			if (trainerLabelHidden) {
				if (!ids.length) trainerLabelHidden.value = '';
				else if (ids.length === 1) trainerLabelHidden.value = '#' + ids[0];
				else trainerLabelHidden.value = ids.length + ' тренера выбрано';
			}
		}
		
		function addChip(id, label) {
			id = Number(id || 0);
			if (!id) return;
			
			var ids = currentIds();
			for (var i = 0; i < ids.length; i++) if (ids[i] === id) return;
			
			var span = document.createElement('span');
			span.className = 'd-flex mb-1 between f-16 fvc pl-1 pr-1';
			
			var t = document.createElement('span');
			t.textContent = label ? String(label) : ('#' + id);
			
			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'trainer-chip-remove btn btn-small btn-secondary';
			btn.setAttribute('data-id', String(id));
			btn.textContent = '×';
			btn.addEventListener('click', function () { removeChip(id); });
			
			span.appendChild(t);
			span.appendChild(btn);
			
			var hidden = document.createElement('input');
			hidden.type = 'hidden';
			hidden.name = 'trainer_user_ids[]';
			hidden.value = String(id);
			hidden.setAttribute('data-trainer-hidden', String(id));
			
			chips.appendChild(span);
			chips.appendChild(hidden);
			
			syncLegacyFirst();
		}
		
		function removeChip(id) {
			id = Number(id || 0);
			if (!id) return;
			
			var ins = chips.querySelectorAll('input[data-trainer-hidden]');
			for (var i = 0; i < ins.length; i++) {
				if (Number(ins[i].value) === id) { ins[i].parentNode.removeChild(ins[i]); break; }
			}
			
			var btns = chips.querySelectorAll('.trainer-chip-remove');
			for (var j = 0; j < btns.length; j++) {
				if (Number(btns[j].getAttribute('data-id')) === id) {
					var chipSpan = btns[j].parentNode;
					chipSpan.parentNode.removeChild(chipSpan);
					break;
				}
			}
			
			syncLegacyFirst();
		}
		
		function clearAll() {
			chips.innerHTML = '';
			if (legacyId) legacyId.value = '';
			if (trainerLabelHidden) trainerLabelHidden.value = '';
			clearDd2();
			hideDd2();
		}
		
		if (clearBtn) clearBtn.addEventListener('click', clearAll);
		
		chips.addEventListener('click', function (e) {
			var el = e.target;
			if (el && el.classList && el.classList.contains('trainer-chip-remove')) {
				var id = Number(el.getAttribute('data-id') || 0);
				if (id) removeChip(id);
			}
		});
		
		var lastReqId = 0;
		
		function closestForm(el) {
			while (el) {
				if (el.tagName && el.tagName.toLowerCase() === 'form') return el;
				el = el.parentNode;
			}
			return null;
		}
		
		function fetchUsers(q, cb) {
			lastReqId++;
			var reqId = lastReqId;
			
			var formRoot = closestForm(trainerInput);
			var baseUrl = formRoot ? (formRoot.getAttribute('data-users-search-url') || '') : '';
			if (!baseUrl) return cb(null);
			
			var url = baseUrl + '?q=' + encodeURIComponent(q || '');
			
			var xhr = new XMLHttpRequest();
			xhr.open('GET', url, true);
			xhr.setRequestHeader('Accept', 'application/json');
			xhr.onreadystatechange = function () {
				if (xhr.readyState !== 4) return;
				if (reqId !== lastReqId) return;
				if (xhr.status < 200 || xhr.status >= 300) return cb(null);
				try { cb(JSON.parse(xhr.responseText)); }
				catch (e) { cb(null); }
			};
			xhr.send();
		}
		
		function render2(items) {
			var html = [];
			for (var i = 0; i < items.length; i++) {
				var it = items[i] || {};
				var id = it.id;
				var label = it.label || '';
				html.push(
					'<div class="trainer-item form-select-option" ' +
					'data-id="' + escHtml(id) + '" data-label="' + escHtml(label) + '">' +
					'<div class="text-sm text-gray-900">' + escHtml(label) + '</div>' +
					'</div>'
				);
			}
			dd.innerHTML = html.join('');
			
			var btns = dd.querySelectorAll('.trainer-item');
			for (var j = 0; j < btns.length; j++) {
				btns[j].addEventListener('click', function () {
					var id = this.getAttribute('data-id');
					var label = this.getAttribute('data-label');
					addChip(id, label);
					trainerInput.value = '';
					hideDd2();
				});
			}
		}
		
		var run2 = debounce(function () {
			var q = trim2(trainerInput.value || '');
			
			if (q.length === 0) { clearDd2(); hideDd2(); return; }
			if (q.length < 2) {
				showDd2();
				dd.innerHTML = '<div class="city-message">Введите ещё символы…</div>';
				return;
			}
			
			showDd2();
			dd.innerHTML = '<div class="city-message">Поиск…</div>';
			
			fetchUsers(q, function (data) {
				if (!data) {
					dd.innerHTML = '<div class="city-message">Не удалось загрузить список.</div>';
					return;
				}
				var items = Array.isArray(data) ? data : (data.items || []);
				if (!items.length) {
					dd.innerHTML = '<div class="city-message">Ничего не найдено.</div>';
					return;
				}
				render2(items.slice(0, 10));
			});
		}, 220);
		
		trainerInput.addEventListener('input', run2);
		trainerInput.addEventListener('focus', function () {
			if (trim2(trainerInput.value || '').length >= 2) run2();
		});
		
		document.addEventListener('click', function (e) {
			if (e.target !== trainerInput && !dd.contains(e.target)) hideDd2();
		});
		
		trainerInput.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') hideDd2();
		});
		
		var formEl = closestForm(trainerInput);
		if (formEl) {
			formEl.addEventListener('submit', function (e) {
				var fmt = fmtEl2 ? String(fmtEl2.value || '') : '';
				var need = (fmt === 'training' || fmt === 'training_game' || fmt === 'camp' || fmt === 'coach_student');
				if (!need) return true;
				
				var ids = currentIds();
				if (!ids.length) {
					e.preventDefault();
					alert('Выбери минимум одного тренера из выпадающего списка.');
					trainerInput.focus();
					return false;
				}
				syncLegacyFirst();
				return true;
			});
		}
		
		syncLegacyFirst();
		hideDd2();
	})();
	
	
	
	// ========== 16. INITIALIZATION HELPERS ==========
	var formInit = qs('form[data-initial-step]');
	var mainForm = qs('form[data-initial-step]');
	
	var hasServerErrors = !!(mainForm && String(mainForm.getAttribute('data-has-errors') || '0') === '1');
	if (mainForm) {
    	mainForm.addEventListener('submit', function (e) {
			
			
			// ДОБАВЬТЕ ВЫЗОВ ВАЛИДАЦИИ ШАГА 3
			if (!validateStep(3)) {
				e.preventDefault();
				return false;
			}			
			
    		if (cityHiddenId && Number(cityHiddenId.value || 0) <= 0) {
    			e.preventDefault();
    			alert('Выбери город перед созданием мероприятия.');
    			try { if (cityInput) cityInput.focus(); } catch (err) {}
    			return false;
			}
    		return true;
		});
	}
	
	
	var initial = 1;
	if (formInit) initial = Number(formInit.getAttribute('data-initial-step') || '1');
	if (initial !== 1 && initial !== 2 && initial !== 3) initial = 1;
	
	try {
		var u0 = new URL(window.location.href);
		var stepQ = Number(u0.searchParams.get('step') || 0);
		if (stepQ === 2 || stepQ === 3) initial = stepQ;
	} catch (e) { }
	
	function ensureCityFromUrl() {
		try {
			if (!cityHiddenId) return;
			if (Number(cityHiddenId.value || 0) > 0) return;
			
			var m = window.location.search.match(/[?&]city_id=(\d+)/);
			if (m && m[1]) cityHiddenId.value = m[1];
		} catch (e) { }
	}
	
	function setDefaultStartTime() {
		var startEl = qs('input[name="starts_at_local"]');
		if (!startEl) return;
		
		if (trim(startEl.value)) return;
		
		var now = new Date();
		now.setHours(now.getHours() + 2);
		
		var yyyy = now.getFullYear();
		var mm = String(now.getMonth() + 1).padStart(2, '0');
		var dd = String(now.getDate()).padStart(2, '0');
		var hh = String(now.getHours()).padStart(2, '0');
		var min = String(now.getMinutes()).padStart(2, '0');
		
		startEl.value = yyyy + '-' + mm + '-' + dd + 'T' + hh + ':' + min;
	}
	
	function ensureOrganizerDefault() {
		var org =
		document.getElementById('organizer_id') ||
		qs('select[name="organizer_id"]') ||
		qs('input[name="organizer_id"]');
		
		if (!org) return;
		
		if (trim(org.value || '')) return;
		
		var form = qs('form[data-initial-step]');
		var adminId = form ? (form.getAttribute('data-admin-id') || '') : '';
		
		if (!adminId) return;
		if (!trim(org.value || '')) org.value = String(adminId);
	}
	
	var lastAutoTitle = '';
	var titleTouchedByUser = false;
	
	function computeAutoTitle() {
		var dir = dirEl ? String(dirEl.value || '') : '';
		var fmt = fmtEl ? String(fmtEl.value || '') : '';
		
		var dirLabel = (dir === 'classic') ? 'Классика' : ((dir === 'beach') ? 'Пляжка' : 'Волейбол');
		
		var fmtMap = {
			game: 'игра',
			training: 'тренировка',
			training_game: 'тренировка+игра',
			training_pro_am: 'про-ам тренировка',
			coach_student: 'тренер+ученик',
			tournament: 'турнир',
			camp: 'кемп'
		};
		
		var fmtLabel = fmtMap[fmt] || fmt;
		return trim(dirLabel + ' ' + fmtLabel);
	}
	
	function syncAutoTitle(reason) {
		var titleEl = qs('input[name="title"]');
		if (!titleEl) return;
		
		var cur = trim(titleEl.value || '');
		var next = computeAutoTitle();
		
		if (titleTouchedByUser) return;
		
		if (!cur || cur === lastAutoTitle) {
			titleEl.value = next;
			lastAutoTitle = next;
		}
	}
	
	(function bindTitleManualEdit() {
		var titleEl = qs('input[name="title"]');
		if (!titleEl) return;
		
		titleEl.addEventListener('input', function () {
			var v = trim(titleEl.value || '');
			if (v && v !== lastAutoTitle) titleTouchedByUser = true;
			if (!v) titleTouchedByUser = false;
		});
	})();
	
	function updatePlayersHint() {
		var directionEl = document.getElementById('direction');
		var subtypeEl = document.getElementById('game_subtype');
		var hint = document.getElementById('game_players_hint');
		
		if (!directionEl || !subtypeEl || !hint) return;
		
		var cfg = window.volleyballConfig || {};
		
		var direction = directionEl.value || '';
		var subtype = subtypeEl.value || '';
		
		if (!cfg[direction] || !cfg[direction].subtypes[subtype]) {
			hint.innerHTML = '';
			return;
		}
		
		var playersPerTeam = cfg[direction].subtypes[subtype].players_per_team || 0;
		var totalPlayers = playersPerTeam * 2;
		
		hint.innerHTML =
		'Команда: <b>' + playersPerTeam +
		'</b> игроков • Всего на площадке: <b>' +
		totalPlayers + '</b>';
	}
	
function recalcPlayers() {
    var cfg = window.volleyballConfig || {};
    
    var dirEl2 = document.getElementById('direction');
    var subtypeEl2 = document.getElementById('game_subtype');
    var teamsEl2 = document.getElementById('teams_count');
    
    var direction = dirEl2 ? dirEl2.value : '';
    var subtype = subtypeEl2 ? subtypeEl2.value : '';
    var teams = parseInt(teamsEl2 ? teamsEl2.value : 2);
    
    if (!direction || !subtype) {
        return;
    }
    
    // ДОБАВЛЯЕМ установку минимума из конфига
    if (cfg[direction] && cfg[direction].subtypes && cfg[direction].subtypes[subtype]) {
        var subtypeCfg = cfg[direction].subtypes[subtype];
        var minEl = document.getElementById('game_min_players');
        if (minEl && subtypeCfg.min_players) {
            minEl.value = subtypeCfg.min_players;
        }
    }
    
    var playersPerTeam = 0;
    
    if (
        cfg[direction] &&
        cfg[direction].subtypes &&
        cfg[direction].subtypes[subtype]
        ) {
        var subtypeCfg = cfg[direction].subtypes[subtype];
        playersPerTeam = subtypeCfg.players_per_team || 0;
        
        var liberoModeEl = document.getElementById('game_libero_mode');
        var liberoMode = liberoModeEl ? liberoModeEl.value : '';
        
        if (subtype === '5x1' && liberoMode === 'with_libero') {
            playersPerTeam += 1;
        }
    }
    
    var maxPlayers = playersPerTeam * teams;
    
    var maxEl = document.getElementById('game_max_players');
    
    if (maxEl) {
        if (!maxEl.dataset.listener) {
            maxEl.addEventListener('input', function () {
                this.dataset.manual = "1";
            });
            maxEl.dataset.listener = "1";
        }
        
        if (!maxEl.dataset.manual) {
            maxEl.value = maxPlayers;
        }
    }
    
    var preview = document.getElementById('players_preview');
    
    if (preview) {
        preview.textContent = maxPlayers + " игроков";
    }
}
	
    function refreshUI(reason) {
    	syncFormatOptions();
    	ensureDefaultFormat(reason);
    	syncRegistrationModeOptions();
		
    	syncGameSubtypeOptions();
    	restoreGameStateIfEmpty();
    	cacheGameState();
    	ensureDefaultSubtypeIfEmpty();
		
		
    	applyGameDefaults();
		
    	syncTournamentSchemeOptions();
    	updateTournamentTotalPlayers();
    	applyTournamentDefaults();
		
    	syncGenderPolicyOptions();
    	updatePlayersHint();
    	syncAutoTitle(reason);
    	ensureOrganizerDefault();
    	updateVisibility();
    	recalcPlayers();
	}
	// ========== 16. INITIAL SETUP ==========
    // Сначала восстанавливаем черновики (если есть ошибки валидации)
	
    
    // Потом применяем настройки по умолчанию ТОЛЬКО для пустых полей
    refreshUI('init');
    
    try { enforceRegistrationRules(); } catch (e) { }
    try { syncRecFieldsVisibility(); } catch (e) { }
    try { syncMonthsVisibility(); } catch (e) { }
    try { updatePreview(); } catch (e) { }
    
    showStep(initial);
    setDefaultStartTime();
    ensureCityFromUrl();
	// ========== 17. EVENT LISTENERS ==========
	if (dirEl) dirEl.addEventListener('change', function () { refreshUI('direction'); });
	if (fmtEl) fmtEl.addEventListener('change', function () { refreshUI('format'); });
	
	if (gameSubtype) {
		gameSubtype.addEventListener('change', function () {
			refreshUI('subtype');
		});
	}
	
	if (teamsEl) teamsEl.addEventListener('input', recalcPlayers);
	if (liberoEl) liberoEl.addEventListener('change', recalcPlayers);
	if (dirEl) dirEl.addEventListener('change', recalcPlayers);
	if (gameSubtype) gameSubtype.addEventListener('change', recalcPlayers);
	
	document.addEventListener('change', function (e) {
		if (!e.target.name && !e.target.id) return;
		
		updateVisibility();
		updatePlayersHint();
		syncAutoTitle();
	});
	
    // ========== 18. FINAL SYNC ==========
    
    try { syncAutoTitle('init'); } catch (e) { }
    
    bindGameCacheListeners();
    enforceRegistrationRules();
    syncGenderLimitedBlocks();
    
    if (genderPolicyEl && trim(genderPolicyEl.value || '') === 'mixed_limited') buildPositionsCheckboxes();
    
    updateLegacyMappingOnly();
    syncRecFieldsVisibility();
    syncMonthsVisibility();
    recalcPlayers();
    try { updatePreview(); } catch (e) { }
	
	
// ========== 19. AGE POLICY HANDLER ==========
(function() {
    var agePolicyRadios = document.querySelectorAll('input[name="age_policy"]');
    var childAgeWrap = document.getElementById('child_age_wrap');
    var childAgeMinEl = document.querySelector('input[name="child_age_min"]');
    var childAgeMaxEl = document.querySelector('input[name="child_age_max"]');
    var classicLevelMin = document.querySelector('select[name="classic_level_min"]');
    var classicLevelMax = document.querySelector('select[name="classic_level_max"]');
    var beachLevelMin = document.querySelector('select[name="beach_level_min"]');
    var beachLevelMax = document.querySelector('select[name="beach_level_max"]');
    
    // Допустимые уровни для детей
    var CHILD_LEVELS = ['1', '2', '4'];
    
    // Сохраняем оригинальные опции при загрузке
    var originalOptions = {};
    
    // ✅ ИСПРАВЛЕНО: функция toggleChildAgeWrap использует getAgePolicyValue()
    function toggleChildAgeWrap() {
        if (!childAgeWrap) return;
        
        var agePolicy = getAgePolicyValue();
        
        if (agePolicy === 'child') {
            childAgeWrap.classList.remove('hidden');
        } else {
            childAgeWrap.classList.add('hidden');
        }
    }

    function saveOriginalOptions() {
        var selects = [classicLevelMin, classicLevelMax, beachLevelMin, beachLevelMax];
        for (var i = 0; i < selects.length; i++) {
            var select = selects[i];
            if (!select) continue;
            
            var key = select.name;
            if (!originalOptions[key]) {
                var options = [];
                for (var j = 0; j < select.options.length; j++) {
                    options.push({
                        value: select.options[j].value,
                        text: select.options[j].textContent,
                        disabled: select.options[j].disabled
                    });
                }
                originalOptions[key] = options;
            }
        }
    }
    
    function restoreOriginalOptions(selectEl) {
        if (!selectEl) return;
        var key = selectEl.name;
        var saved = originalOptions[key];
        if (!saved) return;
        
        // Восстанавливаем оригинальные опции
        for (var i = 0; i < saved.length; i++) {
            var opt = selectEl.options[i];
            if (opt) {
                opt.value = saved[i].value;
                opt.textContent = saved[i].text;
                opt.disabled = saved[i].disabled;
                opt.hidden = false;
            }
        }
    }
    
    function filterLevelOptions(selectEl, allowedLevels) {
        if (!selectEl) return;
        
        var currentValue = selectEl.value;
        var needsReset = false;
        
        for (var i = 0; i < selectEl.options.length; i++) {
            var opt = selectEl.options[i];
            var val = opt.value;
            
            if (val === '') continue; // Пустой option не трогаем
            
            if (allowedLevels.indexOf(val) !== -1) {
                opt.disabled = false;
                opt.hidden = false;
            } else {
                opt.disabled = true;
                opt.hidden = true;
                
                if (currentValue === val) {
                    needsReset = true;
                }
            }
        }
        
        // Сброс на первое допустимое значение или пустое
        if (needsReset) {
            var firstAllowed = selectEl.querySelector('option:not([disabled])');
            if (firstAllowed && firstAllowed.value !== '') {
                selectEl.value = firstAllowed.value;
            } else {
                selectEl.value = '';
            }
        }
    }
    
    function resetAllLevels() {
        if (classicLevelMin) classicLevelMin.value = '';
        if (classicLevelMax) classicLevelMax.value = '';
        if (beachLevelMin) beachLevelMin.value = '';
        if (beachLevelMax) beachLevelMax.value = '';
    }
    
    function rerenderLevelSelects() {
        setTimeout(function() {
            if (typeof safeRerenderEl === 'function') {
                safeRerenderEl('select[name="classic_level_min"]');
                safeRerenderEl('select[name="classic_level_max"]');
                safeRerenderEl('select[name="beach_level_min"]');
                safeRerenderEl('select[name="beach_level_max"]');
            }
        }, 50);
    }
    
    function applyAgePolicy() {
        var selectedPolicy = null;
        
        for (var i = 0; i < agePolicyRadios.length; i++) {
            if (agePolicyRadios[i].checked) {
                selectedPolicy = agePolicyRadios[i].value;
                break;
            }
        }
        
        // Только если выбрали "Для детей" - фильтруем
        if (selectedPolicy === 'child') {
            filterLevelOptions(classicLevelMin, CHILD_LEVELS);
            filterLevelOptions(classicLevelMax, CHILD_LEVELS);
            filterLevelOptions(beachLevelMin, CHILD_LEVELS);
            filterLevelOptions(beachLevelMax, CHILD_LEVELS);
            rerenderLevelSelects();
        }
    }
    
    // Сохраняем оригинальные опции при загрузке
    saveOriginalOptions();
    
    // ✅ ИСПРАВЛЕНО: Добавляем обработчики на radio кнопки
    for (var i = 0; i < agePolicyRadios.length; i++) {
        agePolicyRadios[i].addEventListener('change', function() {
            var selectedPolicy = null;
            for (var j = 0; j < agePolicyRadios.length; j++) {
                if (agePolicyRadios[j].checked) {
                    selectedPolicy = agePolicyRadios[j].value;
                    break;
                }
            }
            
            if (selectedPolicy === 'child') {
                // Для детей - сбрасываем и фильтруем
                resetAllLevels();
                applyAgePolicy();
            } else {
                // Для взрослых/без ограничений - восстанавливаем все опции
                restoreOriginalOptions(classicLevelMin);
                restoreOriginalOptions(classicLevelMax);
                restoreOriginalOptions(beachLevelMin);
                restoreOriginalOptions(beachLevelMax);
                rerenderLevelSelects();
            }
            
            // ✅ ВАЖНО: вызываем toggleChildAgeWrap при каждом изменении
            toggleChildAgeWrap();
        });
    }
    
    // Применяем при загрузке, если по умолчанию выбран "child"
    var defaultChecked = document.querySelector('input[name="age_policy"]:checked');
    if (defaultChecked && defaultChecked.value === 'child') {
        applyAgePolicy();
    }
    
    // ✅ ВАЖНО: вызываем toggleChildAgeWrap при инициализации
    toggleChildAgeWrap();
    
})();	

// ========== BOT ASSISTANT TOGGLE ==========
(function () {
    var enabledCb  = document.getElementById('bot_assistant_enabled');
    var settingsEl = document.getElementById('bot_assistant_settings');
    var fillEl     = document.getElementById('bot_assistant_fill');
    var thresholdEl = document.getElementById('bot_assistant_threshold');
    var thresholdHint = document.getElementById('bot_threshold_hint');
    var fillHint   = document.getElementById('bot_fill_hint');

    if (!enabledCb) return;

    function toggleBotSettings() {
        var show = enabledCb.checked;
        if (settingsEl) settingsEl.style.display = show ? '' : 'none';
        if (fillEl)     fillEl.style.display     = show ? '' : 'none';
    }

    enabledCb.addEventListener('change', toggleBotSettings);

    // Синхронизируем hint с ползунком в реальном времени
    if (thresholdEl && thresholdHint) {
        thresholdEl.addEventListener('input', function () {
            thresholdHint.textContent = this.value + '%';
        });
    }

    if (fillEl) {
        var fillRange = fillEl.querySelector('input[type="range"]');
        if (fillRange && fillHint) {
            fillRange.addEventListener('input', function () {
                fillHint.textContent = this.value + '%';
            });
        }
    }

    toggleBotSettings();
})();
})();
// Инициализация toggle reserve при загрузке и смене схемы
(function() {
    var schemeEl = document.getElementById('tournament_game_scheme');
    if (schemeEl) {
        schemeEl.addEventListener('change', toggleReserveFields);
        toggleReserveFields();
    }
})();
