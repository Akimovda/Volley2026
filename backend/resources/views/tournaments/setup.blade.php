<x-voll-layout body_class="tournament-setup-page">
	@php
    $direction = $event->direction ?? 'classic';
    $isBeach = $direction === 'beach';
    // Терминология уровней игроков на этой странице — по географии СОБЫТИЯ
    // (турнир проходит в конкретном городе), а не по городу каждого игрока —
    // иначе в одном ростере была бы смесь терминологий.
    $levelScope = level_terminology_scope_for_event($event);
	@endphp
	<x-slot name="title">{{ __('tournaments.setup_title_with', ['title' => $event->title]) }}</x-slot>
	
    <x-slot name="h1">{{ __('tournaments.setup_title_with', ['title' => $event->title]) }}</x-slot>

	<x-slot name="style">
		<link href="/css/cropper.min.css" rel="stylesheet">
		<style>
			.cropper-modal-overlay {
				position: fixed; top: 0; bottom: 0; left: 0; right: 0;
				text-align: center; display: flex; flex-flow: column;
				align-items: center; justify-content: center;
				font-size: 0; overflow: hidden; z-index: 10000;
				pointer-events: none; opacity: 0; transition: opacity 0.3s ease;
			}
			.cropper-modal-overlay--active { opacity: 1; pointer-events: auto; }
			.cropper-modal-overlay:before, .cropper-modal-overlay:after {
				content: ""; position: absolute; top: 100vh; width: 100%; height: 100%;
				background: #fff; opacity: 0.8; transition-duration: 0.4s;
				transition-property: all;
				transition-timing-function: cubic-bezier(.47, 0, .74, .71);
				clip-path: polygon(100% 80%, 100% 100%, 0% 100%, 0% 20%);
			}
			.cropper-modal-overlay:after {
				clip-path: polygon(100% 0%, 100% 80%, 0% 20%, 0% 0%);
				top: -100vh; opacity: 0.5;
			}
			.cropper-modal-overlay--active:before, .cropper-modal-overlay--active:after {
				top: 0; left: 0;
				transition-timing-function: cubic-bezier(.22, .61, .36, 1);
			}
			body.dark .cropper-modal-overlay:before, body.dark .cropper-modal-overlay:after { background: #000; }
			.cropper-modal-container {
				position: relative; z-index: 10001; background: #fff;
				border-radius: 1.6rem; padding: 2rem; width: 90vw;
				max-width: 100rem; max-height: 90vh; display: flex;
				flex-direction: column;
				box-shadow: rgba(0,0,0,.1) 0px 1rem 2.2rem, rgba(0,0,0,.05) 0px .5rem 1.2rem;
				transform: scale(0.95); transition: transform 0.3s ease; overflow: hidden;
			}
			.cropper-modal-overlay--active .cropper-modal-container { transform: scale(1); }
			body.dark .cropper-modal-container { background: #2a2b3a; color: #e9ecef; }
			.cropper-image-wrapper {
				background: #f5f5f5; border-radius: 8px; overflow: hidden;
				margin-bottom: 1rem; flex: 1; min-height: 0;
				display: flex; align-items: center; justify-content: center;
			}
			body.dark .cropper-image-wrapper { background: #1e1e2a; }
			.cropper-image-wrapper img {
				max-width: 100%; max-height: 100%; width: auto; height: auto;
				display: block; margin: 0 auto; cursor: move;
			}
			.cropper-modal-container h3 {
				margin: 0 0 2rem 0; text-align: center; flex-shrink: 0; font-size: 2rem;
			}
			.cropper-buttons {
				display: flex; gap: 1rem; justify-content: center; flex-shrink: 0; margin-top: 1rem;
			}
			.cropper-modal-overlay .fancybox-loading {
				position: absolute; top: calc(50% - 75px); left: calc(50% - 75px);
				width: 150px; height: 150px; display: none; z-index: 10002;
			}
			.cropper-modal-overlay.loading .cropper-modal-container * { pointer-events: none; }
			.cropper-modal-overlay.loading .fancybox-loading { display: block !important; }

			/* Форма "Настройки турнира" — визуальная перегруппировка в 4 секции.
			   Токены — из style.css: акцент #2967BA/#E7612F (dark), .filter-section-label
			   (uppercase/1.3rem/700), .seg-control (паттерн выбираемых плиток), .card
			   (border 0.2rem rgba(0,0,0,.1), radius 1rem). Ничего нового в общий style.css
			   не выносим — компонент специфичен для этой страницы. */
			.stage-section-label {
				display: flex; align-items: center; gap: .8rem;
				margin-bottom: 1.4rem; font-weight: 700; font-size: 1.3rem;
				letter-spacing: .04em; text-transform: uppercase;
				color: rgba(41, 103, 186, .7);
			}
			body.dark .stage-section-label { color: rgba(231, 97, 45, .8); }
			.stage-section { margin-bottom: 2rem; }

			/* Тоггл "Добавить стадию" — приглушённый вид, когда стадии уже есть
			   (не конкурирует визуально с карточками активных стадий ниже);
			   функциональность (разворачивание формы) не меняется, только вес. */
			.stage-toggle-subdued {
				display: inline-flex; align-items: center; gap: .4rem;
				font-size: 1.4rem; font-weight: 500; color: #6b7280;
				cursor: pointer; background: none; border: none; padding: 0;
			}
			.stage-toggle-subdued:hover { color: #2967BA; }
			body.dark .stage-toggle-subdued:hover { color: #E7612F; }
			.stage-form-warning {
				display: flex; align-items: center; gap: .8rem;
				margin-bottom: 1.6rem; padding: 1rem 1.4rem;
				border-radius: .8rem; background: rgba(231, 97, 45, .08);
				color: #E7612F; font-size: 1.4rem; font-weight: 500;
			}
			body.dark .stage-form-warning { background: rgba(231, 97, 45, .12); color: #FFB171; }

			/* .radio-item.finals-mode-card (3 класса) — сознательно ВЫШЕ специфичности
			   .form .radio-item (2 класса) из style.css: класс radio-item здесь нужен
			   ТОЛЬКО чтобы сработало сайтовое правило "скрыть нативный input radio"
			   (.form .radio-item input{display:none}) — без него виден настоящий
			   браузерный radio рядом с кастомным кругом, что и давало разнобой
			   размера (нативный чекнутый radio крупнее/иначе стилизован браузером).
			   Но у .radio-item свои display:flex/padding:0/border-radius:0.6rem —
			   переопределяем их здесь явно, а не полагаемся на порядок в каскаде. */
			.radio-item.finals-mode-card {
				display: block; width: 100%; padding: 1.5rem 2rem;
				border: 0.2rem solid rgba(0, 0, 0, .1); border-radius: 1rem;
				margin-bottom: 1.2rem; cursor: pointer;
				transition: border-color .2s ease, background .2s ease;
			}
			.finals-mode-card:last-child { margin-bottom: 0; }
			.finals-mode-card-head { display: flex; align-items: flex-start; gap: 1.2rem; }
			.finals-mode-card-head .custom-radio { margin-top: .2rem; }
			.finals-mode-card-title { font-weight: 600; font-size: 1.7rem; }
			.finals-mode-card-hint { font-size: 1.3rem; color: #6b7280; margin: .4rem 0 0; }
			/* Акцентная 2px рамка выбранной карточки — сознательное исключение из
			   паттерна тонких (0.2rem rgba(0,0,0,.1)) рамок сайта, только для этого
			   единственного места, где нужно явно выделить сделанный выбор. */
			.finals-mode-card.is-selected {
				border: 2px solid #2967BA;
				background: rgba(41, 103, 186, .06);
			}
			body.dark .finals-mode-card { border-color: rgba(255, 255, 255, .1); }
			body.dark .finals-mode-card.is-selected {
				border-color: #E7612F;
				background: rgba(231, 97, 45, .08);
			}
			.finals-mode-card-extra {
				margin: 1.6rem 0 0 3.8rem; padding-top: 1.4rem;
				border-top: .1rem solid rgba(0, 0, 0, .08);
			}
			.finals-mode-card-extra select { max-width: 16rem; }
			body.dark .finals-mode-card-extra { border-top-color: rgba(255, 255, 255, .08); }
			/* Баг №5: третье место — select больше НЕ вложен в <label> карточки
			   bracket (клик по кастомному дропдауну ловил браузерный click-forwarding
			   на radio, дропдаун открывался и тут же закрывался обработчиком "клик
			   вне"). Стоит СНАРУЖИ <label>, визуально имитирует "выдвижную" часть той
			   же карточки: без зазора и верхней рамки сверху (продолжает bracket-card),
			   со своей рамкой по бокам/снизу и скруглением только нижних углов. */
			#finals_mode_card_bracket { margin-bottom: 0; }
			/* Зазор перед divisions держался только на margin-bottom блока
			   "третье место" (.finals-mode-card-extra--outside, 1.2rem ниже) —
			   тот виден ТОЛЬКО при finals_mode=bracket (setBlockActive() в JS).
			   При placement/divisions блок display:none, его margin с ним
			   исчезает, а margin-bottom у bracket-карточки навсегда занулён
			   строкой выше → зазора не остаётся вовсе. Свой margin-top на
			   divisions даёт стабильный зазор независимо от видимости блока
			   (тот же 1.2rem, что уже используется card-to-card — визуально
			   ничего не меняется, когда третье место видно). */
			#finals_mode_card_divisions { margin-top: 1.2rem; }
			.finals-mode-card-extra--outside {
				margin: 0 0 1.2rem; padding: 1.4rem 2rem;
				border: 0.2rem solid rgba(0, 0, 0, .1); border-top: none;
				border-radius: 0 0 1rem 1rem;
				background: rgba(0, 0, 0, .015);
			}
			body.dark .finals-mode-card-extra--outside {
				border-color: rgba(255, 255, 255, .1);
				background: rgba(255, 255, 255, .02);
			}

			/* Плитки-теги кортов — тот же приём, что .seg-control (акцентный фон на
			   выбранном), но как самостоятельные теги, а не связанный pill-бар: число
			   кортов переменное (0-20), связанная сегмент-полоса тут не подходит. */
			.court-tag {
				display: inline-flex; align-items: center; justify-content: center;
				padding: .8rem 1.6rem; border-radius: 999px;
				border: 0.2rem solid rgba(0, 0, 0, .1); background: rgba(0, 0, 0, .02);
				cursor: pointer; font-size: 1.4rem; font-weight: 500;
				transition: all .2s ease; user-select: none; margin: 0 !important;
			}
			.court-tag.is-selected { background: #2967BA; border-color: #2967BA; color: #fff; }
			body.dark .court-tag { border-color: rgba(255, 255, 255, .1); background: rgba(255, 255, 255, .02); }
			body.dark .court-tag.is-selected { background: #E7612F; border-color: #E7612F; color: #fff; }

			/* Состав команды (team_roster_line.blade.php) с аватарами — всегда
			   вертикальный список (по игроку на строке), а не "Имя1 / Имя2" в одну
			   строку — так лучше видно аватар+уровень каждого. Только когда показаны
			   аватары ($avatar=true, т.е. только на пульте) — на публичных/TV/score
			   страницах, где партиал используется без аватаров, поведение не менялось. */
			.team-roster-line--avatars { display: flex; flex-direction: column; gap: .4rem; }
			.team-roster-line--avatars .team-roster-member { display: flex; align-items: center; }
			.team-roster-line--avatars .team-roster-sep { display: none; }

			/* Модификатор общего сайтового .tabs/.tab/.tab-highlight (style.css) —
			   ТОЛЬКО на этой странице (карточка стадии). Базовый компонент
			   переиспользуется на других страницах (event_management, player
			   dashboard и др.) как сплошная pill-кнопка — там не трогаем, здесь
			   только переопределяем визуал через модификатор, JS (script.js
			   updateAllTabHighlights/initTabSet) не меняли и не дублировали. */
			.tabs.tabs--underline {
				display: flex; background: none; box-shadow: none;
				padding: 0; border-radius: 0; gap: 0;
				border-bottom: .1rem solid rgba(0, 0, 0, .1);
			}
			body.dark .tabs.tabs--underline { border-bottom-color: rgba(255, 255, 255, .1); }
			.tabs--underline .tab {
				min-width: auto; padding: 1rem 1.8rem; border-radius: 0;
				color: #6b7280; font-size: 1.4rem;
			}
			.tabs--underline .tab:hover:not(.active) { color: #2967BA; }
			body.dark .tabs--underline .tab:hover:not(.active) { color: #FFB171; }
			.tabs--underline .tab.active { color: #2967BA; text-shadow: none; }
			body.dark .tabs--underline .tab.active { color: #FFB171; }
			/* JS выставляет inline top:0/height:<высота таба> для pill-варианта —
			   переопределяем в тонкую полосу СНИЗУ; translate(x,y) от JS остаётся
			   (y=0 для однострочного ряда табов, двигает только по X). */
			.tabs--underline .tab-highlight {
				top: auto !important; bottom: 0; height: .3rem !important;
				border-radius: 0; box-shadow: none; background: #2967BA;
			}
			body.dark .tabs--underline .tab-highlight { background: #E7612F; }

			/* Тумблер Список/Шахматка (.ct-view-btn, тот же <style> компонент см.
			   ниже в файле) — сплошная кнопка → приглушённый текст/акцент без фона. */
			.ct-view-btn {
				background: none !important; border: none !important; box-shadow: none !important;
				color: #6b7280; padding: .4rem .2rem !important; border-radius: 0 !important;
				border-bottom: .2rem solid transparent !important;
			}
			.ct-view-btn--active {
				color: #2967BA !important; border-bottom-color: #2967BA !important;
			}
			body.dark .ct-view-btn--active { color: #FFB171 !important; border-bottom-color: #E7612F !important; }

			/* Кликабельная ячейка шахматки (group_crosstable.blade.php) — заливает
			   всю <td>, наследует цвет/фон ячейки (не перекрашивает как обычная .blink). */
			.crosstable-cell-link {
				display: block;
				padding: 4px 2px;
				color: inherit;
				text-decoration: none;
				cursor: pointer;
			}
			.crosstable-cell-link:hover {
				text-decoration: underline;
				filter: brightness(0.95);
			}
			body.dark .crosstable-cell-link:hover { filter: brightness(1.15); }
		</style>
	</x-slot>


{{-- Активный тур --}}
@if($selectedOccurrence)
@php
$occDate = \Carbon\Carbon::parse($selectedOccurrence->starts_at)->setTimezone($event->timezone ?? 'Europe/Moscow');
$tourNumber = $seasonData
    ? ($seasonData['occurrences']->search(fn($occ) => $occ->id === $selectedOccurrence->id) + 1)
    : 1;
@endphp
<x-slot name="h2">
		{{ __('tournaments.setup_round_n', ['n' => $tourNumber, 'date' => $occDate->format('d.m.Y')]) }}
</x-slot>
@endif	
	
	
	
	@if($seasonData)
    <x-slot name="t_description">
				{{ $seasonData['season']->name }}
				/ {{ $seasonData['league']->name ?? __('tournaments.setup_league_default') }}
	</x-slot>
	@endif
	
	
	

	<x-slot name="breadcrumbs">
		<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
			<a href="{{ route('events.show', $event) }}{{ $selectedOccurrence ? '?occurrence=' . $selectedOccurrence->id : '' }}" itemprop="item"><span itemprop="name">{{ $event->title }}</span></a>
			<meta itemprop="position" content="2">
		</li>
		<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
			<span itemprop="name">{{ __('tournaments.setup_breadcrumb') }}</span>
			<meta itemprop="position" content="3">
		</li>
	</x-slot>
	
	@if($seasonData && $seasonData['occurrences']->count() > 1)
	<x-slot name="d_description">
		<div class="d-flex flex-wrap gap-1 m-center">
			<div class="mt-2" data-aos-delay="250" data-aos="fade-up">
				<button class="btn ufilter-btn">{{ __('tournaments.setup_pick_round') }}</button>
			</div>
		</div>
	</x-slot>
	@endif	
	
	<div class="users-filter">
		<div class="container">
			<div class="ramka">	
				{{-- Выбор тура --}}
				@if($seasonData && $seasonData['occurrences']->count() > 1)
				<h2 class="-mt-05">{{ __('tournaments.setup_round_label') }}</h2>
				<div class="d-flex text-center" style="gap:1rem; flex-wrap:wrap;">
					@foreach($seasonData['occurrences'] as $occ)
					@php
					$isSelected = $selectedOccurrence && $selectedOccurrence->id === $occ->id;
					$occDate = \Carbon\Carbon::parse($occ->starts_at)->setTimezone($event->timezone ?? 'Europe/Moscow');
					@endphp
					<a href="{{ route('tournament.setup', $event) }}?occurrence_id={{ $occ->id }}"
					class="btn {{ !$isSelected ? 'btn-secondary' : '' }}">
						<span class="b-600">{{ $loop->iteration }}</span> - ({{ $occDate->format('d.m') }})
					</a>
					@endforeach
				</div>
				@endif		
			</div>
		</div>
	</div>
	
	<div class="container form">
		
		{{-- ========================= ЗАЯВКИ ========================= --}}
		{{-- Показываем блок если:
		     - application_mode=manual: все pending+incomplete заявки
		     - application_mode=auto: только incomplete (висят без auto-approval до сбора состава) --}}
		@php
			$mode = $applicationMode ?? 'manual';
			$visibleApps = isset($pendingApplications)
				? ($mode === 'manual'
					? $pendingApplications
					: $pendingApplications->where('status', 'incomplete'))
				: collect();
		@endphp
		@if($visibleApps->count())
		<div class="ramka">
			<h2 class="-mt-05">{{ __('tournaments.apps_h2', ['n' => $visibleApps->count()]) }}</h2>

			@if($mode === 'manual')
			<div class="alert alert-info mb-2">
				{!! __('tournaments.apps_mode_manual') !!}
			</div>
			@else
			<div class="alert alert-warning mb-2">
				{{ __('events.tapp_incomplete_body', ['team' => '—', 'event' => $event->title]) }}
			</div>
			@endif
			
			@foreach($visibleApps as $app)
			@php
				$isIncomplete = $app->status === 'incomplete';
				$canApproveIncomplete = $isIncomplete && ($app->team->is_complete ?? false);
			@endphp
			<div class="card mb-1">
				<div class="d-flex fvc" style="justify-content:space-between;flex-wrap:wrap;gap:.5rem">
					<div>
						<div class="d-flex fvc" style="gap:.5rem;flex-wrap:wrap">
							<a class="b-700 f-17 blink" href="{{ route('tournamentTeams.show', [$event, $app->team]) }}">{{ $app->team->name ?? '?' }}</a>
							@if($isIncomplete)
							<span style="display:inline-block;padding:1px 8px;border-radius:10px;font-size:11px;font-weight:600;background:#fef9c3;color:#854d0e">
								{{ __('events.tapp_status_incomplete') }}
							</span>
							@endif
						</div>
						<div class="f-13" style="opacity:.6">
							{{ __('tournaments.apps_captain') }}
							<a class="blink" href="{{ route('users.show', $app->team->captain_user_id) }}">
								{{ trim(($app->team->captain->last_name ?? '') . ' ' . ($app->team->captain->first_name ?? '')) ?: $app->team->captain->name ?? '?' }}
							</a>
							&middot; {{ __('tournaments.apps_applied_at') }} {{ $app->applied_at?->format('d.m.Y H:i') }}
						</div>
						@if($app->team->members->count())
						<div class="f-13 mt-05">
							{{ __('tournaments.setup_apps_lineup') }}
							@foreach($app->team->members as $m)
							<a class="blink" href="{{ route('users.show', $m->user_id) }}">{{ trim(($m->user->last_name ?? '') . ' ' . ($m->user->first_name ?? '')) ?: $m->user->name ?? '?' }}</a>@if(!$loop->last), @endif
							@endforeach
						</div>
						@endif
					</div>
					<div class="d-flex" style="gap:.5rem">
						@if(!$isIncomplete || $canApproveIncomplete)
						<form method="POST" action="{{ route('tournament.application.approve', [$event, $app]) }}">
							@csrf
							<button type="submit" class="btn btn-small btn-primary btn-alert" data-title="{{ __('tournaments.apps_confirm_approve') }}" data-icon="question" data-confirm-text="{{ __('tournaments.setup_apps_yes') }}" data-cancel-text="{{ __('tournaments.btn_cancel') }}">{{ __('tournaments.setup_apps_btn_approve') }}</button>
						</form>
						@endif
						<form method="POST" action="{{ route('tournament.application.reject', [$event, $app]) }}">
							@csrf
							<button type="submit" class="btn btn-small btn-secondary btn-alert" data-title="{{ __('tournaments.apps_confirm_reject') }}" data-icon="warning" data-confirm-text="{{ __('tournaments.setup_apps_no') }}" data-cancel-text="{{ __('tournaments.btn_cancel') }}">{{ __('tournaments.setup_apps_btn_reject') }}</button>
						</form>
					</div>
				</div>
			</div>
			@endforeach
		</div>
		@elseif(($applicationMode ?? 'manual') === 'auto')
		<div class="ramka">
			<div class="alert alert-success">
				{!! __('tournaments.setup_apps_auto_mode') !!}
			</div>
		</div>
		@endif
		
		
		
		{{-- БАГ №2 (2026-08-15): здесь стоял `Swal.fire({...})` (API SweetAlert2, глобал
		     `Swal` с большой буквы) — в проекте подключён SweetAlert 1.x (глобал `swal`,
		     см. CLAUDE.md/lib.js: window.swal из sweetalert.js.org), `Swal` нигде не
		     определён → `typeof Swal !== 'undefined'` всегда false → блок не выполнялся
		     НИКОГДА, ни guard-ошибка, ни успех отката/удаления не показывались. --}}
		@if(session('success') || session('error'))
		<script>
			document.addEventListener('DOMContentLoaded', function() {
				if (typeof swal !== 'undefined') {
					swal(
						@json(session("success") ? __('tournaments.setup_swal_done') : __('tournaments.setup_swal_error')),
						@json(session('success') ?: session('error')),
						'{{ session("success") ? "success" : "error" }}'
					);
				}
			});
		</script>
		@endif
		@if(session('warning'))
		{{-- Неблокирующее предупреждение (напр. состав команды не соответствует
		     гендерной политике турнира) — сохранение уже прошло успешно, это
		     ДОПОЛНИТЕЛЬНЫЙ тост, не альтернатива success/error выше. --}}
		<script>
			document.addEventListener('DOMContentLoaded', function() {
				if (typeof swal !== 'undefined') {
					swal(
						@json(__('tournaments.setup_swal_warning')),
						@json(session('warning')),
						'warning'
					);
				}
			});
		</script>
		@endif
		@if($errors->any())
		<div class="ramka">
			<div class="alert alert-error">
				@foreach($errors->all() as $err)
                {{ $err }}<br>
				@endforeach
			</div>
		</div>
		@endif
		
		
		
		{{-- ============================================================
		{{ __('tournaments.setup_series_h2') }}
		============================================================ --}}
		@php
		$leagueForSubs    = null;
		$occSubstitutions = collect();
		$_tourStarted     = $selectedOccurrence && now('UTC')->gte($selectedOccurrence->starts_at);
		$_reserveForSubs  = collect();
		$hasStages        = $stages->isNotEmpty();
		@endphp
		@if($seasonData)
		<div class="ramka" id="season_league_management">
			<style>
				.league-table { width:100%; border-collapse:collapse; }
				.league-table th { text-align:left; padding:8px 6px; border-bottom:2px solid #e5e7eb; font-size:13px; color:#6b7280; }
				.league-table td { padding:10px 6px; border-bottom:1px solid #f3f4f6; vertical-align:middle; }
				.league-table tr:last-child td { border-bottom:none; }
				.league-table .team-name { font-weight:600; font-size:15px; }
				.league-table .team-members { font-size:13px; color:#6b7280; margin-top:2px; }
				.league-badge { display:inline-block; padding:3px 10px; border-radius:12px; font-size:12px; font-weight:600; }
				.league-badge-active { background:#dcfce7; color:#166534; }
				.league-badge-reserve { background:#fef3c7; color:#92400e; }
				.league-badge-pending { background:#dbeafe; color:#1e40af; }
				.league-btn { padding:5px 12px; border-radius:8px; font-size:12px; border:1px solid #d1d5db; background:#fff; cursor:pointer; font-weight:500; }
				.league-btn:hover { background:#f9fafb; }
				.league-btn-danger { color:#dc2626; border-color:#fca5a5; }
				.league-btn-danger:hover { background:#fef2f2; }
				.league-btn-success { color:#16a34a; border-color:#86efac; }
				.league-btn-success:hover { background:#f0fdf4; }
				.tour-btn { display:inline-block; padding:6px 14px; border-radius:8px; font-size:13px; border:1px solid #d1d5db; background:#fff; color:#374151; text-decoration:none; font-weight:500; }
				.tour-btn:hover { background:#f3f4f6; text-decoration:none; }
				.tour-btn-active { background:#4f46e5; color:#fff; border-color:#4f46e5; }
				.tour-btn-active:hover { background:#4338ca; }
				@media (max-width:640px) {
				.league-table th:nth-child(1), .league-table td:nth-child(1) { display:none; }
				.league-table th:nth-child(3) { width:70px; }
				.league-table th:nth-child(4) { width:90px; }
				.league-table .team-name { font-size:14px; }
				.league-table .team-members { font-size:12px; }
				.league-badge { padding:2px 8px; font-size:11px; }
				.league-btn { padding:4px 8px; font-size:11px; }
				.tour-btn { padding:5px 10px; font-size:12px; }
				}
			</style>

			
			{{-- Загрузка замен для текущего тура --}}
			@php
			$occSubstitutions = collect();
			$leagueForSubs = null;
			if($selectedOccurrence && $leagueTeams->count()) {
				$_seasonEvtForSubs = \App\Models\TournamentSeasonEvent::where('occurrence_id', $selectedOccurrence->id)->first();
				if($_seasonEvtForSubs?->league_id) {
					$leagueForSubs = \App\Models\TournamentLeague::find($_seasonEvtForSubs->league_id);
					$_activeTeamIds = $leagueTeams->where('status','active')->pluck('team_id')->filter();
					$occSubstitutions = \App\Models\TeamSubstitution::whereIn('team_id', $_activeTeamIds)
						->where('occurrence_id', $selectedOccurrence->id)
						->whereIn('status', ['pending','confirmed'])
						->with(['originalPlayer:id,first_name,last_name','substitutePlayer:id,first_name,last_name'])
						->get()->keyBy('team_id');
					// Список резервистов для модалки
					$_reserveForSubs = $leagueTeams->where('status','reserve')->filter(fn($lt)=>$lt->user_id);
				}
			}
			$_tourStarted = $selectedOccurrence && now('UTC')->gte($selectedOccurrence->starts_at);
			@endphp

			@php $hasStages = $stages->isNotEmpty(); @endphp
			{{-- Состав лиги --}}
			@if($leagueTeams->count())
			@php
				$_activeTeams  = $leagueTeams->where('status', 'active');
				$_reserveTeams = $leagueTeams->whereIn('status', ['reserve', 'pending_confirmation'])
				                             ->sortBy('reserve_position');
			@endphp
			<div class="">
				<h2 class="-mt-05" style="cursor:pointer;user-select:none" onclick="var b=document.getElementById('league-teams-body');b.style.display=b.style.display==='none'?'':'none';this.querySelector('.toggle-icon').textContent=b.style.display==='none'?'▶':'▼'">
					{{ __('tournaments.setup_series_lineup') }}
					<span class="cd">
						— {{ $_activeTeams->count() }} {{ __('tournaments.setup_series_active') }}
						@if($_reserveTeams->count())
						/ {{ $_reserveTeams->count() }} {{ __('tournaments.setup_series_reserve') }} ({{ __('tournaments.setup_series_waitlist_h2') }})
						@endif
					</span>
					<span class="toggle-icon" style="margin-left:8px;font-size:14px">{{ $hasStages ? '▶' : '▼' }}</span>
				</h2>
				<div id="league-teams-body" style="{{ $hasStages ? 'display:none' : '' }}">

				{{-- ===== ОСНОВНОЙ СОСТАВ ===== --}}
				<div class="table-scrollable">
					<div class="table-drag-indicator"></div>
					<table class="table">
						<thead>
							<tr>
								<th style="width:30px">#</th>
								<th>{{ __('tournaments.setup_col_team') }}</th>
								<th>{{ __('tournaments.setup_col_status') }}</th>
								<th>{{ __('tournaments.setup_col_action') }}</th>
							</tr>
						</thead>
						<tbody>
							@foreach($_activeTeams as $lt)
							<tr>
								<td>{{ $loop->iteration }}</td>
								<td>
									@if($lt->team)
									<div class="team-name b-600">
										<a class="blink" href="{{ route('tournamentTeams.show', [$event, $lt->team]) }}">{{ $lt->team->name }}</a>
									</div>
									<div class="team-members">
										@php
										$members = $lt->team->members->map(function($m) {
										$u = $m->user;
										return $u ? ($u->last_name . ' ' . $u->first_name) : '?';
										})->implode(' / ');
										@endphp
										{{ $members }}
									</div>
									@if($leagueForSubs)
									@php $existingSub = $occSubstitutions[$lt->team_id] ?? null; @endphp
									@if($existingSub)
									<div class="f-12 mt-025 d-flex gap-1 align-items-center flex-wrap">
										@if($existingSub->status === 'confirmed')
										<span class="alert-success p-1 pt-025 pb-025">✓ {{ __('tournaments.substitution_confirmed') }}:</span>
										@else
										<span class="alert-warning p-1 pt-025 pb-025">⏳ {{ __('tournaments.awaiting_confirmation') }}:</span>
										@endif
										<span>{{ $existingSub->substitutePlayer->last_name }} {{ $existingSub->substitutePlayer->first_name }}</span>
										<span style="opacity:.5">{{ __('tournaments.sub_instead_of', ['name' => $existingSub->originalPlayer->last_name.' '.$existingSub->originalPlayer->first_name]) }}</span>
										@if(!$_tourStarted)
										@if($existingSub->status === 'pending')
										<form method="POST" action="{{ route('substitutions.confirm', $existingSub) }}" style="display:inline">@csrf
											<button type="submit" class="btn btn-small" style="padding:1px 6px;font-size:11px">✓</button>
										</form>
										@endif
										<form method="POST" action="{{ route('substitutions.cancel', $existingSub) }}" style="display:inline">@csrf
											<button type="submit" class="btn btn-small btn-secondary" style="padding:1px 6px;font-size:11px">✕</button>
										</form>
										@endif
									</div>
									@elseif(!$_tourStarted)
									<div class="mt-025">
										<button type="button" class="btn btn-small btn-secondary" style="font-size:11px;padding:2px 8px"
											data-sub-team="{{ $lt->team_id }}"
											data-sub-league="{{ $leagueForSubs->id }}"
											data-sub-occurrence="{{ $selectedOccurrence->id }}"
											data-sub-members="{{ $lt->team->members->filter(fn($m)=>$m->user)->map(fn($m)=>['id'=>$m->user_id,'name'=>($m->user->last_name.' '.$m->user->first_name)])->values()->toJson() }}"
											onclick="openSubModal(this)">
											{{ __('tournaments.btn_find_sub') }}
										</button>
									</div>
									@endif
									@endif
									@elseif($lt->user)
									<div class="team-name">{{ $lt->user->last_name }} {{ $lt->user->first_name }}</div>
									<div class="f-12 cd mt-025">
										<form method="POST" action="{{ route('tournament.syncLeague', $event) }}" style="display:inline">
											@csrf
											<input type="hidden" name="occurrence_id" value="{{ $selectedOccurrence?->id }}">
											<button type="submit" class="btn btn-small btn-secondary" style="font-size:11px;padding:2px 8px">Создать команду</button>
										</form>
									</div>
									@else
									—
									@endif
								</td>
								<td class="text-center">
									<span class="alert-success p-1 pt-05 pb-05">{{ __('tournaments.setup_st_active') }}</span>
								</td>
								<td class="text-center" style="white-space:nowrap">
									<form method="POST" action="{{ route('divisions.teams.toReserve', $lt) }}" style="display:inline">
										@csrf
										<button type="submit" class="btn btn-secondary btn-alert btn-small" data-title="{{ __('tournaments.setup_to_reserve_title') }}" data-icon="warning" data-confirm-text="{{ __('tournaments.yes') }}" data-cancel-text="{{ __('tournaments.btn_cancel') }}">{{ __('tournaments.setup_btn_to_reserve') }}</button>
									</form>
									<form method="POST" action="{{ route('divisions.teams.destroy', $lt) }}" style="display:inline">
										@csrf
										@method('DELETE')
										<button type="submit" class="btn btn-danger btn-alert btn-small" data-title="{{ __('tournaments.setup_team_delete_title', ['name' => $lt->team?->name ?? '—']) }}" data-icon="warning" data-confirm-text="{{ __('tournaments.btn_delete') }}" data-cancel-text="{{ __('tournaments.btn_cancel') }}">{{ __('tournaments.btn_delete') }}</button>
									</form>
								</td>
							</tr>
							@endforeach
						</tbody>
					</table>
				</div>

				{{-- ===== ЛИСТ ОЖИДАНИЯ ===== --}}
				@if($_reserveTeams->count())
				<h3 class="mt-2 mb-05">⏳ {{ __('tournaments.setup_series_waitlist_h2') }} ({{ $_reserveTeams->count() }})</h3>
				<div class="table-scrollable">
					<div class="table-drag-indicator"></div>
					<table class="table">
						<thead>
							<tr>
								<th style="width:30px">#</th>
								<th>{{ __('tournaments.setup_col_team') }}</th>
								<th>{{ __('tournaments.setup_col_status') }}</th>
								<th>{{ __('tournaments.setup_col_action') }}</th>
							</tr>
						</thead>
						<tbody>
							@foreach($_reserveTeams as $lt)
							<tr>
								<td>{{ $loop->iteration }}</td>
								<td>
									@if($lt->team)
									<div class="team-name b-600">
										<a class="blink" href="{{ route('tournamentTeams.show', [$event, $lt->team]) }}">{{ $lt->team->name }}</a>
									</div>
									<div class="team-members">
										@php
										$members = $lt->team->members->map(function($m) {
										$u = $m->user;
										return $u ? ($u->last_name . ' ' . $u->first_name) : '?';
										})->implode(' / ');
										@endphp
										{{ $members }}
									</div>
									@elseif($lt->user)
									<div class="team-name">{{ $lt->user->last_name }} {{ $lt->user->first_name }}</div>
									<div class="f-12 cd mt-025">
										<form method="POST" action="{{ route('tournament.syncLeague', $event) }}" style="display:inline">
											@csrf
											<input type="hidden" name="occurrence_id" value="{{ $selectedOccurrence?->id }}">
											<button type="submit" class="btn btn-small btn-secondary" style="font-size:11px;padding:2px 8px">Создать команду</button>
										</form>
									</div>
									@else
									—
									@endif
								</td>
								<td class="text-center">
									@if($lt->status === 'reserve')
									<span class="alert-warning p-1 pt-05 pb-05">{{ __('tournaments.setup_st_reserve_n', ['n' => $lt->reserve_position]) }}</span>
									@elseif($lt->status === 'pending_confirmation')
									<span class="alert-info p-1 pt-05 pb-05">{{ __('tournaments.setup_st_pending') }}</span>
									@else
									<span class="league-badge">{{ $lt->status }}</span>
									@endif
								</td>
								<td class="text-center" style="white-space:nowrap">
									<form method="POST" action="{{ route('divisions.teams.activate', $lt) }}" style="display:inline">
										@csrf
										<input type="hidden" name="occurrence_id" value="{{ $selectedOccurrence?->id }}">
										<button type="submit" class="btn btn-secondary btn-alert btn-small" data-title="{{ __('tournaments.setup_activate_title') }}" data-icon="info" data-confirm-text="{{ __('tournaments.yes') }}" data-cancel-text="{{ __('tournaments.btn_cancel') }}">{{ __('tournaments.setup_btn_activate') }}</button>
									</form>
									@if($lt->status === 'reserve')
									<form method="POST" action="{{ route('divisions.teams.moveReserve', $lt) }}" style="display:inline">
										@csrf
										<input type="hidden" name="direction" value="up">
										<button type="submit" class="btn btn-secondary btn-small" title="Вверх по очереди">↑</button>
									</form>
									<form method="POST" action="{{ route('divisions.teams.moveReserve', $lt) }}" style="display:inline">
										@csrf
										<input type="hidden" name="direction" value="down">
										<button type="submit" class="btn btn-secondary btn-small" title="Вниз по очереди">↓</button>
									</form>
									@endif
									<form method="POST" action="{{ route('divisions.teams.destroy', $lt) }}" style="display:inline">
										@csrf
										@method('DELETE')
										<button type="submit" class="btn btn-danger btn-alert btn-small" data-title="{{ __('tournaments.setup_team_delete_title', ['name' => $lt->team?->name ?? '—']) }}" data-icon="warning" data-confirm-text="{{ __('tournaments.btn_delete') }}" data-cancel-text="{{ __('tournaments.btn_cancel') }}">{{ __('tournaments.btn_delete') }}</button>
									</form>
								</td>
							</tr>
							@endforeach
						</tbody>
					</table>
				</div>
				@endif
			</div>
			@else
			<div class="alert alert-info">{{ __('tournaments.setup_no_teams_in_league') }}</div>
			@endif

			@if(!$hasStages)
			@if(!$seasonData['league'])
			<div class="alert alert-info mt-1">{!! __('tournaments.setup_no_division_yet', ['url' => route('seasons.edit', $seasonData['season'])]) !!}</div>
			@else
			{{-- Добавить команду вручную в дивизион --}}
			<div class="mt-1">
				<details id="add-to-league-details">
					@php
					$_addLeagueMax     = $seasonData['league']->max_teams ?? null;
					$_addLeagueActive  = $leagueTeams->where('status', 'active')->count();
					$_addLeagueFull    = $_addLeagueMax && $_addLeagueActive >= $_addLeagueMax;
					@endphp
				<summary class="btn btn-secondary">➕ Добавить в состав / резерв</summary>
					<form method="POST" action="{{ route('divisions.createAndAdd', $seasonData['league']) }}" class="mt-2 form">
						@csrf
						@if($selectedOccurrence)
						<input type="hidden" name="occurrence_id" value="{{ $selectedOccurrence->id }}">
						@endif
						<div class="row">
							<div class="col-md-6">
								<div class="card">
									<label>Капитан / игрок</label>
									<div style="position:relative" id="add-league-captain-wrap">
										<input type="text" id="add-league-captain-search" placeholder="Поиск по имени..." autocomplete="off">
										<input type="hidden" name="captain_user_id" id="add-league-captain-id">
										<div id="add-league-captain-dd" class="form-select-dropdown trainer_dd"></div>
									</div>
								</div>
							</div>
							@if($isBeach)
							<div class="col-md-6">
								<div class="card">
									<label>Партнёр</label>
									<div style="position:relative" id="add-league-partner-wrap">
										<input type="text" id="add-league-partner-search" placeholder="Поиск по имени..." autocomplete="off">
										<input type="hidden" name="partner_user_id" id="add-league-partner-id">
										<div id="add-league-partner-dd" class="form-select-dropdown trainer_dd"></div>
									</div>
								</div>
							</div>
							@endif
							<div class="col-md-6">
								<div class="card">
									<label>Название команды <span class="cd">(необязательно)</span></label>
									<input type="text" name="name" placeholder="Авто по фамилии капитана">
								</div>
							</div>
							<div class="col-md-6">
								<div class="card">
									<label>
									Место
									@if($_addLeagueMax)
									<span class="cd" id="league-cap-hint">— {{ $_addLeagueActive }} / {{ $_addLeagueMax }} команд</span>
									@endif
								</label>
									<select name="target_status" id="add-league-target-status"
										data-max="{{ $_addLeagueMax ?? '' }}"
										data-current="{{ $_addLeagueActive }}">
										<option value="active">Основной состав</option>
										<option value="reserve"{{ $_addLeagueFull ? ' selected' : '' }}>Резерв</option>
									</select>
									@if($_addLeagueFull)
									<div class="alert alert-warning mt-1" id="league-cap-warning" style="font-size:13px;padding-top:6px;padding-bottom:6px;margin:6px 0 0;line-height:1.4">
										⚠ Основной состав заполнен ({{ $_addLeagueActive }}/{{ $_addLeagueMax }}). Добавление переведёт в резерв или вернёт ошибку.
									</div>
									@else
									<div class="alert alert-warning mt-1" id="league-cap-warning" style="font-size:13px;padding-top:6px;padding-bottom:6px;margin:6px 0 0;line-height:1.4;display:none">
										⚠ Основной состав заполнен ({{ $_addLeagueActive }}/{{ $_addLeagueMax ?? '∞' }}). Добавление переведёт в резерв или вернёт ошибку.
									</div>
									@endif
								</div>
							</div>
							<div class="col-md-12 text-center">
								<button type="submit" class="btn">Добавить</button>
							</div>
						</div>
					</form>
				</details>
			</div>
			@endif

			@php
			$_tourAllCompleted = $stages->isNotEmpty() && $stages->every(fn($s) => $s->status === 'completed');
			@endphp
			<div class="mt-2 d-flex text-center gap-1 flex-wrap">
				<a class="btn" href="{{ route('seasons.show', $seasonData['season']) }}">{{ __('tournaments.setup_btn_season_page') }}</a>
				<form method="POST" action="{{ route('tournament.syncLeague', $event) }}" style="margin:0">
					@csrf
					<input type="hidden" name="occurrence_id" value="{{ $selectedOccurrence?->id }}">
					<button type="submit" class="btn">{{ __('tournaments.setup_btn_sync_teams') }}</button>
				</form>
				@if($_tourAllCompleted)
				<form method="POST" action="{{ route('tournament.applyPromotion', $event) }}" style="margin:0">
					@csrf
					<input type="hidden" name="occurrence_id" value="{{ $selectedOccurrence?->id }}">
					<button type="submit" class="btn btn-alert" data-title="{{ __('tournaments.setup_promote_title') }}" data-icon="info" data-confirm-text="{{ __('tournaments.setup_promote_yes') }}" data-cancel-text="{{ __('tournaments.btn_cancel') }}">
						{{ __('tournaments.setup_btn_promote') }}
					</button>
				</form>
				@endif
				</div>
			@endif
			</div>
		</div>
		@endif

		{{-- ============================================================
		Модалка замены
		============================================================ --}}
		@if($leagueForSubs && $selectedOccurrence && !$_tourStarted)
		<div id="subModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center">
			<div class="card p-3" style="max-width:480px;width:95%;max-height:90vh;overflow-y:auto;position:relative">
				<button onclick="closeSubModal()" style="position:absolute;top:10px;right:12px;background:none;border:none;font-size:18px;cursor:pointer">✕</button>
				<h3 class="-mt-05 mb-2" id="subModalTitle">{{ __('tournaments.find_substitute') }}</h3>

				{{-- Шаг 1: выбор кого заменяем --}}
				<div id="subStep1">
					<div class="f-13 mb-2" style="opacity:.7">{{ __('tournaments.invite_substitute') }}:</div>
					<div id="subMemberList"></div>
				</div>

				{{-- Шаг 2: кем заменяем --}}
				<div id="subStep2" style="display:none">
					<div class="f-13 mb-1" style="opacity:.5" id="subReplacingLabel"></div>

					{{-- Вкладки --}}
					<div class="d-flex gap-1 mb-2">
						<button class="btn sub-tab-btn" data-tab="reserve" onclick="switchSubTab('reserve')">{{ __('tournaments.substitute_from_reserve') }}</button>
						<button class="btn btn-secondary sub-tab-btn" data-tab="external" onclick="switchSubTab('external')">{{ __('tournaments.substitute_external') }}</button>
					</div>

					{{-- Из резерва --}}
					<div id="subTabReserve">
						@if(isset($_reserveForSubs) && $_reserveForSubs->isNotEmpty())
						@foreach($_reserveForSubs as $rlt)
						@if($rlt->user)
						<div class="d-flex" style="padding:5px 0;border-bottom:1px solid rgba(128,128,128,.08);align-items:center;gap:8px">
							<span style="flex:1">{{ $rlt->user->last_name }} {{ $rlt->user->first_name }}</span>
							<button type="button" class="btn btn-small"
								onclick="selectSubstitute({{ $rlt->user_id }}, '{{ addslashes($rlt->user->last_name.' '.$rlt->user->first_name) }}', 'reserve')">
								{{ __('tournaments.invite_substitute') }}
							</button>
						</div>
						@endif
						@endforeach
						@else
						<div class="f-13" style="opacity:.5">Резерв пуст</div>
						@endif
					</div>

					{{-- Поиск внешнего --}}
					<div id="subTabExternal" style="display:none">
						<input type="text" id="subSearchInput" class="form-control mb-1" placeholder="Поиск игрока..." autocomplete="off">
						<div id="subSearchResults"></div>
					</div>
				</div>

				{{-- Форма (скрытая) --}}
				<form method="POST" id="subForm" action="{{ route('leagues.substitutions.store', $leagueForSubs) }}" style="display:none">
					@csrf
					<input type="hidden" name="occurrence_id" value="{{ $selectedOccurrence->id }}">
					<input type="hidden" name="team_id" id="subTeamId">
					<input type="hidden" name="original_player_id" id="subOriginalId">
					<input type="hidden" name="substitute_player_id" id="subSubstituteId">
					<input type="hidden" name="substitute_source" id="subSource">
					<div class="mt-3 p-2" style="background:rgba(128,128,128,.08);border-radius:8px" id="subConfirmBlock">
						<div class="f-13 mb-2" id="subConfirmText"></div>
						<button type="submit" class="btn w-100">{{ __('tournaments.invite_substitute') }}</button>
					</div>
				</form>
			</div>
		</div>
		<script>
		var _subTeamId = null, _subOriginalId = null, _subOccurrenceId = {{ $selectedOccurrence->id }};
		function openSubModal(btn) {
			_subTeamId = btn.dataset.subTeam;
			var members = JSON.parse(btn.dataset.subMembers || '[]');
			var list = document.getElementById('subMemberList');
			list.innerHTML = '';
			members.forEach(function(m) {
				var d = document.createElement('div');
				d.style.cssText = 'padding:8px 0;border-bottom:1px solid rgba(128,128,128,.08);display:flex;align-items:center;gap:8px';
				d.innerHTML = '<span style="flex:1">'+m.name+'</span><button type="button" class="btn btn-small" onclick="chooseOriginal('+m.id+',\''+m.name.replace(/'/g,"\\'")+'\')">' + '{{ __("tournaments.invite_substitute") }}' + '</button>';
				list.appendChild(d);
			});
			document.getElementById('subTeamId').value = _subTeamId;
			document.getElementById('subStep1').style.display = '';
			document.getElementById('subStep2').style.display = 'none';
			document.getElementById('subForm').style.display = 'none';
			document.getElementById('subModal').style.display = 'flex';
		}
		function closeSubModal() { document.getElementById('subModal').style.display = 'none'; }
		function chooseOriginal(id, name) {
			_subOriginalId = id;
			document.getElementById('subOriginalId').value = id;
			document.getElementById('subReplacingLabel').textContent = '{{ __("tournaments.replacement_for", ["name" => ""]) }}' + name;
			document.getElementById('subStep1').style.display = 'none';
			document.getElementById('subStep2').style.display = '';
			document.getElementById('subForm').style.display = 'none';
			switchSubTab('reserve');
		}
		function switchSubTab(tab) {
			document.querySelectorAll('.sub-tab-btn').forEach(function(b){ b.classList.toggle('btn-secondary', b.dataset.tab !== tab); });
			document.getElementById('subTabReserve').style.display = tab==='reserve' ? '' : 'none';
			document.getElementById('subTabExternal').style.display = tab==='external' ? '' : 'none';
		}
		function selectSubstitute(id, name, source) {
			document.getElementById('subSubstituteId').value = id;
			document.getElementById('subSource').value = source;
			document.getElementById('subConfirmText').textContent = name;
			document.getElementById('subForm').style.display = '';
			document.getElementById('subConfirmBlock').style.display = '';
		}
		// Поиск внешнего игрока
		(function(){
			var t; document.getElementById('subSearchInput')?.addEventListener('input', function(){
				clearTimeout(t); var q = this.value.trim();
				if(q.length < 2) return;
				t = setTimeout(function(){
					jQuery.ajax({url:'/api/users/search', data:{q:q}, success:function(r){
						var el = document.getElementById('subSearchResults'); el.innerHTML='';
						(r.items||[]).forEach(function(u){
							var d=document.createElement('div');
							d.style.cssText='padding:5px 0;border-bottom:1px solid rgba(128,128,128,.08);display:flex;align-items:center;gap:8px;cursor:pointer';
							d.innerHTML='<span style="flex:1">'+(u.label||u.name)+'</span><button type="button" class="btn btn-small" onclick="selectSubstitute('+u.id+',\''+(u.label||u.name).replace(/'/g,"\\'")+'\',\'external\')">{{ __("tournaments.invite_substitute") }}</button>';
							el.appendChild(d);
						});
					}});
				}, 300);
			});
		})();
		document.getElementById('subModal').addEventListener('click', function(e){ if(e.target===this) closeSubModal(); });
		</script>
		@endif


		{{-- ============================================================
		Команды
		============================================================ --}}
		<div class="ramka">
			@php
				$completeTeams   = $teams->filter(fn($t) => $t->is_complete);
				$incompleteTeams = $teams->filter(fn($t) => !$t->is_complete);
				$isIndividualTournament = ($event->registration_mode ?? '') === 'tournament_individual';
				$teamsHeaderKey = $isIndividualTournament ? 'tournaments.setup_teams_h2_individual' : 'tournaments.setup_teams_h2';
			@endphp
			<h2 class="-mt-05" style="cursor:pointer;user-select:none" onclick="var b=document.getElementById('teams-body');b.style.display=b.style.display==='none'?'':'none';this.querySelector('.toggle-icon').textContent=b.style.display==='none'?'▶':'▼'">{{ __($teamsHeaderKey, ['n' => $completeTeams->count()]) }} <span class="toggle-icon" style="font-size:14px">{{ $hasStages ? '▶' : '▼' }}</span></h2>
			<div id="teams-body" style="{{ $hasStages ? 'display:none' : '' }}">
			@if($completeTeams->isEmpty())
			<div class="alert alert-info">{{ __('tournaments.setup_teams_empty') }}</div>
			@else
			<div class="row">
				@foreach($completeTeams as $team)
				<div class="col-md-6 col-xl-3">
					<div class="card">
						@php
							$members = $team->members->load('user');
							// Уровень по направлению турнира — тот же паттерн, что в
							// team_name_link.blade.php / team_roster_line.blade.php.
							$isBeachPairCard = $team->team_kind === 'beach_pair';
							$cardLvlColor = function ($user) use ($isBeachPairCard) {
								if (!$user) return '#aaaaaa';
								$lvl = $isBeachPairCard
									? (int) ($user->beach_level ?? $user->classic_level ?? 0)
									: (int) ($user->classic_level ?? $user->beach_level ?? 0);
								return $lvl > 0 ? level_color($lvl) : '#aaaaaa';
							};
						@endphp
						{{-- У команды нет "лица"/уровня — это агрегат игроков, аватар+уровень
						     только на строках отдельных игроков ниже. --}}
						<a href="{{ route('tournamentTeams.show', [$event, $team]) }}" class="blink b-600 d-block mb-1">
							{{ $team->name }}
						</a>
						@if($members->count() <= 2)
						@foreach($members as $m)
						<div>
							<img src="{{ $m->user->profile_photo_url }}" class="ms-player-avatar-mini" alt="" style="vertical-align:middle;margin-right:.4rem">
							<span class="level-dot level-dot--sm" style="vertical-align:middle;margin-right:.4rem;background:{{ $cardLvlColor($m->user) }}"></span>
							{{ trim(($m->user->last_name ?? '') . ' ' . ($m->user->first_name ?? '')) ?: $m->user->name ?? '?' }}
						</div>
						@endforeach
						@else
						@foreach($members->take(2) as $m)
						<div>
							<img src="{{ $m->user->profile_photo_url }}" class="ms-player-avatar-mini" alt="" style="vertical-align:middle;margin-right:.4rem">
							<span class="level-dot level-dot--sm" style="vertical-align:middle;margin-right:.4rem;background:{{ $cardLvlColor($m->user) }}"></span>
							{{ trim(($m->user->last_name ?? '') . ' ' . ($m->user->first_name ?? '')) ?: $m->user->name ?? '?' }}
						</div>
						@endforeach
						<div style="font-style:italic">{{ __('tournaments.setup_team_others') }}</div>
						@endif
						<div class="mt-1 d-flex between fvc">
							<div class="mt-05 cd b-600">{{ __('tournaments.setup_team_persons', ['n' => $members->count()]) }}</div>
							<div style="display:flex;gap:4px;align-items:center">
								<form method="POST" action="{{ route('tournamentTeams.sendToWaitlist', [$event, $team]) }}" class="mt-1">
									@csrf
									<button type="submit" class="btn btn-secondary btn-small btn-alert" data-title="Переместить «{{ $team->name }}» в резерв?" data-icon="warning" data-confirm-text="В резерв" data-cancel-text="{{ __('tournaments.btn_cancel') }}" title="В резерв">⏳</button>
								</form>
								<form method="POST" action="{{ route('tournamentTeams.destroy', [$event, $team]) }}" class="mt-1">
									@csrf @method('DELETE')
									<button type="submit" class="icon-delete btn-alert btn btn-danger btn-svg" data-title="{{ __('tournaments.setup_team_delete_title', ['name' => $team->name]) }}" data-icon="warning" data-confirm-text="{{ __('tournaments.btn_delete') }}" data-cancel-text="{{ __('tournaments.btn_cancel') }}">
									</button>
								</form>
							</div>
						</div>
					</div>
				</div>
				@endforeach
			</div>
			@endif

			@if($incompleteTeams->isNotEmpty())
			<h3 class="mt-2 mb-05">⏳ Ищут партнёра ({{ $incompleteTeams->count() }})</h3>
			<div class="row">
				@foreach($incompleteTeams as $team)
				<div class="col-md-6 col-xl-3">
					<div class="card" style="opacity:.8;border-style:dashed">
						<a href="{{ route('tournamentTeams.show', [$event, $team]) }}" class="blink b-600 d-block mb-1">
							{{ $team->name }}
						</a>
						@php $members = $team->members->load('user'); @endphp
						@foreach($members as $m)
						<div>{{ trim(($m->user->last_name ?? '') . ' ' . ($m->user->first_name ?? '')) ?: $m->user->name ?? '?' }}</div>
						@endforeach
						<div class="mt-1 d-flex between fvc">
							<div class="mt-05 cd b-600" style="color:#92400e">Ищет партнёра</div>
							<form method="POST" action="{{ route('tournamentTeams.destroy', [$event, $team]) }}" class="mt-1">
								@csrf @method('DELETE')
								<button type="submit" class="icon-delete btn-alert btn btn-danger btn-svg" data-title="{{ __('tournaments.setup_team_delete_title', ['name' => $team->name]) }}" data-icon="warning" data-confirm-text="{{ __('tournaments.btn_delete') }}" data-cancel-text="{{ __('tournaments.btn_cancel') }}">
								</button>
							</form>
						</div>
					</div>
				</div>
				@endforeach
			</div>
			@endif

			@if($isIndividualTournament)
			<h3 class="mt-2 mb-05">{{ __('tournaments.setup_unassigned_h3', ['n' => $unassignedPlayers->count()]) }}</h3>
			@if($unassignedPlayers->isEmpty())
			<div class="alert alert-info">{{ __('tournaments.setup_unassigned_empty') }}</div>
			@else
			<div class="row">
				@foreach($unassignedPlayers as $p)
				@php
					$pLevel = ($event->direction === 'beach' ? $p->beach_level : $p->classic_level);
					$pLevel = !is_null($pLevel) && $pLevel !== '' ? (int) $pLevel : null;
					$pGenderColor = $p->gender === 'f' ? '#e5395e' : '#2967BA';
					$pGenderSign = $p->gender === 'f' ? '♀' : '♂';
				@endphp
				<div class="col-md-6 col-xl-3">
					<div class="card" style="opacity:.9">
						<div style="display:flex;align-items:center;gap:10px">
							<img src="{{ $p->profile_photo_url }}" alt="" loading="lazy" style="width:40px;height:40px;border-radius:50%;object-fit:cover;flex-shrink:0">
							<div style="min-width:0">
								<div class="b-600" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ trim(($p->last_name ?? '') . ' ' . ($p->first_name ?? '')) ?: ($p->name ?? '?') }}</div>
								<div class="f-13" style="opacity:.85">
									<span style="color:{{ $pGenderColor }};font-weight:700">{{ $pGenderSign }}</span> ·
									{{ __('tournaments.setup_unassigned_level') }}:
									@if($pLevel)
									<span class="levelmark levelmark--event level-{{ $pLevel }}">{{ level_name_short($pLevel, $levelScope) }}</span>
									@else
									<span class="levelmark levelmark--event level-na">!?</span>
									@endif
								</div>
							</div>
						</div>
					</div>
				</div>
				@endforeach
			</div>
			@endif
			@endif

			{{-- Создать команду организатором --}}
			<div class="mt-1">
				<details>
					<summary class="btn btn-secondary">{{ __('tournaments.setup_btn_create_team') }}</summary>
                    <form method="POST" action="{{ route('tournamentTeams.store', $event) }}" class="mt-2 form">
                        @csrf
						<input type="hidden" name="from_setup" value="1">
						@if($selectedOccurrence)
						<input type="hidden" name="occurrence_id" value="{{ $selectedOccurrence->id }}">
						@endif
						<div class="mt-2">
							<div class="row">
								<div class="col-md-6">
									<div class="card">
										<label>{{ __('tournaments.setup_team_label_name') }}</label>
										<input type="text" name="name" placeholder="{{ __('tournaments.setup_team_ph_name') }}">
									</div>
								</div>
								<div class="col-md-6">
									<div class="card" style="overflow:visible">
										<label>{{ __('tournaments.setup_team_label_captain') }}</label>
										@if($isIndividualTournament && $unassignedPlayers->isNotEmpty())
										<div style="position:relative" id="manual-captain-ac-wrap">
											<input type="text" id="manual-captain-search" placeholder="{{ __('tournaments.setup_team_ph_captain') }}" autocomplete="off">
											<input type="hidden" name="captain_user_id" id="manual-captain-id">
											<div id="manual-captain-dd" class="form-select-dropdown trainer_dd"></div>
										</div>
										@else
										<div style="position:relative" id="org-captain-ac-wrap">
											<input type="text" id="org-captain-search" placeholder="{{ __('tournaments.setup_team_ph_captain') }}" autocomplete="off">
											<input type="hidden" name="captain_user_id" id="org-captain-id">
											<div id="org-captain-dd" class="form-select-dropdown trainer_dd"></div>
										</div>
										@endif
									</div>
								</div>
								@if($isIndividualTournament && $unassignedPlayers->isNotEmpty())
								<div class="col-md-12">
									<div class="card" style="overflow:visible">
										<label>{{ __('tournaments.setup_team_label_members') }}</label>
										<div id="manual-members-list" style="display:flex;flex-wrap:wrap;gap:10px;margin-top:.5rem">
											@foreach($unassignedPlayers as $p)
											<label class="checkbox-item" data-user-id="{{ $p->id }}" style="display:flex;align-items:center;gap:6px;margin:0">
												<input type="checkbox" name="member_user_ids[]" value="{{ $p->id }}">
												<div class="custom-checkbox"></div>
												<span>{{ trim(($p->last_name ?? '') . ' ' . ($p->first_name ?? '')) ?: ($p->name ?? '?') }} ({{ $p->gender === 'f' ? '♀' : '♂' }})</span>
											</label>
											@endforeach
										</div>
									</div>
								</div>
								@endif
								<div class="col-md-12 text-center">
									<button type="submit" class="btn">{{ __('tournaments.setup_btn_create') }}</button>
								</div>
							</div>
						</div>
					</form>
				</details>
			</div>

			@if($isIndividualTournament && $unassignedPlayers->isNotEmpty())
			<script>
			(function(){
				var players = @json($unassignedPlayers->map(fn($p) => [
					'id' => $p->id,
					'label' => trim(($p->last_name ?? '') . ' ' . ($p->first_name ?? '')) ?: ($p->name ?? ('#' . $p->id)),
				])->values());
				var inp = document.getElementById('manual-captain-search');
				var hidden = document.getElementById('manual-captain-id');
				var dd = document.getElementById('manual-captain-dd');
				var wrap = document.getElementById('manual-captain-ac-wrap');
				if (!inp || !dd || !hidden) return;

				function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
				// .ramka/.card-ramka создают свой stacking context (backdrop-filter,
				// ≥992px) — абсолютный дропдаун ограничен z-index СВОЕЙ ramka и уходит
				// под следующую по DOM (см. комментарий у .select-dropdown-open в
				// style.css). createCustomSelect() в script.js уже решает это для
				// .form-select-wrapper; этот автокомплит — отдельный самописный виджет,
				// применяем тот же приём вручную.
				var ramkaEl = wrap ? wrap.closest('.ramka, .card-ramka, .top-section') : null;
				function showDd() { dd.classList.add('form-select-dropdown--active'); if (ramkaEl) ramkaEl.classList.add('select-dropdown-open'); }
				function hideDd() { dd.classList.remove('form-select-dropdown--active'); if (ramkaEl) ramkaEl.classList.remove('select-dropdown-open'); }

				function setCaptain(id, label) {
					inp.value = label;
					hidden.value = String(id);
					hideDd();
					document.querySelectorAll('#manual-members-list [data-user-id]').forEach(function(row) {
						var cb = row.querySelector('input[type=checkbox]');
						if (!cb) return;
						var isCaptain = row.dataset.userId === String(id);
						cb.disabled = isCaptain;
						if (isCaptain) cb.checked = false;
					});
				}

				inp.addEventListener('input', function() {
					hidden.value = '';
					document.querySelectorAll('#manual-members-list input[type=checkbox]').forEach(function(cb) { cb.disabled = false; });
					var q = inp.value.trim().toLowerCase();
					if (q.length < 1) { hideDd(); dd.innerHTML = ''; return; }
					var matches = players.filter(function(p) { return p.label.toLowerCase().indexOf(q) !== -1; });
					dd.innerHTML = '';
					if (!matches.length) {
						dd.innerHTML = '<div class="city-message">' + @json(__('tournaments.setup_search_no_results')) + '</div>';
						showDd();
						return;
					}
					matches.slice(0, 8).forEach(function(p) {
						var div = document.createElement('div');
						div.className = 'trainer-item form-select-option';
						div.innerHTML = '<div class="text-sm">' + esc(p.label) + '</div>';
						div.addEventListener('click', function() { setCaptain(p.id, p.label); });
						dd.appendChild(div);
					});
					showDd();
				});

				inp.addEventListener('keydown', function(e) { if (e.key === 'Escape') hideDd(); });
				document.addEventListener('click', function(e) { if (wrap && !wrap.contains(e.target)) hideDd(); });
			})();
			</script>
			@endif

			@if($isIndividualTournament)
			{{-- Случайное распределение игроков по командам (только индивидуальная запись) --}}
			@php
				$remainingTeamsCount = max(0, ($event->tournament_teams_count ?? 0) - ($completeTeams->count() + $incompleteTeams->count()));
				$distributeConfirmText = __('events.tournament_distribute_confirm', [
					'n' => $remainingTeamsCount,
					'p' => $unassignedPlayers->count(),
				]);
			@endphp
			<div class="mt-1">
				<button type="button" id="distribute-teams-btn" class="btn btn-secondary"
					data-event-id="{{ $event->id }}"
					data-occurrence-id="{{ $selectedOccurrence?->id }}">
					{{ __('events.tournament_distribute_random_btn') }}
				</button>
			</div>
			<script>
			(function() {
				var btn = document.getElementById('distribute-teams-btn');
				if (!btn) return;
				var defaultText = btn.textContent.trim();
				btn.addEventListener('click', function() {
					var eventId = btn.dataset.eventId;
					var occurrenceId = btn.dataset.occurrenceId;

					swal({
						title: @json(__('events.tournament_distribute_random_btn')),
						text: @json($distributeConfirmText),
						icon: 'warning',
						buttons: {
							cancel: { text: @json(__('tournaments.btn_cancel')), value: null, visible: true, closeModal: true },
							confirm: { text: @json(__('events.tournament_distribute_btn')), value: true, visible: true, closeModal: true },
						},
						dangerMode: true,
					}).then(function(confirmed) {
						if (!confirmed) return;

						btn.disabled = true;
						btn.textContent = '...';
						fetch('/events/' + eventId + '/distribute-individual', {
							method: 'POST',
							headers: {
								'Content-Type': 'application/json',
								'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
								'Accept': 'application/json',
							},
							body: JSON.stringify({ occurrence_id: occurrenceId ? parseInt(occurrenceId) : null }),
							credentials: 'same-origin',
						})
						.then(function(r) { return r.json(); })
						.then(function(data) {
							if (data.ok) {
								location.reload();
							} else {
								swal({ title: 'Ошибка', text: data.message || 'Не удалось распределить игроков.', icon: 'error', button: 'Понятно' });
								btn.disabled = false;
								btn.textContent = defaultText;
							}
						})
						.catch(function() {
							swal({ title: 'Ошибка', text: 'Ошибка соединения.', icon: 'error', button: 'Понятно' });
							btn.disabled = false;
							btn.textContent = defaultText;
						});
					});
				});
			})();
			</script>
			@endif

			</div>{{-- /teams-body --}}
		</div>
		
		
		
		{{-- ============================================================
		Создание стадии (сворачивается если стадии уже есть; скрывается совсем
		если турнир полностью завершён — $allCompleted, поднято сюда из блока
		"MVP турнира" ниже, т.к. там он вычислялся слишком поздно для этого блока.
		См. report_league_tournament_setup_diag_2026-08-07.md.
		============================================================ --}}
		@php
			$hasStages = $event->tournamentStages->isNotEmpty();
			$lastStage = $stages->last();
			$lastStageHasMatches = $lastStage && $lastStage->matches->isNotEmpty();

			$allCompleted = $stages->isNotEmpty() && $stages->every(fn($s) => $s->status === 'completed');
			// Если сезонный турнир с 2+ группами, но групп Hard/Lite ещё нет — турнир не завершён
			if ($allCompleted && $event->season_id) {
				$groupStage = $stages->firstWhere('type', 'round_robin');
				if ($groupStage && $groupStage->groups->count() >= 2) {
					$hasDivisions = $stages->contains(fn($s) => $s->division_tier !== null || str_starts_with($s->name, 'Группа '));
					if (!$hasDivisions) {
						$allCompleted = false;
					}
				}
			}
			// Кусок 2, шаг 2b: новый divisions-турнир ВСЕГДА имеет pending-скелет
			// "Финальные группы" → главная формула выше сама держит allCompleted=false
			// до его запуска (launchStage). Здесь ловим только СТАРЫЕ divisions без
			// скелета (event 557/561): группа завершена, finals_mode=divisions, но ни
			// скелета, ни Групп ещё нет — турнир не закрыт, пока не нажмут fallback-форму.
			if ($allCompleted && !$event->season_id) {
				$oldDivisionsNoSkeleton = $stages->first(fn($s) => $s->canHaveFollowupStage()
					&& $s->groups->count() >= 2 && $s->cfg('finals_mode') === 'divisions'
					&& !$stages->contains(fn($o) => $o->id !== $s->id && $o->type === 'round_robin'
						&& $o->cfg('finals_mode') === 'divisions')
					&& !$stages->contains(fn($o) => $o->division_tier !== null || str_starts_with($o->name, 'Группа ')));
				if ($oldDivisionsNoSkeleton) {
					$allCompleted = false;
				}
			}
			// БАГ №3 (2026-08-15): groups_playoff/round_robin с finals_mode=placement|bracket
			// ВСЕГДА получает companion-стадию single_elim при createStage() (см. контроллер,
			// автосоздание "Плей-офф" сразу после групповой). Если организатор удалил/откатил
			// эту companion-стадию (destroyStage()/revertStage()) — единственная оставшаяся
			// completed групповая стадия ошибочно давала "турнир завершён" по naive-формуле
			// выше: "все существующие стадии completed" технически true, хотя по продуктовому
			// правилу играть ещё есть что (плей-офф нужно пересоздать). Проверяем, что для
			// каждой такой групповой стадии есть СУЩЕСТВУЮЩАЯ single_elim-стадия того же
			// occurrence — если нет, турнир не завершён (блок "Финальная стадия не создана"
			// ниже уже умеет предлагать её пересоздание через quickCreateFinals()).
			if ($allCompleted && !$event->season_id) {
				$groupStageMissingFinals = $stages->first(function ($s) use ($stages) {
					if (!$s->canHaveFollowupStage() || $s->cfg('finals_mode') === 'divisions' || $s->groups->count() < 2) {
						return false;
					}
					return !$stages->contains(fn($o) => $o->id !== $s->id
						&& $o->occurrence_id === $s->occurrence_id
						&& $o->type === 'single_elim');
				});
				if ($groupStageMissingFinals) {
					$allCompleted = false;
				}
			}
		@endphp
		@if(!$allCompleted)
		<div class="ramka">
			<h2 class="-mt-05">{{ __('tournaments.setup_add_stage_h2') }}</h2>
			@if($hasStages)
			{{-- Стадии уже есть — тоггл приглушён (не конкурирует визуально с
			     карточками активных стадий ниже), но функция та же: разворачивает
			     форму для добавления ЕЩЁ ОДНОЙ отдельной стадии. --}}
			<span class="stage-toggle-subdued" style="cursor:pointer" onclick="var b=this.nextElementSibling;b.style.display=b.style.display==='none'?'':'none';var ic=this.querySelector('.toggle-icon');if(ic)ic.textContent=b.style.display==='none'?'+':'-'">{{ __('tournaments.setup_btn_add_stage_subdued') }}</span>
			@else
			<div class="btn btn-secondary" style="cursor:pointer" onclick="var b=this.nextElementSibling;b.style.display=b.style.display==='none'?'':'none';var ic=this.querySelector('.toggle-icon');if(ic)ic.textContent=b.style.display==='none'?'+':'-'">{{ __('tournaments.setup_btn_add_stage') }}
			</div>
			@endif
			<div style="{{ $hasStages ? 'display:none' : '' }}">
				@if($hasStages && $lastStageHasMatches)
				<div class="stage-form-warning">⚠️ {{ __('tournaments.setup_new_stage_warning') }}</div>
				@endif
				<form class="mt-2 form" method="POST" action="{{ route('tournament.stages.store', $event) }}">
					@csrf
					@if($selectedOccurrence)
					<input type="hidden" name="occurrence_id" value="{{ $selectedOccurrence->id }}">
					@endif
					<div class="stage-section">
						<div class="stage-section-label">{{ __('tournaments.setup_section_type_h') }}</div>
						<div class="card">
							<div class="row">
								<div class="col-lg-4 col-md-6">
									<label>{{ __('tournaments.setup_stage_type') }}</label>
									<select name="type" id="stage_type_select">
										<option value="round_robin">{{ __('tournaments.setup_stage_round_robin') }}</option>
										<option value="groups_playoff">{{ __('tournaments.setup_stage_groups_playoff') }}</option>
										<option value="single_elim">{{ __('tournaments.setup_stage_single_elim') }}</option>
										<option value="swiss">{{ __('tournaments.setup_stage_swiss') }}</option>
										<option value="double_elim">{{ __('tournaments.setup_stage_double_elim') }}</option>
										{{-- thai (TournamentThaiService) нигде не вызывается бэкендом — скрыто из
										     выбора в форме (1A), тип и валидатор не трогаем. king_of_court и
										     king_beach показываем только для пляжного турнира. --}}
										@if($isBeach)
										<option value="king_of_court">{{ __('tournaments.setup_stage_king_of_court') }}</option>
										<option value="king_beach">{{ __('tournaments.setup_stage_king_beach') }}</option>
										@endif
									</select>
									<a href="{{ route('tournament_formats') }}" target="_blank" class="f-16 blink mt-1">{{ __('tournaments.setup_stage_formats_link') }}</a>
								</div>
								<div class="col-lg-4 col-md-6">
									<label>{{ __('tournaments.setup_stage_label_name') }}</label>
									<input name="name" value="{{ old('name', __('tournaments.setup_stage_default_name')) }}" required>
								</div>
								<div class="col-lg-4 col-md-6">
									<label>{{ __('tournaments.setup_stage_match_format') }}</label>
									<select name="match_format" id="match_format_select">
										<option value="bo3">Best of 3 (Bo3)</option>
										<option value="bo1">Best of 1 (Bo1)</option>
										@if(!$isBeach)
										<option value="bo5">Best of 5 (Bo5)</option>
										@endif
									</select>
									<div id="match_format_hint" class="f-16 mt-1"></div>
									<script>
										(function(){
											var hints = {
												bo1: @json(__('tournaments.setup_stage_bo1_hint')),
												bo3: @json(__('tournaments.setup_stage_bo3_hint')),
												bo5: @json(__('tournaments.setup_stage_bo5_hint'))
											};
											var sel = document.getElementById('match_format_select');
											var hint = document.getElementById('match_format_hint');
											function upd() { hint.textContent = hints[sel.value] || ''; }
											sel.addEventListener('change', upd);
											sel.addEventListener('change', function() {
												var wrap = document.getElementById('deciding_set_wrap');
												if (wrap) wrap.style.display = (sel.value === 'bo1') ? 'none' : '';
											});
											upd();
											var _dsw = document.getElementById('deciding_set_wrap'); if (_dsw && sel.value === 'bo1') _dsw.style.display = 'none';
										})();
									</script>
								</div>
							</div>
							<div class="row mt-2">
								<div class="col-md-6">
									<label>{{ __('tournaments.setup_stage_set_pts') }}</label>
									<select name="set_points">
										@if(!$isBeach)
										<option value="25" selected>{{ __('tournaments.setup_stage_set_pts_25') }}</option>
										@endif
										@if($isBeach)
										<option value="21" selected>{{ __('tournaments.setup_stage_set_pts_21') }}</option>
										@endif
										<option value="15">{{ __('tournaments.setup_stage_set_pts_15') }}</option>
									</select>
								</div>
								<div class="col-md-6" id="deciding_set_wrap">
									<label>{{ __('tournaments.setup_stage_deciding_set') }}</label>
									<select name="deciding_set_points">
										<option value="15" selected>15</option>
										@if(!$isBeach)
										<option value="25">25</option>
										@endif
									</select>
								</div>
							</div>
						</div>
					</div>
					{{-- King of the Beach: специфичные настройки --}}
					<div class="mt-2" id="king_beach_fields" style="display:none">
						<div class="row">
							<div class="col-md-4">
								<div class="card">
									<label>{{ __('tournaments.setup_stage_kb_group_size') }}</label>
									<select name="kb_group_size" id="kb_group_size_select">
										<option value="4">{{ __('tournaments.setup_stage_kb_group_size_4') }}</option>
										<option value="6">{{ __('tournaments.setup_stage_kb_group_size_6') }}</option>
									</select>
									<p class="f-16">{{ __('tournaments.setup_stage_kb_group_size_hint') }}</p>
								</div>
							</div>
							<div class="col-md-4">
								<div class="card">
									<label>{{ __('tournaments.setup_stage_kb_draw') }}</label>
									<select name="draw_mode" id="kb_draw_mode_select">
										<option value="random">{{ __('tournaments.setup_stage_seed_random') }}</option>
										<option value="seeded">{{ __('tournaments.setup_stage_seed_seeded') }}</option>
									</select>
									<p class="f-16">{{ __('tournaments.setup_stage_kb_draw_manual_hint') }}</p>
								</div>
							</div>
							<div class="col-md-4">
								<div class="card">
									<label>{{ __('tournaments.setup_stage_kb_players') }}</label>
									<p class="f-16">{{ __('tournaments.setup_stage_kb_players_hint') }}</p>
								</div>
							</div>
						</div>
					</div>

					<div class="mt-2 stage-section" id="group_fields">
						<div class="stage-section-label">{{ __('tournaments.setup_stage_step_1_h') }}</div>
						<div class="stage-section">
							<div class="stage-section-label">{{ __('tournaments.setup_section_group_h') }}</div>
							<div class="card">
								<div class="row">
									<div class="col-lg-4 col-md-6">
										<label>{{ __('tournaments.setup_stage_groups_count') }}</label>
										<input name="groups_count" type="number" value="2" min="1" max="16">
									</div>
									<div class="col-lg-4 col-md-6">
										<label>{{ __('tournaments.setup_stage_groups_advance') }}</label>
										<input name="advance_count" type="number" value="2" min="1" max="8">
										<p class="f-16" id="advance_count_hint">{{ __('tournaments.setup_stage_groups_advance_hint') }}</p>
									</div>
									<div class="col-lg-4 col-md-6">
										<label>{{ __('tournaments.setup_stage_seed') }}</label>
										<select name="draw_mode" id="draw_mode_select">
											<option value="random">{{ __('tournaments.setup_stage_seed_random') }}</option>
											<option value="seeded">{{ __('tournaments.setup_stage_seed_seeded') }}</option>
											<option value="manual">{{ __('tournaments.setup_stage_seed_manual') }}</option>
										</select>
									</div>
								</div>
							</div>
						</div>

						{{-- Живой каскад-предпросмотр: сколько команд идёт напрямую в плей-офф
						     и сколько добирается лучшими из невыходящих мест до полной сетки
						     (Кусок 3, TournamentBracketService — практика FIVB). Пересчитывается
						     JS-ом из groups_count/advance_count/finals_mode, ничего не отправляет
						     на сервер — чистый предпросмотр. --}}
						<div id="cascade_preview" class="alert-info p-2 mb-2" style="display:none">
							<span id="cascade_text"></span>
							<span id="cascade_dobor_hint" class="f-13 cascade-hint" style="display:block;margin-top:.3rem"></span>
						</div>

						{{-- Ручное распределение --}}
						<div class="mt-2" id="manual_draw_block" style="display:none">
							<div class="card">
								<label>{{ __('tournaments.setup_stage_manual_distribution') }}</label>
								<p>{{ __('tournaments.setup_stage_manual_pick_group') }}</p>
								@if($teams->isNotEmpty())
								<div class="table-scrollable no-overflow">
									<div class="table-drag-indicator"></div>				
									<table class="table">
										<thead>
											<tr>
												<th>{{ __('tournaments.setup_col_team') }}</th>
												<th>{{ __('tournaments.setup_stage_col_group') }}</th>
												<th>{{ __('tournaments.setup_stage_manual_position_col') }}</th>
											</tr>
										</thead>
										<tbody>
											@foreach($teams as $team)
											<tr>
												<td>
													<div class="b-600">{{ $team->name }}</div>
													@if($team->members->count())
													<div class="f-16">{{ $team->members->map(fn($m) => trim(($m->user->last_name ?? '') . ' ' . ($m->user->first_name ?? '')))->implode(' / ') }}</div>
													@endif
												</td>
												<td class="text-center">
													<select name="manual_teams[{{ $team->id }}][group]" class="manual-group-select" >
														<option value="">—</option>
														<option value="A">{{ __('tournaments.setup_stage_group_letter', ['l' => 'A']) }}</option>
														<option value="B">{{ __('tournaments.setup_stage_group_letter', ['l' => 'B']) }}</option>
													</select>
												</td>
												<td class="text-center">
													<input type="number" name="manual_teams[{{ $team->id }}][position]" min="1" max="{{ $teams->count() }}" style="width:4.5rem" placeholder="—">
												</td>
											</tr>
											@endforeach
										</tbody>
									</table>
								</div>
								@else
								<div class="alert alert-info">{{ __('tournaments.setup_stage_no_teams_distribute') }}</div>
								@endif
							</div>
						</div>
					</div>

					<div class="stage-section" id="finals_mode_fields">
						<div class="stage-section-label" style="margin-top:2rem">{{ __('tournaments.setup_stage_step_2_h') }}</div>

						<label class="finals-mode-card radio-item" id="finals_mode_card_placement">
							<div class="finals-mode-card-head">
								<input type="radio" name="finals_mode" value="placement" id="finals_mode_placement" checked>
								<div class="custom-radio"></div>
								<div>
									<div class="finals-mode-card-title">{{ __('tournaments.setup_finals_mode_placement') }}</div>
									<p class="finals-mode-card-hint" id="finals_mode_placement_hint">{{ __('tournaments.setup_finals_mode_placement_hint') }}</p>
								</div>
							</div>
						</label>

						<label class="finals-mode-card radio-item" id="finals_mode_card_bracket">
							<div class="finals-mode-card-head">
								<input type="radio" name="finals_mode" value="bracket" id="finals_mode_bracket">
								<div class="custom-radio"></div>
								<div class="finals-mode-card-title">{{ __('tournaments.setup_finals_mode_bracket') }}</div>
							</div>
						</label>
						{{-- ВНЕ <label> карточки bracket намеренно (баг №5): select внутри того же
						     <label>, что и radio, ловил клик-форвардинг браузера на radio (дефолтное
						     поведение label без "for" — не блокируется e.stopPropagation() в
						     createCustomSelect(), т.к. это activation behavior, а не JS-слушатель).
						     Клик по кастомному дропдауну открывал его и тут же закрывал обработчиком
						     "клик вне" — select физически не открывался. Визуально блок остаётся
						     "под" карточкой bracket за счёт margin-top/border-top ниже. --}}
						<div class="finals-mode-card-extra finals-mode-card-extra--outside" id="third_place_match_field">
							<label>{{ __('tournaments.setup_stage_third_place') }}</label>
							<select name="third_place_match" style="max-width:16rem">
								<option value="0">{{ __('tournaments.no') }}</option>
								<option value="1">{{ __('tournaments.yes') }}</option>
							</select>
						</div>

						<label class="finals-mode-card radio-item" id="finals_mode_card_divisions">
							<div class="finals-mode-card-head">
								<input type="radio" name="finals_mode" value="divisions" id="finals_mode_divisions">
								<div class="custom-radio"></div>
								<div>
									<div class="finals-mode-card-title">{{ __('tournaments.setup_finals_mode_divisions') }}</div>
									<p class="finals-mode-card-hint">{{ __('tournaments.setup_finals_mode_divisions_hint') }}</p>
								</div>
							</div>
							<div class="finals-mode-card-extra" id="finals_mode_divisions_fields" style="display:none">
								{{-- Больше не редактируемое поле — чистое вычисление
								     groups_count × advance_count (Section 2 «Групповой этап»),
								     не отправляется в форме. createStage() на бэкенде считает
								     то же самое число тем же способом (см. контроллер). --}}
								<p class="f-13" id="advance_per_group_summary" style="color:#6b7280;margin:0"></p>

								{{-- Формат матча по дивизионам — для любого числа групп (2, 3, 4+),
								     ключ по точному имени дивизиона (div_format_medium-1 и т.п.). --}}
								<div class="mt-2" id="divisions_format_fields"></div>
							</div>
						</label>
					</div>

					{{-- Корты — общий блок для группового этапа и King of the Beach --}}
					<div class="mt-2" id="courts_shared_fields" style="overflow:visible">
						<div class="stage-section">
							<div class="stage-section-label">{{ __('tournaments.setup_section_courts_h') }}</div>

							{{-- Расписание (опционально) — объединено с "Площадками" в одну секцию
							     (та же карточка настроек стадии). Видно только для группового формата,
							     для King of the Beach схлопывается через schedule_fields в toggle() ниже —
							     сам блок "Площадки" остаётся видимым для обоих форматов. --}}
							<div class="stage-section" id="schedule_fields" style="display:none">
								<div class="card">
									<label>{{ __('tournaments.setup_stage_schedule') }}</label>
									<hr class="mb-1">
									<div class="row">
										<div class="col-md-4">
											<label>{{ __('tournaments.setup_stage_start') }}</label>
											<input type="datetime-local" name="schedule_start" value="">
										</div>
										<div class="col-md-4">
											<label>{{ __('tournaments.setup_stage_match_min') }}</label>
											<input type="number" name="schedule_match_duration" value="30" min="15" max="180">
										</div>
										<div class="col-md-4">
											<label>{{ __('tournaments.setup_stage_break_min') }}</label>
											<input type="number" name="schedule_break_duration" value="5" min="0" max="60">
										</div>
									</div>
									<ul class="list f-16 mt-1">
										<li>{{ __('tournaments.setup_stage_schedule_hint') }}</li>
									</ul>
								</div>
							</div>

							<div class="card" style="overflow:visible">
								<div class="row">
									<div class="col-lg-4 col-md-6">
										<label>{{ __('tournaments.setup_stage_courts_count') }}</label>
										<select name="courts_count" id="courts_count_select">
											<option value="0">—</option>
											<option value="1">1</option>
											<option value="2">2</option>
											<option value="3">3</option>
											<option value="4">4</option>
											<option value="5">5</option>
											<option value="6">6</option>
											<option value="7">7</option>
											<option value="8">8</option>
											<option value="9">9</option>
											<option value="10">10</option>
											<option value="11">11</option>
											<option value="12">12</option>
											<option value="13">13</option>
											<option value="14">14</option>
											<option value="15">15</option>
											<option value="16">16</option>
											<option value="17">17</option>
											<option value="18">18</option>
											<option value="19">19</option>
											<option value="20">20</option>
										</select>
										<input type="hidden" name="courts" id="courts_hidden" value="">
									</div>
								</div>

								{{-- Назначение кортов группам (динамическое, только для форматов с группами) —
								     плитки-теги вместо чекбоксов, рендерятся JS (rebuild() ниже) --}}
								<div class="mt-2" id="courts_group_assign" style="display:none">
									<label>{{ __('tournaments.setup_stage_courts_for_groups') }}</label>
									<hr class="mb-1">
									<div id="courts_group_boxes" class="row"></div>
								</div>
							</div>
						</div>
					</div>

					<div class="text-center">
						<button type="submit" class="btn btn-primary mt-2">{{ __('tournaments.setup_stage_btn_create_seed') }}</button>
					</div>
					<script>
						// Единый источник списка "групповых" типов стадий — из
						// TournamentStage::groupTypeValues() (PHP), а не отдельная
						// копия в каждом JS-блоке (см. report_stage_type_branching_audit.md §2.3).
						window.__stageGroupTypes = @json(\App\Models\TournamentStage::groupTypeValues());
						// Типы, для которых бэкенд авто-создаёт парную стадию-продолжение
						// (canHaveFollowupStage()) — блок "Режим финалов" актуален только
						// для них. round_robin И groups_playoff, НЕ thai (см. followupTypeValues()).
						window.__stageFollowupTypes = @json(\App\Models\TournamentStage::followupTypeValues());
						// Дисциплина турнира — дефолт радио "Режим финалов" зависит от неё
						// (пляжка чаще играет финальные группы по уровням, классика — финал за места).
						window.__eventDirection = @json($event->direction);
						// Локаль для склонения "N команд" в каскад-предпросмотре ниже (ru — три формы
						// команда/команды/команд, en — team/teams) — считать на бэкенде один раз,
						// не гадать по document.documentElement.lang на клиенте.
						window.__appLocale = @json(app()->getLocale());
						// Названия финальных групп по groups_count (1..16, тот же диапазон,
						// что у input[name=groups_count] min/max) — считаем ОДИН РАЗ на
						// бэкенде через TournamentStage::divisionNamesFor() (та же формула,
						// что использует formDivisions()/пульт), JS просто индексирует по
						// готовому массиву. НЕ пересчитывать эту формулу отдельно в JS.
						window.__divisionNamesByGroupsCount = @json(
							collect(range(1, 16))->mapWithKeys(fn ($n) => [$n => \App\Models\TournamentStage::divisionNamesFor($n)])
						);
						window.__totalTeamsForDivisions = @json($teams->count());
					</script>
					<script>
						(function(){
							var courtsSel = document.getElementById("courts_count_select");
							var groupsSel = document.querySelector('input[name="groups_count"]');
							var hidden = document.getElementById("courts_hidden");
							var assignBlock = document.getElementById("courts_group_assign");
							var boxesDiv = document.getElementById("courts_group_boxes");
							var typeSel = document.getElementById("stage_type_select");

							function rebuild() {
								var n = parseInt(courtsSel.value) || 0;
								var isGroupType = typeSel && window.__stageGroupTypes.indexOf(typeSel.value) !== -1;
								var g = isGroupType ? (parseInt(groupsSel ? groupsSel.value : 0) || 0) : 0;
								
								var names = [];
								for (var i = 1; i <= n; i++) names.push(@json(__('tournaments.setup_court_n', ['n' => 'X'])).replace('X', i));
								hidden.value = names.join(", ");
								
								if (n === 0 || g === 0) {
									assignBlock.style.display = "none";
									boxesDiv.innerHTML = "";
									return;
								}
								
								assignBlock.style.display = "";
								var groupLabels = [];
								for (var gi = 0; gi < g; gi++) {
									groupLabels.push(String.fromCharCode(65 + gi)); // A, B, C...
								}

								var colSize = Math.floor(12 / g);
								if (colSize < 3) colSize = 3;
								var html = "";
								groupLabels.forEach(function(label) {
									html += '<div class="col-md-' + colSize + ' mb-2">';
									html += '<label>' + @json(__('tournaments.setup_group_label', ['label' => 'X'])).replace('X', label) + '</label>';
									// Плитки-теги (не чекбоксы): реальный чекбокс скрыт правилом
									// ".form .checkbox-item input{display:none}" (глобальное, style.css) —
									// клик по <label> нативно переключает его без доп. JS; отдельный
									// delegated-listener ниже только синхронизирует визуальный класс.
									html += '<div class="d-flex" style="flex-wrap:wrap;gap:.8rem">';
									names.forEach(function(court) {
										html += '<label class="checkbox-item court-tag">';
										html += '<input type="checkbox" name="group_courts[' + label + '][]" value="' + court + '">';
										html += '<span>' + court + '</span>';
										html += '</label>';
									});
									html += '</div></div>';
								});
								boxesDiv.innerHTML = html;
							}

							courtsSel.addEventListener("change", rebuild);
							if (groupsSel) groupsSel.addEventListener("input", rebuild);
							if (typeSel) typeSel.addEventListener("change", rebuild);
							// Делегированный листенер переживает innerHTML-перестройку rebuild() —
							// вешаем один раз на контейнер, не на каждую плитку.
							boxesDiv.addEventListener("change", function(e) {
								if (e.target && e.target.matches('input[type="checkbox"]')) {
									var tag = e.target.closest('.court-tag');
									if (tag) tag.classList.toggle('is-selected', e.target.checked);
								}
							});
							rebuild();
						})();
					</script>
				</div>
			</form>
		</div>
		@endif
		
		{{-- ============================================================
		Стадии
		============================================================ --}}
		
		{{-- MVP турнира: кандидаты — ТОЛЬКО игроки команд-победителей (топ-1),
		     не вся лига/турнир. Логика — TournamentStatsService::getMvpCandidates()
		     (дивизионы → победитель каждой финальной группы; placement/bracket →
		     чемпион; только круговая → 1-е место общей таблицы), переиспользует
		     ту же классификацию мест, что и везде на сайте (calculateFinalClassification).
		     $allCompleted вычислен выше, в блоке "Создание стадии". --}}
		@php
		$participants = collect();
		if ($allCompleted) {
		$participants = app(\App\Services\TournamentStatsService::class)
			->getMvpCandidates($event, $selectedOccurrence?->id);
		}
		@endphp
		@if($allCompleted && $participants->isNotEmpty())
		<div class="ramka">
			<h2 class="-mt-05">{{ __('tournaments.setup_mvp_h2') }}</h2>
			@if($event->tournament_mvp_user_id)
			@php $currentMvp = \App\Models\User::find($event->tournament_mvp_user_id); @endphp
			<div class="card" style="text-align:center;background:rgba(231,97,47,.06);border:1px solid rgba(231,97,47,.2)">
				<div class="f-13 b-600 mb-1">{{ __('tournaments.setup_mvp_current') }}</div>
				<div class="f-20 b-800">⭐
					<a href="{{ route('users.show', $currentMvp) }}" class="blink">
						{{ trim(($currentMvp->last_name ?? '') . ' ' . ($currentMvp->first_name ?? '')) ?: $currentMvp->name ?? '?' }}
					</a>
				</div>
			</div>
			@endif
			<form method="POST" action="{{ route('tournament.mvp', $event) }}">
				@csrf
				<div class="card p-3">
					<label class="f-13 b-600 mb-2 d-block">{{ __('tournaments.setup_mvp_pick') }}</label>
					<table style="width:100%;border-collapse:collapse;font-size:14px">
						<thead>
							<tr style="border-bottom:2px solid rgba(128,128,128,.2)">
								<th class="p-1" style="width:30px"></th>
								<th class="p-1" style="text-align:left">{{ __('tournaments.setup_mvp_col_player') }}</th>
								<th class="p-1" style="text-align:center">WinRate</th>
								<th class="p-1" style="text-align:center">{{ __('tournaments.setup_mvp_col_matches') }}</th>
								<th class="p-1" style="text-align:center">{{ __('tournaments.setup_mvp_col_sets') }}</th>
							</tr>
						</thead>
						<tbody>
							@foreach($participants as $ps)
							<tr style="border-bottom:1px solid rgba(128,128,128,.1);{{ $event->tournament_mvp_user_id == $ps->user_id ? 'background:rgba(231,97,47,.06)' : '' }}">
								<td class="p-1" style="text-align:center">
									<input type="radio" name="mvp_user_id" value="{{ $ps->user_id }}" {{ $event->tournament_mvp_user_id == $ps->user_id ? 'checked' : '' }}>
								</td>
								<td class="p-1">
									<a href="{{ route('users.show', $ps->user_id) }}" class="blink b-600">
										{{ trim(($ps->user->last_name ?? '') . ' ' . ($ps->user->first_name ?? '')) ?: $ps->user->name ?? '?' }}
									</a>
								</td>
								<td class="p-1 b-700" style="text-align:center;color:#E7612F">{{ $ps->match_win_rate }}%</td>
								<td class="p-1" style="text-align:center">{{ $ps->matches_won }}/{{ $ps->matches_played }}</td>
								<td class="p-1" style="text-align:center">{{ $ps->sets_won }}:{{ $ps->sets_lost }}</td>
							</tr>
							@endforeach
						</tbody>
					</table>
					<div class="text-center mt-2">
						<button type="submit" class="btn btn-primary">{{ __('tournaments.setup_mvp_btn_assign') }}</button>
					</div>
				</div>
			</form>
		</div>
		@endif
		
		{{-- Фото турнира --}}
		@if($allCompleted)
		<div class="ramka">
			<h2 class="-mt-05">{{ __('tournaments.setup_photos_h2') }}</h2>
			
			@php
			$tournamentPhotos = $event->getMedia('tournament_photos');
			$currentPhotoIds = $tournamentPhotos->pluck('id')->toArray();
			@endphp
			
			@if($tournamentPhotos->isNotEmpty())
			<div class="d-flex mb-2" style="flex-wrap:wrap;gap:2rem">
				@foreach($tournamentPhotos as $media)
				<div style="position:relative;width:20%;">
					<img src="{{ $media->getUrl('thumb') }}" style="width:100%; aspect-ratio: 16/9;object-fit:cover;border-radius:8px">
					<form method="POST" action="{{ route('tournament.photos.destroy', [$event, $media->id]) }}" style="position:absolute; bottom:1rem; right:1rem">
						@csrf @method('DELETE')
						<button type="submit" 
						class="icon-delete btn-alert btn btn-danger btn-svg"
						data-title="{{ __('tournaments.setup_photos_delete_title') }}"
						data-icon="warning"
						data-confirm-text="{{ __('tournaments.btn_delete') }}"
						data-cancel-text="{{ __('tournaments.btn_cancel') }}">
						</button>										
					</form>
				</div>
				@endforeach
			</div>
			@endif
			
			@php $userTournamentGallery = $userEventPhotos ?? collect(); @endphp

			<div class="card">
				<label>{{ __('tournaments.setup_photos_pick') }}</label>

				<div id="tournament-photos-swiper-wrap" @if($userTournamentGallery->count() === 0) style="display:none" @endif>
					<div class="event-photos-selector" id="tournament-photos-selector"
					data-selected='{{ json_encode($currentPhotoIds) }}'>
						<div class="swiper tournamentPhotosSwiper">
							<div class="swiper-wrapper">
								@foreach($userTournamentGallery as $photo)
								<div class="swiper-slide">
									<div class="hover-image mb-1">
										<img src="{{ $photo->getUrl('event_thumb') }}" alt="photo" loading="lazy"/>
									</div>
									<div class="mt-1 d-flex between fvc">
										<label class="checkbox-item mb-0">
											<input type="checkbox" class="t-photo-select" value="{{ $photo->id }}">
											<div class="custom-checkbox"></div>
											<span>{{ __('tournaments.setup_photos_select') }}</span>
										</label>
										<div class="photo-order-badge f-16 b-600 cd"></div>
									</div>
								</div>
								@endforeach
							</div>
							<div class="swiper-pagination"></div>
						</div>
						<ul class="list f-16 mt-1">
							<li>{{ __('tournaments.setup_photos_hint_1') }}</li>
						</ul>
					</div>
				</div>

				<div class="mt-1">
					<input type="file" id="tournament-photo-upload" accept="image/*" style="display:none">
					<button type="button" id="tournament-upload-photo-btn" class="btn btn-secondary f-13" style="padding:6px 14px">
						+ {{ __('tournaments.setup_photos_add') }}
					</button>
					<div class="f-13 cd mt-05">
						{!! __('tournaments.setup_photos_hint_2', ['link' => '<a target="_blank" href="' . route('user.photos') . '">' . __('tournaments.setup_photos_hint_2_link') . '</a>']) !!}
					</div>
				</div>
			</div>

			<div class="text-center mt-2">
				<form method="POST" action="{{ route('tournament.photos.store', $event) }}" id="tournament-photos-form">
					@csrf
					<input type="hidden" name="photo_ids" id="tournament_photos_input" value="">
					<button type="submit" class="btn btn-primary" id="tournament-photos-submit" style="display:none">{{ __('tournaments.setup_photos_save') }}</button>
				</form>
			</div>
		</div>
		@endif
		
		@foreach($stages as $stage)
		@php
		$borderColor = $stage->isCompleted() ? '#10b981' : ($stage->isInProgress() ? '#2967BA' : '#555');
		$_isDivStage = str_starts_with($stage->name, 'Группа ');
		$stageHasDivDistribution = !$_isDivStage && $stages->contains(fn($s) => str_starts_with($s->name, 'Группа ') && $s->occurrence_id == $stage->occurrence_id);
		@endphp
		{{-- King of the Beach: отдельный рендеринг --}}
		@if($stage->type === 'king_beach')
		@include('tournaments._partials.king_beach_stage', ['stage' => $stage, 'event' => $event, 'selectedOccurrence' => $selectedOccurrence])
		@continue
		@endif
		<div class="ramka" id="stage_{{ $stage->id }}">
			<div class="d-flex between fvc" style="flex-wrap:wrap;gap:8px">
				<div>
					<h2 class="-mt-05">
						{{ $stage->name }}
						@if($stage->isCompleted())
						<span class="f-18 alert-warning pt-05 pb-05 p-1">{{ __('tournaments.setup_st_completed') }}</span>
						@elseif($stage->isInProgress())
						<span class="f-18 alert-success pt-05 pb-05 p-1">{{ __('tournaments.setup_st_in_progress') }}</span>
						@else
						<span class="f-18 alert-info pt-05 pb-05 p-1">{{ __('tournaments.setup_st_waiting') }}</span>
						@endif
					</h2>
					<p>
						@php
						$stageTypeLabels = [
						'round_robin' => __('tournaments.setup_stage_lbl_round_robin'),
						'groups_playoff' => __('tournaments.setup_stage_lbl_groups_playoff'),
						'single_elim' => __('tournaments.setup_stage_lbl_single_elim'),
						'swiss' => __('tournaments.setup_stage_lbl_swiss'),
						'double_elim' => __('tournaments.setup_stage_lbl_double_elim'),
						'king_of_court' => __('tournaments.setup_stage_lbl_king_of_court'),
						'thai' => __('tournaments.setup_stage_lbl_thai'),
						'king_beach' => __('tournaments.setup_stage_lbl_king_beach'),
						];
						// Финал за места напрямую (finals_mode='placement') — плей-офф
						// (сетка) пропущен, "Олимпийка" описывала бы механику, которой
						// не было — подписываем явно (report_402_finals_bug.md).
						if ($stage->isPlacementFinal()) {
							$stageTypeLabels['single_elim'] = __('tournaments.setup_stage_lbl_placement_final');
						}
						$matchFormatLabels = ['bo1' => 'Best of 1', 'bo3' => 'Best of 3', 'bo5' => 'Best of 5'];
						@endphp
						{{ $stageTypeLabels[$stage->type] ?? $stage->type }} · {{ $matchFormatLabels[$stage->matchFormat()] ?? strtoupper($stage->matchFormat()) }} · {{ __('tournaments.score_to_pts') }} {{ $stage->setPoints() }} {{ __('tournaments.pub_pts_label') }}
					</p>
				</div>
				<div class="d-flex" style="gap:6px">
					@if($stage->isInProgress() || $stage->isCompleted())
					<form method="POST" action="{{ route('tournament.stages.revert', $stage) }}">
						@csrf
						<button class="btn btn-secondary f-12 btn-alert" data-title="{{ __('tournaments.setup_rollback_title') }}" data-icon="warning" data-confirm-text="{{ __('tournaments.setup_rollback_yes') }}" data-cancel-text="{{ __('tournaments.btn_cancel') }}">{{ __('tournaments.setup_btn_rollback') }}</button>
					</form>
					@endif
					<form method="POST" action="{{ route('tournament.stages.destroy', $stage) }}">
						@csrf @method('DELETE')
						<button class="btn btn-danger f-12 btn-alert" data-title="{{ __('tournaments.setup_delete_stage_title', ['name' => $stage->name]) }}" data-text="{{ __('tournaments.setup_delete_stage_text') }}" data-icon="warning" data-confirm-text="{{ __('tournaments.btn_delete') }}" data-cancel-text="{{ __('tournaments.btn_cancel') }}">{{ __('tournaments.setup_btn_delete_stage') }}</button>
					</form>
				</div>
			</div>
			
			{{-- Группы --}}
			@if($stage->groups->isNotEmpty())
			<div class="tabs-content mt-2">
				<div class="tabs tabs--underline">
					@foreach($stage->groups as $index => $group)
					<div class="tab" data-tab="group{{ $group->id }}">{{ $group->name }}</div>
					@endforeach
					<div class="tab-highlight"></div>
				</div>
				
				<div class="tab-panes">
					@foreach($stage->groups as $index => $group)
					<div class="tab-pane" id="group{{ $group->id }}">
						{{-- Содержимое группы --}}
						@if($group->standings->isNotEmpty())
						<div class="table-scrollable">
							<div class="table-drag-indicator"></div>
							<table class="table">
								<thead>
									<tr style="border-bottom:2px solid rgba(128,128,128,.2)">
										<th class="p-1" style="text-align:center;width:30px">{{ __('tournaments.setup_standings_col_pos') }}</th>
										<th class="p-1" style="text-align:left;min-width:18rem">{{ __('tournaments.standings_col_team') }}</th>
										<th class="p-1" style="text-align:center">{{ __('tournaments.standings_col_played') }}</th>
										<th class="p-1" style="text-align:center">{{ __('tournaments.standings_col_w') }}</th>
										<th class="p-1" style="text-align:center">{{ __('tournaments.standings_col_l') }}</th>
										<th class="p-1" style="text-align:center">{{ __('tournaments.setup_standings_col_pts') }}</th>
										<th class="p-1" style="text-align:center" title="{{ __('tournaments.setup_standings_col_diff_title') }}">{{ __('tournaments.tv_diff_col') }}</th>
									</tr>
								</thead>
								<tbody>
									@php
									$groupOutsiders = $outsidersByGroup[$group->id] ?? [];
									$groupClean     = $cleanStatsByGroup[$group->id] ?? [];
									$fmtDiff = fn($v) => ($v > 0 ? '+' : '') . $v;
									@endphp
									@foreach($group->standings->sortBy('rank') as $standing)
									@php
									$isOutsider = in_array((int) $standing->team_id, $groupOutsiders, true);
									$fullDiff   = $standing->points_scored - $standing->points_conceded;
									$cleanPs    = $groupClean[$standing->team_id]['points_scored']   ?? $standing->points_scored;
									$cleanPc    = $groupClean[$standing->team_id]['points_conceded'] ?? $standing->points_conceded;
									$cleanDiff  = $cleanPs - $cleanPc;
									@endphp
									<tr>
										<td style="text-align:center">{{ $standing->rank }}</td>
										<td>
											<div class="b-600 cd">@include('tournaments._partials.team_name_link', ['team' => $standing->team, 'showAvatar' => true])@if($isOutsider) <span class="f-16">{{ __('tournaments.setup_outsider_label') }}</span>@endif</div>
											@include('tournaments._partials.team_roster_line', ['team' => $standing->team, 'class' => 'f-13', 'showAvatar' => true])
										</td>
										<td style="text-align:center"><span class="b-600 alert-info pt-05 pb-05 p-1">{{ $standing->played }}</span></td>
										<td style="text-align:center;"><span class="b-600 alert-success pt-05 pb-05 p-1">{{ $standing->wins }}</span></td>
										<td style="text-align:center;"><span class="b-600 alert-danger pt-05 pb-05 p-1">{{ $standing->losses }}</span></td>
										<td class="b-600" style="text-align:center">{{ $standing->rating_points }}</td>
										<td style="text-align:center" title="{{ __('tournaments.setup_standings_col_diff_short_title') }}">
											@if($cleanDiff === $fullDiff)
											{{ $fmtDiff($fullDiff) }}
											@else
											<span class="b-600">{{ $fmtDiff($cleanDiff) }}</span><span style="color:#6b7280">&nbsp;/&nbsp;({{ $fmtDiff($fullDiff) }})</span>
											@endif
										</td>
									</tr>
									@endforeach
								</tbody>
							</table>
						</div>
						@elseif($group->teams->isNotEmpty())
						<div class="d-flex" style="flex-wrap:wrap;gap:6px">
							@foreach($group->teams as $team)
							<span class="p-1 px-2 f-12 b-600" style="background:rgba(41,103,186,.15);border-radius:6px">{{ $team->name }}</span>
							@endforeach
						</div>
						@endif
						
						{{-- Tiebreaker sets (множественные связки команд) --}}
						@php
						$groupSets = $tiebreakerSets[$group->id] ?? collect();
						$pendingSets  = $groupSets->where('status', 'pending');
						$resolvedSets = $groupSets->where('status', 'resolved');
						$teamNames = $group->standings->pluck('team.name', 'team_id');
						@endphp
						
						@if($pendingSets->isNotEmpty())
						<div class="mb-2 alert alert-info">
							<div class="b-600 mb-1">{{ __('tournaments.setup_tb_required') }}</div>
							@foreach($pendingSets as $tset)
							@php
							$tids = array_map('intval', $tset->team_ids ?? []);
							$labels = array_map(fn($tid) => $teamNames[$tid] ?? ('#' . $tid), $tids);
							@endphp
							
							<div class="b-600 mb-1">{{ implode(' = ', $labels) }}</div>
							
							@if($tset->method === 'match')
							<p>{{ __('tournaments.setup_tb_match_created') }}</p>
							@php $ms = $tset->match_settings ?? []; @endphp
							<p>{{ __('tournaments.setup_tb_rules', ['pts' => $ms['points_to_win'] ?? '?', 'margin' => !empty($ms['two_point_margin']) ? __('tournaments.setup_tb_two_point_margin') : '']) }}</p>
							@else
							<p>{{ __('tournaments.setup_tb_choose_method') }}</p>
							<div class="d-flex" style="gap:8px;flex-wrap:wrap;align-items:flex-start">
								{{-- Вариант 1: учесть матчи с аутсайдером (full diff) --}}
								<form method="POST" action="{{ route('tournament.tiebreaker.set.fullDiff', $tset) }}" style="display:inline">
									@csrf
									<input type="hidden" name="occurrence_id" value="{{ $selectedOccurrence?->id }}">
									<button type="submit" class="btn btn-secondary btn-alert"
									data-title="{{ __('tournaments.setup_tb_full_diff_title') }}" data-icon="info"
									data-confirm-text="{{ __('tournaments.setup_tb_btn_apply') }}" data-cancel-text="{{ __('tournaments.btn_cancel') }}">
										{{ __('tournaments.setup_tb_btn_full_diff') }}
									</button>
								</form>
								
								{{-- Вариант 2: сыграть мини-матчи --}}
								<button type="button" class="btn btn-secondary"
								onclick="document.getElementById('tbset-match-{{ $tset->id }}').style.display='block';this.style.display='none'">
									{{ __('tournaments.setup_tb_btn_match') }}
								</button>
								
								{{-- Вариант 3: жребий --}}
								<button type="button" class="btn btn-secondary"
								onclick="document.getElementById('tbset-lot-{{ $tset->id }}').style.display='block';this.style.display='none'">
									{{ __('tournaments.setup_tb_btn_lottery') }}
								</button>
							</div>
							
							{{-- Форма мини-матчей --}}
							<div id="tbset-match-{{ $tset->id }}" style="display:none;margin-top:8px">
								<form method="POST" action="{{ route('tournament.tiebreaker.set.matches', $tset) }}" class="d-flex" style="gap:8px;align-items:center;flex-wrap:wrap">
									@csrf
									<input type="hidden" name="occurrence_id" value="{{ $selectedOccurrence?->id }}">
									<label class="f-12 fvc" style="gap:4px">{{ __('tournaments.setup_tb_to') }}
										<input type="number" name="points_to_win" value="15" min="1" max="30" class="form-control f-13" style="width:70px;padding:2px 6px" required>
									{{ __('tournaments.setup_tb_pts_short') }}</label>
									<label class="fvc" style="gap:4px">
										<input type="checkbox" name="two_point_margin" value="1"> {{ __('tournaments.setup_tb_two_pt') }}
									</label>
									<button type="submit" class="btn btn-primary">{{ __('tournaments.setup_tb_btn_create_matches') }}</button>
								</form>
								<div class="f-11 mt-1" style="opacity:.6">{{ __('tournaments.setup_tb_match_count', ['n' => count($tids) * (count($tids) - 1) / 2]) }}</div>
							</div>
							
							{{-- Форма жребия --}}
							<div id="tbset-lot-{{ $tset->id }}" style="display:none;margin-top:8px">
								<form method="POST" action="{{ route('tournament.tiebreaker.set.lottery', $tset) }}" class="d-flex" style="gap:6px;align-items:center;flex-wrap:wrap">
									@csrf
									<input type="hidden" name="occurrence_id" value="{{ $selectedOccurrence?->id }}">
									<span class="f-12" style="opacity:.7">{{ __('tournaments.setup_tb_order') }}</span>
									@foreach($tids as $i => $tid)
									<select name="order[]" class="form-control f-12" style="width:auto;min-width:120px;padding:2px 6px" required>
										<option value="">{{ __('tournaments.setup_tb_place_n', ['n' => $i + 1]) }}</option>
										@foreach($tids as $tid2)
										<option value="{{ $tid2 }}">{{ $teamNames[$tid2] ?? ('#' . $tid2) }}</option>
										@endforeach
									</select>
									@endforeach
									<button type="submit" class="btn btn-primary f-12" style="padding:4px 12px">{{ __('tournaments.setup_tb_btn_confirm') }}</button>
								</form>
							</div>
							@endif
							
							@endforeach
						</div>
						@endif
						
						@if($resolvedSets->isNotEmpty())
						<div class="mb-2 alert alert-success">
							@foreach($resolvedSets as $rset)
							@php
							$order  = $rset->resolved_order ?: [];
							$labels = array_map(fn($tid) => $teamNames[(int) $tid] ?? ('#' . $tid), $order);
							$methodLabel = ['full_diff' => __('tournaments.setup_tb_method_full_diff'), 'match' => __('tournaments.setup_tb_method_match'), 'lottery' => __('tournaments.setup_tb_method_lottery')][$rset->method] ?? $rset->method;
							@endphp
							<p>{{ __('tournaments.setup_tb_resolved', ['method' => $methodLabel, 'order' => implode(' → ', $labels)]) }}</p>
							@endforeach
						</div>
						@endif
					</div>
					@endforeach
				</div>
			</div>
			@endif
			
			
			
			{{-- Матчи --}}
			@if($stage->matches->isNotEmpty())
			@php
			$matchesByGroup = $stage->matches->sortBy(["round", "match_number"])->groupBy('group_id');
			$hasGroups = $stage->groups->count() > 1;
			@endphp
			
			<div class="tabs-content">
				<div class="tabs tabs--underline">
					@foreach($matchesByGroup as $groupId => $groupMatches)
					@php $groupName = $stage->groups->firstWhere('id', $groupId)?->name ?? ''; @endphp
					<div class="tab" data-tab="matches-group{{ $groupId }}">{{ $groupName ? __('tournaments.setup_tab_matches_group', ['name' => $groupName]) : __('tournaments.setup_tab_matches') }}</div>
					@endforeach
					<div class="tab-highlight"></div>
				</div>
				
				<div class="tab-panes">
					@foreach($matchesByGroup as $groupId => $groupMatches)
					@php
					$groupName       = $stage->groups->firstWhere('id', $groupId)?->name ?? '';
					$groupForCross   = $stage->groups->firstWhere('id', $groupId);
					$crossClean      = $cleanStatsByGroup[$groupId] ?? [];
					$crossOutsiders  = $outsidersByGroup[$groupId] ?? [];
					$hasCrosstable   = $groupForCross && $groupForCross->standings->isNotEmpty();
					@endphp
					<div class="tab-pane" id="matches-group{{ $groupId }}">

						@if($hasCrosstable)
						<div class="d-flex fvc mb-2" style="gap:6px">
							<button class="btn btn-small btn-secondary ct-view-btn ct-view-btn--active" data-group="{{ $groupId }}" data-view="list" style="font-size:12px">📋 {{ __('tournaments.view_list') }}</button>
							<button class="btn btn-small btn-secondary ct-view-btn" data-group="{{ $groupId }}" data-view="crosstable" style="font-size:12px">📊 {{ __('tournaments.view_crosstable') }}</button>
						</div>
						@endif

						<div class="ct-view-list" data-group="{{ $groupId }}">
							<div class="table-scrollable">
								<div class="table-drag-indicator"></div>
								<table class="table">
									<thead>
										<tr style="border-bottom:2px solid rgba(128,128,128,.2)">
											<th class="p-1" style="text-align:left">#</th>
											<th class="p-1" style="text-align:left">{{ __('tournaments.setup_matches_col_round') }}</th>
											<th class="p-1" style="text-align:left;min-width:14rem">{{ __('tournaments.setup_matches_col_home') }}</th>
											<th class="p-1" style="text-align:left;min-width:14rem">{{ __('tournaments.setup_matches_col_away') }}</th>
											<th class="p-1" style="text-align:center">{{ __('tournaments.setup_mvp_col_sets') }}</th>
											<th class="p-1" style="text-align:center">{{ __('tournaments.setup_matches_col_score') }}</th>
											<th class="p-1" style="text-align:center">{{ __('tournaments.setup_matches_col_time') }}</th>
											<th class="p-1" style="text-align:center">{{ __('tournaments.setup_matches_col_court') }}</th>
											<th class="p-1" style="text-align:center">{{ __('tournaments.setup_matches_col_status') }}</th>
											<th class="p-1" style="text-align:center">{{ __('tournaments.setup_matches_col_actions') }}</th>
										</tr>
									</thead>
									<tbody>
										@foreach($groupMatches as $match)
										<tr>
											<td>{{ $match->match_number }}</td>
											<td>R{{ $match->round }}</td>
											<td>
												<div class="{{ $match->winner_team_id === $match->team_home_id ? 'cd b-600' : '' }}">@include('tournaments._partials.team_name_link', ['team' => $match->teamHome, 'fallback' => 'TBD', 'showAvatar' => true])</div>
												@include('tournaments._partials.team_roster_line', ['team' => $match->teamHome, 'class' => 'f-13', 'showAvatar' => true])
											</td>
											<td>
												<div class="{{ $match->winner_team_id === $match->team_away_id ? 'cd b-600' : '' }}">@include('tournaments._partials.team_name_link', ['team' => $match->teamAway, 'fallback' => 'TBD', 'showAvatar' => true])</div>
												@include('tournaments._partials.team_roster_line', ['team' => $match->teamAway, 'class' => 'f-13', 'showAvatar' => true])
											</td>
											<td style="text-align:center">{{ $match->setsScore() ?? '—' }}</td>
											<td style="text-align:center">{{ $match->detailedScore() ?: '—' }}</td>
											<td style="text-align:center">{{ $match->scheduled_at ? $match->scheduled_at->setTimezone($event->timezone ?? 'Europe/Moscow')->format('H:i') : '—' }}</td>
											<td style="text-align:center">{{ $match->court ?? '—' }}</td>
											<td style="text-align:center">
												<div class="text-center d-flex gap-1">
													@if($match->isCompleted())
													<span class="b-600 alert-success pt-05 pb-05 p-1">✓</span>
													@if(!$stageHasDivDistribution)
													<a href="{{ route('tournament.matches.score.form', $match) }}?edit=1" class="btn icon-edit btn-svg btn-secondary" title="{{ __('tournaments.setup_match_fix_title') }}"></a>
													@endif
													@elseif($match->status === 'live')
													<span class="b-600 alert-danger pt-05 pb-05 p-1">LIVE</span>
													@else
													<span class="b-600 alert-warning pt-05 pb-05 p-1">{{ __('tournaments.setup_match_pending') }}</span>
													@endif
												</div>	
											</td>
											<td class="p-1">
												@if(($match->isScheduled() || $match->isLive()) && $match->hasTeams())
												<a href="{{ route('tournament.matches.score.form', $match) }}" class="btn btn-primary btn-small">
													{{ __('tournaments.setup_match_btn_score') }}
												</a>
												@endif
												@if($match->isCompleted())
												<a href="{{ route('tournament.matches.player_stats.form', $match) }}" class="btn btn-secondary btn-small" title="{{ __('tournaments.setup_match_player_stats_title') }}">
													📊
												</a>
												<a href="{{ route('tournament.matches.pdf_stats', $match) }}" class="btn btn-secondary btn-small" title="{{ __('tournaments.rally_btn_pdf_stats') }}">
													📊 PDF
												</a>
												@endif
											</td>
										</tr>
										@endforeach
									</tbody>
								</table>
							</div>
						</div>

						@if($hasCrosstable)
						<div class="ct-view-crosstable" data-group="{{ $groupId }}" style="display:none">
							@include('tournaments._partials.group_crosstable', [
								'group'          => $groupForCross,
								'groupMatches'   => $groupMatches,
								'groupClean'     => $crossClean,
								'groupOutsiders' => $crossOutsiders,
							])
						</div>
						@endif

					</div>
					@endforeach
				</div>
			</div>
			
			
			@php
			$hasUnplayed = $stage->matches->where('status', 'scheduled')
			->filter(fn($m) => $m->team_home_id && $m->team_away_id)->isNotEmpty();
			@endphp
			@if($hasUnplayed)
			<div class="mt-2 mb-3" style="text-align:center">
				<a href="{{ route('tournament.start_scoring', $event) }}" class="btn btn-primary p-3 f-16" style="display:inline-block">
					{{ __('tournaments.setup_btn_start_results') }}
				</a>
			</div>
			@endif
			
			
			{{-- Следующий тур (Swiss/King) --}}
			@if($stage->isInProgress() && in_array($stage->type, ['swiss', 'king_of_court']))
			<div class="p-3 mt-2" style="background:rgba(231,97,47,.08);border-radius:10px">
				<form method="POST" action="{{ route('tournament.stages.nextRound', $stage) }}" class="d-flex fvc" style="gap:10px">
					@csrf
					<div class="b-600">
						{{ $stage->type === 'swiss' ? __('tournaments.setup_btn_swiss_next') : __('tournaments.setup_btn_koc_next') }}
					</div>
					<button type="submit" class="btn btn-primary">{{ __('tournaments.setup_btn_next_arrow') }}</button>
				</form>
			</div>
			@endif
			
		</div>
		
		@endif
		{{-- Продвижение / Группы --}}
		@php
			$finalsMode = $stage->cfg('finals_mode');
			// division_tier — структурный признак дивизионных стадий (Hard/Medium/Lite),
			// заполняется formDivisions() и для сезонных, и для несезонных турниров;
			// паттерн по имени — фоллбэк для стадий, созданных до появления поля
			// (см. report_division_tier_migration_plan_2026-08-04.md).
			$divisionStagesForThis = $stages->filter(fn($s) => $s->division_tier !== null || str_starts_with($s->name, 'Группа '));
			$hasDivStages = $divisionStagesForThis->isNotEmpty();
			$divStagesAllCompleted = $hasDivStages && $divisionStagesForThis->every(fn($s) => $s->status === 'completed');
			$finalsTargetStages = $stages->where('type', 'single_elim')->whereIn('status', ['pending', 'in_progress', 'completed']);
			$isTwoGroups = $stage->groups->count() === 2;
			$finalsModeDefault = $stage->cfg('finals_mode', $isTwoGroups ? 'placement' : 'bracket');
			if (!$isTwoGroups) { $finalsModeDefault = 'bracket'; }
		@endphp

		{{-- Кусок 2, шаг 2b: карточка запуска divisions-скелета ("Финальные группы").
		     Показывается для pending round_robin-скелета с finals_mode=divisions, когда
		     предыдущая (групповая) стадия завершена. Кнопка → launchStage → formDivisionsCore
		     (создаёт Группы Hard/Lite из standings группы) + метит скелет completed. --}}
		@php
			$isDivSkeleton = $stage->type === 'round_robin' && $stage->isPending()
				&& $stage->cfg('finals_mode') === 'divisions' && $stage->groups->isEmpty();
			$divSkeletonPrev = $isDivSkeleton ? $stages->where('occurrence_id', $stage->occurrence_id)
				->where('sort_order', '<', $stage->sort_order)->sortByDesc('sort_order')->first() : null;
		@endphp
		@if($isDivSkeleton)
		<div class="ramka">
			<h2 class="-mt-05">{{ __('tournaments.setup_groups_h2') }}</h2>
			@if(!$divSkeletonPrev || !$divSkeletonPrev->isCompleted())
				<p class="alert-info p-2">{{ __('tournaments.setup_launch_wait_prev') }}</p>
			@else
				@php
				$prevGroupsCount = $divSkeletonPrev->groups->count();
				$divNames = \App\Models\TournamentStage::divisionNamesFor($prevGroupsCount);
				$prevCourts = $divSkeletonPrev->cfg('courts', []);
				@endphp
				<p>{{ __('tournaments.setup_groups_redistribute', ['n' => count($divNames), 'plural' => '']) }} <strong>{{ implode(', ', $divNames) }}</strong></p>
				<form method="POST" action="{{ route('tournament.stages.launch', $stage) }}" class="form">
					@csrf
					@if(count($prevCourts) > 0)
					<div class="card mb-2">
						<label>{{ __('tournaments.setup_stage_courts_for_groups') }}</label><hr class="mb-1">
						<div class="row">
						@foreach($divNames as $dn)
							<div class="col-md-{{ (int)(12 / max(1,count($divNames))) }} mb-2">
								<label>{{ $dn }}:</label>
								<div class="d-flex" style="flex-wrap:wrap;gap:6px">
								@foreach($prevCourts as $court)
									<label class="checkbox-item f-13 pr-2" style="margin:0"><input type="checkbox" name="div_courts_{{ strtolower($dn) }}[]" value="{{ $court }}"><div class="custom-checkbox"></div><span>{{ $court }}</span></label>
								@endforeach
								</div>
							</div>
						@endforeach
						</div>
					</div>
					@endif
					<div class="card mb-2">
						<label>{{ __('tournaments.setup_stage_schedule') }}</label>
						<p>{{ __('tournaments.setup_groups_schedule_hint') }}</p><hr class="mb-1">
						<div class="d-flex" style="gap:12px;flex-wrap:wrap;align-items:flex-end">
							<div><label>{{ __('tournaments.setup_stage_start') }}</label><input type="datetime-local" name="schedule_start" value="{{ \Carbon\Carbon::now($event->timezone ?? 'Europe/Moscow')->format('Y-m-d\TH:i') }}"></div>
							<div><label>{{ __('tournaments.setup_stage_match_min') }}</label><input type="number" name="schedule_match_duration" value="30" min="15" max="180"></div>
							<div><label>{{ __('tournaments.setup_stage_break_min') }}</label><input type="number" name="schedule_break_duration" value="5" min="0" max="60"></div>
						</div>
					</div>
					<button type="submit" class="btn btn-primary btn-alert" data-title="{{ __('tournaments.setup_groups_create_title') }}" data-icon="question" data-confirm-text="{{ __('tournaments.setup_groups_create_yes') }}" data-cancel-text="{{ __('tournaments.btn_cancel') }}">{{ __('tournaments.setup_groups_btn_create') }}</button>
				</form>
			@endif
		</div>
		@endif

		{{-- Состояние 1 из 3 — "Настройка финальных групп" (Hard/Medium/Lite):
		     ТОЛЬКО если организатор в мастере явно выбрал finals_mode=divisions
		     (не placement, не bracket), групповой этап завершён, и сами дивизионные
		     стадии либо ещё не сформированы, либо сформированы, но не все доиграны.
		     Плюс общий гейт !$allCompleted — на полностью закрытом турнире формирование
		     финалов невозможно по определению (и не должно предлагаться повторно).
		     ВАЖНО: это НЕЗАВИСИМЫЙ @if, не @else состояния 2 — раньше здесь было
		     @if($stage->groups->count() >= 2) / @else, из-за чего для ЛЮБОЙ стадии
		     ровно с 2 группами (типичный случай placement/bracket-финала) состояние 2
		     ("Сгенерировать финалы") становилось НЕДОСТИЖИМЫМ — регресс коммита
		     6af49bad, ломавший 2-групповые placement-турниры (напр. event 402). --}}
		@if(!$allCompleted && $stage->isCompleted() && $stage->canHaveFollowupStage()
			&& $stage->groups->count() >= 2 && $finalsMode === 'divisions' && !$divStagesAllCompleted
			&& !$stages->contains(fn($sk) => $sk->type === 'round_robin' && $sk->isPending()
				&& $sk->cfg('finals_mode') === 'divisions' && $sk->groups->isEmpty()
				&& $sk->occurrence_id === $stage->occurrence_id))
		<div class="ramka">
			<div class="d-flex between fvc" style="cursor:pointer" onclick="var b=this.nextElementSibling;b.style.display=b.style.display==='none'?'':'none';var ic=this.querySelector('.toggle-icon');if(ic)ic.textContent=b.style.display==='none'?'+':'-'">
				<h2 class="-mt-05">{{ __('tournaments.setup_groups_h2') }}</h2>
				<span class="toggle-icon b-600 f-20">{{ $hasDivStages ? '+' : '-' }}</span>
			</div>
			<div style="{{ $hasDivStages ? 'display:none' : '' }}">
				@php
				$groupsCount = $stage->groups->count();
				$divisionNames = \App\Models\TournamentStage::divisionNamesFor($groupsCount);
				$availCourts = $stage->cfg('courts', []);
				@endphp

				<p>
					{{ __('tournaments.setup_groups_redistribute', ['n' => count($divisionNames), 'plural' => count($divisionNames) > 2 ? '' : '']) }}
					<strong>{{ implode(', ', $divisionNames) }}</strong>
				</p>

				{{-- Формат матча по дивизионам и "выходят в Hard" задаются в мастере
				     при создании стадии (finals_mode=divisions) — cfg('div_format_hard'
				     /'_medium'/'_lite', 'advance_per_group'), formDivisions() читает их
				     напрямую из конфига. На пульте остаются только площадки/расписание —
				     логистика дня турнира, не решения формата. --}}
				<form method="POST" action="{{ route('tournament.stages.formDivisions', $stage) }}" class="form">
					@csrf

					{{-- Ряд 2: Площадки --}}
					@if(count($availCourts) > 0)
					<div class="card mb-2">
						<label>{{ __('tournaments.setup_stage_courts_for_groups') }}</label>
						<hr class="mb-1">
						<div class="row">
							@foreach($divisionNames as $dn)
							<div class="col-md-{{ (int)(12 / count($divisionNames)) }} mb-2">
								<label>{{ $dn }}:</label>
								<div class="d-flex" style="flex-wrap:wrap;gap:6px">
									@foreach($availCourts as $court)
									<label class="checkbox-item f-13 pr-2" style="margin:0">
										<input type="checkbox" name="div_courts_{{ strtolower($dn) }}[]" value="{{ $court }}">
										<div class="custom-checkbox"></div>
										<span>{{ $court }}</span>
									</label>
									@endforeach
								</div>
							</div>
							@endforeach
						</div>
					</div>
					@endif
					
					{{-- Расписание --}}
					<div class="card mb-2">
						<label>{{ __('tournaments.setup_stage_schedule') }}</label>
						<p>{{ __('tournaments.setup_groups_schedule_hint') }}</p>
						<hr class="mb-1">
						<div class="d-flex" style="gap:12px;flex-wrap:wrap;align-items:flex-end">
							<div>
								<label>{{ __('tournaments.setup_stage_start') }}</label>
								<input type="datetime-local" name="schedule_start" value="{{ \Carbon\Carbon::now($event->timezone ?? 'Europe/Moscow')->format('Y-m-d\TH:i') }}">
							</div>
							<div>
								<label>{{ __('tournaments.setup_stage_match_min') }}</label>
								<input type="number" name="schedule_match_duration" value="30" min="15" max="180">
							</div>
							<div>
								<label>{{ __('tournaments.setup_stage_break_min') }}</label>
								<input type="number" name="schedule_break_duration" value="5" min="0" max="60">
							</div>
						</div>
					</div>
					
					<button type="submit" class="btn btn-primary btn-alert" data-title="{{ __('tournaments.setup_groups_create_title') }}" data-icon="question" data-confirm-text="{{ __('tournaments.setup_groups_create_yes') }}" data-cancel-text="{{ __('tournaments.btn_cancel') }}">{{ __('tournaments.setup_groups_btn_create') }}</button>
				</form>
			</div>
		</div>
		@endif

		{{-- Состояние 2 из 3 — "Сгенерировать финалы" (placement-кроссовер или
		     bracket-плей-офф): когда finals_mode НЕ divisions, и целевая single_elim
		     стадия уже существует, но ещё не доиграна. Восстанавливает достижимость
		     для 2-групповых стадий (event 402) — раньше была недостижима из-за
		     структуры @if/@else с состоянием 1.
		     !$hasDivStages — сезонные дивизионы (Группа Hard/Lite/...) создаются
		     ДРУГИМ механизмом (formDivisions(), не finals_mode-мастером) и никогда
		     не получают finals_mode='divisions' в config — без этого гейта состояния
		     2/3 всплывали и для родительской "Групповой этап", и для уже терминальной
		     "Группа Hard", хотя финалы (дивизионы) уже сформированы (event 376, см.
		     report_league_tournament_setup_diag_2026-08-07.md). --}}
		@php
			// Кусок 2, шаг 2+3 (B-полный): если у этого occurrence РОВНО один pending
			// single_elim скелет (bracket/placement, созданный в createStage() —
			// см. Кусок 2, шаг 2b) — показываем прямую подсказку "запустить именно
			// его" вместо общего select'а. При 0 (нет скелета вообще — своё "Состояние
			// 3" ниже) или 2+ (организатор вручную создал несколько single_elim
			// стадий — редкий edge case, на dev не встречается, см.
			// report/kusok_bpolny_step23_dump_2026-08-19.md) — остаётся старый блок
			// с явным выбором целевой стадии (fallback, без изменений).
			$pendingSkeletons = $finalsTargetStages->filter(fn($s) => $s->isPending());
			$bpSkeleton = $pendingSkeletons->count() === 1 ? $pendingSkeletons->first() : null;
			$bpMode = $bpSkeleton?->cfg('finals_mode') ?? $finalsMode;
		@endphp
		@if(!$allCompleted && $stage->isCompleted() && $stage->canHaveFollowupStage()
			&& $finalsMode !== 'divisions' && !$hasDivStages && $finalsTargetStages->isNotEmpty()
			&& $finalsTargetStages->contains(fn($s) => !$s->isCompleted()))
			@if($bpSkeleton && $bpMode !== 'divisions')
			{{-- Подсказка для единственного pending-скелета — по образцу divisions-подсказки
			     выше (isDivSkeleton): цель предопределена ($bpSkeleton), режим уже решён в
			     мастере при создании стадии — organizr только подтверждает запуск, без
			     переопределения режима и без выбора стадии из списка. --}}
			<div class="ramka" id="launch_finals_hint">
				<h2 class="-mt-05">{{ __('tournaments.setup_launch_hint_h2') }}</h2>
				<p>
					@if($bpMode === 'bracket')
					{{ __('tournaments.setup_launch_hint_bracket', ['n' => $stage->groups->count() * (int) $stage->cfg('advance_count', 2)]) }}
					@else
					{{ __('tournaments.setup_launch_hint_placement', ['n' => 4]) }}
					@endif
					— <strong>{{ $bpMode === 'bracket' ? __('tournaments.setup_finals_mode_bracket') : __('tournaments.setup_finals_mode_placement') }}</strong>
				</p>
				<form method="POST" action="{{ route('tournament.stages.launch', $bpSkeleton) }}" class="form">
					@csrf
					<div class="card mb-2">
						@if($bpMode === 'bracket')
						<label>{{ __('tournaments.setup_promote_advance') }}</label>
						<input type="number" name="advance_per_group" value="{{ (int) $stage->cfg('advance_count', 2) }}" min="1" max="8" style="width:100px">
						@else
						<label>{{ __('tournaments.setup_crossover_places') }}</label>
						<select name="places_count">
							<option value="2">{{ __('tournaments.setup_crossover_places_2') }}</option>
							<option value="4" selected>{{ __('tournaments.setup_crossover_places_4') }}</option>
						</select>
						@endif
					</div>
					<div class="card mb-2">
						<label>{{ __('tournaments.setup_stage_schedule') }}</label>
						<p>{{ __('tournaments.setup_groups_schedule_hint') }}</p><hr class="mb-1">
						<div class="d-flex" style="gap:12px;flex-wrap:wrap;align-items:flex-end">
							<div><label>{{ __('tournaments.setup_stage_start') }}</label><input type="datetime-local" name="schedule_start" value="{{ \Carbon\Carbon::now($event->timezone ?? 'Europe/Moscow')->format('Y-m-d\TH:i') }}"></div>
							<div><label>{{ __('tournaments.setup_stage_match_min') }}</label><input type="number" name="schedule_match_duration" value="30" min="15" max="180"></div>
							<div><label>{{ __('tournaments.setup_stage_break_min') }}</label><input type="number" name="schedule_break_duration" value="5" min="0" max="60"></div>
						</div>
					</div>
					<button type="submit" class="btn btn-primary btn-alert" data-title="{{ __('tournaments.setup_launch_hint_confirm') }}" data-icon="question" data-confirm-text="{{ __('tournaments.yes') }}" data-cancel-text="{{ __('tournaments.btn_cancel') }}">{{ __('tournaments.setup_launch_hint_btn') }}</button>
				</form>
			</div>
			@else
			{{-- FALLBACK — старый блок без изменений: 0 pending-скелетов среди
			     $finalsTargetStages (тут не может быть — тогда $finalsTargetStages
			     было бы empty и внешний @if не пройдёт), 2+ pending-скелетов (organizer
			     создал несколько single_elim вручную), либо остались только
			     in_progress/completed-но-недоигранные стадии (напр. дозаполнение
			     матча за 3-4 после того как за 1-2 уже сыграли) — во всех этих
			     случаях нужен явный выбор целевой стадии, оставляем как было. --}}
			<div class="p-3 mt-2" style="background:rgba(41,103,186,.08);border-radius:10px" id="generate_finals_block">
				<div class="b-700 mb-2">{{ __('tournaments.setup_generate_finals_h4') }}</div>
				<div class="d-flex fvc" style="gap:10px;flex-wrap:wrap">
					<div>
						<label class="f-13 b-600 mb-1 d-block">{{ __('tournaments.setup_promote_stage') }}</label>
						<select id="finals_target_stage_select">
							@foreach($finalsTargetStages as $ns)
							<option value="{{ $ns->id }}">{{ $ns->name }}</option>
							@endforeach
						</select>
					</div>
					<div>
						<label class="f-13 b-600 mb-1 d-block">{{ __('tournaments.setup_finals_mode_override_label') }}</label>
						<select id="finals_mode_override_select">
							<option value="placement" {{ $finalsModeDefault === 'placement' ? 'selected' : '' }} {{ !$isTwoGroups ? 'disabled' : '' }}>
								{{ __('tournaments.setup_finals_mode_placement') }}{{ !$isTwoGroups ? ' (' . __('tournaments.setup_finals_mode_disabled_short') . ')' : '' }}
							</option>
							<option value="bracket" {{ $finalsModeDefault === 'bracket' ? 'selected' : '' }}>{{ __('tournaments.setup_finals_mode_bracket') }}</option>
						</select>
					</div>
					<div id="finals_bracket_extra">
						<label class="f-13 b-600 mb-1 d-block">{{ __('tournaments.setup_promote_advance') }}</label>
						<input type="number" id="finals_advance_per_group" value="{{ $stage->cfg('advance_count', 2) }}" min="1" max="8" style="width:100px">
					</div>
					<div id="finals_placement_extra" style="display:none">
						<label class="f-13 b-600 mb-1 d-block">{{ __('tournaments.setup_crossover_places') }}</label>
						<select id="finals_places_count">
							<option value="2">{{ __('tournaments.setup_crossover_places_2') }}</option>
							<option value="4" selected>{{ __('tournaments.setup_crossover_places_4') }}</option>
						</select>
					</div>
					<button type="button" id="finals_generate_btn" class="btn btn-primary">{{ __('tournaments.setup_generate_finals_btn') }}</button>
				</div>

				{{-- Реальные формы — скрыты, JS сабмитит нужную по выбранному режиму --}}
				<form method="POST" action="{{ route('tournament.stages.advance', $stage) }}" id="finals_bracket_form" style="display:none">
					@csrf
					<input type="hidden" name="playoff_stage_id">
					<input type="hidden" name="advance_per_group">
				</form>
				<form method="POST" action="{{ route('tournament.stages.advanceCrossover', $stage) }}" id="finals_placement_form" style="display:none">
					@csrf
					<input type="hidden" name="playoff_stage_id">
					<input type="hidden" name="places_count">
				</form>
			</div>
			<script>
				(function() {
					var modeSel = document.getElementById('finals_mode_override_select');
					var targetSel = document.getElementById('finals_target_stage_select');
					var bracketExtra = document.getElementById('finals_bracket_extra');
					var placementExtra = document.getElementById('finals_placement_extra');
					var advanceInput = document.getElementById('finals_advance_per_group');
					var placesSelect = document.getElementById('finals_places_count');
					var btn = document.getElementById('finals_generate_btn');
					var bracketForm = document.getElementById('finals_bracket_form');
					var placementForm = document.getElementById('finals_placement_form');
					if (!modeSel || !btn) return;

					function syncVisibility() {
						var isPlacement = modeSel.value === 'placement';
						bracketExtra.style.display = isPlacement ? 'none' : '';
						placementExtra.style.display = isPlacement ? '' : 'none';
					}
					modeSel.addEventListener('change', syncVisibility);
					syncVisibility();

					btn.addEventListener('click', function() {
						var stageId = targetSel.value;
						if (modeSel.value === 'placement') {
							placementForm.querySelector('[name="playoff_stage_id"]').value = stageId;
							placementForm.querySelector('[name="places_count"]').value = placesSelect.value;
							placementForm.submit();
						} else {
							bracketForm.querySelector('[name="playoff_stage_id"]').value = stageId;
							bracketForm.querySelector('[name="advance_per_group"]').value = advanceInput.value;
							bracketForm.submit();
						}
					});
				})();
			</script>
			@endif
		@endif

		{{-- Состояние 3 из 3 — "Финальная стадия не создана": целевой single_elim
		     стадии нет вообще (ни pending, ни completed) — предлагаем быстро создать.
		     !$hasDivStages — см. комментарий у состояния 2 выше: если дивизионы
		     (Группа Hard/Lite/...) для этого occurrence уже созданы, финалы турнира
		     уже сформированы этим путём — предлагать "Создать финалы" не нужно. --}}
		@if(!$allCompleted && $stage->isCompleted() && $stage->canHaveFollowupStage()
			&& $finalsMode !== 'divisions' && !$hasDivStages && $finalsTargetStages->isEmpty()
			&& $stage->groups->count() >= 2)
		<div class="p-3 mt-2 alert-warning" style="border-radius:10px">
			<div class="b-700 mb-1">{{ __('tournaments.setup_no_finals_stage_h4') }}</div>
			<p class="f-13" style="margin-bottom:10px">{{ __('tournaments.setup_no_finals_stage_hint') }}</p>
			<form method="POST" action="{{ route('tournament.stages.quickCreateFinals', $stage) }}">
				@csrf
				<button type="submit" class="btn btn-primary btn-alert" data-title="{{ __('tournaments.setup_no_finals_stage_confirm_title') }}" data-icon="question" data-confirm-text="{{ __('tournaments.yes') }}" data-cancel-text="{{ __('tournaments.btn_cancel') }}">{{ __('tournaments.setup_no_finals_stage_btn') }}</button>
			</form>
		</div>
		@endif

		@endforeach
		
		{{-- Промоушен после групп --}}
		<div id="promotion_block"></div>
		@if($event->season_id && $stages->isNotEmpty())
		@php
		$divStages = $stages->filter(fn($s) => str_starts_with($s->name, 'Группа '));
		$allDivsCompleted = $divStages->isNotEmpty() && $divStages->every(fn($s) => $s->status === 'completed');
		$hasMedium = $divStages->contains(fn($s) => str_contains($s->name, 'Medium'));
		@endphp
		@if($allDivsCompleted)
		<div class="ramka">
			<h3 style="margin:0 0 8px">{{ __('tournaments.setup_groups_completed_h3') }}</h3>
			<p class="f-14" style="color:#6b7280;margin-bottom:12px">
				{{ __('tournaments.setup_groups_completed_text', ['medium' => $hasMedium ? __('tournaments.setup_groups_with_medium') : '']) }}
			</p>
			<form method="POST" action="{{ route('tournament.applyPromotion', $event) }}">
				@csrf
				<button type="submit" class="btn btn-primary btn-alert" data-title="{{ __('tournaments.setup_groups_apply_promotion_title') }}" data-icon="question" data-confirm-text="{{ __('tournaments.setup_groups_apply_yes') }}" data-cancel-text="{{ __('tournaments.btn_cancel') }}">{{ __('tournaments.setup_groups_apply_promotion') }}</button>
			</form>
		</div>
		@endif
		
		{{-- Кнопка результатов тура --}}
		@if($divStages->isNotEmpty())
		<div class="ramka" style="text-align:center">
			<a href="{{ route('tournament.public.show', $event) }}{{ $selectedOccurrence ? '?occurrence_id=' . $selectedOccurrence->id : '' }}" class="btn btn-primary p-3 f-16" style="display:inline-block">
				{{ __('tournaments.setup_btn_round_results') }}
			</a>
		</div>
		@endif
		@endif

		{{-- Кнопка результатов турнира (без сезона — обычный/standalone турнир без дивизионов).
		     БАГ №3 (2026-08-15): раньше здесь была независимая копия naive-формулы
		     $stages->every(...) без season/divisions/companion-оговорок $allCompleted
		     (вычисленного в блоке "Создание стадии" выше) — из-за этого кнопка
		     "Результаты турнира" могла появиться даже когда $allCompleted уже корректно
		     false (например, плей-офф-стадия удалена). Переиспользуем $allCompleted. --}}
		@if(!$event->season_id && $allCompleted)
		<div class="ramka" style="text-align:center">
			<a href="{{ route('tournament.public.show', $event) }}{{ $selectedOccurrence ? '?occurrence_id=' . $selectedOccurrence->id : '' }}" class="btn btn-primary p-3 f-16" style="display:inline-block">
				{{ __('tournaments.setup_btn_tournament_results') }}
			</a>
		</div>
		@endif

		@if($stages->isEmpty())
		<div class="ramka" style="text-align:center">
			<p class="f-18 b-600">{{ __('tournaments.setup_empty_h2') }}</p>
			<p>{{ __('tournaments.setup_empty_text') }}</p>
		</div>
		@endif


	</div>

	@php
		// Живой каскад-предпросмотр: тексты через __() с placeholder'ами
		// заранее в @php, НЕ напрямую внутри @json(__(...)) — вложенный
		// массив-аргумент внутри @json(__(...)) ломает извлечение аргументов
		// blade-директивы ("Unclosed '[' does not match ')'"), см. CLAUDE.md.
		$cascadeSingleGroupText = __('tournaments.setup_cascade_single_group');
		$cascadeDirectOnlyText = __('tournaments.setup_cascade_direct_only', ['direct' => 'DIRECT_N', 'noun' => 'NOUN_WORD', 'verb' => 'VERB_WORD', 'size' => 'SIZE_N']);
		$cascadeRank2Text = __('tournaments.setup_cascade_rank_2');
		$cascadeRank3Text = __('tournaments.setup_cascade_rank_3');
		$cascadeRankGenericText = __('tournaments.setup_cascade_rank_generic', ['n' => 'RANK_N']);
		$cascadeDirectPlusBestText = __('tournaments.setup_cascade_direct_plus_best', ['direct' => 'DIRECT_N', 'noun' => 'NOUN_WORD', 'verb' => 'VERB_WORD', 'take' => 'TAKE_N', 'rank' => 'RANK_WORD', 'size' => 'SIZE_N']);
		$cascadeNoDoborHintText = __('tournaments.setup_cascade_no_dobor_hint', ['z' => 'Z_N']);
	@endphp

	<script>
		document.addEventListener('DOMContentLoaded', function() {
			var typeSelect = document.getElementById('stage_type_select');
			var groupFields = document.getElementById('group_fields');
			var kbFields = document.getElementById('king_beach_fields');
			var courtsFields = document.getElementById('courts_shared_fields');
			var scheduleFields = document.getElementById('schedule_fields');
			var finalsModeFields = document.getElementById('finals_mode_fields');
			var groupsCountInput = document.querySelector('input[name="groups_count"]');
			var advanceCountInput = document.querySelector('input[name="advance_count"]');
			var finalsModePlacement = document.getElementById('finals_mode_placement');
			var finalsModeBracket = document.getElementById('finals_mode_bracket');
			var finalsModeDivisions = document.getElementById('finals_mode_divisions');
			var finalsModePlacementHint = document.getElementById('finals_mode_placement_hint');
			var divisionsFields = document.getElementById('finals_mode_divisions_fields');
			var thirdPlaceField = document.getElementById('third_place_match_field');
			var advancePerGroupSummary = document.getElementById('advance_per_group_summary');
			var divisionsFormatFields = document.getElementById('divisions_format_fields');
			var divisionsFormatTouched = {};
			var cascadePreview = document.getElementById('cascade_preview');
			var cascadeText = document.getElementById('cascade_text');
			var cascadeDoborHint = document.getElementById('cascade_dobor_hint');
			// group_fields и king_beach_fields содержат поля с ОДИНАКОВЫМИ name (draw_mode) —
			// display:none не мешает браузеру отправить их оба на сервер. Отключаем инпуты
			// скрытого блока через disabled, чтобы в форму попадали только видимые поля.
			function setBlockActive(block, active) {
				if (!block) return;
				block.style.display = active ? '' : 'none';
				block.querySelectorAll('input, select, textarea').forEach(function(el) {
					el.disabled = !active;
				});
			}
			// "Прямые матчи за места" однозначны только при РОВНО 2 группах —
			// при другом числе групп задизейблить радио и форсировать 'bracket'
			// (бэкенд тоже это форсирует, см. createStage() — это чисто UX-гейт).
			// "Финальные группы по уровням" НЕ имеет такого ограничения — работает
			// при любом числе групп (>=2), поэтому её радио этот гейт не трогает.
			function syncFinalsModeGuard() {
				if (!groupsCountInput || !finalsModePlacement) return;
				var g = parseInt(groupsCountInput.value, 10) || 0;
				var isTwoGroups = g === 2;
				finalsModePlacement.disabled = !isTwoGroups;
				if (finalsModePlacementHint) finalsModePlacementHint.style.display = isTwoGroups ? 'none' : '';
				if (!isTwoGroups && finalsModePlacement.checked) {
					finalsModePlacement.checked = false;
					if (finalsModeBracket) finalsModeBracket.checked = true;
				}
				// Мягкий режим (1 группа): второй этап не нужен вообще — round_robin/
				// groups_playoff с единственной группой сам даёт места 1-2-3 по итоговой
				// таблице (см. cascadeSingleGroupText в syncCascadePreview), ни форма, ни
				// бэкенд (createStage()/launchStage()) не создают скелет финала при N=1.
				// Эта функция уже переисполняется и при смене groups_count (слушатель
				// ниже), и при смене типа стадии (toggle()) — оба случая сворачивают/
				// разворачивают блок живьём без доп. слушателей.
				var t = typeSelect ? typeSelect.value : null;
				var isFollowup = t !== null && window.__stageFollowupTypes.indexOf(t) !== -1;
				setBlockActive(finalsModeFields, isFollowup && g !== 1);
				syncFinalsModeCardVisuals();
			}
			// Акцентная рамка/фон на выбранной карточке "Режим финалов" — класс
			// переключается JS-ом (не CSS :has(), ради совместимости с более старыми
			// WKWebView/Safari), т.к. .checked меняется и программно (дефолт по
			// direction, форс-гейт guard'а выше), не только пользовательским кликом.
			function syncFinalsModeCardVisuals() {
				[
					['finals_mode_card_placement', finalsModePlacement],
					['finals_mode_card_bracket', finalsModeBracket],
					['finals_mode_card_divisions', finalsModeDivisions],
				].forEach(function(pair) {
					var card = document.getElementById(pair[0]);
					if (card) card.classList.toggle('is-selected', !!(pair[1] && pair[1].checked));
				});
			}
            // Подсказка раскладки по дивизионам (модель A — ровные размеры).
            // Backend (formDivisionsCore) делит РЕАЛЬНЫЕ команды со standings
            // завершённого группового этапа. На форме их ещё нет — показываем
            // ОЦЕНКУ по текущему числу зарегистрированных команд
            // (window.__totalTeamsForDivisions). advance_count в раскладку
            // дивизионов не входит (влияет только на bracket/placement).
            function syncDivisionsFields() {
                var isDivisions = !!(finalsModeDivisions && finalsModeDivisions.checked);
                setBlockActive(divisionsFields, isDivisions);
                if (isDivisions) rebuildDivisionFormatFields();
                if (!isDivisions || !advancePerGroupSummary) return;
                var g = parseInt(groupsCountInput ? groupsCountInput.value : 0, 10) || 0;
                var totalTeams = window.__totalTeamsForDivisions || 0;
                var divisionNames = (window.__divisionNamesByGroupsCount || {})[g] || [];
                var divisionCount = divisionNames.length;
                if (totalTeams < 1 || divisionCount < 1) {
                    advancePerGroupSummary.textContent = '';
                    return;
                }
                var base = Math.floor(totalTeams / divisionCount);
                var remainder = totalTeams % divisionCount;
                var parts = [];
                divisionNames.forEach(function(name, idx) {
                    var size = idx < remainder ? base + 1 : base;
                    if (size > 0) parts.push(name + ' ' + size);
                });
                advancePerGroupSummary.textContent = @json(__('tournaments.setup_divisions_advance_summary'))
                    .replace(':count', totalTeams)
                    .replace(':noun', cascadeTeamForms(totalTeams).noun)
                    .replace(':breakdown', parts.join(', '));
            }
			// Склонение "N команда/команды/команд" + согласование глагола
			// "выходит"/"выходят" с числом direct — без этого JS всегда подставлял
			// родительный падеж мн. числа ("4 команд" вместо "4 команды").
			// Упрощённое правило (без редких форм вида "21 команда") — этого
			// достаточно для реалистичных groups_count×advance_count на этой форме.
			function cascadeTeamForms(n) {
				if (window.__appLocale === 'ru') {
					var mod10 = n % 10, mod100 = n % 100;
					var noun = (mod10 === 1 && mod100 !== 11) ? 'команда'
						: (mod10 >= 2 && mod10 <= 4 && !(mod100 >= 12 && mod100 <= 14)) ? 'команды'
						: 'команд';
					var verb = (n === 1) ? 'выходит' : 'выходят';
					return { noun: noun, verb: verb };
				}
				return { noun: (n === 1) ? 'team' : 'teams', verb: (n === 1) ? 'advances' : 'advance' };
			}
			// Живой каскад-предпросмотр (Кусок 3, TournamentBracketService — добор
			// лучших до полной сетки, практика FIVB): сколько команд идёт напрямую
			// в плей-офф и сколько добирается лучшими из невыходящих мест, чтобы
			// сетка была степенью двойки. Divisions уже показывает своё саммари
			// (advancePerGroupSummary) — каскад для divisions скрыт, не дублируем.
			function syncCascadePreview() {
				if (!cascadePreview || !cascadeText) return;
				var isDivisions = !!(finalsModeDivisions && finalsModeDivisions.checked);
				if (isDivisions) {
					cascadePreview.style.display = 'none';
					return;
				}
				var g = parseInt(groupsCountInput ? groupsCountInput.value : 0, 10) || 0;
				var a = parseInt(advanceCountInput ? advanceCountInput.value : 0, 10) || 0;
				if (cascadeDoborHint) cascadeDoborHint.textContent = '';
				if (g === 1) {
					cascadePreview.className = 'alert-info p-2 mb-2';
					cascadeText.textContent = @json($cascadeSingleGroupText);
					cascadePreview.style.display = '';
					return;
				}
				if (g < 2 || a < 1) {
					cascadePreview.style.display = 'none';
					return;
				}
				var direct = g * a;
				if (direct < 2) {
					cascadePreview.style.display = 'none';
					return;
				}
				var size = 1;
				while (size < direct) size *= 2;
				var needed = size - direct;
				var directForms = cascadeTeamForms(direct);
				if (needed <= 0) {
					cascadePreview.className = 'alert-success p-2 mb-2';
					cascadeText.textContent = @json($cascadeDirectOnlyText)
						.replace('DIRECT_N', direct).replace('NOUN_WORD', directForms.noun).replace('VERB_WORD', directForms.verb)
						.replace('SIZE_N', size);
				} else {
					cascadePreview.className = 'alert-warning p-2 mb-2';
					var take = Math.min(needed, g);
					var rankIdx = a + 1;
					var rankWord = (rankIdx === 2)
						? @json($cascadeRank2Text)
						: (rankIdx === 3)
							? @json($cascadeRank3Text)
							: @json($cascadeRankGenericText).replace('RANK_N', rankIdx);
					cascadeText.textContent = @json($cascadeDirectPlusBestText)
						.replace('DIRECT_N', direct).replace('NOUN_WORD', directForms.noun).replace('VERB_WORD', directForms.verb)
						.replace('TAKE_N', take).replace('RANK_WORD', rankWord).replace('SIZE_N', size);
					// Рекомендация "без добора": ближайшее меньшее advance_count (a),
					// при котором g*a' сразу степень двойки — не всегда существует
					// (напр. g=3: 3*1=3 не степень двойки), тогда подсказка не показывается.
					var z = null;
					for (var candidate = a - 1; candidate >= 1; candidate--) {
						var directCandidate = g * candidate;
						if (directCandidate >= 2 && (directCandidate & (directCandidate - 1)) === 0) {
							z = candidate;
							break;
						}
					}
					if (z !== null && cascadeDoborHint) {
						cascadeDoborHint.textContent = @json($cascadeNoDoborHintText).replace('Z_N', z);
					}
				}
				cascadePreview.style.display = '';
			}
			// Формат матча по дивизионам (div_format_hard/_medium-N/_lite) — для
			// любого числа групп (2, 3, 4+). Названия дивизионов берём из
			// window.__divisionNamesByGroupsCount (посчитано PHP один раз при загрузке
			// страницы — TournamentStage::divisionNamesFor(), та же формула, что и в
			// formDivisions()/на пульте — НЕ дублировать формулу тут). formDivisions()
			// теперь читает per-division ключ по точному имени (div_format_medium-1,
			// div_format_medium-2, ...) — раньше при 4+ группах поле для Medium-N не
			// читалось вообще (см. CLAUDE.md, баг Medium-N), заметка про gap убрана.
			function rebuildDivisionFormatFields() {
				if (!divisionsFormatFields) return;
				var g = parseInt(groupsCountInput ? groupsCountInput.value : 0, 10) || 0;
				var names = window.__divisionNamesByGroupsCount[g] || [];
				var html = '';
				names.forEach(function(name) {
					var key = name.toLowerCase();
					var current = divisionsFormatFields.querySelector('[name="div_format_' + key + '"]');
					var val = current ? current.value : (divisionsFormatTouched[key] || '');
					html += '<label class="mt-1">' + @json(__('tournaments.setup_groups_format_for', ['name' => 'X'])).replace('X', name) + '</label>';
					html += '<select name="div_format_' + key + '" class="f-13" style="max-width:16rem">';
					html += '<option value=""' + (val === '' ? ' selected' : '') + '>' + @json(__('tournaments.setup_groups_format_default')) + '</option>';
					html += '<option value="bo1"' + (val === 'bo1' ? ' selected' : '') + '>Bo1</option>';
					html += '<option value="bo3"' + (val === 'bo3' ? ' selected' : '') + '>Bo3</option>';
					html += '</select>';
				});
				divisionsFormatFields.innerHTML = html;
				divisionsFormatFields.querySelectorAll('select').forEach(function(sel) {
					sel.addEventListener('change', function() { divisionsFormatTouched[sel.name.replace('div_format_', '')] = sel.value; });
					// Динамически вставленный <select> внутри .form схлопывается в 1px
					// сайтовым правилом (.form select{position:absolute;width:1px...}
					// под @media(hover:hover)) без обёртки .form-select-wrapper — та же
					// логика, что уже задокументирована в проекте и используется в
					// admin/locations/edit.blade.php, occurrence_edit.blade.php и др.
					if (window.createCustomSelect && window.jQuery) {
						window.createCustomSelect(window.jQuery(sel));
					}
				});
			}
			// Подсказка advance_count зависит от finals_mode: поле реально влияет
			// только на bracket (для placement/divisions игнорируется на сервере).
			var advanceHints = {
				bracket:   @json(__('tournaments.setup_stage_groups_advance_hint')),
				divisions: @json(__('tournaments.setup_stage_groups_advance_hint_divisions')),
				placement: @json(__('tournaments.setup_stage_groups_advance_hint_placement'))
			};
			function updateAdvanceCountHint() {
				var hintEl = document.getElementById('advance_count_hint');
				if (!hintEl) return;
				var mode = 'bracket';
				if (finalsModeDivisions && finalsModeDivisions.checked) mode = 'divisions';
				else if (finalsModePlacement && finalsModePlacement.checked) mode = 'placement';
				hintEl.textContent = advanceHints[mode] || advanceHints.bracket;
			}
			[finalsModePlacement, finalsModeBracket, finalsModeDivisions].forEach(function(radio) {
				if (radio) radio.addEventListener('change', syncDivisionsFields);
				if (radio) radio.addEventListener('change', syncCascadePreview);
				if (radio) radio.addEventListener('change', updateAdvanceCountHint);
			});
			if (groupsCountInput) {
				groupsCountInput.addEventListener('input', function() { syncFinalsModeGuard(); syncDivisionsFields(); syncCascadePreview(); updateAdvanceCountHint(); });
				syncFinalsModeGuard();
				updateAdvanceCountHint();
			}
			if (advanceCountInput) {
				advanceCountInput.addEventListener('input', function() { syncDivisionsFields(); syncCascadePreview(); });
			}
			// "Матч за 3-е место" читается ТОЛЬКО генерацией полного плей-офф
			// (bracket) — для placement (счёт по рангам) и divisions (нет бракета
			// вообще) поле ни на что не влияет. Показываем его только при bracket,
			// чтобы не сбивать организатора несуществующей настройкой. Для типов
			// без finals_mode (thai и т.п.) поведение не трогаем — оставляем видимым.
			function syncThirdPlaceMatchField() {
				if (!thirdPlaceField || !typeSelect) return;
				var t = typeSelect.value;
				var showGroup = window.__stageGroupTypes.indexOf(t) !== -1;
				// Для не-групповых типов (single_elim/swiss/king_of_court/...) сам
				// #group_fields уже целиком задизейблен выше — не трогаем его потомков
				// здесь повторно, иначе рискуем случайно РАЗдизейблить это поле.
				if (!showGroup) return;
				var isFollowup = window.__stageFollowupTypes.indexOf(t) !== -1;
				if (!isFollowup) {
					// thai — group_stage без finals_mode; поведение поля не трогаем.
					setBlockActive(thirdPlaceField, true);
					return;
				}
				setBlockActive(thirdPlaceField, !!(finalsModeBracket && finalsModeBracket.checked));
			}
			[finalsModePlacement, finalsModeBracket, finalsModeDivisions].forEach(function(radio) {
				if (radio) radio.addEventListener('change', syncThirdPlaceMatchField);
				if (radio) radio.addEventListener('change', syncFinalsModeCardVisuals);
			});
			// Дефолт радио "Режим финалов" по дисциплине турнира — пляжка чаще
			// играет финальные группы по уровням, классика — финал за места.
			// Это только дефолт: организатор переключает руками в любой момент,
			// повторно применяется при каждой смене типа стадии на групповой.
			function applyFinalsModeDefaultByDirection() {
				if (window.__eventDirection === 'beach' && finalsModeDivisions) {
					finalsModeDivisions.checked = true;
				} else if (finalsModePlacement && !finalsModePlacement.disabled) {
					// placement доступен только при РОВНО 2 группах (см. syncFinalsModeGuard,
					// вызывается ДО этой функции) — если он уже задизейблен гейтом, дефолт
					// классики откатывается на bracket, а не перезаписывает disabled-радио.
					finalsModePlacement.checked = true;
				} else if (finalsModeBracket) {
					finalsModeBracket.checked = true;
				}
				syncDivisionsFields();
				syncCascadePreview();
				syncThirdPlaceMatchField();
				syncFinalsModeCardVisuals();
			}
			if (typeSelect) {
				function toggle() {
					var t = typeSelect.value;
					var showGroup = window.__stageGroupTypes.indexOf(t) !== -1;
					var showKb = (t === 'king_beach');
					var isFollowup = window.__stageFollowupTypes.indexOf(t) !== -1;
					setBlockActive(groupFields, showGroup);
					setBlockActive(kbFields, showKb);
					// Корты — общий блок для групповых форматов и King of the Beach
					setBlockActive(courtsFields, showGroup || showKb);
					// Расписание — только для группового формата (объединено с "Площадками" в
					// одну секцию "4", но видимость своя): вызывается ПОСЛЕ courtsFields, иначе
					// activate(courtsFields, true) для king_beach снимет disabled со всех своих
					// потомков, включая поля расписания.
					setBlockActive(scheduleFields, showGroup);
					// finals_mode актуален для типов с авто-продолжением (canHaveFollowupStage() —
					// round_robin И groups_playoff, НЕ thai) — единый список из PHP, не хардкод.
					setBlockActive(finalsModeFields, isFollowup);
					if (isFollowup) {
						syncFinalsModeGuard();
						applyFinalsModeDefaultByDirection();
					} else {
						syncThirdPlaceMatchField();
					}
				}
				typeSelect.addEventListener('change', toggle);
				toggle();
			}
		});
	</script>
	
	
	<script>
		document.addEventListener('DOMContentLoaded', function() {
			document.querySelectorAll('.draw-mode-select').forEach(function(sel) {
				sel.addEventListener('change', function() {
					var stageId = this.dataset.stage;
					var block = document.querySelector('.manual-draw-block[data-stage="' + stageId + '"]');
					if (block) {
						block.style.display = this.value === 'manual' ? '' : 'none';
					}
				});
			});
		});
	</script>
	
	
	<script>
		(function(){
			var inp = document.getElementById('org-captain-search');
			var hidden = document.getElementById('org-captain-id');
			var dd = document.getElementById('org-captain-dd');
			var wrap = document.getElementById('org-captain-ac-wrap');
			if (!inp || !dd || !hidden) return;
			var timer = null;
			
			function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
			// См. комментарий у manual-captain-search выше — тот же приём поднятия
			// z-index родительской .ramka, что и у createCustomSelect() в script.js.
			var ramkaEl = wrap ? wrap.closest('.ramka, .card-ramka, .top-section') : null;
			function showDd() { dd.classList.add('form-select-dropdown--active'); if (ramkaEl) ramkaEl.classList.add('select-dropdown-open'); }
			function hideDd() { dd.classList.remove('form-select-dropdown--active'); if (ramkaEl) ramkaEl.classList.remove('select-dropdown-open'); }

			inp.addEventListener('input', function() {
				clearTimeout(timer);
				var q = inp.value.trim();
				if (q.length < 2) { hideDd(); dd.innerHTML = ''; return; }
				dd.innerHTML = '<div class="city-message">' + @json(__('tournaments.setup_search_loading')) + '</div>';
				showDd();
				timer = setTimeout(function() {
					fetch('/api/users/search?q=' + encodeURIComponent(q), {
						headers: { 'Accept': 'application/json' },
						credentials: 'same-origin'
					})
					.then(function(r) { return r.json(); })
					.then(function(data) {
						dd.innerHTML = '';
						var items = data.items || data || [];
						if (!items.length) {
							dd.innerHTML = '<div class="city-message">' + @json(__('tournaments.setup_search_no_results')) + '</div>';
							showDd();
							return;
						}
						items.slice(0, 8).forEach(function(u) {
							var label = u.label || u.name || '#' + u.id;
							var div = document.createElement('div');
							div.className = 'trainer-item form-select-option';
							var botBadge = u.is_bot ? '<span style="display:inline-block;padding:1px 8px;border-radius:10px;font-size:11px;font-weight:600;background:#fef3c7;color:#92400e;margin-left:.5rem">🤖 бот</span>' : '';
							div.innerHTML = '<div class="text-sm">' + esc(label) + botBadge + '</div>';
							div.addEventListener('click', function() {
								inp.value = label;
								hidden.value = String(u.id);
								hideDd();
							});
							dd.appendChild(div);
						});
						showDd();
					})
					.catch(function() {
						dd.innerHTML = '<div class="city-message">' + @json(__('tournaments.setup_search_load_err')) + '</div>';
						showDd();
					});
				}, 250);
			});
			
			inp.addEventListener('keydown', function(e) { if (e.key === 'Escape') hideDd(); });
			
			document.addEventListener('click', function(e) {
				if (wrap && !wrap.contains(e.target)) hideDd();
			});
		})();
	</script>
	
	
	<script>
	(function(){
		function makeAC(inputId, hiddenId, ddId, wrapId) {
			var inp = document.getElementById(inputId);
			var hidden = document.getElementById(hiddenId);
			var dd = document.getElementById(ddId);
			var wrap = document.getElementById(wrapId);
			if (!inp || !dd || !hidden) return;
			var timer = null;
			function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
			// См. комментарий у manual-captain-search — тот же приём поднятия
			// z-index родительской .ramka, что и у createCustomSelect() в script.js.
			var ramkaEl = wrap ? wrap.closest('.ramka, .card-ramka, .top-section') : null;
			function showDd() { dd.classList.add('form-select-dropdown--active'); if (ramkaEl) ramkaEl.classList.add('select-dropdown-open'); }
			function hideDd() { dd.classList.remove('form-select-dropdown--active'); if (ramkaEl) ramkaEl.classList.remove('select-dropdown-open'); }
			inp.addEventListener('input', function() {
				clearTimeout(timer);
				hidden.value = '';
				var q = inp.value.trim();
				if (q.length < 2) { hideDd(); dd.innerHTML = ''; return; }
				dd.innerHTML = '<div class="city-message">Загрузка...</div>';
				showDd();
				timer = setTimeout(function() {
					fetch('/api/users/search?q=' + encodeURIComponent(q), {
						headers: {'Accept':'application/json'}, credentials:'same-origin'
					})
					.then(function(r){ return r.json(); })
					.then(function(data){
						dd.innerHTML = '';
						var items = data.items || data || [];
						if (!items.length) { dd.innerHTML = '<div class="city-message">Не найдено</div>'; showDd(); return; }
						items.slice(0,8).forEach(function(u) {
							var label = u.label || u.name || '#'+u.id;
							var div = document.createElement('div');
							div.className = 'trainer-item form-select-option';
							var botBadge = u.is_bot ? '<span style="display:inline-block;padding:1px 8px;border-radius:10px;font-size:11px;font-weight:600;background:#fef3c7;color:#92400e;margin-left:.5rem">🤖 бот</span>' : '';
							div.innerHTML = '<div class="text-sm">'+esc(label)+botBadge+'</div>';
							div.addEventListener('click', function() {
								inp.value = label; hidden.value = String(u.id); hideDd();
							});
							dd.appendChild(div);
						});
						showDd();
					})
					.catch(function(){ dd.innerHTML = '<div class="city-message">Ошибка загрузки</div>'; showDd(); });
				}, 250);
			});
			inp.addEventListener('keydown', function(e){ if(e.key==='Escape') hideDd(); });
			document.addEventListener('click', function(e){ if(wrap && !wrap.contains(e.target)) hideDd(); });
		}
		makeAC('add-league-captain-search','add-league-captain-id','add-league-captain-dd','add-league-captain-wrap');
		makeAC('add-league-partner-search','add-league-partner-id','add-league-partner-dd','add-league-partner-wrap');

		// Предупреждение о лимите дивизиона
		(function(){
			var sel = document.getElementById('add-league-target-status');
			var warn = document.getElementById('league-cap-warning');
			if (!sel || !warn) return;
			var max = parseInt(sel.dataset.max) || 0;
			var cur = parseInt(sel.dataset.current) || 0;
			sel.addEventListener('change', function(){
				if (max && cur >= max && this.value === 'active') {
					warn.style.display = '';
				} else {
					warn.style.display = 'none';
				}
			});
		})();
	})();
	</script>

	{{-- fas.js подключает jQuery.fancybox/Swiper и на верхнем уровне зовёт jQuery —
	jQuery определяется в lib.js, который грузится в общем layout (voll-layout.blade.php)
	ПОСЛЕ основного контента страницы. Здесь, в отличие от всех остальных
	blade-страниц с fas.js, тег стоял в общем $slot (внутри <main>) — то есть
	раньше lib.js в итоговом HTML. Chrome это прощал по таймингу, Safari/WebKit —
	нет ("Can't find variable: jQuery", fas.js падает целиком). Оборачиваем в
	x-slot="script" — тот же механизм, что и на всех остальных страницах с
	fas.js (events/create.blade.php и др.): контент рендерится в layout ПОСЛЕ
	lib.js/script.js, независимо от места объявления x-slot в этом файле. --}}
	<x-slot name="script">
		<script src="/assets/fas.js"></script>
		<script src="/js/cropper.min.js"></script>
	</x-slot>
	<script>
		document.addEventListener('DOMContentLoaded', function() {
			// Tournament Photos Swiper
			var tournamentPhotosSwiper = null;
			if (document.querySelector('.tournamentPhotosSwiper .swiper-wrapper')) {
				tournamentPhotosSwiper = new Swiper('.tournamentPhotosSwiper', {
					slidesPerView: 3,
					spaceBetween: 20,
					pagination: { el: '.tournamentPhotosSwiper .swiper-pagination', clickable: true },
					breakpoints: {
						320: { slidesPerView: 2 },
						640: { slidesPerView: 3 },
						1024: { slidesPerView: 4 }
					}
				});
			}

			var selectorEl = document.getElementById('tournament-photos-selector');
			var savedPhotos = selectorEl ? JSON.parse(selectorEl.dataset.selected || '[]') : [];
			var selectedPhotos = savedPhotos.slice();
			var tPhotoSelectLabel = @json(__('tournaments.setup_photos_select'));
			var tPhotoMainLabel   = @json(__('tournaments.setup_photo_main'));
			var tPhotoPosLabel    = @json(__('tournaments.setup_photo_pos_n', ['n' => '']));

			function updateTournamentUI() {
				document.querySelectorAll('.t-photo-select').forEach(function(cb) {
					var id = parseInt(cb.value);
					var isSelected = selectedPhotos.indexOf(id) !== -1;
					cb.checked = isSelected;
					var badge = cb.closest('.swiper-slide').querySelector('.photo-order-badge');
					if (isSelected) {
						var order = selectedPhotos.indexOf(id) + 1;
						badge.textContent = order === 1 ? tPhotoMainLabel : (tPhotoPosLabel + order);
					} else {
						badge.textContent = '';
					}
				});
				var inp = document.getElementById('tournament_photos_input');
				if (inp) inp.value = JSON.stringify(selectedPhotos);
				var btn = document.getElementById('tournament-photos-submit');
				if (btn) btn.style.display = selectedPhotos.length > 0 ? '' : 'none';
			}

			function bindTournamentCheckbox(cb) {
				cb.addEventListener('change', function() {
					var id = parseInt(this.value);
					if (this.checked) {
						selectedPhotos.push(id);
					} else {
						var idx = selectedPhotos.indexOf(id);
						if (idx !== -1) selectedPhotos.splice(idx, 1);
					}
					updateTournamentUI();
				});
			}

			document.querySelectorAll('.t-photo-select').forEach(bindTournamentCheckbox);
			updateTournamentUI();

			// --- Загрузка фото с кропом 4:3 (800×600) в турнирный альбом ---
			var tournamentCropper = null;

			function supportsWebPT() {
				try {
					var c = document.createElement('canvas');
					return c.toDataURL('image/webp').indexOf('data:image/webp') === 0;
				} catch(e) { return false; }
			}

			function processImageT(file, callback) {
				var url = URL.createObjectURL(file);
				var img = new Image();
				img.onload = function() {
					var w = img.width, h = img.height, maxSize = 1920;
					if (w > maxSize || h > maxSize) {
						var r = Math.min(maxSize / w, maxSize / h);
						w = Math.round(w * r); h = Math.round(h * r);
					}
					var canvas = document.createElement('canvas');
					canvas.width = w; canvas.height = h;
					canvas.getContext('2d').drawImage(img, 0, 0, w, h);
					var fmt = supportsWebPT() ? 'image/webp' : 'image/jpeg';
					canvas.toBlob(function(blob) { callback(blob, fmt); }, fmt, 0.85);
				};
				img.src = url;
			}

			function showTournamentCropperModal(imageUrl, onCropComplete) {
				var modal = document.createElement('div');
				modal.className = 'cropper-modal-overlay';
				var mc = document.createElement('div');
				mc.className = 'cropper-modal-container';
				var mt = document.createElement('h3');
				mt.textContent = 'Обрезать фото';
				var imgWrapper = document.createElement('div');
				imgWrapper.className = 'cropper-image-wrapper';
				var img = document.createElement('img');
				img.src = imageUrl;
				imgWrapper.appendChild(img);
				var bc = document.createElement('div');
				bc.className = 'cropper-buttons';
				var saveBtn = document.createElement('button');
				saveBtn.textContent = 'Добавить'; saveBtn.type = 'button'; saveBtn.className = 'btn';
				var cancelBtn = document.createElement('button');
				cancelBtn.textContent = 'Отмена'; cancelBtn.type = 'button'; cancelBtn.className = 'btn btn-secondary';
				bc.appendChild(saveBtn); bc.appendChild(cancelBtn);
				var loading = document.createElement('div');
				loading.className = 'fancybox-loading'; loading.style.display = 'none';
				modal.appendChild(loading);
				mc.appendChild(mt); mc.appendChild(imgWrapper); mc.appendChild(bc);
				modal.appendChild(mc);
				document.body.appendChild(modal);
				modal.offsetHeight;
				requestAnimationFrame(function() { modal.classList.add('cropper-modal-overlay--active'); });
				img.onload = function() {
					if (tournamentCropper) tournamentCropper.destroy();
					tournamentCropper = new Cropper(img, {
						aspectRatio: 4 / 3,
						viewMode: 1, background: true, dragMode: 'crop',
						autoCropArea: 0.8, cropBoxMovable: true, cropBoxResizable: true,
						zoomable: true, zoomOnWheel: true, wheelZoomRatio: 0.1,
						movable: true, guides: true, center: true, highlight: true,
						responsive: true, restore: false,
					});
				};
				saveBtn.onclick = function() {
					if (!tournamentCropper) return;
					modal.classList.add('loading');
					saveBtn.disabled = true; cancelBtn.disabled = true;
					var canvas = tournamentCropper.getCroppedCanvas({ width: 800, height: 600 });
					var fmt = supportsWebPT() ? 'image/webp' : 'image/jpeg';
					canvas.toBlob(function(blob) { onCropComplete(blob, fmt); }, fmt, 0.90);
				};
				cancelBtn.onclick = function() {
					modal.remove();
					if (tournamentCropper) { tournamentCropper.destroy(); tournamentCropper = null; }
					document.getElementById('tournament-photo-upload').value = '';
				};
				modal.onclick = function(e) { if (e.target === modal) cancelBtn.onclick(); };
			}

			function sendTournamentPhoto(originalBlob, croppedBlob, format) {
				var ext = format === 'image/webp' ? 'webp' : 'jpg';
				var ts = Date.now();
				var fd = new FormData();
				fd.append('photo_original', originalBlob, 'original_' + ts + '.' + ext);
				fd.append('photo_cropped',  croppedBlob, 'thumb_' + ts + '.' + ext);
				fd.append('photo_type', 'tournament_photos');
				fd.append('make_avatar', '0');
				fd.append('_token', document.querySelector('meta[name="csrf-token"]').content);
				fetch('/user/photos', { method: 'POST', body: fd })
					.then(function(r) {
						return r.json().then(function(data) {
							var modal = document.querySelector('.cropper-modal-overlay');
							if (r.ok && data.success) {
								if (modal) modal.remove();
								onTournamentPhotoUploaded(data.media_id, data.thumb_url);
							} else {
								if (modal) modal.remove();
								swal({ title: 'Ошибка', text: data.error || 'Не удалось загрузить фото', icon: 'error', button: 'Понятно' });
							}
						});
					})
					.catch(function() {
						var modal = document.querySelector('.cropper-modal-overlay');
						if (modal) modal.remove();
						swal({ title: 'Ошибка', text: 'Ошибка сети. Попробуйте ещё раз.', icon: 'error', button: 'Понятно' });
					});
			}

			function onTournamentPhotoUploaded(mediaId, thumbUrl) {
				var slideHtml = '<div class="swiper-slide">' +
					'<div class="hover-image mb-1">' +
					'<img src="' + thumbUrl + '" alt="photo" loading="lazy" style="width:100%;aspect-ratio:4/3;object-fit:cover;border-radius:8px"/>' +
					'</div>' +
					'<div class="mt-1 d-flex between fvc">' +
					'<label class="checkbox-item mb-0">' +
					'<input type="checkbox" class="t-photo-select" value="' + mediaId + '">' +
					'<div class="custom-checkbox"></div>' +
					'<span>' + tPhotoSelectLabel + '</span>' +
					'</label>' +
					'<div class="photo-order-badge f-16 b-600 cd"></div>' +
					'</div></div>';

				var swiperWrap = document.getElementById('tournament-photos-swiper-wrap');
				if (swiperWrap) swiperWrap.style.display = '';

				if (!tournamentPhotosSwiper) {
					tournamentPhotosSwiper = new Swiper('.tournamentPhotosSwiper', {
						slidesPerView: 3, spaceBetween: 20,
						pagination: { el: '.tournamentPhotosSwiper .swiper-pagination', clickable: true },
						breakpoints: { 320: { slidesPerView: 2 }, 640: { slidesPerView: 3 }, 1024: { slidesPerView: 4 } }
					});
				}

				tournamentPhotosSwiper.prependSlide(slideHtml);
				tournamentPhotosSwiper.slideTo(0);

				var newCb = document.querySelector('.t-photo-select[value="' + mediaId + '"]');
				if (newCb) {
					bindTournamentCheckbox(newCb);
					selectedPhotos.unshift(mediaId);
					updateTournamentUI();
				}

				document.getElementById('tournament-photo-upload').value = '';
			}

			var uploadBtn = document.getElementById('tournament-upload-photo-btn');
			var uploadInput = document.getElementById('tournament-photo-upload');
			if (uploadBtn && uploadInput) {
				uploadBtn.addEventListener('click', function() { uploadInput.click(); });
				uploadInput.addEventListener('change', function(e) {
					var file = e.target.files[0];
					if (!file) return;
					if (!file.type.startsWith('image/')) {
						swal({ title: 'Ошибка', text: 'Пожалуйста, выберите изображение', icon: 'error', button: 'Понятно' });
						this.value = ''; return;
					}
					if (file.size > 15 * 1024 * 1024) {
						swal({ title: 'Ошибка', text: 'Файл слишком большой. Максимум 15 МБ.', icon: 'error', button: 'Понятно' });
						this.value = ''; return;
					}
					processImageT(file, function(blob, fmt) {
						var url = URL.createObjectURL(blob);
						showTournamentCropperModal(url, function(croppedBlob, cropFmt) {
							sendTournamentPhoto(blob, croppedBlob, cropFmt);
						});
					});
				});
			}
		});
	</script>
	
	<script>
		// Инжектируем occurrence_id во все формы на странице
		(function() {
			var params = new URLSearchParams(window.location.search);
			var occId = params.get('occurrence_id');
			if (!occId) return;
			document.querySelectorAll('form[method="POST"]').forEach(function(form) {
				if (form.querySelector('input[name="occurrence_id"]')) return;
				var input = document.createElement('input');
				input.type = 'hidden';
				input.name = 'occurrence_id';
				input.value = occId;
				form.appendChild(input);
			});
			// Сохраняем позицию прокрутки перед отправкой формы
			document.querySelectorAll('form[method="POST"]').forEach(function(form) {
				form.addEventListener('submit', function() {
					try { window.name = 'scrollY:' + window.scrollY; } catch(e) {}
				});
			});
			
			// Восстанавливаем после перезагрузки
			try {
				if (window.name && window.name.indexOf('scrollY:') === 0) {
					var y = parseInt(window.name.split(':')[1]);
					window.name = '';
					if (y > 0) {
						setTimeout(function() { window.scrollTo(0, y); }, 100);
					}
				}
			} catch(e) {}
			
			// Прокрутка к якорю (если есть hash)
			if (window.location.hash) {
				setTimeout(function() {
					var el = document.querySelector(window.location.hash);
					if (el) el.scrollIntoView({behavior: 'smooth', block: 'start'});
				}, 300);
			}
		})(); // inject_occurrence_id
	</script>
	<script>
		// Manual draw — show/hide + update group options
		(function(){
			var drawSel = document.getElementById('draw_mode_select');
			var manualBlock = document.getElementById('manual_draw_block');
			var groupsInput = document.querySelector('input[name="groups_count"]');
			if (!drawSel || !manualBlock) return;
			
			function updateManual() {
				manualBlock.style.display = (drawSel.value === 'manual') ? '' : 'none';
			}
			
			function updateGroupOptions() {
				var g = parseInt(groupsInput ? groupsInput.value : 2) || 2;
				var selects = manualBlock.querySelectorAll('.manual-group-select');
				selects.forEach(function(sel) {
					var current = sel.value;
					// Remove all except first option
					while (sel.options.length > 1) sel.remove(1);
					for (var i = 0; i < g; i++) {
						var label = String.fromCharCode(65 + i);
						var opt = document.createElement('option');
						opt.value = label;
						opt.textContent = '\u0413\u0440\u0443\u043f\u043f\u0430 ' + label;
						sel.appendChild(opt);
					}
					// Restore selection if still valid
					if (current && current.charCodeAt(0) - 65 < g) sel.value = current;
				});
			}
			
			drawSel.addEventListener('change', updateManual);
			if (groupsInput) groupsInput.addEventListener('input', updateGroupOptions);
			updateManual();
			updateGroupOptions();
		})();
	</script>
	<script>
		// Переключатель Список / Шахматка
		(function () {
			var LS_KEY = 'ct_view_pref';

			function setView(groupId, view) {
				var listEl  = document.querySelector('.ct-view-list[data-group="' + groupId + '"]');
				var crossEl = document.querySelector('.ct-view-crosstable[data-group="' + groupId + '"]');
				var btns    = document.querySelectorAll('.ct-view-btn[data-group="' + groupId + '"]');
				if (!listEl) return;
				// Шахматки для этой группы/стадии нет (напр. финальная стадия — у
				// single_elim нет $stage->groups, $hasCrosstable=false, .ct-view-crosstable
				// вообще не рендерится) — список всегда виден. Раньше глобальный
				// localStorage-предпочтение 'crosstable' (сохранённое на ДРУГОЙ, групповой
				// панели) на инициализации прогонялось по ВСЕМ .ct-view-list на странице и
				// прятало список без чего-либо взамен — панель схлопывалась в 0 высоты.
				if (!crossEl) {
					listEl.style.display = '';
					return;
				}
				listEl.style.display  = view === 'list'       ? '' : 'none';
				crossEl.style.display = view === 'crosstable' ? '' : 'none';
				btns.forEach(function (b) {
					b.classList.toggle('ct-view-btn--active', b.dataset.view === view);
				});
				try { localStorage.setItem(LS_KEY, view); } catch(e) {}
			}

			// Восстановить сохранённый вид
			var savedView = 'list';
			try { savedView = localStorage.getItem(LS_KEY) || 'list'; } catch(e) {}
			if (savedView === 'crosstable') {
				document.querySelectorAll('.ct-view-list[data-group]').forEach(function (el) {
					setView(el.dataset.group, 'crosstable');
				});
			}

			document.addEventListener('click', function (e) {
				var btn = e.target.closest('.ct-view-btn');
				if (!btn) return;
				setView(btn.dataset.group, btn.dataset.view);
			});
		})();
	</script>
	<style>
		.ct-view-btn { opacity: .55; }
		.ct-view-btn--active { opacity: 1; }
	</style>
</x-voll-layout>
