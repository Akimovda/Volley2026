{{-- Попап события состава (замена/либеро) по тапу на строку ленты. Делегирование на document,
     поэтому работает и для строк, подставленных live-обновлением. Один раз на страницу. --}}
@once
<style>
.lu-pop{position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:10001;display:none;align-items:center;justify-content:center;padding:16px}
.lu-pop.lu-open{display:flex}
.lu-pop-box{background:#fff;color:#2c2c2c;width:100%;max-width:420px;border-radius:16px;padding:18px}
body.dark .lu-pop-box{background:#222333;color:#cacaca}
.lu-pop-title{font-weight:700;font-size:17px;margin-bottom:4px}
.lu-pop-sub{font-size:13px;opacity:.65;margin-bottom:12px}
.lu-pop-line{font-size:15px;margin:6px 0}
.lu-pop-close{margin-top:14px;width:100%}
</style>
<script>
(function () {
	if (window.__luPopup) return;
	window.__luPopup = true;
	var el = null;
	function build() {
		el = document.createElement('div');
		el.className = 'lu-pop';
		el.innerHTML = '<div class="lu-pop-box"><div class="lu-pop-title"></div><div class="lu-pop-sub"></div><div class="lu-pop-lines"></div><button type="button" class="btn btn-secondary lu-pop-close">{{ __('tournaments.lineup_close') }}</button></div>';
		document.body.appendChild(el);
		el.addEventListener('click', function (e) { if (e.target === el || e.target.classList.contains('lu-pop-close')) el.classList.remove('lu-open'); });
		document.addEventListener('keydown', function (e) { if (e.key === 'Escape') el.classList.remove('lu-open'); });
	}
	function open(row) {
		var d;
		try { d = JSON.parse(row.getAttribute('data-lineup-popup')); } catch (e) { return; }
		if (!el) build();
		el.querySelector('.lu-pop-title').textContent = d.title || '';
		el.querySelector('.lu-pop-sub').textContent = d.sub || '';
		var box = el.querySelector('.lu-pop-lines');
		box.innerHTML = '';
		(d.lines || []).forEach(function (t) {
			var p = document.createElement('div'); p.className = 'lu-pop-line'; p.textContent = t; box.appendChild(p);
		});
		el.classList.add('lu-open');
	}
	document.addEventListener('click', function (e) {
		var row = e.target.closest ? e.target.closest('[data-lineup-popup]') : null;
		if (row) open(row);
	});
	document.addEventListener('keydown', function (e) {
		if ((e.key === 'Enter' || e.key === ' ') && e.target && e.target.hasAttribute && e.target.hasAttribute('data-lineup-popup')) {
			e.preventDefault(); open(e.target);
		}
	});
})();
</script>
@endonce
