{{-- window.FlipScore.update(box, home, away): обновляет табло-счёт с анимацией переворота изменившихся цифр (для страниц, где счёт меняется на клиенте). --}}
<script>
window.FlipScore = window.FlipScore || (function () {
	function flipDigit(el, od, nd) {
		Array.prototype.forEach.call(el.querySelectorAll('.rw-fd-ft,.rw-fd-fb'), function (n) { n.remove(); });
		var top = el.querySelector('.rw-fd-t i'), bot = el.querySelector('.rw-fd-b i');
		if (top) top.textContent = nd;
		if (bot) bot.textContent = od;
		var ft = document.createElement('span'); ft.className = 'rw-fd-ft'; ft.innerHTML = '<i>' + od + '</i>';
		var fb = document.createElement('span'); fb.className = 'rw-fd-fb'; fb.innerHTML = '<i>' + nd + '</i>';
		el.appendChild(ft); el.appendChild(fb);
		var done = false;
		function fin() { if (done) return; done = true; if (bot) bot.textContent = nd; if (ft.parentNode) ft.remove(); if (fb.parentNode) fb.remove(); }
		fb.addEventListener('animationend', fin);
		setTimeout(fin, 1200);
	}
	function tiles(str) {
		return str.split('').map(function (d) { return '<span class="rw-fd" data-d="' + d + '"><span class="rw-fd-t"><i>' + d + '</i></span><span class="rw-fd-b"><i>' + d + '</i></span></span>'; }).join('');
	}
	function pad(n, len) { var s = String(Math.max(0, parseInt(n, 10) || 0)); while (s.length < len) s = '0' + s; return s; }
	function update(box, home, away) {
		if (!box) return;
		['home', 'away'].forEach(function (side) {
			var grp = box.querySelector('.rw-flipgroup[data-side="' + side + '"]');
			if (!grp) return;
			var val = side === 'home' ? home : away;
			var ds = grp.querySelectorAll('.rw-fd');
			var nv = pad(val, ds.length);
			if (nv.length !== ds.length) { grp.innerHTML = tiles(nv); return; }
			for (var i = 0; i < ds.length; i++) {
				var cur = ds[i].getAttribute('data-d');
				if (cur !== nv.charAt(i)) { flipDigit(ds[i], cur, nv.charAt(i)); ds[i].setAttribute('data-d', nv.charAt(i)); }
			}
		});
		box.setAttribute('data-home', home); box.setAttribute('data-away', away);
		box.setAttribute('aria-label', home + ' : ' + away);
	}
	return { update: update };
})();
</script>
