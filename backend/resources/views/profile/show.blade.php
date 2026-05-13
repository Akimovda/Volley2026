{{-- resources/views/profile/show.blade.php --}}

<x-voll-layout body_class="profile-page">
    
    <x-slot name="title">
        {{ __('profile.show_title') }}
	</x-slot>
    
    <x-slot name="description">{{ __('profile.show_description_prefix') }} 
        @php
		$user = auth()->user();
        @endphp
        @if(!empty($user->first_name) || !empty($user->last_name))
		{{ trim($user->first_name . ' ' . $user->last_name) }}
        @else
		{{ __('profile.show_user_n', ['id' => $user->id]) }}
        @endif
	</x-slot>
    
    <x-slot name="canonical">
        {{ route('profile.show') }}
	</x-slot> 
    
	
	
    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('profile.show') }}" itemprop="item">
                <span itemprop="name">{{ __('profile.show_title') }}</span>
			</a>
            <meta itemprop="position" content="2">
		</li>
	</x-slot>
    
    <x-slot name="h1">{{ __('profile.show_title') }}</x-slot>
    
    <x-slot name="h2">
        @php
		$user = auth()->user();
        @endphp
        @if(!empty($user->first_name) || !empty($user->last_name))
		{{ trim($user->first_name . ' ' . $user->last_name) }}
        @else
		{{ __('profile.show_user_n', ['id' => $user->id]) }}
        @endif
	</x-slot>
    
    <x-slot name="t_description">
        {{ __('profile.show_t_description') }}
	</x-slot>
    
    
    {{-- FLASH --}}
    @if (session('status'))
	<div class="container">	
		<div class="ramka">
			<div class="alert alert-success">
				{{ session('status') }}
			</div>
		</div>
	</div>
    @endif
    
    @if (session('error'))
	<div class="container">
		<div class="ramka">
			<div class="alert alert-error">
				{{ session('error') }}
			</div>
		</div>
	</div>
    @endif

    @if (session('duplicate_user_id'))
    @php $dupeUser = \App\Models\User::find(session('duplicate_user_id')); @endphp
    @if ($dupeUser)
	<div class="container">
		<div class="ramka" style="border-left: 4px solid #f59e0b; background: #fffbeb;">
			<div class="f-16">
				<strong>{{ __('profile.dupe_title') }}</strong><br>
				{{ __('profile.dupe_text_lead') }}
				<strong>{{ $dupeUser->name ?: __('profile.dupe_user_id_prefix').$dupeUser->id }}</strong>
				({{ $dupeUser->email }}).
				{{ __('profile.dupe_text_action') }}
				<a href="/admin/users/duplicates">{{ __('profile.dupe_link_text') }}</a> {{ __('profile.dupe_text_admin') }}
			</div>
		</div>
	</div>
    @endif
    @endif
    
    @php
    /** @var \App\Models\User $u */
    $u = auth()->user();
	$u->refresh();
	
    $u->loadMissing(['city', 'classicPositions', 'beachZones']);
    
    $posMap = __('profile.pos_long');
    
    $classicPrimary = optional($u->classicPositions)->firstWhere('is_primary', true)?->position;
    $classicExtras  = optional($u->classicPositions)
    ?->where('is_primary', false)
    ->pluck('position')->values()->all() ?? [];
    
    $beachPrimary = optional($u->beachZones)->firstWhere('is_primary', true)?->zone;
    $beachExtras  = optional($u->beachZones)
    ?->where('is_primary', false)
    ->pluck('zone')->values()->all() ?? [];
    
    $age   = method_exists($u, 'ageYears') ? $u->ageYears() : null;
    $birth = $u->birth_date ? $u->birth_date->format('Y-m-d') : '—';
    
    
    $hasTg = !empty($u?->telegram_id);
    $hasVk = !empty($u?->vk_id);
    $hasYa = !empty($u?->yandex_id);
    $hasApple = !empty($u?->apple_id);
    $hasGoogle = !empty($u?->google_id);
	// персональные уведомления
    $hasMaxNotify = !empty($u?->max_chat_id);
    $hasTelegramNotify = !empty($u?->telegram_notify_chat_id);
    $hasVkNotify = !empty($u?->vk_notify_user_id);
    
    // provider in session: telegram|vk|yandex|null
    $provider = session('auth_provider');
    $providerId = (string) session('auth_provider_id', '');
    
    if (!$provider && $providerId !== '') {
	if ($hasTg && (string) $u->telegram_id === $providerId) {
	$provider = 'telegram';
	} elseif ($hasVk && (string) $u->vk_id === $providerId) {
	$provider = 'vk';
	} elseif ($hasYa && (string) $u->yandex_id === $providerId) {
	$provider = 'yandex';
	} elseif ($hasApple && (string) $u->apple_id === $providerId) {
	$provider = 'apple';
	} elseif ($hasGoogle && (string) $u->google_id === $providerId) {
	$provider = 'google';
	}
    }

    // fallback 1: если в сессии ничего нет, но привязан только один вход
    if (!$provider) {
	$linkedProviders = array_filter([
	'telegram' => $hasTg,
	'vk' => $hasVk,
	'yandex' => $hasYa,
	'apple' => $hasApple,
	'google' => $hasGoogle,
	]);
    
	if (count($linkedProviders) === 1) {
	$provider = array_key_first($linkedProviders);
	}
    }
    
    // fallback 2: если определить нельзя — вход через email/пароль
    if (!$provider) {
	$provider = 'password';
    }

    $linkedCount = (int)$hasTg + (int)$hasVk + (int)$hasYa + (int)$hasApple + (int)$hasGoogle;
    
    // “provider looks off” (после неуспешной привязки мог остаться мусор в сессии)
	
    
    // link urls
    $vkLinkUrl      = route('auth.vk.redirect', ['link' => 1]);
    $yandexLinkUrl  = route('auth.yandex.redirect', ['link' => 1]);
    $googleLinkUrl  = route('auth.google.redirect', ['link' => 1]);

    $allLinked = $hasTg && $hasVk && $hasYa && $hasApple && $hasGoogle;
    
    $tgBotUsername = config('services.telegram.bot_username');
    
    // can unlink only if more than one provider linked (чтобы не потерять доступ)
    $canUnlink = $linkedCount > 1;
    
	$hasPendingOrganizerRequest = (bool)($hasPendingOrganizerRequest ?? ($hasPendingRequest ?? false));
	
    // UI helpers
    $providerIcon = function (?string $p) {
    $p = $p ?: 'unknown';
    $base = 'provider-icon';
    $dot  = 'provider-dot';
    $txt  = 'provider-text';
	
	if ($p === 'vk') {
	return '<span class="'.$base.'"><span class="'.$dot.'" style="background:#2787F5;"></span><span class="'.$txt.'">VK</span></span>';
	}
	if ($p === 'telegram') {
	return '<span class="'.$base.'"><span class="'.$dot.'" style="background:#2AABEE;"></span><span class="'.$txt.'">Telegram</span></span>';
	}
	if ($p === 'yandex') {
	return '<span class="'.$base.'"><span class="'.$dot.'" style="background:#FF0000;"></span><span class="'.$txt.'">Yandex</span></span>';
	}
	if ($p === 'apple') {
	return '<span class="'.$base.'"><span class="'.$dot.'" style="background:#000000;"></span><span class="'.$txt.'">Apple</span></span>';
	}
	if ($p === 'google') {
	return '<span class="'.$base.'"><span class="'.$dot.'" style="background:#4285F4;"></span><span class="'.$txt.'">Google</span></span>';
	}
	if ($p === 'password') {
	return '<span class="'.$base.'"><span class="'.$dot.'" style="background:#6B7280;"></span><span class="'.$txt.'">Объединённый аккаунт</span></span>';
	}
	return '<span class="'.$base.'"><span class="'.$dot.'" style="background:#9CA3AF;"></span><span class="'.$txt.'">'.e(__('profile.providers_unknown')).'</span></span>';
    };
    
    $badge = function (bool $ok) {
    if ($ok) {
    return '<span class="badge badge-success"></span>';
    }
    return '<span class="badge badge-muted"></span>';
    };
    
    $miniIcon = function (string $p) {
    $dot  = 'provider-dot';
    if ($p === 'vk') return '<span title="VK" class="'.$dot.'" style="background:#2787F5;"></span>';
    if ($p === 'telegram') return '<span title="Telegram" class="'.$dot.'" style="background:#2AABEE;"></span>';
    if ($p === 'yandex') return '<span title="Yandex" class="'.$dot.'" style="background:#FF0000;"></span>';
    if ($p === 'apple') return '<span title="Apple" class="'.$dot.'" style="background:#000000;"></span>';
    if ($p === 'google') return '<span title="Google" class="'.$dot.'" style="background:#4285F4;"></span>';
    return '<span class="'.$dot.'" style="background:#9CA3AF;"></span>';
    };
    @endphp
	
	
	
    <div class="container">
        <div class="row">
			<div class="col-lg-4 col-xl-3 order-2 d-none d-lg-block">
				<div class="sticky">
					<div class="card-ramka">
						@include('profile._menu', [
						'menuUser'   => $user,
						'activeMenu' => 'profile',
						])
					</div>
				</div>
			</div>
			<div class="col-lg-8 col-xl-9 order-1">
				<div class="ramka pb-2">  
					{{-- Анкета игрока --}}
					
					<h2 class="-mt-05">{{ __('profile.sec_personal') }}</h2>
					<div class="row mb-0">
						<div class="col-sm-6 pb-0">
							<ul class="list mb-0">	
								<li><span class="b-600">{{ __('profile.pers_last_name') }}</span> {{ $u->last_name ?? '—' }}</li>
								<li><span class="b-600">{{ __('profile.pers_first_name') }}</span> {{ $u->first_name ?? '—' }}</li>
								<li><span class="b-600">{{ __('profile.pers_patronymic') }}</span> {{ $u->patronymic ?? '—' }}</li>
								<li><span class="b-600">{{ __('profile.pers_phone') }}</span> {{ $u->phone ?? '—' }}</li>
							</ul>	
						</div>	
						<div class="col-sm-6 pb-0">
							<ul class="list mb-0">	
								<li>
									<span class="b-600">{{ __('profile.pers_gender') }}</span>		
									@if($u->gender === 'm') {{ __('profile.pers_gender_m') }}
									@elseif($u->gender === 'f') {{ __('profile.pers_gender_f') }}
									@else — @endif
									
								</li>
								<li>
									<span class="b-600">{{ __('profile.pers_height') }}</span>			
									
									{{ !empty($u->height_cm) ? ($u->height_cm.' '.__('profile.pers_height_unit')) : '—' }}
									
								</li>
								<li>
									<span class="b-600">{{ __('profile.pers_city') }}</span>		
									
									@if($u->city)
									{{ $u->city->name }}@if($u->city->region) ({{ $u->city->region }})@endif
									@else
									—
									@endif
									
								</li>
								<li><span class="b-600">{{ __('profile.pers_birth') }}</span> {{ $birth }} 					@if(!is_null($age))
									{{ __('profile.pers_age', ['age' => $age]) }}
								@endif</li>
							</ul>								
						</div>
					</div>
					
				</div>
				
				
				<div class="ramka">  	
					<h2 class="-mt-05">{{ __('profile.sec_skills') }}</h2>
					<div class="row">
						<div class="col-md-6 col-lg-12 col-xl-6">
							<div class="card">
								<p class="b-600 mb-1">{{ __('profile.skill_classic') }}</p>
								
								<div class="level-wrap">
									<div class="level-levelmark levelmark level-{{ $u->classic_level ?? '—' }}">
										<div class="f-11">{{ __('profile.skill_level') }} </div>
										<div class="f-22 l-13">{{ $u->classic_level ?? '—' }}</div>
										<div class="f-11">{{ level_name($u->classic_level) ?? '—' }}</div>
									</div>	
									<div class="level-level">	
										<ul class="list">
											<li>
												<span class="b-600">{{ __('profile.skill_role') }}</span><br>
												
												@if($classicPrimary)
												{{ $posMap[$classicPrimary] ?? $classicPrimary }}
												@if(!empty($classicExtras))
												</li><li><span class="b-600">{{ __('profile.skill_role_extra') }}</span><br>{{ collect($classicExtras)->map(fn($p) => $posMap[$p] ?? $p)->join(', ') }}
												@endif
												@else
												—
												@endif
											</li>
										</ul>
									</div>
								</div>
							</div>
						</div>
						<div class="col-md-6 col-lg-12 col-xl-6">
							<div class="card">
								<p class="b-600 mb-1">{{ __('profile.skill_beach') }}</p>
								
								
								<div class="level-wrap">
									<div class="level-levelmark levelmark level-{{ $u->beach_level ?? '—' }}">
										<div class="f-11">{{ __('profile.skill_level') }} </div>
										<div class="f-22 l-13">{{ $u->beach_level ?? '—' }}</div>
										<div class="f-11">{{ level_name($u->beach_level) ?? '—' }}</div>
									</div>	
									<div class="level-level">	
										<ul class="list">
											<li>
												<span class="b-600">{{ __('profile.skill_zone') }}</span><br>
												
												
												@if(!empty($u->beach_universal))
												{{ __('profile.skill_universal') }}
												@elseif(!is_null($beachPrimary))
												{{ __('profile.skill_zone_main') }} {{ $beachPrimary }}
												@if(!empty($beachExtras))
												{{ __('profile.skill_zone_extra') }} {{ collect($beachExtras)->join(', ') }}
												@endif
												@else
												—
												@endif
												
											</li>
										</ul>
									</div>
								</div>								
							</div>
						</div>
					</div>
					
					
					
				</div>	
				@php
    $providerCount = (int)!empty($u?->telegram_id) + (int)!empty($u?->vk_id) + (int)!empty($u?->yandex_id) + (int)!empty($u?->apple_id) + (int)!empty($u?->google_id);
