{{-- resources/views/auth/login.blade.php --}}
<x-voll-layout body_class="auth-page auth-login">
    <x-slot name="title">
        {{ __('auth.login_title') }}
	</x-slot>
	
    <x-slot name="description">
        {{ __('auth.login_description') }}
	</x-slot>
	
    <x-slot name="h1">
        {{ __('auth.login_h1') }}
	</x-slot>
	
    <x-slot name="h2">
        {{ __('auth.login_h2') }}
	</x-slot>
	
    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <span itemprop="name">{{ __('auth.login_breadcrumb') }}</span>
            <meta itemprop="position" content="1">
		</li>
	</x-slot>
	
    <x-slot name="t_description">
        {{ __('auth.login_t_description') }}
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
					@include('auth._oauth_buttons', ['returnUrl' => $returnUrl])
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