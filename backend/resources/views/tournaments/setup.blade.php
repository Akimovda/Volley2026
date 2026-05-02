<x-voll-layout body_class="tournament-setup-page">
	@php
    $direction = $event->direction ?? 'classic';
    $isBeach = $direction === 'beach';
	@endphp
	<x-slot name="title">Управление турниром — {{ $event->title }}</x-slot>
	
    <x-slot name="h1">Турнир: {{ $event->title }}</x-slot>
	 <x-slot name="h2">Управление турниром</x-slot>
	
	
	
	<x-slot name="breadcrumbs">
		<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
			<a href="{{ route('events.show', $event) }}" itemprop="item"><span itemprop="name">{{ $event->title }}</span></a>
			<meta itemprop="position" content="2">
		</li>
		<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
			<span itemprop="name">Управление турниром</span>
			<meta itemprop="position" content="3">
		</li>
	</x-slot>
	
	<div class="container form">
		
		{{-- ========================= ЗАЯВКИ ========================= --}}
		@if(($applicationMode ?? 'manual') === 'manual' && isset($pendingApplications) && $pendingApplications->count())
		<div class="ramka">
			<h2 class="-mt-05">Заявки на участие ({{ $pendingApplications->count() }})</h2>
			
			<div class="alert alert-info mb-2">
				Режим: <b>ручное одобрение</b>. Одобрите или отклоните заявки команд.
			</div>
			
			@foreach($pendingApplications as $app)
			<div class="card mb-1">
				<div class="d-flex fvc" style="justify-content:space-between;flex-wrap:wrap;gap:.5rem">
					<div>
						<div class="b-700 f-17">{{ $app->team->name ?? '?' }}</div>
						<div class="f-13" style="opacity:.6">
							Капитан: 
							<a class="blink" href="{{ route('users.show', $app->team->captain_user_id) }}">
								{{ trim(($app->team->captain->last_name ?? '') . ' ' . ($app->team->captain->first_name ?? '')) ?: $app->team->captain->name ?? '?' }}
							</a>
							&middot; Подана: {{ $app->applied_at?->format('d.m.Y H:i') }}
						</div>
						@if($app->team->members->count())
						<div class="f-13 mt-05">
							Состав: 
							@foreach($app->team->members as $m)
							<a class="blink" href="{{ route('users.show', $m->user_id) }}">{{ trim(($m->user->last_name ?? '') . ' ' . ($m->user->first_name ?? '')) ?: $m->user->name ?? '?' }}</a>@if(!$loop->last), @endif
							@endforeach
						</div>
						@endif
					</div>
					<div class="d-flex" style="gap:.5rem">
						<form method="POST" action="{{ route('tournament.application.approve', [$event, $app]) }}">
							@csrf
							<button type="submit" class="btn btn-small btn-primary btn-alert" data-title="Одобрить заявку?" data-icon="question" data-confirm-text="Да, одобрить" data-cancel-text="Отмена">✅ Одобрить</button>
						</form>
						<form method="POST" action="{{ route('tournament.application.reject', [$event, $app]) }}">
							@csrf
							<button type="submit" class="btn btn-small btn-secondary btn-alert" data-title="Отклонить заявку?" data-icon="warning" data-confirm-text="Да, отклонить" data-cancel-text="Отмена">❌ Отклонить</button>
						</form>
					</div>
				</div>
			</div>
			@endforeach
		</div>
		@elseif(($applicationMode ?? 'manual') === 'auto')
		<div class="ramka">
			<div class="alert alert-success">
				✅ Режим: <b>автоматическое одобрение</b>. Заявки одобряются автоматически при подаче.
			</div>
		</div>
		@endif
		
		
		
		@if(session('success') || session('error'))
		<script>
			document.addEventListener('DOMContentLoaded', function() {
				if (typeof Swal !== 'undefined') {
					Swal.fire({
						icon: '{{ session("success") ? "success" : "error" }}',
						title: '{{ session("success") ? "Готово" : "Ошибка" }}',
						text: {!! json_encode(session('success') ?: session('error')) !!},
						timer: 3000,
						showConfirmButton: false,
						toast: true,
						position: 'top-end',
					});
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
		Серия турниров — управление составом лиги
		============================================================ --}}
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
			
			<h2 class="-mt-05">
				{{ $seasonData['season']->name }}
				<span class="f-16">/ {{ $seasonData['league']->name ?? 'Лига' }}</span>
			</h2>
			
			{{-- Выбор тура --}}
			@if($seasonData['occurrences']->count() > 1)
			<div class="mb-2">
				<label>Тур:</label>
				<div class="d-flex" style="gap:1rem;flex-wrap:wrap;">
					@foreach($seasonData['occurrences'] as $occ)
					@php
					$isSelected = $selectedOccurrence && $selectedOccurrence->id === $occ->id;
					$occDate = \Carbon\Carbon::parse($occ->starts_at)->setTimezone($event->timezone ?? 'Europe/Moscow');
					@endphp
					<a href="{{ route('tournament.setup', $event) }}?occurrence_id={{ $occ->id }}"
					class="btn btn-small {{ !$isSelected ? 'btn-secondary' : '' }}">
						{{ $loop->iteration }} ({{ $occDate->format('d.m') }})
					</a>
					@endforeach
				</div>
			</div>
			@endif
			
			{{-- Состав лиги --}}
			@if($leagueTeams->count())
			<div class="mt-2">
				<h3 style="font-size:16px;margin-bottom:10px">
					Состав лиги
					<span style="font-weight:400;color:#6b7280">
						— {{ $leagueTeams->where('status', 'active')->count() }} акт.
						@if($leagueTeams->where('status', 'reserve')->count())
						/ {{ $leagueTeams->where('status', 'reserve')->count() }} рез.
						@endif
					</span>
				</h3>
				
				<table class="league-table">
					<thead>
						<tr>
							<th style="width:30px">#</th>
							<th>Команда</th>
							<th>Статус</th>
							<th>Действие</th>
						</tr>
					</thead>
					<tbody>
						@foreach($leagueTeams as $lt)
						<tr style="{{ $lt->status === 'reserve' ? 'opacity:.55' : '' }}">
							<td>{{ $loop->iteration }}</td>
							<td>
								@if($lt->team)
								<div class="team-name">{{ $lt->team->name }}</div>
								<div class="team-members">
									@php
									$members = $lt->team->members->map(function($m) {
									$u = $m->user;
									return $u ? ($u->first_name . ' ' . $u->last_name) : '?';
									})->implode(' / ');
									@endphp
									{{ $members }}
								</div>
								@elseif($lt->user)
								<div class="team-name">{{ $lt->user->first_name }} {{ $lt->user->last_name }}</div>
								@else
								—
								@endif
							</td>
							<td>
								@if($lt->status === 'active')
								<span class="league-badge league-badge-active">Активен</span>
								@elseif($lt->status === 'reserve')
								<span class="league-badge league-badge-reserve">Резерв #{{ $lt->reserve_position }}</span>
								@elseif($lt->status === 'pending_confirmation')
								<span class="league-badge league-badge-pending">Ожидает</span>
								@else
								<span class="league-badge">{{ $lt->status }}</span>
								@endif
							</td>
							<td>
								@if($lt->status === 'active')
								<form method="POST" action="{{ route('divisions.teams.toReserve', $lt) }}" style="display:inline">
									@csrf
									<button type="submit" class="league-btn league-btn-danger btn-alert" data-title="Перевести в резерв?" data-icon="warning" data-confirm-text="Да" data-cancel-text="Отмена">В резерв</button>
								</form>
								@elseif($lt->status === 'reserve')
								<form method="POST" action="{{ route('divisions.teams.activate', $lt) }}" style="display:inline">
									@csrf
									<button type="submit" class="league-btn league-btn-success btn-alert" data-title="Активировать команду?" data-icon="question" data-confirm-text="Да" data-cancel-text="Отмена">Активировать</button>
								</form>
								@endif
							</td>
						</tr>
						@endforeach
					</tbody>
				</table>
			</div>
			@else
			<div class="alert alert-info">В лиге пока нет команд. Команды добавятся после регистрации на турнир.</div>
			@endif

			@php
			$_tourAllCompleted = $stages->isNotEmpty() && $stages->every(fn($s) => $s->status === 'completed');
			@endphp
			<div class="mt-2 flex items-center gap-3 flex-wrap">
				<a class="blink" href="{{ route('seasons.show', $seasonData['season']) }}">Страница сезона →</a>
				<form method="POST" action="{{ route('tournament.syncLeague', $event) }}" style="margin:0">
					@csrf
					<button type="submit" class="league-btn league-btn-success">↻ Синхронизировать команды в лигу</button>
				</form>
				@if($_tourAllCompleted)
				<form method="POST" action="{{ route('tournament.applyPromotion', $event) }}" style="margin:0">
					@csrf
					<button type="submit" class="league-btn btn-alert" style="background:#fff3cd;border-color:#ffc107;color:#856404" data-title="Применить продвижение?" data-icon="question" data-confirm-text="Да, перенести" data-cancel-text="Отмена">
						🔄 Перенести составы на следующий тур
					</button>
				</form>
				@endif
			</div>
		</div>
		@endif
		
		
		{{-- ============================================================
		Команды
		============================================================ --}}
		<div class="ramka">
			<h2 class="-mt-05">Команды ({{ $teams->count() }})</h2>
			@if($teams->isEmpty())
			<div class="alert alert-info">Нет подтверждённых команд.</div>
			@else
			<div class="row row2">
				@foreach($teams as $team)
				<div class="col-6 col-md-3 mb-1">
					<div class="card">
						<a href="{{ route('tournamentTeams.show', [$event, $team]) }}" class="blink b-600 d-block mb-1">
							{{ $team->name }}
						</a>
						@php $members = $team->members->load('user'); @endphp
						@if($members->count() <= 2)
						@foreach($members as $m)
						<div>{{ trim(($m->user->last_name ?? '') . ' ' . ($m->user->first_name ?? '')) ?: $m->user->name ?? '?' }}</div>
						@endforeach
						@else
						@foreach($members->take(2) as $m)
						<div>{{ trim(($m->user->last_name ?? '') . ' ' . ($m->user->first_name ?? '')) ?: $m->user->name ?? '?' }}</div>
						@endforeach
						<div style="font-style:italic">и другие...</div>
						@endif
						<div class="mt-1 d-flex between fvc">
							<div class="mt-05 cd b-600">{{ $members->count() }} чел.</div>
							<form method="POST" action="{{ route('tournamentTeams.destroy', [$event, $team]) }}" class="mt-1">
								@csrf @method('DELETE')
								<button type="submit" class="icon-delete btn-alert btn btn-danger btn-svg" data-title="Удалить команду {{ $team->name }}?" data-icon="warning" data-confirm-text="Да, удалить" data-cancel-text="Отмена">
								</button>
							</form>
						</div>
					</div>
				</div>
				@endforeach
			</div>
			@endif
			
			{{-- Создать команду организатором --}}
			<div class="mt-1">
				<details>
					<summary class="btn btn-secondary">➕ Создать команду</summary>
                    <form method="POST" action="{{ route('tournamentTeams.store', $event) }}">
                        @csrf
						<div class="mt-2">
							<div class="row">
								<div class="col-md-6">
									<div class="card">
										<label>Название команды</label>
										<input type="text" name="name" placeholder="Название (авто по фамилии капитана)">
									</div>
								</div>
								<div class="col-md-6">
									<div class="card">
										<label>Капитан (поиск)</label>
										<div style="position:relative" id="org-captain-ac-wrap">
											<input type="text" id="org-captain-search" placeholder="Имя или ID..." autocomplete="off">
											<input type="hidden" name="captain_user_id" id="org-captain-id">
											<div id="org-captain-dd" class="form-select-dropdown trainer_dd"></div>
										</div>
									</div>
								</div>
								<div class="col-md-12 text-center">
									<button type="submit" class="btn">Создать</button>
								</div>
							</div>
						</div>
					</form>
				</details>
			</div>		
			
			
		</div>
		
		
		
		{{-- ============================================================
		Создание стадии (сворачивается если стадии уже есть)
		============================================================ --}}
		@php $hasStages = $event->tournamentStages->isNotEmpty(); @endphp
		<div class="ramka">
			<div class="d-flex between fvc" style="cursor:pointer" onclick="var b=this.nextElementSibling;b.style.display=b.style.display==='none'?'':'none';this.querySelector('.toggle-icon').textContent=b.style.display==='none'?'+':'-'">
				<h2 class="-mt-05">Добавить стадию</h2>
				<span class="toggle-icon b-700 f-20">{{ $hasStages ? '+' : '-' }}</span>
			</div>
			<div style="{{ $hasStages ? 'display:none' : '' }}">
				<form method="POST" action="{{ route('tournament.stages.store', $event) }}">
					@csrf
					@if($selectedOccurrence)
					<input type="hidden" name="occurrence_id" value="{{ $selectedOccurrence->id }}">
					@endif
					<div class="row">
						<div class="col-md-6">
							<div class="card">
								<label>Тип</label>
								<select name="type" id="stage_type_select">
									<option value="round_robin">Круговая система (Round Robin)</option>
									<option value="groups_playoff">Группы + плей-офф</option>
									<option value="single_elim">Олимпийка</option>
									<option value="swiss">Швейцарская</option>
									<option value="double_elim">Двойное выбывание (Double Elimination)</option>
									<option value="king_of_court">Король площадки (King of the Court)</option>
									<option value="thai">Тайский формат</option>
								</select>
								<a href="{{ route('tournament_formats') }}" target="_blank" class="f-16 blink mt-1">Шпаргалка по форматам →</a>
								
								<label class="mt-2">Название</label>
								<input name="name" value="{{ old('name', 'Групповой этап') }}" required>
							</div>
						</div>
						<div class="col-md-6">
							<div class="card">
								<label>Формат матча</label>
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
											bo1: 'Играют 1 сет. Кто выиграл сет — выиграл матч. Быстрый формат для пулов.',
											bo3: 'Играют до 2 побед в сетах. Максимум 3 сета (2:0 или 2:1). Стандарт для пляжки и групповых этапов.',
											bo5: 'Играют до 3 побед в сетах. Максимум 5 сетов. Обычно только для финалов классики 6×6.'
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
								
								<div class="row row2">
									<div class="col-md-6">
										<label class="mt-2">Очки в сете</label>
										<select name="set_points">
											@if(!$isBeach)
											<option value="25" selected>25 (классика)</option>
											@endif
											<option value="21" @if($isBeach) selected @endif>21 (пляж)</option>
											<option value="15">15 (мини)</option>
										</select>
									</div>
									<div class="col-md-6" id="deciding_set_wrap">
										<label class="mt-2">Решающий сет</label>
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
						
					</div>
					<div class="mt-2" id="group_fields">
						<div class="row">
							<div class="col-md-3">
								<div class="card"><label>Кол-во групп</label>
									<input name="groups_count" type="number" value="2" min="1" max="16">
								</div>
							</div>
							<div class="col-md-3">
								<div class="card"><label>Выходят из группы</label>
									<input name="advance_count" type="number" value="2" min="1" max="8">
								</div>
							</div>
							<div class="col-md-3">
								<div class="card"><label>Матч за 3-е место</label>
									<select name="third_place_match">
										<option value="0">Нет</option>
										<option value="1">Да</option>
									</select>
								</div>
							</div>
							<div class="col-md-3">
								<div class="card">
									<label>Кол-во площадок</label>
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
						</div>
						
						{{-- Назначение кортов группам (динамическое) --}}
						<div class="mt-2" id="courts_group_assign" style="display:none">
						<div class="card">
							<label>Площадки для групп</label>
							<hr class="mb-1">
							<div id="courts_group_boxes" class="row"></div>
						</div>	
						</div>
						{{-- Жеребьёвка --}}
						<div class="row mt-2 mb-2">
							<div class="col-md-4">
								<div class="card">
									<label>Жеребьёвка</label>
									<select name="draw_mode" id="draw_mode_select">
										<option value="random">Случайная</option>
										<option value="seeded">По рейтингу (seed)</option>
										<option value="manual">Ручная</option>
									</select>
								</div>
							</div>
						</div>
						
						{{-- Ручное распределение --}}
						<div id="manual_draw_block" style="display:none" class="mt-2 mb-2">
							<div class="card p-3">
								<div class="b-700 f-14 mb-2">Ручное распределение по группам</div>
								<div class="f-13 mb-2" style="color:#9ca3af">Выберите группу для каждой команды.</div>
								@if($teams->isNotEmpty())
								<table style="width:100%;border-collapse:collapse;font-size:14px">
									<thead>
										<tr style="border-bottom:2px solid rgba(128,128,128,.2)">
											<th class="p-1" style="text-align:left">Команда</th>
											<th class="p-1" style="text-align:center;width:150px">Группа</th>
										</tr>
									</thead>
									<tbody>
										@foreach($teams as $team)
										<tr style="border-bottom:1px solid rgba(128,128,128,.1)">
											<td class="p-1">
												<div class="b-600">{{ $team->name }}</div>
												@if($team->members->count())
												<div class="f-12" style="color:#6b7280">{{ $team->members->map(fn($m) => trim(($m->user->last_name ?? '') . ' ' . ($m->user->first_name ?? '')))->implode(' / ') }}</div>
												@endif
											</td>
											<td class="p-1" style="text-align:center">
												<select name="manual_teams[{{ $team->id }}]" class="f-13 manual-group-select" style="width:100%">
													<option value="">—</option>
													<option value="A">Группа A</option>
													<option value="B">Группа B</option>
												</select>
											</td>
										</tr>
										@endforeach
									</tbody>
								</table>
								@else
								<div class="f-13" style="opacity:.5">Нет команд для распределения</div>
								@endif
							</div>
						</div>
						
						{{-- Расписание (опционально) --}}
<div id="schedule_fields" class="mt-2 mb-2">
<div class="card p-3">
<div class="b-700 f-14 mb-2">Расписание (опционально)</div>
<div class="f-13 mb-2">Если указать время начала — матчи автоматически получат расписание.</div>
<div class="d-flex" style="gap:12px;flex-wrap:wrap;align-items:flex-end">
<div>
<label>Начало</label>
<input type="datetime-local" name="schedule_start" value="">
</div>
<div>
<label>Матч (мин)</label>
<input type="number" name="schedule_match_duration" value="30" min="15" max="180">
</div>
<div>
<label>Перерыв (мин)</label>
<input type="number" name="schedule_break_duration" value="5" min="0" max="60">
</div>
</div>
</div>
</div>
<div class="text-center">
<button type="submit" class="btn btn-primary mt-1">Создать стадию и провести жеребьёвку</button>
</div>
						<script>
							(function(){
								var courtsSel = document.getElementById("courts_count_select");
								var groupsSel = document.querySelector('input[name="groups_count"]');
								var hidden = document.getElementById("courts_hidden");
								var assignBlock = document.getElementById("courts_group_assign");
								var boxesDiv = document.getElementById("courts_group_boxes");
								
								function rebuild() {
									var n = parseInt(courtsSel.value) || 0;
									var g = parseInt(groupsSel ? groupsSel.value : 0) || 0;
									
									var names = [];
									for (var i = 1; i <= n; i++) names.push("Корт " + i);
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
										html += '<label>Группа ' + label + ':</label>';
										html += '<div class="d-flex" style="flex-wrap:wrap;gap:1rem">';
										names.forEach(function(court) {
											html += '<label class="checkbox-item f-13" style="min-width: 12rem; margin:0">';
											html += '<input type="checkbox" name="group_courts[' + label + '][]" value="' + court + '">';
											html += '<div class="custom-checkbox"></div>';
											html += '<span>' + court + '</span>';
											html += '</label>';
										});
										html += '</div></div>';
									});
									boxesDiv.innerHTML = html;
								}
								
								courtsSel.addEventListener("change", rebuild);
								if (groupsSel) groupsSel.addEventListener("input", rebuild);
								rebuild();
							})();
						</script>
					</div>
				</div>
			</form>
		</div>
		
		{{-- ============================================================
		Стадии
		============================================================ --}}
		
		{{-- MVP турнира --}}
		@php
			$allCompleted = $stages->isNotEmpty() && $stages->every(fn($s) => $s->status === 'completed');
			// Если сезонный турнир с 2+ группами, но групп Hard/Lite ещё нет — турнир не завершён
			if ($allCompleted && $event->season_id) {
				$groupStage = $stages->firstWhere('type', 'round_robin');
				if ($groupStage && $groupStage->groups->count() >= 2) {
					$hasDivisions = $stages->contains(fn($s) => str_starts_with($s->name, 'Группа '));
					if (!$hasDivisions) {
						$allCompleted = false;
					}
				}
			}
			$participants = collect();
			if ($allCompleted) {
				$participants = \App\Models\PlayerTournamentStats::where('event_id', $event->id)
					->with('user')
					->orderByDesc('match_win_rate')
					->get();
			}
		@endphp
		@if($allCompleted && $participants->isNotEmpty())
		<div class="ramka">
			<h2 class="-mt-05">⭐ MVP турнира</h2>
			@if($event->tournament_mvp_user_id)
				@php $currentMvp = \App\Models\User::find($event->tournament_mvp_user_id); @endphp
				<div class="card p-3 mb-2" style="text-align:center;background:rgba(231,97,47,.06);border:1px solid rgba(231,97,47,.2)">
					<div class="f-13 b-600 mb-1" style="opacity:.5">Текущий MVP</div>
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
					<label class="f-13 b-600 mb-2 d-block">Выберите MVP</label>
					<table style="width:100%;border-collapse:collapse;font-size:14px">
						<thead>
							<tr style="border-bottom:2px solid rgba(128,128,128,.2)">
								<th class="p-1" style="width:30px"></th>
								<th class="p-1" style="text-align:left">Игрок</th>
								<th class="p-1" style="text-align:center">WinRate</th>
								<th class="p-1" style="text-align:center">Матчи</th>
								<th class="p-1" style="text-align:center">Сеты</th>
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
						<button type="submit" class="btn btn-primary">Назначить MVP</button>
					</div>
				</div>
			</form>
		</div>
		@endif

		{{-- Фото турнира --}}
		@if($allCompleted)
		<div class="ramka">
			<h2 class="-mt-05">Фото турнира</h2>
			
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
						data-title="Удалить фотографию?"
						data-icon="warning"
						data-confirm-text="Да, удалить"
						data-cancel-text="Отмена">
						</button>										
					</form>
				</div>
				@endforeach
			</div>
			@endif
			
			@if(($userEventPhotos ?? collect())->count() > 0)
			<div class="card">
				<label>Выберите фото из вашей галереи</label>
				
				<div class="event-photos-selector" id="tournament-photos-selector"
				data-selected='{{ json_encode($currentPhotoIds) }}'>
					<div class="swiper tournamentPhotosSwiper">
						<div class="swiper-wrapper">
							@foreach($userEventPhotos as $photo)
							<div class="swiper-slide">
								<div class="hover-image mb-1">
									<img src="{{ $photo->getUrl('event_thumb') }}" alt="photo" loading="lazy"/>
								</div>
								<div class="mt-1 d-flex between fvc">
									<label class="checkbox-item mb-0">
										<input type="checkbox" class="t-photo-select" value="{{ $photo->id }}">
										<div class="custom-checkbox"></div>
										<span>Выбрать</span>
									</label>
									<div class="photo-order-badge f-16 b-600 cd"></div>
								</div>
							</div>
							@endforeach
						</div>
						<div class="swiper-pagination"></div>
					</div>
					
					<ul class="list f-16 mt-1">
						<li>Выберите фото для турнира. Первое отмеченное фото будет главным.</li>
						<li>Фотографии можно добавить в разделе <a target="_blank" href="{{ route('user.photos') }}">Ваши фотографии</a> (с галочкой «Для мероприятий»)</li>
					</ul>
				</div>
			</div>	
			<div class="text-center">
				<form method="POST" action="{{ route('tournament.photos.store', $event) }}" id="tournament-photos-form" class="mt-2">
					@csrf
					<input type="hidden" name="photo_ids" id="tournament_photos_input" value="">
					<button type="submit" class="btn btn-primary" id="tournament-photos-submit" style="display:none">Сохранить фото</button>
				</form>
			</div>
			@else
			<div class="alert alert-info">
				Нет фото в галерее. <a href="{{ route('user.photos') }}" target="_blank">Загрузите фото</a> с пометкой «Для мероприятий».
			</div>
			@endif
		</div>
		@endif
		
		@foreach($stages as $stage)
		@php
		$borderColor = $stage->isCompleted() ? '#10b981' : ($stage->isInProgress() ? '#2967BA' : '#555');
		$_isDivStage = str_starts_with($stage->name, 'Группа ');
		$stageHasDivDistribution = !$_isDivStage && $stages->contains(fn($s) => str_starts_with($s->name, 'Группа ') && $s->occurrence_id == $stage->occurrence_id);
		@endphp
		<div class="ramka" id="stage_{{ $stage->id }}" style="border-left:4px solid {{ $borderColor }}">
			<div class="d-flex between fvc mb-2" style="flex-wrap:wrap;gap:8px">
				<div>
					<h3 class="mb-1" style="font-size:1.3rem">
						{{ $stage->name }}
						@if($stage->isCompleted())
						<span class="f-12 b-600 p-1 px-2 ml-1" style="background:rgba(16,185,129,.15);border-radius:6px;color:#10b981">Завершена</span>
						@elseif($stage->isInProgress())
						<span class="f-12 b-600 p-1 px-2 ml-1" style="background:rgba(41,103,186,.15);border-radius:6px;color:#5b9ef0">В процессе</span>
						@else
						<span class="f-12 b-600 p-1 px-2 ml-1" style="opacity:.5">Ожидание</span>
						@endif
					</h3>
					<div class="f-13" style="opacity:.5">
						@php
						$stageTypeLabels = [
						'round_robin' => 'Круговая система',
						'groups_playoff' => 'Группы + плей-офф',
						'single_elim' => 'Олимпийка',
						'swiss' => 'Швейцарская',
						'double_elim' => 'Двойное выбывание',
						'king_of_court' => 'Король площадки',
						'thai' => 'Тайский формат',
						];
						$matchFormatLabels = ['bo1' => 'Best of 1', 'bo3' => 'Best of 3', 'bo5' => 'Best of 5'];
						@endphp
						{{ $stageTypeLabels[$stage->type] ?? $stage->type }} · {{ $matchFormatLabels[$stage->matchFormat()] ?? strtoupper($stage->matchFormat()) }} · до {{ $stage->setPoints() }} очков
					</div>
				</div>
				<div class="d-flex" style="gap:6px">
					@if($stage->isInProgress() || $stage->isCompleted())
					<form method="POST" action="{{ route('tournament.stages.revert', $stage) }}">
						@csrf
						<button class="btn btn-secondary f-12 btn-alert" style="color:#ca8a04" data-title="Откатить стадию?" data-icon="warning" data-confirm-text="Да, откатить" data-cancel-text="Отмена">Откатить</button>
					</form>
					@endif
					<form method="POST" action="{{ route('tournament.stages.destroy', $stage) }}">
						@csrf @method('DELETE')
						<button class="btn btn-secondary f-12 btn-alert" style="color:#dc2626" data-title="Удалить стадию и все её матчи?" data-icon="warning" data-confirm-text="Да, удалить" data-cancel-text="Отмена">Удалить</button>
					</form>
				</div>
			</div>
			
			{{-- Группы --}}
			@if($stage->groups->isNotEmpty())
			<div class="row mb-3">
				@foreach($stage->groups as $group)
				<div class="col-md-6 mb-2">
					<div class="card p-2">
						<div class="b-700 f-14 mb-2">{{ $group->name }}</div>
						
						@if($group->standings->isNotEmpty())
						<div style="overflow-x:auto">
						<table style="width:100%;border-collapse:collapse;font-size:13px">
							<thead>
								<tr style="border-bottom:2px solid rgba(128,128,128,.2)">
									<th class="p-1" style="text-align:center;width:30px">Место</th>
									<th class="p-1" style="text-align:left">Команда</th>
									<th class="p-1" style="text-align:center">И</th>
									<th class="p-1" style="text-align:center">В</th>
									<th class="p-1" style="text-align:center">П</th>
									<th class="p-1" style="text-align:center">Очки</th>
