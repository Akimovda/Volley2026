{{-- Фото игрока не загрузилось (404/обрыв) → вместо пустого кружка инициалы на цвете команды. Работает для любого <img data-ini="…"> (+ data-bg/data-fg/data-cls). --}}
<script>
(function () {
	function fix(im) {
		if (!im || im.tagName !== 'IMG' || !im.dataset.ini || !im.parentNode) { return; }
		var box = im.parentNode, s = document.createElement('span');
		if (im.dataset.bg) { box.style.background = im.dataset.bg; }
		if (im.dataset.fg) { box.style.color = im.dataset.fg; }
		if (im.dataset.cls) { s.className = im.dataset.cls; }
		s.textContent = im.dataset.ini;
		box.replaceChild(s, im);
	}
	function sweep() { document.querySelectorAll('img[data-ini]').forEach(function (im) { if (im.complete && im.naturalWidth === 0) { fix(im); } }); }
	document.addEventListener('error', function (e) { fix(e.target); }, true);
	document.addEventListener('rally-live-swapped', sweep);
	document.addEventListener('DOMContentLoaded', sweep);
	window.addEventListener('load', sweep);
	window.PhotoFallback = {sweep: sweep};
})();
</script>
