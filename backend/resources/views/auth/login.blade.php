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
					$returnUrl = url()->full(); // страница, где нажали кнопку
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
						
						<div class="auth-btn auth-btn-telegram">
							<div class="auth-btn-telegram-widget-up">
								<span class="auth-icon-circle">
									<span class="icon-tg"></span>
								</span>
								<span id="TGloadlogin" class="auth-text">Загрузка Telegram...</span>
							</div>						
							<div class="auth-btn-telegram-widget">
								<script async
    onload="document.getElementById('TGloadlogin').textContent = 'Войти через Telegram'"
    onerror="document.getElementById('TGloadlogin').textContent = 'Ошибка Telegram'"									
								src="https://telegram.org/js/telegram-widget.js?22"
								data-telegram-login="VolleyEvent_bot"
								data-size="large"
								data-userpic="false"
								data-radius="6"
								data-request-access="write"
								data-auth-url="{{ route('auth.telegram.callback', ['return' => url()->full()]) }}"
								data-lang="ru">
								</script>
							</div>

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