@endphp

@if(session('show_providers_hint') || $providerCount < 4)
<div class="ramka" id="providers-hint">
    <div class="d-flex fvc gap-2">
        <span style="font-size:2.4rem">🔐</span>
        <div>
            <div class="b-600 mb-05">
                @if($providerCount === 1)
                    {{ __('profile.phint_only_one') }}
                @elseif($providerCount === 2)
                    {{ __('profile.phint_two_of_three') }}
                @endif
            </div>
            <p>
                {{ __('profile.phint_lead') }}
                <a href="#providers" class="cd b-600">{{ __('profile.phint_link') }}</a>
            </p>
        </div>
    </div>
</div>
@endif

<div class="ramka" id="providers">  	
					
                    {{-- Привязка провайдеров --}}
					
					<h2 class="-mt-05">{{ __('profile.sec_providers') }}</h2>
					<p>{{ __('profile.providers_lead') }}</p>
					
					<p>
                        <span class="text-muted">{{ __('profile.providers_current') }}</span>
                        {!! $providerIcon($provider) !!}
					</p>
					
					@if($allLinked)
					<p>{{ __('profile.providers_all_linked') }}</p>
					@else
					<p>{{ __('profile.providers_how') }}</p>
					<ul class="list">
						<li>{{ __('profile.providers_step_1') }}</li>
						<li>{{ __('profile.providers_step_2') }}</li>
						<li>{{ __('profile.providers_step_3') }}</li>
					</ul>
					@endif					
					
					
					<div class="mt-2"> 
						
						<div class="row provider-cards fc">
							{{-- VK card --}}
							<div class="col-6 col-md-3 col-lg-6 col-xl-4">
								<div class="card">
									<div class="provider-card__header">
										<span class="provider-card__icon icon-vk"></span>
										<span class="provider-card__title">ВКонтакте</span>
									</div>
									
									<div class="provider-card__status">
										{!! $badge($hasVk) !!}
									</div>
									
									<div class="provider-card__actions">
										@if($hasVk)
										@if($canUnlink)
										<form method="POST" 
										action="{{ route('account.unlink.vk') }}" 
										onsubmit="return confirm('{{ __('profile.unlink_confirm_vk') }}');">
											@csrf
											{{-- btn-danger --}}
											<button type="submit" class="btn-alert w-100 btn btn-small btn-secondary"
											data-title="{{ __('profile.unlink_confirm_vk') }}"
											data-icon="warning"
											data-confirm-text="{{ __('profile.unlink_confirm_yes') }}"
											data-cancel-text="{{ __('profile.unlink_confirm_no') }}">{{ __('profile.providers_unlink_btn') }}</button>
										</form>
										@else
										<button class="w-100 btn btn-small btn-secondary" disabled>{{ __('profile.providers_cant_unlink') }}</button>
										@endif
										@else
										<a href="{{ $vkLinkUrl }}" class="w-100 btn btn-small">{{ __('profile.providers_link_btn') }}</a>
										@endif
									</div>
								</div>
							</div>
							{{-- Yandex card --}}
							<div class="col-6 col-md-3 col-lg-6 col-xl-4">
								<div class="card">
									<div class="provider-card__header">
										<span class="provider-card__icon icon-yandex"></span>
										<span class="provider-card__title">Yandex</span>
									</div>
									
									<div class="provider-card__status">
										{!! $badge($hasYa) !!}
									</div>
									
									<div class="provider-card__actions">
										@if($hasYa)
										@if($canUnlink)
										<form method="POST" 
										action="{{ route('account.unlink.yandex') }}" 
										onsubmit="return confirm('{{ __('profile.unlink_confirm_yandex') }}');">
											@csrf
											{{-- btn-danger --}}
											<button type="submit" class="w-100 btn btn-alert btn-small btn-secondary"
											data-title="{{ __('profile.unlink_confirm_yandex') }}"
											data-icon="warning"
											data-confirm-text="{{ __('profile.unlink_confirm_yes') }}"
											data-cancel-text="{{ __('profile.unlink_confirm_no') }}">{{ __('profile.providers_unlink_btn') }}</button>
										</form>
										@else
										<button class="w-100 btn btn-small btn-secondary" disabled>{{ __('profile.providers_cant_unlink') }}</button>
										@endif
										@else
										<a href="{{ $yandexLinkUrl }}" class="w-100 btn btn-small">{{ __('profile.providers_link_btn') }}</a>
										@endif
									</div>
								</div>
							</div>
							{{-- Telegram card --}}
							<div class="col-6 col-md-3 col-lg-6 col-xl-4">
								<div class="card">
									<div class="provider-card__header">
										<span class="provider-card__icon icon-tg"></span>
										<span class="provider-card__title">Telegram</span>
									</div>
									
									<div class="provider-card__status">
										{!! $badge($hasTg) !!}
									</div>
									
									<div class="provider-card__actions">
										@if($hasTg)
										@if($canUnlink)
										<form method="POST" 
										action="{{ route('account.unlink.telegram') }}" 
										onsubmit="return confirm('{{ __('profile.unlink_confirm_telegram') }}');">
											@csrf
											{{-- btn-danger --}}
											<button type="submit" class="btn-alert w-100 btn btn-small btn-secondary"
											data-title="{{ __('profile.unlink_confirm_telegram') }}"
											data-icon="warning"
											data-confirm-text="{{ __('profile.unlink_confirm_yes') }}"
											data-cancel-text="{{ __('profile.unlink_confirm_no') }}">{{ __('profile.providers_unlink_btn') }}</button>
										</form>
										@else
										<button class="w-100 btn btn-small btn-secondary" disabled>{{ __('profile.providers_cant_unlink') }}</button>
										@endif
										@else
										@if(empty($tgBotUsername))
										<div class="provider-error">
											{{ __('profile.tg_setup_error') }}
										</div>
										<button class="w-100 btn btn-disabled btn-small" disabled>
											{{ __('profile.providers_link_btn') }}
										</button>
										@else
										<a href="{{ route('auth.telegram.redirect', ['return' => url()->current()]) }}"
										   class="w-100 btn btn-small btn-tg">
											{{ __('profile.tg_button') }}
										</a>
										@endif
										@endif
									</div>
								</div>
							</div>
							@unless(str_contains(request()->userAgent() ?? '', 'Android'))
							{{-- Apple card --}}
							<div class="col-6 col-md-3 col-lg-6 col-xl-4">
								<div class="card">
									<div class="provider-card__header">
										<span class="provider-card__icon icon-apple"></span>
										<span class="provider-card__title">Apple</span>
									</div>

									<div class="provider-card__status">
										{!! $badge($hasApple) !!}
									</div>

									<div class="provider-card__actions">
										@if($hasApple)
										@if($canUnlink)
										<form method="POST"
										action="{{ route('account.unlink.apple') }}"
										onsubmit="return confirm('{{ __('profile.unlink_confirm_apple') }}');">
											@csrf
											<button type="submit" class="btn-alert w-100 btn btn-small btn-secondary"
											data-title="{{ __('profile.unlink_confirm_apple') }}"
											data-icon="warning"
											data-confirm-text="{{ __('profile.unlink_confirm_yes') }}"
											data-cancel-text="{{ __('profile.unlink_confirm_no') }}">{{ __('profile.providers_unlink_btn') }}</button>
										</form>
										@else
										<button class="w-100 btn btn-small btn-secondary" disabled>{{ __('profile.providers_cant_unlink') }}</button>
										@endif
										@else
										<a href="{{ route('auth.apple.redirect', ['link' => 1]) }}" class="w-100 btn btn-small">{{ __('profile.providers_link_btn') }}</a>
										@endif
									</div>
								</div>
							</div>
							@endunless
							{{-- Google card --}}
							<div class="col-6 col-md-3 col-lg-6 col-xl-4">
								<div class="card">
									<div class="provider-card__header">
										<span class="provider-card__icon" style="background: linear-gradient(90deg, #4285F4 0%, #EA4335 30%, #FBBC05 60%, #34A853 100%);">
											<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:inline-block;vertical-align:middle">
												<path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#fff"/>
												<path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#fff"/>
												<path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#fff"/>
												<path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#fff"/>
											</svg>
										</span>
										<span class="provider-card__title">Google</span>
									</div>

									<div class="provider-card__status">
										{!! $badge($hasGoogle) !!}
									</div>

									<div class="provider-card__actions">
										@if($hasGoogle)
										@if($canUnlink)
										<form method="POST"
										action="{{ route('account.unlink.google') }}"
										onsubmit="return confirm('{{ __('profile.unlink_confirm_google') }}');">
											@csrf
											<button type="submit" class="btn-alert w-100 btn btn-small btn-secondary"
											data-title="{{ __('profile.unlink_confirm_google') }}"
											data-icon="warning"
											data-confirm-text="{{ __('profile.unlink_confirm_yes') }}"
											data-cancel-text="{{ __('profile.unlink_confirm_no') }}">{{ __('profile.providers_unlink_btn') }}</button>
										</form>
										@else
										<button class="w-100 btn btn-small btn-secondary" disabled>{{ __('profile.providers_cant_unlink') }}</button>
										@endif
										@else
										<a href="{{ $googleLinkUrl }}" class="w-100 btn btn-small">{{ __('profile.providers_link_btn') }}</a>
										@endif
									</div>
								</div>
							</div>
							{{-- MAX card (placeholder) --}}
							<div class="col-6 col-md-3 col-lg-6 col-xl-4">
								<div class="card">
									<div class="provider-card__header">
										<span class="provider-card__icon icon-max"></span>
										<span class="provider-card__title">MAX</span>
									</div>
									
									<div class="provider-card__status">
										<span class="badge badge-muted"></span>
									</div>
									
									<div class="provider-card__actions">
										<button class="btn btn-disabled w-100 btn-small" disabled>
											{{ __('profile.max_in_dev') }}
										</button>
									</div>
								</div>
							</div>
						</div>						
					</div>
					
					
					@if(($hasTg || $hasVk || $hasYa) && !$canUnlink)
					<p class="mt-2">{{ __('profile.providers_only_left') }}</p>
					@endif
					
					
				</div>	
				
				<div class="ramka is-app-only" style="display:none">
					<h2 class="-mt-05">{{ __('profile.sec_quick_login') }}</h2>
					<p>{{ __('profile.quick_login_lead') }}</p>
					<div id="biometric-status" class="mb-2"></div>
					<button id="biometric-disable-btn" class="btn btn-secondary btn-small" style="display:none">
						{{ __('profile.face_disable_btn') }}
					</button>
					<button id="biometric-enable-btn" class="btn btn-small" style="display:none">
						{{ __('profile.face_enable_btn') }}
					</button>
				</div>

				<div class="ramka">
                    <h2 class="-mt-05">{{ __('profile.sec_notifications') }}</h2>
					
                    <p class="mb-15">
                        {{ __('profile.notif_lead') }}
					</p>
					
                    <div class="row provider-cards">
                        {{-- Telegram --}}
                        <div class="col-md-4">
                            <div class="card">
                                <div class="provider-card__header">
                                    <span class="provider-card__icon icon-tg"></span>
                                    <span class="provider-card__title">{{ __('profile.notif_tg_title') }}</span>
								</div>
								
                                @if($hasTelegramNotify)
								<div class="provider-card__status">
									<span class="badge badge-success"></span>
								</div>
								
								<ul class="list">
									<li>{!! __('profile.notif_tg_on') !!}</li>
								</ul>
								
								<form method="POST" action="{{ route('profile.telegram.disconnect') }}" class="mt-3">
									@csrf
									<button
									type="submit"
									class="w-100 btn btn-small btn-secondary btn-alert"
									data-title="{{ __('profile.notif_tg_disconnect_title') }}"
									data-icon="warning"
									data-confirm-text="{{ __('profile.notif_disconnect_yes') }}"
									data-cancel-text="{{ __('profile.unlink_confirm_no') }}"
									>{{ __('profile.notif_disable_btn') }}</button>
								</form>
                                @else
								<div class="provider-card__status">
									<span class="badge badge-muted"></span>
								</div>
								
								<ul class="list f-16">
									<li>{!! __('profile.notif_tg_offer') !!}</li>
								</ul>
								
								<button type="button" id="connect-telegram-btn" class="w-100 btn btn-small">
									{{ __('profile.notif_tg_connect') }}
								</button>
								
								<div id="connect-telegram-result" class="mt-1 hidden">
									<ul class="list f-16">
										<li>{{ __('profile.notif_tg_step_1') }}</li>
										<li>{{ __('profile.notif_tg_step_2') }}</li>
										<li>{!! __('profile.notif_tg_step_3') !!}</li>
										<li>{{ __('profile.notif_tg_step_4') }}</li>
									</ul>
									
									<a
									style="word-break: break-all"
									id="connect-telegram-link"
									href="#"
									target="_blank"
									rel="noopener"
									class="f-16 b-600"
									></a>
								</div>
                                @endif
							</div>
						</div>
						
						
                        {{-- MAX --}}
                        <div class="col-md-4">
                            <div class="card">
                                <div class="provider-card__header">
                                    <span class="provider-card__icon icon-max"></span>
                                    <span class="provider-card__title">{{ __('profile.notif_max_title') }}</span>
								</div>
								
                                @if($hasMaxNotify)
								<div class="provider-card__status">
									<span class="badge badge-success"></span>
								</div>
								
								<ul class="list">
									<li>{!! __('profile.notif_max_on') !!}</li>
								</ul>
								
								<form method="POST" action="{{ route('profile.max.disconnect') }}" class="mt-3">
									@csrf
									<button type="submit" class="w-100 btn btn-small btn-secondary btn-alert" data-title="{{ __('profile.notif_max_disconnect_title') }}" data-icon="warning" data-confirm-text="{{ __('profile.notif_disconnect_yes') }}" data-cancel-text="{{ __('profile.unlink_confirm_no') }}">{{ __('profile.notif_disable_btn') }}</button>
								</form>
                                @else
								<div class="provider-card__status">
									<span class="badge badge-muted"></span>
								</div>
								
								<ul class="list f-16">
									<li>{!! __('profile.notif_max_offer') !!}</li>
								</ul>
								
								<button type="button" id="connect-max-btn" class="w-100 btn btn-small">
									{{ __('profile.notif_max_connect') }}
								</button>
								
								<div id="connect-max-result" class="mt-1 hidden">
									<ul class="list f-16">
										<li>{{ __('profile.notif_max_step_1') }}</li>
										<li>{{ __('profile.notif_max_step_2') }}</li>
										<li>{!! __('profile.notif_max_step_3') !!}</li>
										<li>{{ __('profile.notif_max_step_4') }}</li>
									</ul>
									
									<a
									style="word-break: break-all"
									id="connect-max-link"
									href="#"
									target="_blank"
									rel="noopener"
									class="f-16 b-600"
									></a>
								</div>
                                @endif
							</div>
						</div>
						
                        {{-- VK --}}
                        <div class="col-md-4">
                            <div class="card">
                                <div class="provider-card__header">
                                    <span class="provider-card__icon icon-vk"></span>
                                    <span class="provider-card__title">{{ __('profile.notif_vk_title') }}</span>
								</div>
								
                                @if($hasVkNotify)
								<div class="provider-card__status">
									<span class="badge badge-success"></span>
								</div>
								
								<ul class="list">
									<li>{!! __('profile.notif_vk_on') !!}</li>
								</ul>
								
								<form method="POST" action="{{ route('profile.vk.disconnect') }}" class="mt-1">
									@csrf
									<button
									type="submit"
									class="w-100 btn btn-small btn-secondary btn-alert"
									data-title="{{ __('profile.notif_vk_disconnect_title') }}"
									data-icon="warning"
									data-confirm-text="{{ __('profile.notif_disconnect_yes') }}"
									data-cancel-text="{{ __('profile.unlink_confirm_no') }}"
									>{{ __('profile.notif_disable_btn') }}</button>
								</form>
                                @else
								<div class="provider-card__status">
									<span class="badge badge-muted"></span>
								</div>
                                
								<ul class="list f-16">
									<li>{!! __('profile.notif_vk_offer') !!}</li>
								</ul>
								
								<button type="button" id="connect-vk-btn" class="w-100 btn btn-small">
									{{ __('profile.notif_vk_connect') }}
								</button>					
                                
								<div id="connect-vk-result" class="mt-1 hidden">
									<ul class="list f-16">
										<li>{{ __('profile.notif_vk_step_1') }}</li>
										<li>{{ __('profile.notif_vk_step_2') }}</li>
										<li>{{ __('profile.notif_vk_step_3') }}</li>
										<li>{{ __('profile.notif_vk_step_4') }}</li>
										<li>{{ __('profile.notif_vk_step_5') }}</li>
									</ul>
									
									
									
									<a
									style="word-break: break-all"
									id="connect-vk-link"
									href="#"
									target="_blank"
									rel="noopener"
									class="f-16 b-600"
									></a>
									
									<div id="connect-vk-command-box" class="mt-1 hidden">
										<div class="f-16 text-muted">{{ __('profile.notif_vk_command_label') }}</div>
										<div style="overflow-wrap:break-word" id="connect-vk-command" class="f-16 b-600"></div>
										<div id="connect-vk-copy-hint" class="f-14 text-muted mt-05 hidden"></div>
									</div>
								</div>
                                @endif
							</div>
						</div>
						
						
						
					</div>

					@if(($hasTelegramNotify || $hasVkNotify || $hasMaxNotify) && in_array((string)($u->role ?? 'user'), ['admin', 'organizer', 'staff'], true))
					<div class="mt-2 pt-2 form" style="border-top:1px solid var(--border-color, #e0e0e0)">
						<label>{{ __('profile.notif_org_label') }}</label>
						<p>
							{{ __('profile.notif_org_lead') }}
						</p>
						<form method="POST" action="{{ route('profile.notification_channels.settings') }}" class="d-flex align-items-center gap-1">
							@csrf
							<label class="checkbox-item">
								<input type="hidden" name="notify_player_registrations" value="0">
								<input type="checkbox" name="notify_player_registrations" value="1"
									   onchange="this.form.submit()"
									   {{ $notifyPlayerRegistrations ? 'checked' : '' }}>
								<div class="custom-checkbox"></div>	   
								<span>{{ $notifyPlayerRegistrations ? __('profile.notif_org_on') : __('profile.notif_org_off') }}</span>
							</label>
						</form>
					</div>
					@endif
				</div>
				
				@php
                $canManageNotificationChannels = in_array((string)($u->role ?? 'user'), ['admin', 'organizer', 'staff'], true);
                
                $notificationChannels = collect();
                
                if (
				$canManageNotificationChannels &&
				\Illuminate\Support\Facades\Schema::hasTable('user_notification_channels')
                ) {
				$notificationChannels = \Illuminate\Support\Facades\DB::table('user_notification_channels')
				->where('user_id', (int) $u->id)
				->orderBy('created_at')
				->get([
				'id',
				'title',
				'platform',
				'chat_id',
				'is_verified',
				]);
                }
                @endphp
				@if($canManageNotificationChannels)
                <div class="ramka">
                    <h2 class="-mt-05">{{ __('profile.sec_channels') }}</h2>
					
                    @if($notificationChannels->isNotEmpty())
					<p><b>{{ __('profile.channels_list_label') }}</b></p>
					<ol class="list">
						@foreach($notificationChannels as $channel)
						<li>
							{{ $channel->title ?: __('profile.channels_default_n', ['id' => $channel->id]) }}
							@if(!empty($channel->platform))
							<span class="text-muted">({{ strtoupper($channel->platform) }})</span>
							@endif
							@if(!(bool) $channel->is_verified)
							<span class="text-muted">{{ __('profile.channels_unverified') }}</span>
							@endif
						</li>
						@endforeach
					</ol>
                    @endif
					
                    <p>
                        {{ __('profile.channels_lead') }}
					</p>
					
                    <div class="mt-2 m-center">
                        <a href="{{ route('profile.notification_channels') }}" class="btn">
                            {{ __('profile.channels_manage_btn') }}
						</a>
					</div>
				</div>
				@endif
				
				
				
				@if(auth()->check() && (auth()->user()->isOrganizer() || auth()->user()->isAdmin()))
				@php $paymentSettings = \App\Models\PaymentSetting::where('organizer_id', auth()->id())->first(); @endphp
				<div class="ramka">
					<h2 class="-mt-05">{{ __('profile.sec_payment') }}</h2>
					
					{{-- Выбор назначения --}}
					<div class="card mb-2">
						<div class="f-15 b-600 mb-1">{{ __('profile.pay_setup_title') }}</div>
						<div class="tabs-content">
							<div class="tabs">
								<div class="tab active" data-tab="pay-events">{{ __('profile.pay_tab_events') }}</div>
								@if(auth()->user()->isAdmin())
								<div class="tab" data-tab="pay-premium">{{ __('profile.pay_tab_premium') }}</div>
								@endif
								<div class="tab-highlight"></div>
							</div>
							<div class="tab-panes">
								
								{{-- Вкладка: Мероприятия --}}
								<div class="tab-pane active" id="pay-events">
									<div class="mt-1">
										@if($paymentSettings?->yoomoney_verified)
										<div class="f-16 cs b-600">{{ __('profile.pay_yoo_ok') }}</div>
										<div class="f-15 mt-05" style="opacity:.6">{{ __('profile.pay_shop_id') }} {{ $paymentSettings->yoomoney_shop_id }}</div>
										@elseif($paymentSettings && ($paymentSettings->tbank_link || $paymentSettings->sber_link))
										<div class="f-16 cd b-600">{{ __('profile.pay_link_ok') }}</div>
										@if($paymentSettings->tbank_link)
										<div class="f-15 mt-05" style="opacity:.6">{{ __('profile.pay_tbank_label') }} {{ $paymentSettings->tbank_link }}</div>
										@endif
										@if($paymentSettings->sber_link)
										<div class="f-15 mt-05" style="opacity:.6">{{ __('profile.pay_sber_label') }} {{ $paymentSettings->sber_link }}</div>
										@endif
										@else
										<div class="f-15" style="opacity:.5">{{ __('profile.pay_not_setup') }}</div>
										@endif
										<div class="mt-2">
											<a href="{{ route('profile.payment_settings') }}" class="btn btn-secondary">{{ __('profile.pay_setup_btn') }}</a>
										</div>
									</div>
								</div>
								
								{{-- Вкладка: Premium и реклама (только Admin) --}}
								@if(auth()->user()->isAdmin())
								<div class="tab-pane" id="pay-premium">
									<div class="mt-1">
										@php
										$premiumPayment = \App\Models\PlatformPaymentSetting::first();
										@endphp
										@if($premiumPayment)
										<div class="f-16 b-600 mb-1">{{ __('profile.pay_method_current') }}
											<span class="cd">{{ match($premiumPayment->method) {
												'tbank_link' => __('profile.pay_method_tbank'),
												'sber_link'  => __('profile.pay_method_sber'),
												'yoomoney'   => __('profile.pay_method_yoo'),
												default       => $premiumPayment->method,
											} }}</span>
										</div>
										@else
										<div class="f-15" style="opacity:.5">{{ __('profile.pay_premium_not_setup') }}</div>
										@endif
										<div class="mt-2">
											<a href="{{ route('admin.platform_payment_settings') }}" class="btn btn-secondary">{{ __('profile.pay_premium_setup_btn') }}</a>
										</div>
									</div>
								</div>
								@endif
								
							</div>
						</div>
					</div>
				</div>
				@endif
				
				
				{{-- ===== МОИ КОМАНДЫ ===== --}}
				@php $myUserTeams = \App\Models\UserTeam::where('user_id', auth()->id())->withCount('members')->orderByDesc('created_at')->limit(10)->get(); @endphp
				<div class="ramka">
					<div class="d-flex between fvc -mt-05 mb-2">
						<h2 class="mb-0">Мои команды</h2>
					</div>
					@if($myUserTeams->isNotEmpty())
					@foreach($myUserTeams as $ut)
					<div class="card mb-1 d-flex between fvc">
						<div>
							<div class="b-600">{{ $ut->name }}</div>
							<div class="f-14 mt-05" style="opacity:.6">
								{{ $ut->direction === 'beach' ? 'Пляжный' : 'Классический' }}
								@if($ut->subtype) · {{ $ut->subtype }}@endif
								· {{ $ut->members_count }} {{ $ut->members_count === 1 ? 'игрок' : ($ut->members_count < 5 ? 'игрока' : 'игроков') }}
							</div>
						</div>
						<div class="d-flex gap-1">
							<a href="{{ route('user.teams.edit', $ut->id) }}" class="btn btn-small btn-secondary">Изменить</a>
							<form method="POST" action="{{ route('user.teams.destroy', $ut->id) }}"
								onsubmit="return confirm('Удалить команду «{{ addslashes($ut->name) }}»?')">
								@csrf @method('DELETE')
								<button class="btn btn-small btn-danger">✕</button>
							</form>
						</div>
					</div>
					@endforeach
					@else
					<p class="f-15" style="opacity:.7">Сохранённых команд нет. Создайте команду при записи на турнир и сохраните её в профиль.</p>
					@endif
				</div>

				@if(auth()->check() && (auth()->user()->isOrganizer() || auth()->user()->isAdmin()))
				@php
				$mySchools = \App\Models\VolleyballSchool::where('organizer_id', auth()->id())->get();
				$allSchools = auth()->user()->isAdmin()
				? \App\Models\VolleyballSchool::with('organizer:id,first_name,last_name')->orderBy('name')->get()
				: collect();
				@endphp
				<div class="ramka">
					<h2 class="-mt-05">{{ __('profile.sec_schools') }}</h2>
					
					@if(auth()->user()->isAdmin() && $allSchools->isNotEmpty())
					<div class="b-600 mb-1 f-16">{{ __('profile.schools_admin_all') }}</div>
					@foreach($allSchools as $s)
					<div class="d-flex between fvc mb-1 card">
						<div class="d-flex fvc gap-2">
							@php $sLogo = $s->getFirstMediaUrl('logo', 'thumb'); @endphp
							@if($sLogo)<img src="{{ $sLogo }}" alt="" style="width:3.2rem;height:3.2rem;border-radius:50%;object-fit:cover">@endif
							<div>
								<div class="b-600">{{ $s->name }}</div>
								<div class="f-14" style="opacity:.6">{{ trim($s->organizer?->first_name . ' ' . $s->organizer?->last_name) }} · {{ $s->is_published ? '✅' : '⏸' }}</div>
							</div>
						</div>
						<div class="d-flex gap-1">
							<a href="{{ route('volleyball_school.show', $s->slug) }}" class="btn btn-small btn-secondary">👁</a>
							<a href="{{ route('volleyball_school.edit') }}?id={{ $s->id }}" class="btn btn-small btn-secondary">✏️</a>
						</div>
					</div>
					@endforeach
					<div class="mt-2">
						<a href="{{ route('volleyball_school.create') }}" class="btn btn-secondary">{{ __('profile.schools_admin_create_btn') }}</a>
					</div>
					
					@elseif($mySchools->isNotEmpty())
					@php $s = $mySchools->first(); $sLogo = $s->getFirstMediaUrl('logo', 'thumb'); @endphp
					<div class="d-flex fvc gap-2 mb-2">
						@if($sLogo)<img src="{{ $sLogo }}" alt="logo" style="width:4rem;height:4rem;border-radius:50%;object-fit:cover">@endif
						<div>
							<div class="b-600">{{ $s->name }}</div>
							<div class="f-14 mt-05" style="opacity:.6">{{ $s->is_published ? __('profile.schools_published') : __('profile.schools_hidden') }}</div>
						</div>
					</div>
					<div class="d-flex gap-1">
						<a href="{{ route('volleyball_school.show', $s->slug) }}" class="btn btn-secondary">{{ __('profile.schools_open_btn') }}</a>
						<a href="{{ route('volleyball_school.edit') }}?id={{ $s->id }}" class="btn btn-secondary">{{ __('profile.schools_edit_btn') }}</a>
					</div>
					
					@else
					<p>{{ __('profile.schools_lead') }}</p>
					<a href="{{ route('volleyball_school.create') }}" class="btn">{{ __('profile.schools_create_btn') }}</a>
					@endif
				</div>
				@endif
				
				<div class="ramka">
					@php $activePremium = auth()->user()->activePremium(); @endphp
					<h2 class="-mt-05">{{ __('profile.sec_premium') }}</h2>
					<div class="card">
						@if($activePremium)
						<div class="d-flex between fvc">
							<div>
								<div class="f-18 b-600" style="color:#f5c842;">{{ __('profile.premium_active') }}</div>
								<div class="f-15 mt-05" style="opacity:.6;">
									{{ __('profile.premium_until', ['date' => $activePremium->expires_at->format('d.m.Y')]) }}
									· {{ match($activePremium->plan) {
									'trial'   => __('profile.premium_plan_trial'),
									'month'   => __('profile.premium_plan_month'),
									'quarter' => __('profile.premium_plan_quarter'),
									'year'    => __('profile.premium_plan_year'),
									} }}
								</div>
							</div>
							<div class="d-flex gap-1">
								<a href="{{ route('premium.settings') }}" class="btn btn-secondary">{{ __('profile.premium_settings_btn') }}</a>
								<a href="{{ route('premium.index') }}" class="btn btn-secondary">{{ __('profile.premium_renew_btn') }}</a>
							</div>
						</div>
						@else
						<div class="row row2">
							<div class="col-md-8">
								<div class="f-17 b-600 mb-1">{{ __('profile.premium_offer_title') }}</div>
								<ul class="list f-15">
									<li>{{ __('profile.premium_offer_li_1') }}</li>
									<li>{{ __('profile.premium_offer_li_2') }}</li>
									<li>{{ __('profile.premium_offer_li_3') }}</li>
									<li>{{ __('profile.premium_offer_li_4') }}</li>
									<li>{{ __('profile.premium_offer_li_5') }}</li>
								</ul>
							</div>
							<div class="col-md-4 text-center" style="display:flex;flex-direction:column;justify-content:center;gap:1rem;">
								<a href="{{ route('premium.index') }}" class="btn">{{ __('profile.premium_subscribe_btn') }}</a>
								<div class="f-14" style="opacity:.5;">{{ __('profile.premium_price_from') }}</div>
							</div>
						</div>
						@endif
					</div>
				</div>
				
				<div class="ramka">  	
					
					{{-- ✅ Приватность --}}
					
					<h2 class="-mt-05">{{ __('profile.sec_privacy') }}</h2>
					
					<form class="form" method="POST" action="{{ route('profile.contact_privacy.update') }}">
						@csrf
						
						
						
						<label for="allow_user_contact" class="checkbox-item">
							<input type="hidden" name="allow_user_contact" value="0">
							<input type="checkbox" 
							class="form-check-input" 
							name="allow_user_contact" 
							value="1"
							id="allow_user_contact"
							@checked((bool)($u->allow_user_contact ?? true))>
							
							<div class="custom-checkbox"></div>
							<span>{{ __('profile.privacy_allow_text') }}</span>
						</label>
						
						
						
						<p>{{ __('profile.privacy_lead') }}</p>									
						
						
						<div class="mt-2 m-center">
							<button type="submit" class="btn">
								{{ __('profile.btn_save') }}
							</button>
						</div>
						
					</form>
				</div>	
				
				
				
				{{-- ========================= ORGANIZER REQUEST ========================= --}}
				@auth
				@if (($user->role ?? 'user') === 'user')
				<div class="ramka form">  	
					<h2 class="-mt-05">{{ __('profile.sec_organizer_request') }}</h2>
					
					<p>{{ __('profile.org_request_lead') }}</p>
					
					@if (!empty($hasPendingOrganizerRequest))
					<div class="alert alert-info">{{ __('profile.org_request_pending') }}</div>
					@else
					<form method="POST" action="{{ route('organizer.request') }}">
						@csrf
						<div class="mb-1">
							<label>{{ __('profile.org_request_comment_label') }}</label>
							<textarea name="message" rows="3" placeholder="{{ __('profile.org_request_comment_ph') }}"></textarea>
						</div>
						<button type="submit" class="btn">{{ __('profile.org_request_submit') }}</button>
					</form>
					@endif
				</div>
				@endif
				@endauth				
				
				
				
				<div class="ramka" id="delete-account">

					<h2 class="mt-0">{{ __('profile.sec_delete') }}</h2>

					<p class="f-15" style="opacity:.8">{!! __('profile.delete_lead_html', ['n' => $deletionDelay]) !!}</p>

					<form method="POST" action="{{ route('account.delete.request') }}" id="form-delete-account" style="display:none">
						@csrf
					</form>

					<button type="button" class="btn btn-danger" id="btn-delete-account">
						{{ __('profile.delete_btn') }}
					</button>

					<button type="button" class="btn btn-warning" id="btn-cancel-deletion" style="display:none">
						{!! __('profile.delete_cancel_btn_html') !!}
					</button>

				</div>
			</div>
		</div>
	</div>	
	<script>
	(function () {
		var deleteBtn = document.getElementById('btn-delete-account');
		var cancelBtn = document.getElementById('btn-cancel-deletion');
		var countdownSpan = document.getElementById('countdown-seconds');
		var gracePeriodEl = document.getElementById('grace-period-display');
		var countdownTimer = null;
		var deletionDelay = parseInt(gracePeriodEl ? gracePeriodEl.textContent : '30', 10);

		if (!deleteBtn) return;

		deleteBtn.addEventListener('click', function () {
			swal({
				title: @json(__('profile.delete_swal_q_title')),
				text: @json(__('profile.delete_swal_q_text', ['n' => '_N_'])).replace('_N_', deletionDelay),
				icon: 'warning',
				dangerMode: true,
				buttons: {
					cancel: { text: @json(__('profile.delete_swal_cancel')), visible: true, closeModal: true },
					confirm: { text: @json(__('profile.delete_swal_confirm_q')), className: 'swal-button--danger' }
				}
			}).then(function (confirmed) {
				if (!confirmed) return;

				swal({
					title: @json(__('profile.delete_swal_w_title')),
					text: @json(__('profile.delete_swal_w_text')),
					dangerMode: true,
					content: {
						element: 'input',
						attributes: {
							placeholder: @json(__('profile.delete_confirm_word')),
							type: 'text',
							style: 'color:#333;border:1px solid #ccc;font-size:16px;padding:8px;width:100%;box-sizing:border-box;'
						}
					},
					buttons: {
						cancel: { text: @json(__('profile.delete_swal_cancel')), visible: true, closeModal: true },
						confirm: { text: @json(__('profile.delete_swal_confirm_btn')), className: 'swal-button--danger' }
					}
				}).then(function (value) {
					if (value === null) return;
					if (value !== @json(__('profile.delete_confirm_word'))) {
						swal({ title: @json(__('profile.delete_swal_wrong_title')), text: @json(__('profile.delete_swal_wrong_text')), icon: 'error', timer: 2000, buttons: false });
						return;
					}
					startCountdown();
				});
			});
		});

		cancelBtn.addEventListener('click', function () {
			if (countdownTimer) {
				clearInterval(countdownTimer);
				countdownTimer = null;
			}
			cancelBtn.style.display = 'none';
			deleteBtn.style.display = '';
			swal({ title: @json(__('profile.delete_swal_cancelled')), icon: 'info', timer: 1500, buttons: false });
		});

		function startCountdown() {
			var remaining = deletionDelay;
			deleteBtn.style.display = 'none';
			cancelBtn.style.display = '';
			countdownSpan.textContent = remaining;

			countdownTimer = setInterval(function () {
				remaining--;
				countdownSpan.textContent = remaining;
				if (remaining <= 0) {
					clearInterval(countdownTimer);
					countdownTimer = null;
					executeAccountDeletion();
				}
			}, 1000);
		}

		function executeAccountDeletion() {
			cancelBtn.style.display = 'none';

			jQuery.ajax({
				url: '/account/delete-request',
				method: 'POST',
				data: {
					_token: document.querySelector('meta[name="csrf-token"]').content
				},
				success: function() {
					document.cookie.split(';').forEach(function(c) {
						var name = c.split('=')[0].trim();
						document.cookie = name + '=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/';
						document.cookie = name + '=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/;domain=.volleyplay.club';
						document.cookie = name + '=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/;domain=volleyplay.club';
					});

					swal({
						title: @json(__('profile.delete_swal_done_title')),
						text: @json(__('profile.delete_swal_done_text')),
						icon: 'success',
						button: 'OK'
					}).then(function() {
						try { localStorage.clear(); } catch(e) {}
						try { sessionStorage.clear(); } catch(e) {}
						window.location.replace('/');
					});
				},
				error: function() {
					swal({ title: @json(__('profile.delete_swal_err_title')), text: @json(__('profile.delete_swal_err_text')), icon: 'error' });
					cancelBtn.style.display = 'none';
					deleteBtn.style.display = '';
				}
			});
		}
	})();
	</script>
	<script>
		document.addEventListener('DOMContentLoaded', function () {
			async function copyToClipboard(text) {
				if (!text) return false;
				
				if (navigator.clipboard && window.isSecureContext) {
					try {
						await navigator.clipboard.writeText(text);
						return true;
						} catch (e) {
						console.warn('Clipboard API copy failed', e);
					}
				}
				
				try {
					const textarea = document.createElement('textarea');
					textarea.value = text;
					textarea.setAttribute('readonly', '');
					textarea.style.position = 'fixed';
					textarea.style.left = '-9999px';
					textarea.style.top = '0';
					document.body.appendChild(textarea);
					textarea.focus();
					textarea.select();
					
					const ok = document.execCommand('copy');
					document.body.removeChild(textarea);
					return ok;
					} catch (e) {
					console.warn('Fallback copy failed', e);
					return false;
				}
			}
			
			function setupBindButton(buttonId, resultId, linkId, url, payload = {}, options = {}) {
				const button = document.getElementById(buttonId);
				if (!button) {
					console.warn('Bind button not found:', buttonId);
					return;
				}
				
				button.addEventListener('click', async function () {
					button.disabled = true;
					
					try {
						const res = await fetch(url, {
							method: 'POST',
							headers: {
								'X-CSRF-TOKEN': '{{ csrf_token() }}',
								'Accept': 'application/json',
								'Content-Type': 'application/json',
							},
							body: JSON.stringify(payload),
						});
						
						let data = null;
						try {
							data = await res.json();
							} catch (e) {
							console.error('Invalid JSON response', e);
							alert(@json(__('profile.bind_invalid_response')));
							return;
						}
						
						if (!res.ok || !data?.ok) {
							alert(data?.message || @json(__('profile.bind_link_error')));
							return;
						}
						
						const box = document.getElementById(resultId);
						const link = document.getElementById(linkId);
						
						if (link && data.link) {
							link.href = data.link;
							link.textContent = data.link;
						}
						
						if (options.commandBoxId && options.commandId) {
							const commandBox = document.getElementById(options.commandBoxId);
							const commandEl = document.getElementById(options.commandId);
							const copyHint = options.copyHintId
							? document.getElementById(options.copyHintId)
							: null;
							
							if (commandBox && commandEl && data.command) {
								commandEl.textContent = data.command;
								commandBox.classList.remove('hidden');
								
								const copied = await copyToClipboard(data.command);
								if (copied && copyHint) {
									copyHint.classList.remove('hidden');
								}
							}
						}
						
						if (box) {
							box.classList.remove('hidden');
						}
						
						if (options.autoOpenLink && data.link) {
							window.open(data.link, '_blank', 'noopener');
						}
						} catch (e) {
						console.error(e);
						alert(@json(__('profile.bind_request_error')));
						} finally {
						button.disabled = false;
					}
				});
			}
			
			setupBindButton(
			'connect-max-btn',
			'connect-max-result',
			'connect-max-link',
			'{{ route('profile.max.generate_link') }}',
			{ kind: 'personal' }
			);
			
			setupBindButton(
			'connect-telegram-btn',
			'connect-telegram-result',
			'connect-telegram-link',
			'{{ route('profile.telegram.generate_link') }}',
			{ kind: 'personal' }
			);
			
			setupBindButton(
			'connect-vk-btn',
			'connect-vk-result',
			'connect-vk-link',
			'{{ route('profile.vk.generate_link') }}',
			{ kind: 'personal' },
			{
				commandBoxId: 'connect-vk-command-box',
				commandId: 'connect-vk-command',
				copyHintId: 'connect-vk-copy-hint',
				autoOpenLink: false,
			}
			);
		});
	</script>

	<script>
	(function() {
		if (!navigator.userAgent.includes('VolleyPlayApp') || !window.Capacitor) return;

		document.querySelectorAll('.is-app-only').forEach(function(el) { el.style.display = ''; });

		var NativeBiometric = window.Capacitor.Plugins.NativeBiometric;
		if (!NativeBiometric) return;

		var statusEl     = document.getElementById('biometric-status');
		var disableBtn   = document.getElementById('biometric-disable-btn');
		var enableBtn    = document.getElementById('biometric-enable-btn');

		async function refreshStatus() {
			try {
				var avail = await NativeBiometric.isAvailable();
				if (!avail.isAvailable) {
					if (statusEl) statusEl.textContent = @json(__('profile.face_unavailable'));
					return;
				}
				try {
					var creds = await NativeBiometric.getCredentials({ server: 'volleyplay.club' });
					if (creds && creds.password) {
						if (statusEl) statusEl.textContent = @json(__('profile.face_enabled_status'));
						if (disableBtn) disableBtn.style.display = '';
						if (enableBtn) enableBtn.style.display = 'none';
						return;
					}
				} catch (e) { /* нет credentials */ }
				if (statusEl) statusEl.textContent = @json(__('profile.face_not_setup'));
				if (disableBtn) disableBtn.style.display = 'none';
				if (enableBtn) enableBtn.style.display = '';
			} catch (e) {
				if (statusEl) statusEl.textContent = @json(__('profile.face_status_error'));
			}
		}

		if (disableBtn) {
			disableBtn.addEventListener('click', async function() {
				disableBtn.disabled = true;
				try {
					await fetch('/api/biometric/revoke', {
						method: 'DELETE',
						headers: {
							'Accept': 'application/json',
							'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
						},
						credentials: 'same-origin'
					});
					await NativeBiometric.deleteCredentials({ server: 'volleyplay.club' });
					await refreshStatus();
				} catch (e) {
					alert(@json(__('profile.face_disable_error')));
				} finally {
					disableBtn.disabled = false;
				}
			});
		}

		if (enableBtn) {
			enableBtn.addEventListener('click', async function() {
				enableBtn.disabled = true;
				try {
					var avail = await NativeBiometric.isAvailable();
					if (!avail.isAvailable) { alert(@json(__('profile.face_unavailable_alert'))); return; }

					var token = crypto.randomUUID();
					var resp = await fetch('/api/biometric/register', {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'Accept': 'application/json',
							'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
						},
						credentials: 'same-origin',
						body: JSON.stringify({ biometric_token: token })
					});

					if (resp.ok) {
						await NativeBiometric.setCredentials({
							username: 'volleyplay_user',
							password: token,
							server: 'volleyplay.club'
						});
						await refreshStatus();
					}
				} catch (e) {
					alert(@json(__('profile.face_enable_error')));
				} finally {
					enableBtn.disabled = false;
				}
			});
		}

		refreshStatus();
	})();
	</script>
</x-voll-layout>