<th class="p-1" style="text-align:center">Разн.</th>
								</tr>
							</thead>
							<tbody>
								@foreach($group->standings->sortBy('rank') as $standing)
								<tr style="border-bottom:1px solid rgba(128,128,128,.1)">
									<td class="p-1 b-700" style="text-align:center">{{ $standing->rank }}</td>
									<td class="p-1">
										<div class="b-600">{{ $standing->team->name ?? '—' }}</div>
										@if($standing->team && $standing->team->members->count())
										<div class="f-11" style="color:#6b7280">{{ $standing->team->members->map(fn($m) => $m->user->last_name ?? '?')->implode(' / ') }}</div>
										@endif
									</td>
									<td class="p-1" style="text-align:center">{{ $standing->played }}</td>
									<td class="p-1" style="text-align:center;color:#10b981">{{ $standing->wins }}</td>
									<td class="p-1" style="text-align:center;color:#dc2626">{{ $standing->losses }}</td>
									<td class="p-1 b-700" style="text-align:center">{{ $standing->rating_points }}</td>
<td class="p-1" style="text-align:center">{{ $standing->points_scored - $standing->points_conceded > 0 ? '+' : '' }}{{ $standing->points_scored - $standing->points_conceded }}</td>
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
					</div>
				</div>
				@endforeach
			</div>
			@endif
			

			
			{{-- Матчи --}}
			@if($stage->matches->isNotEmpty())
			@php
			$matchesByGroup = $stage->matches->sortBy(["round", "match_number"])->groupBy('group_id');
			$hasGroups = $stage->groups->count() > 1;
			@endphp
			@foreach($matchesByGroup as $groupId => $groupMatches)
			@php $groupName = $stage->groups->firstWhere('id', $groupId)?->name ?? ''; @endphp
			<div class="card p-2">
				<div class="b-700 f-14 mb-2">{{ $groupName ? 'Матчи ' . $groupName : 'Матчи' }}</div>
				<div style="overflow-x:auto">
					<table style="width:100%;border-collapse:collapse;font-size:13px">
						<thead>
