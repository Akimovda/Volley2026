{{-- resources/views/events/create.blade.php --}}
@php
$prefill = $prefill ?? [];
$volleyballConfig = config('volleyball');

$tournamentGameScheme = old('tournament_game_scheme', $prefill['tournament_game_scheme'] ?? '');
$tournamentTeamSizeMin = old('tournament_team_size_min', $prefill['tournament_team_size_min'] ?? '');
$tournamentReservePlayersMax = old('tournament_reserve_players_max', $prefill['tournament_reserve_players_max'] ?? '');
$tournamentTotalPlayersMax = old('tournament_total_players_max', $prefill['tournament_total_players_max'] ?? '');
$tournamentRequireLibero = old('tournament_require_libero', $prefill['tournament_require_libero'] ?? false);
$tournamentMaxRatingSum = old('tournament_max_rating_sum', $prefill['tournament_max_rating_sum'] ?? '');
$tournamentCaptainConfirmsMembers = old('tournament_captain_confirms_members', $prefill['tournament_captain_confirms_members'] ?? true);
$tournamentAutoSubmitWhenReady = old('tournament_auto_submit_when_ready',
'tournament_application_mode', $prefill['tournament_auto_submit_when_ready'] ?? false);
@endphp
@php
$registrationMode = old('registration_mode', $prefill['registration_mode'] ?? 'single');

$registrationModeLabels = [
'classic' => [
'single' => 'Одиночная запись игроков',
'team' => 'Командная запись',
],
'beach' => [
'single' => 'Одиночная запись игроков',
'mixed_group' => 'Групповая / смешанная запись',
],
];
@endphp
@php

$formats = [
'game' => 'Игра',
'training' => 'Тренировка',
'training_game' => 'Тренировка + Игра',
'coach_student' => 'Тренер + ученик (только пляж)',
'tournament' => 'Турнир',
'camp' => 'КЕМП',
];
// ✅ Timezones groups приходят из контроллера: $tzGroups + $tzDefault
$timezoneGroups  = $tzGroups ?? (array) config('event_timezones.groups', []);
$timezoneDefault = !empty($prefill['timezone'])
? (string) $prefill['timezone']
: (string) ($tzDefault ?? 'Europe/Moscow');

$currentTimezone = (string) old('timezone', $timezoneDefault);

$isAdmin = (auth()->user()?->role ?? null) === 'admin';

$step1Fields = [
'organizer_id',
'title','direction','format','registration_mode',
'tournament_game_scheme',
'tournament_team_size_min',
'tournament_reserve_players_max',
'tournament_total_players_max',
'tournament_require_libero',
'tournament_max_rating_sum',
'tournament_captain_confirms_members',
'tournament_auto_submit_when_ready',
'tournament_application_mode',
// ✅ trainer
'trainer_user_ids',      // новое
'trainer_user_id',       // legacy оставить
'trainer_user_label',    // чтобы ошибки лейбла тоже попадали в шаг 1 (если будут)

'game_subtype','game_min_players','game_max_players',
'game_libero_mode',
'game_gender_policy','game_gender_limited_side','game_gender_limited_max','game_gender_limited_positions',
'classic_level_min','classic_level_max',
'beach_level_min','beach_level_max',
'allow_registration',
'age_policy','is_snow',
];
// ✅ Step 2 fields (including registration timings)
$step2Fields = [
'timezone','starts_at_local','ends_at_local','location_id',
'is_recurring','recurrence_type','recurrence_interval','recurrence_months','recurrence_rule',
'reg_starts_days_before','reg_ends_minutes_before','cancel_lock_minutes_before',
];

$step3Fields = [
'is_private',
'is_paid',
'price_amount',
'price_currency',
'requires_personal_data',
'remind_registration_enabled',
'remind_registration_minutes_before',
'show_participants',
'cover_upload',
'cover_media_id',
'description_html',
];


// --- wizard initial step (server-side) ---
$initialStep = (int) request()->query('step', session('wizard_initial_step', 0));
// helper: ловим и обычные ошибки, и ошибки массива вида field.0
$hasErr = function (string $field) use ($errors): bool {
return $errors->has($field) || $errors->has($field . '.*');
};

