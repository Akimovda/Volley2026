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
					$ua = request()->userAgent() ?? '';
					$isAndroid  = str_contains($ua, 'Android');
					$isApple    = str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') || str_contains($ua, 'Macintosh');
					@endphp
					<div class="social-auth">
						@unless($isAndroid)
						<!-- Кнопка Apple (обязательно первой по требованию Apple) -->
						<a href="{{ route('auth.apple.redirect', ['return' => $returnUrl]) }}" class="auth-btn auth-btn-apple">
							<span class="auth-icon-circle">
								<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
									<path d="M17.05 20.28c-.98.95-2.05.88-3.08.4-1.09-.5-2.08-.48-3.24 0-1.44.62-2.2.44-3.06-.4C2.79 15.25 3.51 7.59 9.05 7.31c1.35.07 2.29.74 3.08.8 1.18-.24 2.31-.93 3.57-.84 1.51.12 2.65.72 3.4 1.8-3.12 1.87-2.38 5.98.48 7.13-.57 1.5-1.31 2.99-2.54 4.09zM12.03 7.25c-.15-2.23 1.66-4.07 3.74-4.25.29 2.58-2.34 4.5-3.74 4.25z"/>
								</svg>
							</span>
							<span class="auth-text">Войти через Apple</span>
						</a>
						@endunless

						<!-- Кнопка VK -->
						<div data-href="{{ route('auth.vk.redirect', ['return' => $returnUrl]) }}" class="auth-btn auth-btn-vk">
							<span class="auth-icon-circle">
								<span class="icon-vk"></span>
							</span>
							<span class="auth-text">Войти через ВКонтакте</span>
						</div>
						
						<!-- Кнопка Яндекс -->
						<div data-href="{{ route('auth.yandex.redirect', ['return' => $returnUrl]) }}" class="auth-btn auth-btn-yandex">
							<span class="auth-icon-circle">
								<span class="icon-yandex"></span>
							</span>
							<span class="auth-text">Войти с Яндекс ID</span>
						</div>
						
<a href="{{ route('auth.telegram.redirect', ['return' => url()->full()]) }}" class="auth-btn auth-btn-telegram">
<span class="auth-icon-circle">
<span class="icon-tg"></span>
</span>
<span class="auth-text">Войти через Telegram</span>
</a>

						@unless($isApple)
						<!-- Кнопка Google (Android + desktop, не Apple) -->
						<a href="{{ route('auth.google.redirect', ['return' => $returnUrl]) }}" class="auth-btn auth-btn-google">
							<span class="auth-icon-circle">
								<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
									<path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
									<path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
									<path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/>
									<path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
								</svg>
							</span>
							<span class="auth-text">Войти через Google</span>
						</a>
						@endunless

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