<tr style="border-bottom:2px solid rgba(128,128,128,.2)">
<th class="p-1" style="text-align:left">#</th>
<th class="p-1" style="text-align:left">Тур</th>
<th class="p-1" style="text-align:left">Дома</th>
<th class="p-1" style="text-align:left">Гости</th>
<th class="p-1" style="text-align:center">Сеты</th>
<th class="p-1" style="text-align:center">Счёт</th>
<th class="p-1" style="text-align:center">Время</th>
<th class="p-1" style="text-align:center">Корт</th>
<th class="p-1" style="text-align:center">Статус</th>
<th class="p-1"></th>
</tr>
</thead>
						<tbody>
@foreach($groupMatches as $match)
<tr style="border-bottom:1px solid rgba(128,128,128,.1);{{ $match->isCompleted() ? 'background:rgba(16,185,129,.06)' : '' }}">
<td class="p-1">{{ $match->match_number }}</td>
<td class="p-1">R{{ $match->round }}</td>
<td class="p-1 {{ $match->winner_team_id === $match->team_home_id ? 'b-700' : '' }}">
<div>{{ $match->teamHome->name ?? 'TBD' }}</div>
@if($match->teamHome && $match->teamHome->members->count())
<div class="f-11" style="color:#6b7280">{{ $match->teamHome->members->map(fn($m) => $m->user->last_name ?? '?')->implode(' / ') }}</div>
@endif
</td>
<td class="p-1 {{ $match->winner_team_id === $match->team_away_id ? 'b-700' : '' }}">
<div>{{ $match->teamAway->name ?? 'TBD' }}</div>
@if($match->teamAway && $match->teamAway->members->count())
<div class="f-11" style="color:#6b7280">{{ $match->teamAway->members->map(fn($m) => $m->user->last_name ?? '?')->implode(' / ') }}</div>
@endif
</td>
<td class="p-1" style="text-align:center">{{ $match->setsScore() ?? '—' }}</td>
<td class="p-1" style="text-align:center">{{ $match->detailedScore() ?: '—' }}</td>
<td class="p-1" style="text-align:center">{{ $match->scheduled_at ? $match->scheduled_at->setTimezone($event->timezone ?? 'Europe/Moscow')->format('H:i') : '—' }}</td>
<td class="p-1" style="text-align:center">{{ $match->court ?? '—' }}</td>
<td class="p-1" style="text-align:center">
@if($match->isCompleted())
<span class="f-11 b-600 p-1 px-2" style="background:rgba(16,185,129,.15);border-radius:6px;color:#10b981">✓</span>
@if(!$stageHasDivDistribution)
<a href="{{ route('tournament.matches.score.form', $match) }}?edit=1" class="f-14" style="text-decoration:none;margin-left:4px" title="Исправить счёт">🛠</a>
@endif
@elseif($match->status === 'live')
<span class="f-11 b-600 p-1 px-2" style="background:rgba(220,38,38,.15);border-radius:6px;color:#dc2626">LIVE</span>
@else
<span class="f-11" style="opacity:.5">ожидание</span>
@endif
</td>
<td class="p-1">
@if($match->isScheduled() && $match->hasTeams())
<a href="{{ route('tournament.matches.score.form', $match) }}" class="btn btn-primary f-12" style="padding:4px 10px">
Счёт
</a>
@endif
@if($match->isCompleted())
<a href="{{ route('tournament.matches.player_stats.form', $match) }}" class="btn btn-secondary f-12" style="padding:4px 8px" title="Статистика игроков">
📊
</a>
@endif
</td>
</tr>
@endforeach
</tbody>
					</table>
				</div>
			</div>
			@endforeach
			@php
			$hasUnplayed = $stage->matches->where('status', 'scheduled')
			->filter(fn($m) => $m->team_home_id && $m->team_away_id)->isNotEmpty();
			@endphp
			@if($hasUnplayed)
			<div class="mt-2 mb-3" style="text-align:center">
				<a href="{{ route('tournament.start_scoring', $event) }}" class="btn btn-primary p-3 f-16" style="display:inline-block">
					▶ Приступить к заполнению результатов
				</a>
			</div>
			@endif
			
			
			{{-- Следующий тур (Swiss/King) --}}
			@if($stage->isInProgress() && in_array($stage->type, ['swiss', 'king_of_court']))
			<div class="p-3 mt-2" style="background:rgba(231,97,47,.08);border-radius:10px">
				<form method="POST" action="{{ route('tournament.stages.nextRound', $stage) }}" class="d-flex fvc" style="gap:10px">
					@csrf
					<div class="b-600">
						{{ $stage->type === 'swiss' ? 'Сгенерировать следующий тур' : 'Следующий матч King of the Court' }}
					</div>
					<button type="submit" class="btn btn-primary">Далее →</button>
				</form>
			</div>
			@endif
			
			
			
			@endif
			{{-- Продвижение / Группы --}}
			@if($stage->isCompleted() && in_array($stage->type, ['round_robin', 'groups_playoff']))
			
			{{-- Сезонный турнир → группы Hard/Lite --}}
			@if($event->season_id && $stage->groups->count() >= 2)
			@php $hasDivStages = $stages->filter(fn($s) => str_starts_with($s->name, 'Группа '))->isNotEmpty(); @endphp
			<div class="ramka" style="background:rgba(41,103,186,.04);border:1px solid rgba(41,103,186,.15)">
				<div class="d-flex between fvc" style="cursor:pointer" onclick="var b=this.nextElementSibling;b.style.display=b.style.display==='none'?'':'none';this.querySelector('.toggle-icon').textContent=b.style.display==='none'?'+':'-'">
					<h3 class="-mt-05 mb-0">🏆 Формирование групп</h3>
					<span class="toggle-icon b-700 f-20">{{ $hasDivStages ? '+' : '-' }}</span>
				</div>
				<div style="{{ $hasDivStages ? 'display:none' : '' }}">
					@php
					$groupsCount = $stage->groups->count();
					$divisionNames = match($groupsCount) {
					2 => ['Hard', 'Lite'],
					3 => ['Hard', 'Medium', 'Lite'],
					default => array_merge(['Hard'], array_map(fn($i) => 'Medium-' . $i, range(1, max(1, $groupsCount - 2))), ['Lite']),
					};
					$advanceCount = (int) $stage->cfg('advance_count', 2);
					$availCourts = $stage->cfg('courts', []);
					@endphp
					
					<div class="f-13 mb-3" style="color:#6b7280">
						По результатам группового этапа команды распределяются в {{ count($divisionNames) }} групп{{ count($divisionNames) > 2 ? 'а' : '' }}:
						<strong>{{ implode(', ', $divisionNames) }}</strong>
					</div>
					
					<form method="POST" action="{{ route('tournament.stages.formDivisions', $stage) }}">
						@csrf
						
						{{-- Ряд 1: Кол-во + форматы --}}
						<div class="row mb-3">
							<div class="col-md-3 mb-2">
								<label class="f-13 b-600 mb-1 d-block">Выходят в Hard</label>
								<input name="advance_per_group" type="number" value="{{ $advanceCount }}" min="1" max="8" style="width:70px">
								<div class="f-12 mt-1" style="color:#9ca3af">из каждой группы</div>
							</div>
							@foreach($divisionNames as $dn)
							<div class="col-md-3 mb-2">
								<label class="f-13 b-600 mb-1 d-block">Формат {{ $dn }}</label>
								<select name="div_format_{{ strtolower($dn) }}" class="f-13" style="width:100%">
									<option value="">как в группах</option>
									<option value="bo1">Bo1</option>
									<option value="bo3">Bo3</option>
								</select>
							</div>
							@endforeach
						</div>
						
						{{-- Ряд 2: Площадки --}}
						@if(count($availCourts) > 0)
						<div class="mb-3">
							<label class="f-13 b-600 mb-2 d-block">Площадки для групп</label>
							<div class="row">
								@foreach($divisionNames as $dn)
								<div class="col-md-{{ (int)(12 / count($divisionNames)) }} mb-2">
									<div class="f-13 b-600 mb-1">{{ $dn }}:</div>
									<div class="d-flex" style="flex-wrap:wrap;gap:6px">
										@foreach($availCourts as $court)
										<label class="checkbox-item f-13" style="margin:0">
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
						<div class="mb-3">
							<label class="f-13 b-600 mb-2 d-block">Расписание (опционально)</label>
							<div class="card p-3">
								<div class="f-13 mb-2">Если указать время начала — матчи автоматически получат расписание и площадки.</div>
								<div class="d-flex" style="gap:12px;flex-wrap:wrap;align-items:flex-end">
									<div>
										<label>Начало</label>
										<input type="datetime-local" name="schedule_start" value="{{ \Carbon\Carbon::now($event->timezone ?? 'Europe/Moscow')->format('Y-m-d\TH:i') }}">
									</div>
									<div>
										<label>Матч (мин)</label>
										<input type="number" name="schedule_match_duration" value="30" min="15" max="180">
									</div>
									<div>
										<label>Перерыв (мин)</label>
										<input type="number" name="schedule_break_duration" value="5" min="0" max="60">
									</div>
								</div>
							</div>
						</div>

						<button type="submit" class="btn btn-primary btn-alert" data-title="Сформировать группы?" data-icon="question" data-confirm-text="Да, сформировать" data-cancel-text="Отмена">Сформировать группы</button>
					</form>
				</div>
			</div>
			@else
			{{-- Обычный → плей-офф --}}
			@php $nextStages = $stages->where('type', 'single_elim')->where('status', 'pending'); @endphp
			@if($nextStages->isNotEmpty())
			<div class="p-3 mt-2" style="background:rgba(41,103,186,.08);border-radius:10px">
				<div class="b-700 mb-2">Продвижение в плей-офф</div>
				<form method="POST" action="{{ route('tournament.stages.advance', $stage) }}" class="d-flex fvc" style="gap:10px;flex-wrap:wrap">
					@csrf
					<div>
						<label class="f-13 b-600 mb-1 d-block">Стадия</label>
						<select name="playoff_stage_id">
							@foreach($nextStages as $ns)
							<option value="{{ $ns->id }}">{{ $ns->name }}</option>
							@endforeach
						</select>
					</div>
					<div>
						<label class="f-13 b-600 mb-1 d-block">Выходят</label>
						<input name="advance_per_group" type="number" value="{{ $stage->cfg('advance_count', 2) }}" min="1" max="8" style="width:60px">
					</div>
					<button type="submit" class="btn btn-primary">Продвинуть</button>
				</form>
			</div>
			@endif
			@endif
			@endif
		</div>
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
		<div class="ramka" style="background:rgba(16,185,129,.06);border:1px solid rgba(16,185,129,.2)">
			<h3 style="margin:0 0 8px">✅ Все группы завершены</h3>
			<p class="f-14" style="color:#6b7280;margin-bottom:12px">
				По правилам сезона: все команды Hard остаются, из Lite — top-2 остаются{{ $hasMedium ? ', из Medium — top-3 остаются' : '' }}, остальные уходят в резерв.
				Освободившиеся места заполняются из резерва.
			</p>
			<form method="POST" action="{{ route('tournament.applyPromotion', $event) }}">
				@csrf
				<button type="submit" class="btn btn-primary btn-alert" data-title="Применить промоушен?" data-icon="question" data-confirm-text="Да, применить" data-cancel-text="Отмена">Применить промоушен</button>
			</form>
		</div>
		@endif
		
		{{-- Кнопка результатов тура --}}
		@if($divStages->isNotEmpty())
		<div class="ramka" style="text-align:center">
			<a href="{{ route('tournament.public.show', $event) }}{{ $selectedOccurrence ? '?occurrence_id=' . $selectedOccurrence->id : '' }}" class="btn btn-primary p-3 f-16" style="display:inline-block">
				📊 Посмотреть результаты тура
			</a>
		</div>
		@endif
		@endif
		
		
		@if($stages->isEmpty())
		<div style="text-align:center;opacity:.5;padding:40px 0">
			<p class="f-18 b-600">Турнир пока не настроен</p>
			<p>Создайте первую стадию выше, затем проведите жеребьёвку.</p>
		</div>
		@endif
		
		
	</div>

	<script>
		document.addEventListener('DOMContentLoaded', function() {
			var typeSelect = document.getElementById('stage_type_select');
			var groupFields = document.getElementById('group_fields');
			if (typeSelect && groupFields) {
				function toggle() {
					var t = typeSelect.value;
					groupFields.style.display = (t === 'round_robin' || t === 'groups_playoff' || t === 'thai') ? '' : 'none';
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
			function showDd() { dd.classList.add('form-select-dropdown--active'); }
			function hideDd() { dd.classList.remove('form-select-dropdown--active'); }

			inp.addEventListener('input', function() {
				clearTimeout(timer);
				var q = inp.value.trim();
				if (q.length < 2) { hideDd(); dd.innerHTML = ''; return; }
				dd.innerHTML = '<div class="city-message">Поиск…</div>';
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
							dd.innerHTML = '<div class="city-message">Ничего не найдено</div>';
							showDd();
							return;
						}
						items.slice(0, 8).forEach(function(u) {
							var label = u.label || u.name || '#' + u.id;
							var div = document.createElement('div');
							div.className = 'trainer-item form-select-option';
							div.innerHTML = '<div class="text-sm">' + esc(label) + '</div>';
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
						dd.innerHTML = '<div class="city-message">Ошибка загрузки</div>';
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
	
	
	<script src="/assets/fas.js"></script>  
	<script>
		document.addEventListener('DOMContentLoaded', function() {
			// Tournament Photos Swiper
			if (document.querySelector('.tournamentPhotosSwiper')) {
				new Swiper('.tournamentPhotosSwiper', {
					slidesPerView: 3,
					spaceBetween: 20,
					pagination: { el: '.tournamentPhotosSwiper .swiper-pagination', clickable: true },
					breakpoints: {
						320: { slidesPerView: 2 },
						640: { slidesPerView: 3 },
						1024: { slidesPerView: 4 }
					}
				});
				
				var container = document.getElementById('tournament-photos-selector');
				if (container) {
					var savedPhotos = JSON.parse(container.dataset.selected || '[]');
					var selectedPhotos = savedPhotos.slice();
					
					function updateTournamentUI() {
						document.querySelectorAll('.t-photo-select').forEach(function(cb) {
							var id = parseInt(cb.value);
							var isSelected = selectedPhotos.indexOf(id) !== -1;
							cb.checked = isSelected;
							var badge = cb.closest('.swiper-slide').querySelector('.photo-order-badge');
							if (isSelected) {
								var order = selectedPhotos.indexOf(id) + 1;
								badge.textContent = order === 1 ? '★ Главное' : 'Фото: ' + order;
								} else {
								badge.textContent = '';
							}
						});
						document.getElementById('tournament_photos_input').value = JSON.stringify(selectedPhotos);
						var btn = document.getElementById('tournament-photos-submit');
						btn.style.display = selectedPhotos.length > 0 ? '' : 'none';
					}
					
					document.querySelectorAll('.t-photo-select').forEach(function(cb) {
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
					});
					
					updateTournamentUI();
				}
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
</x-voll-layout>