if ($initialStep < 1 || $initialStep > 3) {
	$initialStep = 1;
	
	if ($errors->any()) {
	foreach ($step3Fields as $f) { if ($hasErr($f)) { $initialStep = 3; break; } }
	
	if ($initialStep === 1) {
	foreach ($step2Fields as $f) { if ($hasErr($f)) { $initialStep = 2; break; } }
	}
	} else {
	// fallback по old()
	if (
	old('timezone') || old('starts_at_local') || old('location_id') ||
	old('is_recurring') || old('recurrence_type') || old('recurrence_interval') || old('recurrence_months') ||
	old('reg_starts_days_before') || old('reg_ends_minutes_before') || old('cancel_lock_minutes_before')
	) {
	$initialStep = 2;
	} elseif (
	old('is_private') || old('is_paid') ||
	old('requires_personal_data') ||
	old('cover_media_id') ||
	old('remind_registration_enabled') ||
	old('remind_registration_minutes_before') ||
	old('show_participants') ||
	old('description_html') // ✅ чтобы оставаться на шаге 3
	) {
	$initialStep = 3;
	}
	}
    }
	
    // --- other precomputed helpers ---
    $monthsMap = [
	1=>'Янв',2=>'Фев',3=>'Мар',4=>'Апр',5=>'Май',6=>'Июн',
	7=>'Июл',8=>'Авг',9=>'Сен',10=>'Окт',11=>'Ноя',12=>'Дек'
    ];
	
    $oldMonths = old('recurrence_months', $prefill['recurrence_months'] ?? []);
    if (is_string($oldMonths)) $oldMonths = [$oldMonths];
    if (!is_array($oldMonths)) $oldMonths = [];
    $oldMonths = array_map('intval', $oldMonths);
	
    // ✅ Prefill trainers (multi + legacy fallback)
    $oldTrainerIds = old('trainer_user_ids', $prefill['trainer_user_ids'] ?? []);
    if (is_string($oldTrainerIds)) $oldTrainerIds = [$oldTrainerIds];
    if (!is_array($oldTrainerIds)) $oldTrainerIds = [];
    $oldTrainerIds = array_values(array_filter(array_unique(array_map('intval', $oldTrainerIds)), fn($id) => $id > 0));
    // ✅ Prefill tозрастные ограничения
    $oldAgePolicy = (string) old('age_policy', $prefill['age_policy'] ?? 'adult');
    if (!in_array($oldAgePolicy, ['adult','child','any'], true)) $oldAgePolicy = 'any';
	
    
    // legacy fallback: если пришёл один trainer_user_id
    $legacyOne = (int) old('trainer_user_id', $prefill['trainer_user_id'] ?? 0);
    if ($legacyOne > 0 && !in_array($legacyOne, $oldTrainerIds, true)) {
	$oldTrainerIds[] = $legacyOne;
    }
    
    // label для инпута (теперь контроллер отдаёт trainerPrefillLabel)
    $oldTrainerLabel = (string) old('trainer_user_label', $trainerPrefillLabel ?? ($prefill['trainer_user_label'] ?? ''));
	
	
    // ✅ registration offsets defaults
    $oldRegStartsDaysBefore = (int) old('reg_starts_days_before', 3);
    $oldRegEndsMinutesBefore = (int) old('reg_ends_minutes_before', 15);
    $oldCancelLockMinutesBefore = (int) old('cancel_lock_minutes_before', 60);
	
    if ($oldRegStartsDaysBefore < 0) $oldRegStartsDaysBefore = 3;
    if ($oldRegEndsMinutesBefore < 0) $oldRegEndsMinutesBefore = 15;
    if ($oldCancelLockMinutesBefore < 0) $oldCancelLockMinutesBefore = 60;

    $regEndsHours = intdiv($oldRegEndsMinutesBefore, 60);
    $regEndsMinutes = $oldRegEndsMinutesBefore % 60;
    $cancelLockHours = intdiv($oldCancelLockMinutesBefore, 60);
    $cancelLockMinutes = $oldCancelLockMinutesBefore % 60;
	@endphp
	
	
	
	
	<x-voll-layout body_class="create-blade"> 
		
    <x-slot name="image">
					<div class="top-section-img" data-aos="fade" data-aos-duration="1000">
						<div class="top-section-light-img">
							<img src="/img/arh/create_events.png" alt="img">
						</div>	
						<div class="top-section-dark-img">
							<img src="/img/arh/create_events.png" alt="img">
						</div>
					</div>	
	</x-slot>		
		
		<x-slot name="title">
			Создание мероприятия
		</x-slot>
		
		<x-slot name="description">
			Создание мероприятия
		</x-slot>
		
		<x-slot name="canonical">
			{{-- Ссылка страницы в тег canonical, например --}} 
			{{ route('users.index') }}
		</x-slot>
		
		<x-slot name="h1">
			Создание мероприятия
		</x-slot>
		
		
		
		<x-slot name="t_description">
			<h2 class="-mt-1">Шаг <span id="wizard_step_num">1</span> из 3</h2>	
			<div class="mt-1">
				<div class="wizard-pill" id="pill_1">Настройка мероприятия</div>
				<div class="wizard-pill" id="pill_2">Выбор локации,времени и ограничений записи</div>
				<div class="wizard-pill" id="pill_3">Доступность, описание и др.</div>			
			</div>
		</x-slot>
		
		<x-slot name="breadcrumbs">
			<li itemprop="itemListElement" itemscope="" itemtype="http://schema.org/ListItem">
				<a href="{{ route('events.create') }}" itemprop="item"><span itemprop="name">Создание мероприятия</span></a>
				<meta itemprop="position" content="2">
			</li>
		</x-slot>	
		
		<x-slot name="d_description">
			<div class="wizard-wrap">
				<div class="wizard-line">
					<div id="wizard_bar" style="width: 33%;"></div>
				</div>
				<span id="wizard_percent" class="cd b-600">33%</span>
			</div>	
		</x-slot>
		
		<x-slot name="style">
			<link href="/assets/org.css" rel="stylesheet">		
		</x-slot>			
		
		
		<div class="container">
    {{-- Детект встроенного браузера MAX/Telegram/VK --}}
    <div id="inapp-browser-warning" style="display:none;background:#fff3cd;border:1px solid #ffc107;border-radius:.75rem;padding:1rem 1.25rem;margin-bottom:1rem;">
        ⚠️ <b>Вы используете встроенный браузер</b> — некоторые функции могут не работать.<br>
        Для создания мероприятия откройте страницу в <b>обычном браузере</b> (Safari, Chrome).
        <br><br>
        <a href="{{ url()->current() }}" target="_blank" class="btn btn-secondary btn-small">🌐 Открыть в браузере</a>
    </div>
    <script>
    (function(){
        var ua = navigator.userAgent || '';
        var isInApp = /MAX/i.test(ua) || /VKWebApp/i.test(ua) || /FB_IAB/i.test(ua) || /Instagram/i.test(ua);
        if (isInApp) {
            var el = document.getElementById('inapp-browser-warning');
            if (el) el.style.display = '';
        }
    })();
    </script>

			
			{{-- FLASH --}}
			@if (session('private_link'))
			
			<div class="ramka">	
				<div class="alert alert-info">
					<div class="b-600">Ссылка на приватное мероприятие:</div>
					<div class="mt-1">
						<a class="text-blue-700 underline break-all" href="{{ session('private_link') }}">
							{{ session('private_link') }}
						</a>
					</div>					
				</div>
			</div>
			@endif
			
			{{-- SUCCESS --}}
			@if (session('status'))
			<div class="ramka">	
				<div class="alert alert-success">
					{{ session('status') }}
				</div>
			</div>
			@endif
			
			{{-- ERROR --}}
			@if (session('error'))
			<div class="ramka">		
				<div class="alert alert-error">
					{{ session('error') }}
				</div>
			</div>
			@endif
			
			{{-- VALIDATION ERRORS --}}
			@if ($errors->any())
			<div class="ramka">		
				<div class="alert alert-error">
					<div class="alert-title">Проверьте поля</div>
					<ul class="list">
						@foreach ($errors->all() as $err)
						<li>{{ $err }}</li>
						@endforeach
					</ul>
				</div>
			</div>				
			@endif
			@if (session('wizard_errors'))
			<div class="ramka">
				<div class="alert alert-error">
					<div class="alert-title">Проверьте поля</div>
					<ul class="list">
						@foreach (session('wizard_errors') as $field => $messages)
						@foreach ((array) $messages as $msg)
						<li><strong>{{ $field }}:</strong> {{ $msg }}</li>
						@endforeach
						@endforeach
					</ul>
				</div>
			</div>
            @endif
			
			<div class="form">					
				<form 
				method="POST"
				action="{{ route('events.store') }}"
				data-initial-step="{{ $initialStep }}"
				data-admin-id="{{ auth()->id() }}"
				data-organizer-city-id="{{ auth()->user()->city_id ?? '' }}"
				data-users-search-url="{{ route('api.users.search') }}"
				data-has-errors="{{ $errors->any() ? '1' : '0' }}"
				enctype="multipart/form-data"
                >
					@csrf
					<input type="hidden" name="wizard_step" id="wizard_step" value="{{ $initialStep }}">
					{{-- ✅ city-first --}}
					<input type="hidden" name="city_id" id="event_city_id" value="{{ auth()->user()->city ? auth()->user()->city->id : '' }}">
					
					{{-- ✅ timezone пусть отправляется всегда (даже если select будет disabled) --}}
					<input type="hidden" name="timezone" id="event_timezone_hidden" value="{{ auth()->user()->city->timezone ?? '' }}">
					{{-- STEP 1 --}}
					<div data-step="1" class="wizard-step step-shell">
						@include('events._partials.create.step1')
					</div>
					{{-- STEP 2 --}}
					<div data-step="2" class="wizard-step hidden step-shell">
						@include('events._partials.create.step2')
					</div>
					
					{{-- STEP 3 --}}
					<div data-step="3" class="wizard-step hidden step-shell">
						@include('events._partials.create.step3')
