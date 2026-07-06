<x-voll-layout body_class="tournament-score-page">
	@php $isEdit = request()->query('edit') === '1' && $match->isCompleted(); @endphp
	<x-slot name="title">{{ $isEdit ? __('tournaments.score_h1_edit') : __('tournaments.score_h1') }} — {{ $event->title }}</x-slot>
	<x-slot name="h1">{{ $isEdit ? __('tournaments.score_h1_edit') : __('tournaments.score_h1') }}</x-slot>
	
	<x-slot name="breadcrumbs">
		<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
			<a href="{{ route('tournament.setup', $event) }}{{ $match->stage->occurrence_id ? '?occurrence_id=' . $match->stage->occurrence_id : '' }}" itemprop="item"><span itemprop="name">{{ $event->title }}</span></a>
			<meta itemprop="position" content="2">
		</li>
		<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
			<span itemprop="name">{{ __('tournaments.score_match_n', ['n' => $match->match_number]) }}</span>
			<meta itemprop="position" content="3">
		</li>
	</x-slot>
	
	<div class="container">
		
		@if(session('error'))
        <div class="alert alert-error">
            {{ session('error') }}
		</div>
		@endif
		@if($errors->any())
		<div class="alert alert-error">
            @foreach($errors->all() as $err)
			{{ $err }}<br>
            @endforeach
		</div>
		@endif	
		
		
		<div class="ramka">
			
			
			{{-- Шапка матча --}}
			<div class="card p-3 mb-2" style="text-align:center">
				<div class="f-13 mb-2">
					{{ __('tournaments.score_match_header', ['n' => $match->match_number, 'r' => $match->round]) }} · {{ strtoupper($stage->matchFormat()) }} · {{ __('tournaments.score_to_pts') }} {{ $stage->setPoints() }}@if($match->group) · {{ $match->group->name }}@endif
				</div>
				@php $kbMeta = ($stage->type === 'king_beach') ? ($match->meta ?? []) : null; @endphp
				@if($kbMeta)
				{{-- King of the Beach: показываем пары игроков --}}
				@php
				$kbUsers = \App\Models\User::whereIn('id', array_merge($kbMeta['home_players'] ?? [], $kbMeta['away_players'] ?? []))->get()->keyBy('id');
				$kbNameOf = fn($uid) => trim(($kbUsers[$uid]->last_name ?? '') . ' ' . ($kbUsers[$uid]->first_name ?? '')) ?: '#'.$uid;
				$kbHomeNames = array_map($kbNameOf, $kbMeta['home_players'] ?? []);
				$kbAwayNames = array_map($kbNameOf, $kbMeta['away_players'] ?? []);
				@endphp
				<div class="d-flex between fvc">
					<div style="flex:1;text-align:center">
						<div class="b-700 f-18">{{ implode(' + ', $kbHomeNames) }}</div>
					</div>
					<div class="px-2 f-20 b-600">VS</div>
					<div style="flex:1;text-align:center">
						<div class="b-700 f-18">{{ implode(' + ', $kbAwayNames) }}</div>
					</div>
				</div>
				@else
				<div class="d-flex between fvc">
					<div style="flex:1;text-align:center">
						@if($match->teamHome && $match->teamHome->team_kind === 'classic_team')
						{{-- Классика: только название команды + капитан --}}
						<div class="b-700 f-18">@include('tournaments._partials.team_name_link', ['team' => $match->teamHome, 'fallback' => 'TBD'])</div>
						@include('tournaments._partials.team_roster_line', ['team' => $match->teamHome, 'class' => 'f-16'])
						@else
						{{-- Пляжка: полный состав с учётом замен, как раньше --}}
						@php
								$occId = $match->stage->occurrence_id;
								$homeRoster = $match->teamHome && $occId
									? app(\App\Services\TeamSubstitutionService::class)->getActualRoster($match->teamHome->id, $occId)
									: collect();
								@endphp
						<div class="b-700 f-18">{{ $match->teamHome->name ?? 'TBD' }}</div>
						@if($match->teamHome && ($homeRoster->isNotEmpty() || $match->teamHome->members->count()))
						<div class="f-16 team-members">
							@foreach(($homeRoster->isNotEmpty() ? $homeRoster : $match->teamHome->members->map(fn($m)=>['user'=>$m->user,'is_substitute'=>false,'original_user'=>null,'member'=>$m])) as $mi => $row)
							@if($row['user'] ?? null)
							<a href="{{ route('users.show', $row['user']) }}" class="blink">{{ $row['user']->last_name }} {{ $row['user']->first_name }}</a>@if($row['is_substitute'] ?? false) <span class="f-12" style="opacity:.6">({{ __('tournaments.sub_label') }})</span>@endif{{ $mi < count($homeRoster->isNotEmpty() ? $homeRoster->toArray() : $match->teamHome->members->toArray()) - 1 ? ' / ' : '' }}
							@endif
							@endforeach
						</div>
						@endif
						@endif
					</div>
					<div class="px-2 f-20 b-600">VS</div>
					<div style="flex:1;text-align:center">
						@if($match->teamAway && $match->teamAway->team_kind === 'classic_team')
						{{-- Классика: только название команды + капитан --}}
						<div class="b-700 f-18">@include('tournaments._partials.team_name_link', ['team' => $match->teamAway, 'fallback' => 'TBD'])</div>
						@include('tournaments._partials.team_roster_line', ['team' => $match->teamAway, 'class' => 'f-16'])
						@else
						{{-- Пляжка: полный состав с учётом замен, как раньше --}}
						@php
								$occId = $match->stage->occurrence_id;
								$awayRoster = $match->teamAway && $occId
									? app(\App\Services\TeamSubstitutionService::class)->getActualRoster($match->teamAway->id, $occId)
									: collect();
								@endphp
						<div class="b-700 f-18">{{ $match->teamAway->name ?? 'TBD' }}</div>
						@if($match->teamAway && ($awayRoster->isNotEmpty() || $match->teamAway->members->count()))
						<div class="f-16 team-members">
							@foreach(($awayRoster->isNotEmpty() ? $awayRoster : $match->teamAway->members->map(fn($m)=>['user'=>$m->user,'is_substitute'=>false,'original_user'=>null,'member'=>$m])) as $mi => $row)
							@if($row['user'] ?? null)
							<a href="{{ route('users.show', $row['user']) }}" class="blink">{{ $row['user']->last_name }} {{ $row['user']->first_name }}</a>@if($row['is_substitute'] ?? false) <span class="f-12" style="opacity:.6">({{ __('tournaments.sub_label') }})</span>@endif{{ $mi < count($awayRoster->isNotEmpty() ? $awayRoster->toArray() : $match->teamAway->members->toArray()) - 1 ? ' / ' : '' }}
							@endif
							@endforeach
						</div>
						@endif
						@endif
					</div>
				</div>
				@endif
				</div>

				@if($stage->type !== 'king_beach' && !$match->isCompleted())
					@if($hasRallyData ?? false)
					<div class="alert alert-info d-flex fvc gap-1" style="flex-wrap:wrap">
						<span>{{ __('tournaments.rally_partial_banner') }}</span>
						<a href="{{ route('tournament.matches.rally.form', $match) }}" class="btn btn-small btn-primary">{{ __('tournaments.rally_continue') }}</a>
					</div>
					@else
					<div class="text-center mb-2">
						<a href="{{ route('tournament.matches.rally.form', $match) }}" class="btn btn-outline">📊 {{ __('tournaments.rally_switch_link') }}</a>
					</div>
					@endif
				@endif

				@if($stage->type !== 'king_beach' && $isEdit && ($hasRallyDataCompleted ?? false))
				<div class="alert alert-info d-flex fvc gap-1 mb-2" style="flex-wrap:wrap">
					<span>{{ __('tournaments.rally_reopen_banner') }}</span>
					<form method="POST" action="{{ route('tournament.matches.rally.reopen', $match) }}" style="margin:0">
						@csrf
						<button type="submit" class="btn btn-small btn-primary btn-alert"
							data-title="{{ __('tournaments.rally_reopen_confirm_title') }}"
							data-icon="warning"
							data-confirm-text="{{ __('tournaments.rally_reopen_btn') }}"
							data-cancel-text="{{ __('tournaments.btn_cancel') }}">{{ __('tournaments.rally_reopen_btn') }}</button>
					</form>
				</div>
				@endif

					{{-- Форма счёта --}}
					<form method="POST" action="{{ $isEdit ? route('tournament.matches.rescore', $match) : route('tournament.matches.score', $match) }}" id="scoreForm">
						@csrf
						@method('PATCH')
						@if($match->stage->occurrence_id)
							<input type="hidden" name="occurrence_id" value="{{ $match->stage->occurrence_id }}">
						@endif
						
						@php
						$format = $stage->matchFormat();
						$maxSets = match($format) { 'bo1' => 1, 'bo3' => 3, 'bo5' => 5, default => 3 };
						$setsToWin = match($format) { 'bo1' => 1, 'bo3' => 2, 'bo5' => 3, default => 2 };
						$setPoints = $stage->setPoints();
						$maxScore = $setPoints + 20; // допуск для overtime (25+20=45)
						$existingHome = $isEdit ? ($match->score_home ?? []) : [];
						$existingAway = $isEdit ? ($match->score_away ?? []) : [];
						$existingSetsCount = max(count($existingHome), $setsToWin);
						@endphp
						
						@if($isEdit)
						<div class="alert alert-info">
							{{ __('tournaments.score_edit_warn') }}
						</div>
						@endif
						
						<div id="sets_container" class="form">
							@for($i = 0; $i < $maxSets; $i++)
							<div class="card mt-2 set-row" data-set="{{ $i }}" style="{{ $i >= $existingSetsCount && !isset($existingHome[$i]) ? 'display:none;' : '' }}">
								<div class="d-flex between fvc">
									<span class="b-700 f-14">{{ __('tournaments.score_set_n', ['n' => $i + 1]) }}</span>
									<div class="d-flex fvc" style="gap:10px">
										<select name="sets[{{ $i }}][0]" class="score-select" data-set="{{ $i }}" data-side="home"
										style="width:60px;text-align:center;font-size:1.2rem;font-weight:700;padding:6px 2px">
											<option value="">—</option>
											@for($s = 0; $s <= $maxScore; $s++)
											<option value="{{ $s }}" {{ isset($existingHome[$i]) && $existingHome[$i] == $s ? 'selected' : '' }}>{{ $s }}</option>
											@endfor
										</select>
										<span class="f-18 b-700">:</span>
										<select name="sets[{{ $i }}][1]" class="score-select" data-set="{{ $i }}" data-side="away"
										style="width:60px;text-align:center;font-size:1.2rem;font-weight:700;padding:6px 2px">
											<option value="">—</option>
											@for($s = 0; $s <= $maxScore; $s++)
											<option value="{{ $s }}" {{ isset($existingAway[$i]) && $existingAway[$i] == $s ? 'selected' : '' }}>{{ $s }}</option>
											@endfor
										</select>
									</div>
								</div>
							</div>
							@endfor
						</div>
						
						<div class="mt-2 mb-3" style="text-align:center">
							<span id="score_summary" class="f-20 b-800">0 : 0</span>
							<div class="f-16">{{ __('tournaments.score_by_sets') }}</div>
						</div>
						<div class="d-flex text-center gap-1">
							<button type="submit" class="btn btn-primary w-100 mt-2" id="submitBtn" {{ $isEdit ? '' : 'disabled' }}>
								{{ $isEdit ? __('tournaments.score_btn_save_edit') : __('tournaments.score_btn_save_new') }}
							</button>
							
							@php
							$backOccId = $match->stage->occurrence_id;
							@endphp
							<a href="{{ route('tournament.setup', $event) }}{{ $backOccId ? '?occurrence_id=' . $backOccId : '' }}" class="btn btn-secondary w-100 mt-2" style="text-align:center;display:block">
								{{ __('tournaments.btn_back') }}
							</a>
							
							@if($match->isCompleted())
							<a href="{{ route('tournament.matches.player_stats.form', $match) }}" class="w-100 btn btn-secondary mt-2">
								📊 {{ __('tournaments.score_fill_stats') }}
							</a>
							@endif
						</div>	
					</form>
					
				</div>
			</div>
			
			<script>
				document.addEventListener('DOMContentLoaded', function() {
					var maxSets = {{ $maxSets }};
					var setsToWin = {{ $setsToWin }};
					var setPoints = {{ $setPoints }};
					var decidingPts = {{ $stage->decidingSetPoints() }};
					var selects = document.querySelectorAll('.score-select');
					var submitBtn = document.getElementById('submitBtn');
					var summary = document.getElementById('score_summary');
					
					// Определяем очки для конкретного сета
					// Решающий сет (3-й в Bo3, 5-й в Bo5) играется до decidingPts
					// Bo1 — всегда до setPoints (нет решающего)
					function getSetTarget(setIndex) {
						if (maxSets === 1) return setPoints;
						// Решающий = последний возможный сет в Bo3/Bo5
						if (setIndex === maxSets - 1) return decidingPts;
						return setPoints;
					}
					
					// Авто-заполнение: если проигравший ≤ target-2, победитель = target
					function autoFill(changedSel) {
						var setIdx = parseInt(changedSel.dataset.set);
						var side = changedSel.dataset.side;
						var row = document.querySelector('.set-row[data-set="' + setIdx + '"]');
						var otherSide = (side === 'home') ? 'away' : 'home';
						var otherSel = row.querySelector('[data-side="' + otherSide + '"]');
						
						var val = changedSel.value;
						if (val === '') return;
						var num = parseInt(val);
						
						var target = getSetTarget(setIdx);
						var threshold = target - 2; // 23 для 25, 19 для 21, 13 для 15
						
						// Если другая ячейка пуста и введённое значение ≤ threshold → другая = target
						if (otherSel.value === '' && num <= threshold && num >= 0) {
							$(otherSel).val(target).trigger('change');
						}
						// Если введено ровно target → проигравший заполняется вручную
						// Если введое ≥ threshold+1 → обе вручную (overtime)
					}
					
					function recalc() {
						var homeWon = 0, awayWon = 0;
						
						for (var i = 0; i < maxSets; i++) {
							var row = document.querySelector('.set-row[data-set="' + i + '"]');
							if (row.style.display === 'none') continue;
							
							var hVal = row.querySelector('[data-side="home"]').value;
							var aVal = row.querySelector('[data-side="away"]').value;
							var h = hVal === '' ? -1 : parseInt(hVal);
							var a = aVal === '' ? -1 : parseInt(aVal);
							
							if (h >= 0 && a >= 0 && h !== a) {
								if (h > a) homeWon++;
								else awayWon++;
							}
						}
						
						summary.textContent = homeWon + ' : ' + awayWon;
						
						// Показать/скрыть доп. сеты
						for (var j = setsToWin; j < maxSets; j++) {
							var row2 = document.querySelector('.set-row[data-set="' + j + '"]');
							var hSel = row2.querySelector('[data-side="home"]');
							var aSel = row2.querySelector('[data-side="away"]');
							var hasData = hSel.value !== '' || aSel.value !== '';
							
							var show = hasData || (homeWon < setsToWin && awayWon < setsToWin);
							row2.style.display = show ? '' : 'none';
							
							if (!show) {
								hSel.value = '';
								aSel.value = '';
							}
						}
						
						submitBtn.disabled = !(homeWon === setsToWin || awayWon === setsToWin);
					}
					
					selects.forEach(function(sel) {
						sel.addEventListener('change', function() {
							autoFill(this);
							recalc();
						});
					});
					
					recalc();
				});
			</script>
			
		</x-voll-layout>
		