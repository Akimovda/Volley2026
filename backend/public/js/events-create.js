
//Events-create.js
// Trix: запрет загрузки файлов/картинок
document.addEventListener("trix-file-accept", function (event) {
  event.preventDefault();
});
 (function () {
            function hasClass(el, c) { return el && el.classList && el.classList.contains(c); }
            function addClass(el, c) { if (el && el.classList) el.classList.add(c); }
            function removeClass(el, c) { if (el && el.classList) el.classList.remove(c); }
            function toggleClass(el, c, on) { if (!el || !el.classList) return; if (on) el.classList.add(c); else el.classList.remove(c); }
        
            function qs(sel, root) { return (root || document).querySelector(sel); }
            function qsa(sel, root) { return (root || document).querySelectorAll(sel); }
        
            function trim(s) { return String(s || '').replace(/^\s+|\s+$/g, ''); }
        
            function escHtml(s) {
                return String(s || '').replace(/</g,'&lt;').replace(/>/g,'&gt;');
            }
        
            // ====== Base refs ======
            var dirEl = document.getElementById('direction');
            var fmtEl = document.getElementById('format');
            
        
            // steps
            var stepBlocks = qsa('[data-step]');
            var btnNext = qsa('[data-next]');
            var btnBack = qsa('[data-back]');
            var stepNumEl = document.getElementById('wizard_step_num');
            var stepTitleEl = document.getElementById('wizard_step_title');
            var percentEl = document.getElementById('wizard_percent');
            var barEl = document.getElementById('wizard_bar');
            var pill1 = document.getElementById('pill_1');
            var pill2 = document.getElementById('pill_2');
            var pill3 = document.getElementById('pill_3');
        
            var titles = { 1:'Настройка мероприятия', 2:'Выбор локации и времени', 3:'Доступность' };
        
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
                if (step === 1) c = '#4f46e5';
                if (step === 2) c = '#10b981';
                if (step === 3) c = '#f59e0b';
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
                if (stepTitleEl) stepTitleEl.textContent = titles[step] || '';
                var pct = stepPercent(step);
                if (barEl) barEl.style.width = pct + '%';
                if (percentEl) percentEl.textContent = pct + '%';
                setActivePills(step);
                setBarColor(step);
                try { window.scrollTo({ top: 0, behavior: 'smooth' }); } catch (e) { window.scrollTo(0,0); }
            }
        
            function getCurrentStep() {
                for (var i = 0; i < stepBlocks.length; i++) {
                    if (!hasClass(stepBlocks[i], 'hidden')) return Number(stepBlocks[i].getAttribute('data-step')) || 1;
                }
                return 1;
            }
        
            // ✅ coach_student only beach (как было)
            function syncFormatOptions() {
                var direction = dirEl ? dirEl.value : '';
                var optCoach = fmtEl ? fmtEl.querySelector('option[value="coach_student"]') : null;
                if (!optCoach) return;
                var shouldShow = (direction === 'beach');
                optCoach.disabled = !shouldShow;
                optCoach.hidden = !shouldShow;
                if (!shouldShow && fmtEl && fmtEl.value === 'coach_student') fmtEl.value = 'training';
            }
        
            // ✅ trainer field visibility
            var trainerBlock = document.getElementById('trainer_block');
            function syncTrainerVisibility() {
                var fmt = fmtEl ? trim(fmtEl.value) : '';
                var show = (fmt === 'training' || fmt === 'training_game' || fmt === 'training_pro_am' || fmt === 'camp' || fmt === 'coach_student');
                if (trainerBlock) trainerBlock.style.display = show ? '' : 'none';
            }
        
            // levels by direction
            var levelsClassic = document.getElementById('levels_classic');
            var levelsBeach = document.getElementById('levels_beach');
            function syncLevelsUI() {
                var direction = dirEl ? dirEl.value : '';
                if (levelsClassic) toggleClass(levelsClassic, 'hidden', direction !== 'classic');
                if (levelsBeach) toggleClass(levelsBeach, 'hidden', direction !== 'beach');
            }
            // game UI
            var gameSubtype = document.getElementById('game_subtype');
            var gameMinEl = document.getElementById('game_min_players');
            var gameMaxEl = document.getElementById('game_max_players');
            function bindGameCacheListeners() {
              if (gameSubtype) gameSubtype.addEventListener('change', cacheGameState);
              if (gameMinEl)  gameMinEl.addEventListener('input', cacheGameState);
              if (gameMaxEl)  gameMaxEl.addEventListener('input', cacheGameState);
            }

            // ---- cache last picked values (to prevent "prefill исчезает" on UI rebuild) ----
                var lastClassic = { subtype: '', min: '', max: '' };
                var lastBeach   = { subtype: '', min: '', max: '' };
                
                function cacheGameState() {
                  var direction = dirEl ? String(dirEl.value || '') : '';
                  var format = fmtEl ? String(fmtEl.value || '') : '';
                  if (format !== 'game') return;
                
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
                  if (format !== 'game') return;
                
                  var cache = (direction === 'beach') ? lastBeach : lastClassic;
                
                  if (gameSubtype && trim(gameSubtype.value || '') === '' && cache.subtype) {
                    gameSubtype.value = cache.subtype;
                  }
                  if (gameMinEl && trim(gameMinEl.value || '') === '' && cache.min !== '') {
                    gameMinEl.value = cache.min;
                  }
                  if (gameMaxEl && trim(gameMaxEl.value || '') === '' && cache.max !== '') {
                    gameMaxEl.value = cache.max;
                  }
                }
            var liberoModeBlock = document.getElementById('libero_mode_block');
            var liberoModeSelect = document.getElementById('game_libero_mode');
            var gameDefaultsHint = document.getElementById('game_defaults_hint');
            var gameMinHint = document.getElementById('game_min_hint');
            var gameMaxHint = document.getElementById('game_max_hint');
        
            var subtypeMetaClassic = {
                  '4x4': { min: 4, max: 8, range: '4–8' },
                  '4x2': { min: 6, max: 12, range: '6–12' },
                  '5x1': { min: 6, max: 12, range: '6–12' }
                };
                
                var subtypeMetaBeach = {
                  '2x2': { min: 4, max: 6,  range: '4–6'  },
                  '3x3': { min: 6, max: 12, range: '6–12' },
                  '4x4': { min: 8, max: 16, range: '8–16' }
                };
            function syncGameSubtypeOptions() {
                  if (!gameSubtype) return;
                
                  var direction = dirEl ? dirEl.value : '';
                  var format = fmtEl ? fmtEl.value : '';
                  var isGame = (format === 'game');
                
                  // для не-game можно оставить select как есть, но лучше не мучить
                  if (!isGame) return;
                
                  cacheGameState(); // ✅ сохраняем до innerHTML = ''

                  var current = trim(gameSubtype.value || '');
                  var opts = [];
                
                  if (direction === 'classic') {
                    opts = [
                      {v:'',    t:'— выбрать —'},
                      {v:'4x4', t:'4×4'},
                      {v:'4x2', t:'4×2'},
                      {v:'5x1', t:'5×1'}
                    ];
                  } else {
                    opts = [
                      {v:'',    t:'— выбрать —'},
                      {v:'2x2', t:'2×2'},
                      {v:'3x3', t:'3×3'},
                      {v:'4x4', t:'4×4'}
                    ];
                  }
                
                  gameSubtype.innerHTML = '';
                  for (var i=0;i<opts.length;i++){
                    var o = document.createElement('option');
                    o.value = opts[i].v;
                    o.textContent = opts[i].t;
                    if (opts[i].v && opts[i].v === current) o.selected = true;
                    gameSubtype.appendChild(o);
                  }
            }
                
            restoreGameStateIfEmpty(); // ✅ вернём subtype/min/max если они пропали

            function syncGenderPolicyOptions() {
              if (!genderPolicyEl) return;
            
              var direction = dirEl ? dirEl.value : '';
              var policy = trim(genderPolicyEl.value || '');
            
              if (direction === 'beach') {
                // если стоял mixed_limited — сбросим
                if (policy === 'mixed_limited') genderPolicyEl.value = 'mixed_open';
            
                // спрятать limited блоки
                if (limitedSideWrap) addClass(limitedSideWrap, 'hidden');
                if (limitedMaxWrap) addClass(limitedMaxWrap, 'hidden');
                if (limitedPositionsWrap) addClass(limitedPositionsWrap, 'hidden');
            
                // option mixed_5050 (добавим, если нет)
                var opt5050 = genderPolicyEl.querySelector('option[value="mixed_5050"]');
                if (!opt5050) {
                  opt5050 = document.createElement('option');
                  opt5050.value = 'mixed_5050';
                  opt5050.textContent = 'М/Ж (50/50)';
                  genderPolicyEl.appendChild(opt5050);
                }
            
                // mixed_limited выключаем
                var optLim = genderPolicyEl.querySelector('option[value="mixed_limited"]');
                if (optLim) { optLim.disabled = true; optLim.hidden = true; }
            
              } else {
                // classic: mixed_limited вернуть
                var optLim2 = genderPolicyEl.querySelector('option[value="mixed_limited"]');
                if (optLim2) { optLim2.disabled = false; optLim2.hidden = false; }
                // mixed_5050 можно скрыть на classic (чтобы не путать)
                var opt50502 = genderPolicyEl.querySelector('option[value="mixed_5050"]');
                if (opt50502) { opt50502.disabled = true; opt50502.hidden = true; }
              }
            
              // потом обычная логика limited для classic
              syncGenderLimitedBlocks();
            }
            function isEmptyInput(el) { return !el || trim(el.value) === ''; }
        
            function getSubtypeMeta() {
                var direction = dirEl ? dirEl.value : '';
                 return (direction === 'beach') ? subtypeMetaBeach : subtypeMetaClassic;
            }
 
             function applySmartDefaults(force) {
                      var st = gameSubtype ? trim(gameSubtype.value) : '';
                      var metaMap = getSubtypeMeta();
                      var meta = metaMap ? metaMap[st] : null;
                      if (!meta) return;
                    
                      var doMin = force || (gameMinEl && isEmptyInput(gameMinEl));
                      var doMax = force || (gameMaxEl && isEmptyInput(gameMaxEl));
                    
                      if (gameMinEl && doMin && typeof meta.min === 'number') gameMinEl.value = String(meta.min);
                      if (gameMaxEl && doMax && typeof meta.max === 'number') gameMaxEl.value = String(meta.max);
                    }
        
                function updateRecommendedHints() {
                    if (!gameDefaultsHint) return;
                
                    var direction = dirEl ? dirEl.value : '';
                    var st = gameSubtype ? trim(gameSubtype.value) : '';
                    var metaMap = getSubtypeMeta();
                    var meta = metaMap ? metaMap[st] : null;
                
                    if (!meta) {
                        gameDefaultsHint.textContent = (direction === 'beach')
                            ? 'Подсказки: 2×2 → 4–6; 3×3 → 6–12; 4×4 → 8–16.'
                            : 'Подсказки: 4×4 → 8–16; 4×2 → 6–12; 5×1 → 6–12 (режим либеро — ниже).';
                    } else {
                        gameDefaultsHint.textContent = 'Рекомендуемо: ' + meta.range + ' участников.';
                    }
                
                    var show = !!meta;
                
                    if (gameMinHint) {
                        gameMinHint.style.display = show ? '' : 'none';
                        gameMinHint.textContent = show ? ('Рекомендуемо: ' + meta.range) : '';
                    }
                    if (gameMaxHint) {
                        gameMaxHint.style.display = show ? '' : 'none';
                        gameMaxHint.textContent = show ? ('Рекомендуемо: ' + meta.range) : '';
                    }
                }
        
            function syncGameUI() {
                var direction = dirEl ? dirEl.value : '';
                var format = fmtEl ? fmtEl.value : '';
                var isClassicGame = (direction === 'classic' && format === 'game');
                var st = gameSubtype ? trim(gameSubtype.value) : '';
        
                if (liberoModeBlock) toggleClass(liberoModeBlock, 'hidden', !(isClassicGame && st === '5x1'));
                if (st !== '5x1' && liberoModeSelect) liberoModeSelect.value = 'with_libero';
        
                updateRecommendedHints();
            }
        
            // gender UI (оставлено как было логически, только ES5)
            var genderPolicyEl = document.getElementById('game_gender_policy');
            var limitedSideWrap = document.getElementById('gender_limited_side_wrap');
            var limitedMaxWrap = document.getElementById('gender_limited_max_wrap');
            var limitedPositionsWrap = document.getElementById('gender_limited_positions_wrap');
            var genderMaxEl = document.getElementById('game_gender_limited_max');
            var positionsBox = document.getElementById('gender_positions_box');
            var positionsOldJson = document.getElementById('gender_positions_old_json');
            var positionsClearBtn = document.getElementById('gender_positions_clear');
            var legacyAllowGirls = document.getElementById('game_allow_girls_legacy');
            var legacyGirlsMax = document.getElementById('game_girls_max_legacy');
        
            var POS_LABELS = {
                setter:   'Связующий (setter)',
                outside:  'Доигровщик (outside)',
                opposite: 'Диагональный (opposite)',
                middle:   'Центральный (middle)',
                libero:   'Либеро (libero)'
            };
        
            function positionsForSubtype() {
                var st = gameSubtype ? trim(gameSubtype.value) : '';
                var libero = liberoModeSelect ? trim(liberoModeSelect.value || 'with_libero') : 'with_libero';
        
                if (st === '4x2') return ['setter','outside'];
                if (st === '4x4') return ['setter','outside','opposite'];
                if (st === '5x1') return (libero === 'with_libero')
                    ? ['setter','outside','opposite','middle','libero']
                    : ['setter','outside','opposite','middle'];
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
                    label.className = 'flex items-center gap-3 p-3 rounded-lg border border-gray-200 bg-white';
        
                    var cb = document.createElement('input');
                    cb.type = 'checkbox';
                    cb.name = 'game_gender_limited_positions[]';
                    cb.value = key;
        
                    // если уже есть текущий выбор — используем его, иначе старый
                    var hasCur = (cur.length > 0);
                    cb.checked = hasCur ? !!curMap[key] : !!oldMap[key];
        
                    var span = document.createElement('span');
                    span.className = 'text-sm font-semibold text-gray-800';
                    span.textContent = POS_LABELS[key] || key;
        
                    label.appendChild(cb);
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
            }
        
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
        
            // ✅ Recurrence UI (Step 2)
            var recEl = document.getElementById('is_recurring');
            var recFields = document.getElementById('recurrence_fields');
            var recType = document.getElementById('recurrence_type');
            var recInterval = document.getElementById('recurrence_interval');
            var monthsWrap = document.getElementById('months_wrap');
            var recurrenceHint = document.getElementById('recurrence_hint');
        
            function syncMonthsVisibility() {
                if (!monthsWrap || !recType) return;
                monthsWrap.style.display = (recType.value === 'monthly') ? '' : 'none';
            }
        
            function syncRecFieldsVisibility() {
                if (!recEl || !recFields) return;
                recFields.style.display = recEl.checked ? '' : 'none';
                syncMonthsVisibility();
            }
        
            if (recEl) recEl.addEventListener('change', syncRecFieldsVisibility);
            if (recType) recType.addEventListener('change', syncMonthsVisibility);
        
            // Step validation (логика та же, только ES5)
            function validateStep(step) {
                function focusEl(el){ try { if (el && el.focus) el.focus(); } catch(e) {} }
                function val(el){ return el ? trim(el.value) : ''; }
                function need(cond, msg, el){
                    if (!cond) { alert(msg); focusEl(el); return false; }
                    return true;
                }
                function num(el){ return Number(val(el)); }
                function has(el){ return val(el) !== ''; }
            
                function checkMinMaxPair(minName, maxName, label) {
                    // сначала пробуем select, потом input (fallback)
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
                        if (!need(genderMaxEl && val(genderMaxEl), 'Укажи максимум мест для ограничиваемых.', genderMaxEl)) return false;
                    }
                    return true;
                }
            
                function validateGameBeach() {
                    if (!need(gameSubtype && val(gameSubtype), 'Выбери подтип игры (2×2 / 3×3 / 4×4).', gameSubtype)) return false;
                    if (!need(gameMaxEl && val(gameMaxEl), 'Укажи максимум участников для игры.', gameMaxEl)) return false;
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
                    var titleEl = qs('input[name="title"]');
                    if (!need(!!val(titleEl), 'Заполни название мероприятия.', titleEl)) return false;
            
                    var direction = dirEl ? dirEl.value : '';
                    var format = fmtEl ? fmtEl.value : '';
            
                    if (direction === 'classic' && format === 'game') {
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
                    var loc = document.getElementById('location_id');
                    if (!need(loc && val(loc), 'Выбери локацию.', loc)) return false;
            
                    if (recEl && recEl.checked) {
                        if (!need(recType && val(recType), 'Выбери тип повторения (ежедневно/еженедельно/ежемесячно).', recType)) return false;
                        if (!need(recInterval && val(recInterval), 'Укажи интервал повторения.', recInterval)) return false;
                    }
            
                    if (getAllowRegistrationValue() === 1) {
                        if (!readNonNegative(regStartsInp, 'Укажи начало регистрации (дней до).')) return false;
                        if (!readNonNegative(regEndsInp,   'Укажи окончание регистрации (минут до).')) return false;
                        if (!readNonNegative(cancelInp,    'Укажи запрет отмены (минут до).')) return false;
                    }
            
                    return true;
                }
            
                // ---- Step 3 ----
                if (step === 3) {
                    var paidEl = document.getElementById('is_paid');
                    var isPaid = paidEl ? !!paidEl.checked : false;
                    var price = qs('input[name="price_text"]');
                    if (isPaid && !need(price && val(price), 'Укажи стоимость/условия оплаты (price_text).', price)) return false;
                    return true;
                }
            
                return true;
            }
        
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
        
            // Location preview
            var sel = document.getElementById('location_id');
            var wrap = document.getElementById('location_preview');
            var img = document.getElementById('location_preview_img');
            var noimg = document.getElementById('location_preview_noimg');
            var nameEl = document.getElementById('location_preview_name');
            var metaEl = document.getElementById('location_preview_meta');
            var shortEl = document.getElementById('location_preview_short');
            var mapWrap = document.getElementById('location_preview_map_wrap');
            var mapEl = document.getElementById('location_preview_map');
            var coordsEl = document.getElementById('location_preview_coords');
        
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
                var shortText = opt.getAttribute('data-short') || '';
                var thumb = opt.getAttribute('data-thumb') || '';
                var lat = trim(opt.getAttribute('data-lat') || '');
                var lng = trim(opt.getAttribute('data-lng') || '');
        
                if (wrap) removeClass(wrap, 'hidden');
                if (nameEl) nameEl.textContent = name;
        
                var metaParts = [];
                if (city) metaParts.push(city);
                if (address) metaParts.push(address);
                if (metaEl) metaEl.textContent = metaParts.join(' • ');
        
                if (shortEl) {
                    if (trim(shortText)) { shortEl.style.display = ''; shortEl.textContent = shortText; }
                    else { shortEl.style.display = 'none'; shortEl.textContent = ''; }
                }
        
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
                if (mapWrap && mapEl && coordsEl) {
                    if (hasCoords) {
                        mapWrap.style.display = '';
                        coordsEl.style.display = '';
                        coordsEl.textContent = 'Координаты: ' + lat + ', ' + lng;
                        mapEl.src = 'https://www.openstreetmap.org/export/embed.html?layer=mapnik&marker=' +
                            encodeURIComponent(lat) + ',' + encodeURIComponent(lng) + '&zoom=16';
                    } else {
                        mapWrap.style.display = 'none';
                        coordsEl.style.display = 'none';
                        coordsEl.textContent = '';
                        mapEl.src = '';
                    }
                }
            }
        
            if (sel) sel.addEventListener('change', updatePreview);
            updatePreview();
        
            // paid UX
            var paidEl2 = document.getElementById('is_paid');
            var priceWrap = document.getElementById('price_wrap');
            function togglePaid() {
                if (!paidEl2 || !priceWrap) return;
                priceWrap.style.opacity = paidEl2.checked ? '1' : '0.45';
            }
            if (paidEl2) paidEl2.addEventListener('change', togglePaid);
            togglePaid();
        
            // allow_registration rule (affects recurring)
            var noRegStub = document.getElementById('no_registration_stub');
            // ✅ registration timing refs (used in validateStep + enforceRegistrationRules)
            var regTimingBox = document.getElementById('reg_timing_box');
            var regStartsInp = document.getElementById('reg_starts_days_before');
            var regEndsInp   = document.getElementById('reg_ends_minutes_before');
            var cancelInp    = document.getElementById('cancel_lock_minutes_before');
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
                // ✅ registration timing fields enabled only when allow_registration=1
                        if (regTimingBox) {
                        var on = (allowReg === 1);
                        regTimingBox.style.opacity = on ? '1' : '0.45';
                    
                        if (regStartsInp) regStartsInp.disabled = !on;
                        if (regEndsInp) regEndsInp.disabled = !on;
                        if (cancelInp) cancelInp.disabled = !on;
                    }
                syncRecFieldsVisibility();
            }
        
            var allowRegs = qsa('input[name="allow_registration"]');
            for (var ar = 0; ar < allowRegs.length; ar++) {
                allowRegs[ar].addEventListener('change', enforceRegistrationRules);
            }
    
            // ---------- Trainer autocomplete (как города) ----------
            (function () {
                var trainerInput = document.getElementById('trainer_search');
                var dd = document.getElementById('trainer_dd');
                var clearBtn = document.getElementById('trainer_clear');
                var chips = document.getElementById('trainer_chips');
                var legacyId = document.getElementById('trainer_user_id_legacy');
                var trainerLabelHidden = document.getElementById('trainer_user_label');
                var fmtEl2 = document.getElementById('format');
            
                if (!trainerInput || !dd || !chips) return;
            
                function trim(s){ return String(s||'').replace(/^\s+|\s+$/g,''); }
                function escapeHtml(s){
                    return String(s||'')
                        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
                        .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
                }
                function showDd(){ dd.style.display = 'block'; }
                function hideDd(){ dd.style.display = 'none'; }
                function clearDd(){ dd.innerHTML = ''; }
            
                function currentIds(){
                    var ins = chips.querySelectorAll('input[data-trainer-hidden]');
                    var out = [];
                    for (var i=0;i<ins.length;i++){
                        out.push(Number(ins[i].value));
                    }
                    return out;
                }
            
                function syncLegacyFirst(){
                    var ids = currentIds();
                    if (legacyId) legacyId.value = ids.length ? String(ids[0]) : '';
                    if (trainerLabelHidden) {
                        // простая подпись: “3 тренера выбрано” / “#id”
                        if (!ids.length) trainerLabelHidden.value = '';
                        else if (ids.length === 1) trainerLabelHidden.value = '#'+ids[0];
                        else trainerLabelHidden.value = ids.length + ' тренера выбрано';
                    }
                }
            
                function addChip(id, label){
                    id = Number(id||0);
                    if (!id) return;
            
                    var ids = currentIds();
                    for (var i=0;i<ids.length;i++) if (ids[i] === id) return;
            
                    // chip
                    var span = document.createElement('span');
                    span.className = 'inline-flex items-center gap-2 px-3 py-1 rounded-full bg-gray-100 border border-gray-200 text-sm';
            
                    var t = document.createElement('span');
                    t.textContent = label ? String(label) : ('#'+id);
            
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'trainer-chip-remove text-gray-500 hover:text-gray-800';
                    btn.setAttribute('data-id', String(id));
                    btn.textContent = '×';
                    btn.addEventListener('click', function(){
                        removeChip(id);
                    });
            
                    span.appendChild(t);
                    span.appendChild(btn);
            
                    // hidden input
                    var hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = 'trainer_user_ids[]';
                    hidden.value = String(id);
                    hidden.setAttribute('data-trainer-hidden', String(id));
            
                    chips.appendChild(span);
                    chips.appendChild(hidden);
            
                    syncLegacyFirst();
                }
            
                function removeChip(id){
                    id = Number(id||0);
                    if (!id) return;
            
                    // remove hidden
                    var ins = chips.querySelectorAll('input[data-trainer-hidden]');
                    for (var i=0;i<ins.length;i++){
                        if (Number(ins[i].value) === id) {
                            ins[i].parentNode.removeChild(ins[i]);
                            break;
                        }
                    }
                    // remove chip span (ищем кнопку с data-id)
                    var btns = chips.querySelectorAll('.trainer-chip-remove');
                    for (var j=0;j<btns.length;j++){
                        if (Number(btns[j].getAttribute('data-id')) === id) {
                            var chipSpan = btns[j].parentNode;
                            chipSpan.parentNode.removeChild(chipSpan);
                            break;
                        }
                    }
            
                    syncLegacyFirst();
                }
            
                function clearAll(){
                    chips.innerHTML = '';
                    if (legacyId) legacyId.value = '';
                    if (trainerLabelHidden) trainerLabelHidden.value = '';
                    clearDd();
                    hideDd();
                }
            
                if (clearBtn) clearBtn.addEventListener('click', clearAll);
            
                // делегирование удаления для SSR-чипов
                chips.addEventListener('click', function(e){
                    var el = e.target;
                    if (!el) return;
                    if (el.classList && el.classList.contains('trainer-chip-remove')) {
                        var id = Number(el.getAttribute('data-id') || 0);
                        if (id) removeChip(id);
                    }
                });
            
                // ---- fetch ----
                var lastReqId = 0;
            
                function closestForm(el){
                      while (el) {
                        if (el.tagName && el.tagName.toLowerCase() === 'form') return el;
                        el = el.parentNode;
                      }
                      return null;
                    }
                    
                    function fetchUsers(q, cb){
                      lastReqId++;
                      var reqId = lastReqId;
                    
                      var formRoot = closestForm(trainerInput);
                      var baseUrl = formRoot ? (formRoot.getAttribute('data-users-search-url') || '') : '';
                      if (!baseUrl) return cb(null);
                    
                      var url = baseUrl + '?q=' + encodeURIComponent(q || '');
                    
                      var xhr = new XMLHttpRequest();
                      xhr.open('GET', url, true);
                      xhr.setRequestHeader('Accept', 'application/json');
                      xhr.onreadystatechange = function(){
                        if (xhr.readyState !== 4) return;
                        if (reqId !== lastReqId) return;
                        if (xhr.status < 200 || xhr.status >= 300) return cb(null);
                    
                        try { cb(JSON.parse(xhr.responseText)); }
                        catch (e) { cb(null); }
                      };
                      xhr.send();
                    }
            
                function render(items){
                    var html = [];
                    for (var i=0;i<items.length;i++){
                        var it = items[i] || {};
                        var id = it.id;
                        var label = it.label || '';
                        html.push(
                            '<button type="button" class="w-full text-left px-3 py-2 hover:bg-gray-50 border-b border-gray-100 trainer-item" ' +
                            'data-id="' + escapeHtml(id) + '" data-label="' + escapeHtml(label) + '">' +
                            '<div class="text-sm text-gray-900">' + escapeHtml(label) + '</div>' +
                            '</button>'
                        );
                    }
                    dd.innerHTML = html.join('');
            
                    var btns = dd.querySelectorAll('.trainer-item');
                    for (var j=0;j<btns.length;j++){
                        btns[j].addEventListener('click', function(){
                            var id = this.getAttribute('data-id');
                            var label = this.getAttribute('data-label');
                            addChip(id, label);
                            trainerInput.value = '';
                            hideDd();
                        });
                    }
                }
            
                function debounce(fn, ms){
                    var t = null;
                    return function(){
                        var args = arguments;
                        clearTimeout(t);
                        t = setTimeout(function(){ fn.apply(null, args); }, ms);
                    };
                }
            
                var run = debounce(function(){
                    var q = trim(trainerInput.value || '');
            
                    if (q.length === 0) { clearDd(); hideDd(); return; }
                    if (q.length < 2) {
                        showDd();
                        dd.innerHTML = '<div class="px-3 py-3 text-sm text-gray-500">Введите ещё символы…</div>';
                        return;
                    }
            
                    showDd();
                    dd.innerHTML = '<div class="px-3 py-3 text-sm text-gray-500">Поиск…</div>';
            
                    fetchUsers(q, function(data){
                        if (!data) {
                            dd.innerHTML = '<div class="px-3 py-3 text-sm text-gray-500">Не удалось загрузить список.</div>';
                            return;
                        }
                        var items = Array.isArray(data) ? data : (data.items || []);
                        if (!items.length) {
                            dd.innerHTML = '<div class="px-3 py-3 text-sm text-gray-500">Ничего не найдено.</div>';
                            return;
                        }
                        render(items.slice(0, 10));
                    });
                }, 220);
            
                trainerInput.addEventListener('input', run);
                trainerInput.addEventListener('focus', function(){
                    if (trim(trainerInput.value || '').length >= 2) run();
                });
            
                document.addEventListener('click', function(e){
                    if (e.target !== trainerInput && !dd.contains(e.target)) hideDd();
                });
            
                trainerInput.addEventListener('keydown', function(e){
                    if (e.key === 'Escape') hideDd();
                });
            
                // жёсткая проверка перед submit: для нужных форматов должен быть хотя бы 1 тренер
                var formEl = closestForm(trainerInput);
                if (formEl) {
                    formEl.addEventListener('submit', function(e){
                        var fmt = fmtEl2 ? String(fmtEl2.value || '') : '';
                        var need = (fmt === 'training' || fmt === 'training_game' || fmt === 'training_pro_am' || fmt === 'camp' || fmt === 'coach_student');
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
                hideDd();
            })();
            // initial step (ES5)
            var formInit = qs('form[data-initial-step]');
            var initial = 1;
            if (formInit) initial = Number(formInit.getAttribute('data-initial-step') || '1');
            if (initial !== 1 && initial !== 2 && initial !== 3) initial = 1;
            showStep(initial);
        
            // events
                function refreshUI(reason) {
                  cacheGameState();
                
                  syncFormatOptions();
                  syncLevelsUI();
                
                  // пересобрали options → восстановили выбор/значения
                  syncGameSubtypeOptions();
                  restoreGameStateIfEmpty();
                
                  // ✅ если подтип уже выбран — проставим дефолты (только если пусто)
                  applySmartDefaults(false);
                
                  // остальной UI
                  syncGenderPolicyOptions();
                  syncBeachFlagsUI();
                  syncTrainerVisibility();
                  syncGameUI();
                }

                
                if (dirEl) dirEl.addEventListener('change', function () { refreshUI('direction'); });
                if (fmtEl) fmtEl.addEventListener('change', function () { refreshUI('format'); });
                
                if (gameSubtype) {
                    gameSubtype.addEventListener('change', function () {
                  applySmartDefaults(true); // ✅ при выборе подтипа всегда выставляем дефолты
                  syncGameUI();
                });
            }
            var climateBlock = document.getElementById('climate_block');
                function syncBeachFlagsUI() {
                  var direction = dirEl ? dirEl.value : '';
                  var format = fmtEl ? fmtEl.value : '';
                
                  // возрастные ограничения показываем всегда (и для classic, и для beach) — ничего делать не надо
                
                  // климат — только beach + game
                  if (climateBlock) {
                    climateBlock.style.display = (direction === 'beach' && format === 'game') ? '' : 'none';
                  }
                }
            // initial sync
            refreshUI('init');
            bindGameCacheListeners();
            enforceRegistrationRules(); // отдельно: влияет на recurrence + reg timing enable/disable
            syncGenderLimitedBlocks();  // отдельно: включает/выключает блоки и строит позиции при необходимости
            
            // если mixed_limited — отрисовать позиции (syncGenderLimitedBlocks уже делает это),
            // но оставим страховку на случай будущих правок:
            if (genderPolicyEl && trim(genderPolicyEl.value || '') === 'mixed_limited') buildPositionsCheckboxes();
            
            updateLegacyMappingOnly();
            syncRecFieldsVisibility();
            syncMonthsVisibility();
        })();