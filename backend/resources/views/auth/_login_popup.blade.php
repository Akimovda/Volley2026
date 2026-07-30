{{-- resources/views/auth/_login_popup.blade.php --}}
{{-- Общий поп-ап "Войдите, чтобы записаться" (fancybox inline). Подключать ОДИН
     РАЗ на странице — не внутри цикла карточек (events/_card.blade.php рендерится
     много раз за страницу, дублировать этот блок нельзя, id должны быть уникальны).
     Использовать: events/index.blade.php, locations/show.blade.php,
     volleyball_school/show.blade.php — везде, где рендерятся events._card. --}}
<div id="loginPopupContent" style="display:none; max-width: 42rem; text-align:center">
    <h2 class="title-h login-popup-title -mt-05">{{ __('auth.login_popup_title') }}</h2>
    <p class="text-muted mb-2">{{ __('auth.login_popup_subtitle') }}</p>
    <div id="loginPopupOauth">
        @include('auth._oauth_buttons')
    </div>
</div>

<script>
	// Открывает общий поп-ап логина, подставляя returnUrl в ?return= всех OAuth-ссылок
	// внутри него непосредственно перед открытием (returnUrl разный для каждой карточки).
	window.openLoginPopup = function(returnUrl) {
		var target = returnUrl || window.location.href;
		document.querySelectorAll('#loginPopupOauth a[href]').forEach(function(a) {
			try {
				var url = new URL(a.getAttribute('href'), window.location.origin);
				url.searchParams.set('return', target);
				a.setAttribute('href', url.toString());
			} catch (e) {}
		});
		jQuery.fancybox.open({
			src: '#loginPopupContent',
			type: 'inline',
			opts: { hideScrollbar: false, touch: false, toolbar: false, smallBtn: true, animationEffect: 'zoom-in-out', transitionEffect: 'zoom-in-out' }
		});
	};

	document.addEventListener('click', function(e) {
		var trigger = e.target.closest('.js-open-login-popup');
		if (!trigger) return;
		e.preventDefault();
		window.openLoginPopup(trigger.getAttribute('data-return-url'));
	});
</script>