</div>	
</form>

</div>
</div>


<x-slot name="script"> 
	<script src="/assets/fas.js"></script>    
	<script src="/assets/org.js"></script>
	<script>
		window.volleyballConfig = @json($volleyballConfig);
	</script>
	{{-- Page JS --}}
	<script src="/js/config/volleyball-config.js"></script>
	<script src="/js/events-create.js?v={{ time() }}"></script>
	
	<script>
		let rerenderTimer = null;
		
		// Универсальная функция перерисовки одного селекта
		function safeRerenderEl(selector) {
			const $select = $(selector);
			if (!$select.length) {
				//console.log('Селект не найден:', selector);
				return;
// Переключение способа оплаты
(function() {
    const sel = document.getElementById('payment_method');
    const linkWrap = document.getElementById('payment_link_wrap');
    const hintCash = document.getElementById('hint_cash');
    const hintLink = document.getElementById('hint_link');
    const hintYoo  = document.getElementById('hint_yoomoney');
    const methodWrap = document.getElementById('payment_method_wrap');
    const isPaid = document.getElementById('is_paid');

    const orgSettings = {
        tbank: '{{ $orgPaySettings?->tbank_link ?? "" }}',
        sber:  '{{ $orgPaySettings?->sber_link ?? "" }}',
    };

    const refundWrap = document.getElementById('refund_wrap');

    function syncPaymentMethod() {
        if (!sel) return;
        const v = sel.value;
        const isLink = v === 'tbank_link' || v === 'sber_link';

        if (linkWrap) linkWrap.style.display = isLink ? '' : 'none';
        if (hintCash) hintCash.style.display = v === 'cash' ? '' : 'none';
        if (hintLink) hintLink.style.display = isLink ? '' : 'none';
        if (hintYoo)  hintYoo.style.display  = v === 'yoomoney' ? '' : 'none';

        // Политика возврата — только для ЮМани
        if (refundWrap) refundWrap.style.display = v === 'yoomoney' ? '' : 'none';

        // Автозаполнение ссылки из настроек
        const linkInput = linkWrap?.querySelector('[name="payment_link"]');
        if (linkInput && !linkInput.value) {
            if (v === 'tbank_link') linkInput.value = orgSettings.tbank;
            if (v === 'sber_link')  linkInput.value = orgSettings.sber;
        }
    }

    function syncIsPaid() {
        if (!methodWrap || !isPaid) return;
        const paid = isPaid.checked;
        methodWrap.style.display = paid ? '' : 'none';
        if (!paid && refundWrap) refundWrap.style.display = 'none';
        if (paid) syncPaymentMethod();
    }

    sel?.addEventListener('change', syncPaymentMethod);
    isPaid?.addEventListener('change', syncIsPaid);

    syncPaymentMethod();
    syncIsPaid();
})();

// Логика tournament_payment_mode перенесена в events-create.js
			}
			
			const $wrapper = $select.prev('.form-select-wrapper');
			
			if ($wrapper.length) {
				// Сохраняем значение
				const currentValue = $select.val();
				
				// Удаляем старую обертку
				$wrapper.remove();
				$select.removeData('custom-initialized');
				
				// Создаем новую
				if (typeof createCustomSelect === 'function') {
					createCustomSelect($select);
					$select.val(currentValue);
					
					// Обновляем отображение
					setTimeout(function() {
						const $newWrapper = $select.prev('.form-select-wrapper');
						if ($newWrapper.length && typeof updateCustomSelect === 'function') {
							updateCustomSelect($select, $newWrapper);
						}
					}, 20);
				}
				} else {
				//console.log('Нет кастомной обертки для:', selector);
			}
		}
		
		// Существующая функция для всех селектов
		function safeRerenderAll() {
			if (rerenderTimer) clearTimeout(rerenderTimer);
			rerenderTimer = setTimeout(function() {
				$('select').each(function() {
					safeRerenderEl(this);
				});
			}, 150);
		}
		
		// Делаем функции глобальными (чтоб из консоли можно было вызывать)
		window.safeRerenderEl = safeRerenderEl;
		window.safeRerenderAll = safeRerenderAll;
		
		// Вешаем на все значимые события
		$('form').on('change', '#direction, #format, #game_subtype, #game_libero_mode, #game_gender_policy', safeRerenderAll);
		
		// Обработчик для registration_type (скрытие блока бота при командной записи)
		$('form').on('change', 'input[name="registration_type"]', function() {
			applyAllowRegShowIf(); // Вызываем функцию которая обработает data-hide-if
		});
		// Показ/скрытие блока "Начало регистрации для ограничиваемого пола"
		function toggleGenderLimitedReg() {
			var policy = $('#game_gender_policy').val();
			var side = $('input[name="game_gender_limited_side"]:checked').val();
			var $box = $('#gender_limited_reg_box');
			var $label = $('#gender_limited_reg_label');
			if (policy === 'mixed_limited') {
				$box.show();
				if (side === 'male') $label.text('Мужчины');
				else if (side === 'female') $label.text('Девушки');
				else $label.text('Ограничиваемый пол');
			} else {
				$box.hide();
				$('#game_gender_limited_reg_starts_days_before').val('');
			}
		}
		$(document).on('change', '#game_gender_policy, input[name="game_gender_limited_side"]', toggleGenderLimitedReg);
		$(function(){ toggleGenderLimitedReg(); });

		
		// На клик по городу
		$('body').on('click', '.city-item', function() {
			setTimeout(safeRerenderAll, 100);
		});
		
		// Первичная отрисовка
		safeRerenderEl('#format');
		safeRerenderEl('#game_gender_policy');
		
		// На AJAX
		$(document).ajaxComplete(safeRerenderAll);
		
	</script>			
	
</x-slot>			


<script>
document.addEventListener('DOMContentLoaded', function() {
    function syncMinutes(hoursId, minsId, hiddenId) {
        var hSel = document.getElementById(hoursId);
        var mSel = document.getElementById(minsId);
        var hidden = document.getElementById(hiddenId);
        if (!hSel || !mSel || !hidden) return;

        function sync() {
            var total = parseInt(hSel.value) * 60 + parseInt(mSel.value);
            if (total < 1) total = 1;
            hidden.value = total;
        }

        hSel.addEventListener('change', sync);
        mSel.addEventListener('change', sync);
        sync();
    }

    syncMinutes('reg_ends_hours', 'reg_ends_mins', 'reg_ends_minutes_before');
    syncMinutes('cancel_lock_hours', 'cancel_lock_mins', 'cancel_lock_minutes_before');
});
</script>

</x-voll-layout>
