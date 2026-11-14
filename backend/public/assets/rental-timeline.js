/*
 * VolleyPlay — виджет «Таймлайн бронирования кортов».
 * Встраивается на сайт площадки: показывает занятость кортов (день / неделя), а само бронирование происходит на сайте владельца —
 * клик по свободному слоту открывает его ссылку (booking_url) и/или отправляет событие `volley-rental-pick` в страницу.
 * Подключение: window.VolleyRentalTimeline.mount(element, { api, key, mode, theme, lang, accent, header, location, direction }).
 */
(function () {
  'use strict';
  if (window.VolleyRentalTimeline) return;

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function toMin(hhmm) { var p = String(hhmm).split(':'); return (+p[0]) * 60 + (+p[1]); }
  function toHHMM(m) { return pad(Math.floor(m / 60)) + ':' + pad(m % 60); }
  function parseDate(iso) { var p = iso.split('-'); return new Date(+p[0], +p[1] - 1, +p[2], 12, 0, 0); }
  function isoDate(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
  function addDays(iso, n) { var d = parseDate(iso); d.setDate(d.getDate() + n); return isoDate(d); }
  function weekStart(iso) { var d = parseDate(iso); var wd = (d.getDay() + 6) % 7; d.setDate(d.getDate() - wd); return isoDate(d); }

  function css(s) {
    var fonts = {
      system: '-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif',
      serif: 'Georgia,"Times New Roman",serif',
      mono: 'ui-monospace,SFMono-Regular,Menlo,Consolas,monospace',
      inherit: 'inherit'
    };
    var bg = s.bg_transparent ? 'transparent' : s.bg;
    var r = +s.radius;
    return ':host{display:block}' +
      '.vrt{--vrt-accent:var(--vw-accent,' + s.accent + ');--vrt-text:var(--vw-text,' + s.color_text + ');--vrt-muted:var(--vw-muted,' + s.color_muted + ');' +
      '--vrt-grid:var(--vw-grid,' + s.color_grid + ');--vrt-free:var(--vw-free,' + s.color_free + ');--vrt-free-h:var(--vw-free-hover,' + s.color_free_hover + ');' +
      '--vrt-busy:var(--vw-busy,' + s.color_busy + ');--vrt-busy-t:var(--vw-busy-text,' + s.color_busy_text + ');--vrt-past:var(--vw-past,' + s.color_past + ');' +
      '--vrt-panel:var(--vw-panel,' + (s.theme === 'dark' ? '#222333' : '#ffffff') + ');--vrt-r:var(--vw-radius,' + r + 'px);--vrt-classic:var(--vw-dir-classic,' + s.dir_classic + ');--vrt-beach:var(--vw-dir-beach,' + s.dir_beach + ');' +
      'box-sizing:border-box;font-family:' + (fonts[s.font] || fonts.system) + ';background:' + bg + ';color:var(--vrt-text);padding:12px;line-height:1.35;font-size:14px}' +
      '.vrt *,.vrt *::before,.vrt *::after{box-sizing:border-box}' +
      '.vrt button{font:inherit;color:inherit;cursor:pointer}' +
      '.vrt-header{font-size:' + (+s.header_size) + 'px;font-weight:' + s.header_weight + ';color:' + s.header_color + ';text-align:' + s.header_align + ';margin:0 0 12px}' +
      '.vrt-bar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:10px}' +
      '.vrt-grow{flex:1 1 auto}' +
      '.vrt-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px}' +
      '.vrt-tab{border:2px solid var(--vrt-grid);background:transparent;border-radius:999px;padding:7px 16px;font-weight:700;display:inline-flex;align-items:center;gap:8px}' +
      '.vrt-tab i{width:10px;height:10px;border-radius:50%;display:inline-block}' +
      '.vrt-tab.classic i{background:var(--vrt-classic)}.vrt-tab.beach i{background:var(--vrt-beach)}' +
      '.vrt-tab.on.classic{border-color:var(--vrt-classic);background:var(--vrt-classic);color:#fff}.vrt-tab.on.beach{border-color:var(--vrt-beach);background:var(--vrt-beach);color:#fff}' +
      '.vrt-tab.on i{background:#fff}' +
      (s.tab_style === 'underline'
        ? '.vrt-tab{border:0;border-bottom:3px solid transparent;border-radius:0;padding:6px 4px;margin-right:10px}.vrt-tab.on.classic,.vrt-tab.on.beach{background:transparent;color:var(--vrt-text);border-bottom-color:currentColor}.vrt-tab.on.classic{border-bottom-color:var(--vrt-classic)}.vrt-tab.on.beach{border-bottom-color:var(--vrt-beach)}.vrt-tab.on i{background:currentColor}.vrt-tab.on.classic i{background:var(--vrt-classic)}.vrt-tab.on.beach i{background:var(--vrt-beach)}'
        : '') +
      '.vrt-seg{display:inline-flex;border:2px solid var(--vrt-grid);border-radius:10px;overflow:hidden}' +
      '.vrt-seg button{border:0;background:transparent;padding:6px 14px;font-weight:600}' +
      '.vrt-seg button.on{background:var(--vrt-accent);color:#fff}' +
      '.vrt-nav{display:inline-flex;align-items:center;gap:6px}' +
      '.vrt-btn{border:2px solid var(--vrt-grid);background:transparent;border-radius:10px;padding:6px 12px;font-weight:600}' +
      '.vrt-btn:hover:not(:disabled){border-color:var(--vrt-accent)}.vrt-btn:disabled{opacity:.4;cursor:default}' +
      '.vrt-label{font-weight:700;min-width:9em;text-align:center}' +
      '.vrt select{font:inherit;color:inherit;background:transparent;border:2px solid var(--vrt-grid);border-radius:10px;padding:6px 10px;max-width:100%}' +
      '.vrt-scroll{overflow-x:auto}' +
      '.vrt-day{display:grid;border:1px solid var(--vrt-grid);border-radius:var(--vrt-r);overflow:hidden;min-width:max-content;width:100%}' +
      '.vrt-axis{position:relative;border-right:1px solid var(--vrt-grid);font-size:11px;color:var(--vrt-muted)}' +
      '.vrt-axis span{position:absolute;right:6px;transform:translateY(-50%)}' +
      '.vrt-court{position:relative;border-left:1px solid var(--vrt-grid)}' +
      '.vrt-court:first-of-type{border-left:0}' +
      '.vrt-ch{position:sticky;left:0;display:flex;align-items:center;justify-content:center;gap:6px;height:34px;font-weight:700;font-size:13px;border-bottom:1px solid var(--vrt-grid);padding:0 6px;text-align:center}' +
      '.vrt-body{position:relative}' +
      '.vrt-hl{position:absolute;left:0;right:0;height:0;border-top:1px solid var(--vrt-grid);pointer-events:none}' +
      '.vrt-hl.half{border-top-style:dashed;opacity:.55}' +
      '.vrt-free{position:absolute;left:3px;right:3px;border:0;border-radius:calc(var(--vrt-r) / 2);background:var(--vrt-free);padding:0;text-align:left;transition:background .12s;font-size:12px;color:var(--vrt-muted);display:flex;align-items:flex-start;padding:3px 6px}' +
      'button.vrt-free:hover,button.vrt-free:focus-visible{background:var(--vrt-free-h);outline:2px solid var(--vrt-accent);outline-offset:-2px;color:var(--vrt-text)}' +
      'div.vrt-free{cursor:default}' +
      '.vrt-free.sel,button.vrt-free.sel:hover{background:var(--vrt-accent);color:#fff;outline:0}.vrt-free.sel .vrt-p{color:#fff}' +
      '.vrt-free{flex-direction:column;gap:1px;justify-content:flex-start}.vrt-t{font-weight:600}.vrt-p{font-weight:700;color:var(--vrt-text);font-size:12px}' +
      '.vrt-busy{position:absolute;left:3px;right:3px;border-radius:calc(var(--vrt-r) / 2);background:var(--vrt-busy);color:var(--vrt-busy-t);padding:4px 6px;font-size:12px;font-weight:600;overflow:hidden;' +
      'background-image:repeating-linear-gradient(135deg,rgba(127,127,127,.12) 0 6px,transparent 6px 12px)}' +
      '.vrt-past{position:absolute;left:0;right:0;top:0;background:var(--vrt-past);opacity:.75;pointer-events:none}' +
      '.vrt-now{position:absolute;left:0;right:0;height:0;border-top:2px solid #ef4444;pointer-events:none;z-index:3}' +
      '.vrt-sel{position:sticky;bottom:8px;z-index:5;margin-top:10px;display:flex;flex-wrap:wrap;gap:10px 16px;align-items:center;justify-content:space-between;padding:12px 14px;border-radius:var(--vrt-r);border:2px solid var(--vrt-accent);background:var(--vrt-panel);box-shadow:0 6px 24px rgba(0,0,0,.16)}' +
      '.vrt-sel-info{font-weight:600}.vrt-sel-info small{display:block;font-weight:400;color:var(--vrt-muted)}' +
      '.vrt-total{font-size:20px;font-weight:800;white-space:nowrap}' +
      '.vrt-book{border:0;background:var(--vrt-accent);color:#fff;border-radius:10px;padding:10px 20px;font-weight:700}.vrt-book:hover{opacity:.9}' +
      '.vrt-legend{margin-top:12px;font-size:12px;color:var(--vrt-muted);display:flex;flex-direction:column;gap:6px}' +
      '.vrt-legend-row{display:flex;flex-wrap:wrap;gap:6px 16px;align-items:center}.vrt-sw{display:inline-block;width:14px;height:14px;border-radius:4px;vertical-align:-2px;margin-right:5px;border:1px solid var(--vrt-grid)}' +
      '.vrt-prices b{color:var(--vrt-text)}' +
      '.vrt-week{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:8px}' +
      '.vrt-wd{border:2px solid var(--vrt-grid);border-radius:var(--vrt-r);padding:10px 8px;background:transparent;text-align:center;display:flex;flex-direction:column;gap:6px;align-items:center}' +
      'button.vrt-wd:hover{border-color:var(--vrt-accent)}' +
      '.vrt-wd.today{border-color:var(--vrt-accent)}' +
      '.vrt-wd.dim{opacity:.45}' +
      '.vrt-wd b{font-size:18px}.vrt-wd small{color:var(--vrt-muted);text-transform:uppercase;font-size:11px}' +
      '.vrt-meter{width:100%;height:8px;border-radius:5px;background:var(--vrt-grid);overflow:hidden}' +
      '.vrt-meter i{display:block;height:100%;border-radius:5px;background:#34c759}.vrt-meter i.mid{background:#e7612f}.vrt-meter i.low{background:#9e9e9e}' +
      '.vrt-msg{padding:22px 14px;text-align:center;color:var(--vrt-muted);border:1px dashed var(--vrt-grid);border-radius:var(--vrt-r)}' +
      '.vrt-foot{margin-top:10px;font-size:11px;text-align:right;color:' + s.footer_color + '}.vrt-foot a{color:inherit;text-decoration:none}' +
      '@media (max-width:560px){.vrt-week{grid-template-columns:repeat(4,minmax(0,1fr))}.vrt-label{min-width:0}}';
  }

  function mount(host, opts, cfg) {
    cfg = cfg || {};
    var root = host.attachShadow ? (host.shadowRoot || host.attachShadow({ mode: 'open' })) : host;
    var st = {
      loc: opts.location || 0, dir: opts.direction || '', mode: opts.mode || '', date: '',
      style: null, labels: null, locale: 'ru', data: null, error: null, loading: true, sel: null
    };
    var seq = 0;

    function note(text) { root.innerHTML = '<div style="font-family:sans-serif;padding:12px;font-size:14px;color:#888">' + esc(text) + '</div>'; }

    function load() {
      var my = ++seq;
      st.loading = true;
      if (st.data) render(); else note('…');
      var q = 'key=' + encodeURIComponent(opts.key || '');
      if (st.loc) q += '&location_id=' + st.loc;
      if (st.dir) q += '&direction=' + encodeURIComponent(st.dir);
      if (st.date) q += '&date=' + encodeURIComponent(st.date);
      if (st.mode) q += '&mode=' + encodeURIComponent(st.mode);
      ['theme', 'lang', 'accent', 'header'].forEach(function (k) { if (opts[k]) q += '&' + k + '=' + encodeURIComponent(opts[k]); });
      fetch(opts.api + (opts.api.indexOf('?') >= 0 ? '&' : '?') + q, { credentials: opts.credentials || 'omit' })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          if (my !== seq) return;
          st.loading = false;
          if (!j.ok) { st.error = j.error || 'unavailable'; st.data = null; render(); return; }
          st.error = null; st.style = opts.styleOverride || j.style; st.labels = opts.labels || j.labels; st.locale = opts.locale || j.locale || 'ru'; st.data = j.data;
          if (!j.data.empty) { st.loc = j.data.location.id; st.dir = j.data.direction; st.date = j.data.date; st.mode = j.data.mode; }
          render();
        })
        .catch(function () { if (my !== seq) return; st.loading = false; st.error = (st.labels && st.labels.error) || 'Error'; st.data = null; render(); });
    }

    function dayLabel(iso, withWeekday) {
      var o = withWeekday ? { weekday: 'short', day: 'numeric', month: 'long' } : { day: 'numeric', month: 'long' };
      try { return parseDate(iso).toLocaleDateString(st.locale === 'en' ? 'en-GB' : 'ru-RU', o); } catch (e) { return iso; }
    }

    function limits() {
      var s = st.style, d = st.data;
      var min = s.hide_past ? d.today : null;
      var max = (+s.days_ahead > 0) ? addDays(d.today, +s.days_ahead) : null;
      return { min: min, max: max };
    }

    // Ставка корта (₽/час) в минуту суток из сжатых отрезков [from, to, rate]
    function rateAt(court, m) {
      var r = court.rates || [];
      for (var i = 0; i < r.length; i++) { if (m >= r[i][0] && m < r[i][1]) return r[i][2]; }
      return null;
    }
    // Цена слота: по получасам от начала слота — как на сервере (CourtPricingService::calculate)
    function slotPrice(court, from, to) {
      var total = 0, any = false;
      for (var m = from; m < to; m += 30) {
        var len = Math.min(30, to - m), rate = rateAt(court, m);
        if (rate != null) { total += rate * len / 60; any = true; }
      }
      return any ? Math.round(total * 100) / 100 : null;
    }
    function fmtMoney(v) {
      try { return v.toLocaleString(st.locale === 'en' ? 'en-GB' : 'ru-RU', { maximumFractionDigits: 0 }) + ' \u20BD'; } catch (e) { return Math.round(v) + ' \u20BD'; }
    }

    function courtById(id) {
      var r = null;
      ((st.data && st.data.courts) || []).forEach(function (c) { if (String(c.id) === String(id)) r = c; });
      return r;
    }
    function rangeFree(court, a, b) {
      return !court.slots.some(function (x) { return toMin(x.from) < b && toMin(x.to) > a; });
    }
    function fmtDur(min) {
      var L = st.labels, h = Math.floor(min / 60), m = min % 60;
      return (h ? h + ' ' + L.h : '') + (m ? (h ? ' ' : '') + m + ' ' + L.min : '');
    }
    // выбор диапазона слотов на одном корте: клик — выбрать, соседний слот — продлить, крайний выбранный — сократить
    function toggleCell(courtId, from, to) {
      var a = toMin(from), b = toMin(to), c = courtById(courtId), sel = st.sel;
      if (!sel || sel.court !== +courtId) { st.sel = { court: +courtId, from: a, to: b }; return; }
      if (a >= sel.from && b <= sel.to) {
        if (a === sel.from && b === sel.to) { st.sel = null; return; }
        if (a === sel.from) { sel.from = b; return; }
        if (b === sel.to) { sel.to = a; return; }
        st.sel = { court: +courtId, from: a, to: b }; return;
      }
      var nf = Math.min(a, sel.from), nt = Math.max(b, sel.to);
      if (c && rangeFree(c, nf, nt)) { sel.from = nf; sel.to = nt; } else { st.sel = { court: +courtId, from: a, to: b }; }
    }

    function freeWindows(court, opens, closes, isToday, isPast, nowMin, hidePast) {
      var iv = court.slots.map(function (s) { return [toMin(s.from), toMin(s.to)]; }).sort(function (a, b) { return a[0] - b[0]; });
      var wins = [], cur = opens;
      iv.forEach(function (x) {
        if (x[0] > cur) wins.push([cur, Math.min(x[0], closes)]);
        cur = Math.max(cur, x[1]);
      });
      if (cur < closes) wins.push([cur, closes]);
      if (hidePast && (isPast || isToday)) {
        var from = isPast ? closes : Math.ceil(nowMin / 30) * 30;
        wins = wins.map(function (w) { return [Math.max(w[0], from), w[1]]; }).filter(function (w) { return w[1] > w[0]; });
      }
      return wins;
    }

    function renderDay() {
      var s = st.style, d = st.data, L = st.labels;
      if (d.closed) return '<div class="vrt-msg">' + esc(L.closed) + '</div>';
      if (!d.courts.length) return '<div class="vrt-msg">' + esc(L.no_courts) + '</div>';

      var opens = toMin(d.opens), closes = toMin(d.closes), PX = (+s.row_height) / 60, H = Math.round((closes - opens) * PX);
      var isToday = d.date === d.today, isPast = d.date < d.today;
      var slotMin = +s.slot_minutes, clickable = s.click_mode !== 'none';
      var hl = '', axis = '';
      var tickStep = slotMin <= 30 ? 30 : 60, tm = Math.ceil(opens / tickStep) * tickStep;
      var labelEvery = (tickStep * PX >= 22) ? 1 : 2;
      for (var ti = 0; tm <= closes; tm += tickStep, ti++) {
        var top = Math.round((tm - opens) * PX), isHour = tm % 60 === 0;
        hl += '<div class="vrt-hl' + (isHour ? '' : ' half') + '" style="top:' + top + 'px"></div>';
        if (isHour || labelEvery === 1) axis += '<span style="top:' + top + 'px">' + pad(Math.floor(tm / 60)) + ':' + pad(tm % 60) + '</span>';
      }
      var past = '';
      if (s.hide_past && (isPast || isToday)) {
        var pm = isPast ? closes : Math.max(opens, Math.min(closes, d.now_min));
        if (pm > opens) past = '<div class="vrt-past" style="height:' + Math.round((pm - opens) * PX) + 'px"></div>';
      }
      var now = (isToday && d.now_min > opens && d.now_min < closes)
        ? '<div class="vrt-now" title="' + esc(L.now) + '" style="top:' + Math.round((d.now_min - opens) * PX) + 'px"></div>' : '';

      var cols = '';
      d.courts.forEach(function (c) {
        var cells = '';
        freeWindows(c, opens, closes, isToday, isPast, d.now_min, s.hide_past).forEach(function (w) {
          for (var m = w[0]; m + slotMin <= w[1]; m += slotMin) {
            var style = 'top:' + Math.round((m - opens) * PX) + 'px;height:' + Math.round(slotMin * PX) + 'px';
            var from = toHHMM(m), to = toHHMM(m + slotMin);
            var price = s.show_prices ? slotPrice(c, m, m + slotMin) : null;
            var priceText = price != null ? fmtMoney(price) : '';
            var aria = L.free_aria.replace('{court}', c.name).replace('{date}', dayLabel(d.date, true)).replace('{from}', from).replace('{to}', to) + (priceText ? ', ' + priceText : '');
            var cellH = slotMin * PX;
            var inner = '<span class="vrt-t">' + (cellH >= 24 ? from : '') + '</span>' + (priceText && cellH >= 24 ? '<span class="vrt-p">' + esc(priceText) + '</span>' : '');
            var tip = s.free_hint + (priceText ? ' \u2014 ' + priceText : '');
            var isSel = st.sel && st.sel.court === c.id && m >= st.sel.from && m + slotMin <= st.sel.to;
            if (clickable) {
              cells += '<button type="button" class="vrt-free' + (isSel ? ' sel' : '') + '" aria-pressed="' + (isSel ? 'true' : 'false') + '" style="' + style + '" data-act="pick" data-court="' + c.id + '" data-from="' + from + '" data-to="' + to + '" title="' + esc(tip) + '" aria-label="' + esc(aria) + '">' + inner + '</button>';
            } else {
              cells += '<div class="vrt-free" style="' + style + '" title="' + esc(priceText) + '">' + inner + '</div>';
            }
          }
        });
        var busy = c.slots.map(function (b) {
          var a = Math.max(opens, toMin(b.from)), z = Math.min(closes, toMin(b.to));
          if (z <= a) return '';
          return '<div class="vrt-busy" style="top:' + Math.round((a - opens) * PX) + 'px;height:' + Math.round((z - a) * PX) + 'px" title="' + esc(b.title || L.busy_n.replace('{n}', '')) + '">' + esc(b.title || s.busy_label) + '</div>';
        }).join('');
        var icon = s.show_court_icons ? (c.indoor ? '⌂ ' : '☀ ') : '';
        cols += '<div class="vrt-court"><div class="vrt-ch">' + (s.show_court_names ? esc(icon + c.name) : '') + '</div>' +
          '<div class="vrt-body" style="height:' + H + 'px">' + hl + past + cells + busy + now + '</div></div>';
      });

      return '<div class="vrt-scroll"><div class="vrt-day" style="grid-template-columns:52px repeat(' + d.courts.length + ',minmax(' + (+s.court_min_width) + 'px,1fr))">' +
        '<div class="vrt-axis"><div style="height:34px"></div><div style="position:relative;height:' + H + 'px">' + axis + '</div></div>' + cols + '</div></div>';
    }

    function selPanel() {
      var s = st.style, d = st.data, L = st.labels, sel = st.sel;
      if (!sel || !s.confirm_step || s.click_mode === 'none') return '';
      var c = courtById(sel.court);
      if (!c) return '';
      var price = s.show_prices ? slotPrice(c, sel.from, sel.to) : null;
      return '<div class="vrt-sel" role="status"><div class="vrt-sel-info">' + esc(c.name) + ' \u00B7 ' + esc(dayLabel(d.date, true)) +
        '<small>' + toHHMM(sel.from) + '\u2013' + toHHMM(sel.to) + ' (' + esc(fmtDur(sel.to - sel.from)) + ')</small></div>' +
        (price != null ? '<div class="vrt-total">' + esc(L.total.replace('{sum}', fmtMoney(price))) + '</div>' : '') +
        '<span style="display:inline-flex;gap:8px"><button type="button" class="vrt-btn" data-act="reset">' + esc(L.reset) + '</button>' +
        '<button type="button" class="vrt-book" data-act="book">' + esc(s.book_button_text || L.book) + '</button></span></div>';
    }

    function legend() {
      var s = st.style, d = st.data, L = st.labels;
      if (!s.show_legend || d.closed || !(d.courts || []).length) return '';
      var isToday = d.date === d.today;
      var row = '<div class="vrt-legend-row"><span><i class="vrt-sw" style="background:var(--vrt-free)"></i>' + esc(L.legend_free) + '</span>' +
        '<span><i class="vrt-sw" style="background:var(--vrt-busy)"></i>' + esc(L.legend_busy) + '</span>' +
        (s.hide_past && (isToday || d.date < d.today) ? '<span><i class="vrt-sw" style="background:var(--vrt-past)"></i>' + esc(L.legend_past) + '</span>' : '') +
        (s.click_mode !== 'none' ? '<span>' + esc(L.select_hint) + '</span>' : '') + '</div>';
      var prices = '';
      if (s.show_prices) {
        var groups = {}, order = [];
        d.courts.forEach(function (c) {
          var segs = (c.rates || []).filter(function (r) { return r[2] != null; });
          if (!segs.length) return;
          var sig = JSON.stringify(segs);
          if (!groups[sig]) { groups[sig] = { names: [], segs: segs }; order.push(sig); }
          groups[sig].names.push(c.name);
        });
        if (order.length) {
          prices = '<div class="vrt-prices"><div style="margin-bottom:3px">' + esc(L.legend_prices) + ':</div>' + order.map(function (sig) {
            var g = groups[sig];
            return '<div class="vrt-legend-row"><span>' + (order.length === 1 ? esc(L.all_courts) : '<b>' + esc(g.names.join(', ')) + '</b>') + ':</span>' +
              g.segs.map(function (r) { return '<span>' + toHHMM(r[0]) + '\u2013' + toHHMM(r[1]) + ' \u2014 <b>' + esc(fmtMoney(r[2])) + esc(L.per_hour) + '</b></span>'; }).join('') + '</div>';
          }).join('') + '</div>';
        }
      }
      return '<div class="vrt-legend">' + row + prices + '</div>';
    }

    function renderWeek() {
      var s = st.style, d = st.data, L = st.labels;
      var out = '<div class="vrt-week">';
      d.days.forEach(function (x) {
        var dt = parseDate(x.date), isPast = s.hide_past && x.date < d.today, isToday = x.date === d.today;
        var dis = isPast || (limits().max && x.date > limits().max);
        var wd = ''; try { wd = dt.toLocaleDateString(st.locale === 'en' ? 'en-GB' : 'ru-RU', { weekday: 'short' }); } catch (e) {}
        var lvl = x.pct > 70 ? '' : (x.pct >= 30 ? 'mid' : 'low');
        var inner = '<small>' + esc(wd) + '</small><b>' + dt.getDate() + '</b>' +
          (x.closed ? '<span style="color:var(--vrt-muted);font-size:12px">' + esc(L.closed_short) + '</span>'
            : '<div class="vrt-meter" title="' + esc(L.occupancy.replace('{p}', x.pct)) + '"><i class="' + lvl + '" style="width:' + Math.max(3, x.pct) + '%"></i></div>' +
              '<span style="font-size:12px;color:var(--vrt-muted)">' + esc(L.busy_n.replace('{n}', x.busy)) + '</span>');
        var cls = 'vrt-wd' + (isToday ? ' today' : '') + (dis ? ' dim' : '');
        out += dis ? '<div class="' + cls + '">' + inner + '</div>'
                   : '<button type="button" class="' + cls + '" data-act="gotoday" data-date="' + x.date + '">' + inner + '</button>';
      });
      return out + '</div>';
    }

    function render() {
      if (st.error) { note(st.error); return; }
      var s = st.style, d = st.data, L = st.labels;
      if (!s || !d) { note('…'); return; }
      if (d.empty) { root.innerHTML = '<style>' + css(s) + '</style><div class="vrt"><div class="vrt-msg">' + esc(L.empty) + '</div></div>'; fit(); return; }

      var lim = limits(), html = '';
      if (s.header_show && s.header_text) html += '<div class="vrt-header">' + esc(s.header_text) + '</div>';

      if (s.show_direction_tabs && d.directions.length > 1) {
        html += '<div class="vrt-tabs" role="tablist">' + d.directions.map(function (k) {
          return '<button type="button" role="tab" class="vrt-tab ' + k + (k === d.direction ? ' on' : '') + '" data-act="dir" data-dir="' + k + '"><i></i>' + esc(L[k] || k) + '</button>';
        }).join('') + '</div>';
      }

      var bar = '';
      if (s.show_location_select && d.locations.length > 1) {
        bar += '<select data-act="loc" aria-label="' + esc(L.location) + '">' + d.locations.map(function (l) {
          return '<option value="' + l.id + '"' + (l.id === d.location.id ? ' selected' : '') + '>' + esc(l.name) + '</option>';
        }).join('') + '</select>';
      }
      if (s.show_mode_switch) {
        bar += '<span class="vrt-seg"><button type="button" data-act="mode" data-mode="day" class="' + (d.mode === 'day' ? 'on' : '') + '">' + esc(L.day) + '</button>' +
          '<button type="button" data-act="mode" data-mode="week" class="' + (d.mode === 'week' ? 'on' : '') + '">' + esc(L.week) + '</button></span>';
      }
      bar += '<span class="vrt-grow"></span>';
      if (s.show_date_nav) {
        var step = d.mode === 'week' ? 7 : 1, base = d.mode === 'week' ? d.week_start : d.date;
        var prev = addDays(base, -step), next = addDays(base, step);
        var prevOff = lim.min && (d.mode === 'week' ? addDays(prev, 6) < lim.min : prev < lim.min);
        var nextOff = lim.max && next > lim.max;
        var label = d.mode === 'week' ? dayLabel(d.week_start, false) + ' – ' + dayLabel(addDays(d.week_start, 6), false) : dayLabel(d.date, true);
        bar += '<span class="vrt-nav"><button type="button" class="vrt-btn" data-act="nav" data-date="' + prev + '" aria-label="' + esc(L.prev) + '"' + (prevOff ? ' disabled' : '') + '>‹</button>' +
          '<span class="vrt-label">' + esc(label) + '</span>' +
          '<button type="button" class="vrt-btn" data-act="nav" data-date="' + next + '" aria-label="' + esc(L.next) + '"' + (nextOff ? ' disabled' : '') + '>›</button>' +
          (s.show_today_button ? '<button type="button" class="vrt-btn" data-act="nav" data-date="' + d.today + '">' + esc(L.today) + '</button>' : '') + '</span>';
      }
      html += '<div class="vrt-bar">' + bar + '</div>';
      html += d.mode === 'week' ? renderWeek() : renderDay() + selPanel() + legend();

      var origin = ''; try { origin = new URL(opts.api, location.href).host; } catch (e) {}
      if (origin) html += '<div class="vrt-foot"><a href="https://' + esc(origin) + '" target="_blank" rel="noopener">' + esc(origin) + '</a></div>';

      root.innerHTML = '<style>' + css(s) + '</style><div class="vrt"' + (st.loading ? ' aria-busy="true"' : '') + '>' + html + '</div>';
      fit();
    }

    function fit() {
      if (!cfg.iframe) return;
      try { parent.postMessage({ type: 'volley-widget-height', height: document.documentElement.scrollHeight }, '*'); } catch (e) {}
    }

    function pick(courtId, from, to) {
      var d = st.data, s = st.style;
      var court = null;
      (d.courts || []).forEach(function (c) { if (String(c.id) === String(courtId)) court = c; });
      var price = (court && s.show_prices) ? slotPrice(court, toMin(from), toMin(to)) : null;
      var detail = {
        location_id: d.location.id, location: d.location.name, direction: d.direction,
        court_id: +courtId, court: court ? court.name : '', date: d.date, from: from, to: to, duration: toMin(to) - toMin(from),
        price: price, price_text: price != null ? fmtMoney(price) : '', currency: 'RUB'
      };
      try { host.dispatchEvent(new CustomEvent('volley-rental-pick', { detail: detail, bubbles: true, composed: true })); } catch (e) {}
      if (cfg.iframe) { try { parent.postMessage({ type: 'volley-rental-pick', detail: detail }, '*'); } catch (e) {} }
      if (s.click_mode === 'link' && s.booking_url) {
        var url = s.booking_url.replace(/\{(\w+)\}/g, function (m, k) { return detail.hasOwnProperty(k) ? encodeURIComponent(detail[k] == null ? '' : detail[k]) : m; });
        if (/^https?:\/\//i.test(url)) {
          if (s.link_target === '_top') { try { window.top.location.href = url; } catch (e) { window.open(url, '_blank', 'noopener'); } }
          else if (s.link_target === '_self') { window.location.href = url; }
          else { window.open(url, '_blank', 'noopener'); }
        }
      }
    }

    root.addEventListener('click', function (e) {
      var el = e.target.closest ? e.target.closest('[data-act]') : null;
      if (!el || el.disabled) return;
      var a = el.getAttribute('data-act');
      if (a === 'pick') {
        var cid = el.getAttribute('data-court'), f = el.getAttribute('data-from'), t = el.getAttribute('data-to');
        if (!st.style.confirm_step) { pick(cid, f, t); return; }
        toggleCell(cid, f, t); render(); return;
      }
      if (a === 'book') {
        var c = st.sel && courtById(st.sel.court);
        if (c) pick(c.id, toHHMM(st.sel.from), toHHMM(st.sel.to));
        return;
      }
      if (a === 'reset') { st.sel = null; render(); return; }
      if (a === 'dir') { st.sel = null; st.dir = el.getAttribute('data-dir'); load(); }
      else if (a === 'mode') { st.sel = null; st.mode = el.getAttribute('data-mode'); load(); }
      else if (a === 'nav') { st.sel = null; st.date = el.getAttribute('data-date'); load(); }
      else if (a === 'gotoday') { st.sel = null; st.date = el.getAttribute('data-date'); st.mode = 'day'; load(); }
    });
    root.addEventListener('change', function (e) {
      var el = e.target;
      if (el && el.getAttribute && el.getAttribute('data-act') === 'loc') { st.sel = null; st.loc = +el.value; st.dir = ''; load(); }
    });
    window.addEventListener('resize', fit);

    note('…');
    load();
  }

  window.VolleyRentalTimeline = { mount: mount };
})();
