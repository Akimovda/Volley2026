{{-- resources/views/auth/login.blade.php --}}
<x-voll-layout body_class="auth-page auth-login">
    <x-slot name="title">
        Вход в аккаунт
	</x-slot>
	
    <x-slot name="description">
        Войдите в свой аккаунт через Telegram, ВКонтакте, Яндекс или по email
	</x-slot>
	
    <x-slot name="h1">
        Вход
	</x-slot>
	
    <x-slot name="h2">
        Добро пожаловать
	</x-slot>
	
    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <span itemprop="name">Вход</span>
            <meta itemprop="position" content="1">
		</li>
	</x-slot>
	
    <x-slot name="t_description">
        Войдите, чтобы продолжить
	</x-slot>	
	
    <x-slot name="style">
        <style>
		.auth-page .auth-btn  {
		opacity: 1;
		transform: translateY(0);
		}
		</style>
	</x-slot>	
	
	
    <div class="container">
                <div class="ramka">
  					@php
					// Приоритет: ?return= в URL → url.intended (поставил Authenticate middleware) → /events
					$returnUrl = filled(request()->query('return'))
					    ? request()->query('return')
					    : (session('url.intended') ?: url('/events'));
					@endphp                  
					<div class="social-auth">
						<!-- Кнопка Apple (обязательно первой по требованию Apple) -->
						<a href="{{ route('auth.apple.redirect', ['return' => $returnUrl]) }}" class="auth-btn auth-btn-apple" onclick="if(window.VolleyNative)window.VolleyNative.showAuthOverlay()">
							<span class="auth-icon-circle">
								<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
									<path d="M17.05 20.28c-.98.95-2.05.88-3.08.4-1.09-.5-2.08-.48-3.24 0-1.44.62-2.2.44-3.06-.4C2.79 15.25 3.51 7.59 9.05 7.31c1.35.07 2.29.74 3.08.8 1.18-.24 2.31-.93 3.57-.84 1.51.12 2.65.72 3.4 1.8-3.12 1.87-2.38 5.98.48 7.13-.57 1.5-1.31 2.99-2.54 4.09zM12.03 7.25c-.15-2.23 1.66-4.07 3.74-4.25.29 2.58-2.34 4.5-3.74 4.25z"/>
								</svg>
							</span>
							<span class="auth-text">Войти через Apple</span>
						</a>

						<!-- Кнопка VK -->
						<div data-href="{{ route('auth.vk.redirect', ['return' => $returnUrl]) }}" class="auth-btn auth-btn-vk" onclick="if(window.VolleyNative)window.VolleyNative.showAuthOverlay()">
							<span class="auth-icon-circle">
								<span class="icon-vk"></span>
							</span>
							<span class="auth-text">Войти через ВКонтакте</span>
						</div>
						
						<!-- Кнопка Яндекс -->
						<div data-href="{{ route('auth.yandex.redirect', ['return' => $returnUrl]) }}" class="auth-btn auth-btn-yandex" onclick="if(window.VolleyNative)window.VolleyNative.showAuthOverlay()">
							<span class="auth-icon-circle">
								<span class="icon-yandex"></span>
							</span>
							<span class="auth-text">Войти с Яндекс ID</span>
						</div>
						
<a href="{{ route('auth.telegram.redirect', ['return' => url()->full()]) }}" class="auth-btn auth-btn-telegram" onclick="if(window.VolleyNative)window.VolleyNative.showAuthOverlay()">
<span class="auth-icon-circle">
<span class="icon-tg"></span>
</span>
<span class="auth-text">Войти через Telegram</span>
</a>

						</div>
						{{--
						<div data-href="#max" class="auth-btn auth-btn-max">
							<span class="auth-icon-circle">
								<span class="icon-max"></span>
							</span>
							<span class="auth-text">Войти через Max</span>
						</div>								
						--}}
						
						
						
					</div>
                    
                </div>
    </div>


</x-voll-layout>