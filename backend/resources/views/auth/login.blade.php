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