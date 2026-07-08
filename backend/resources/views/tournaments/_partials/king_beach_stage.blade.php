@php
use App\Models\KingBeachStanding;
use App\Models\TournamentMatch;
use App\Models\User;
use App\Models\TournamentStage;
use Illuminate\Support\Facades\DB;

$kbAdvanceCount = (int) $stage->configValue('advance_count', 2);
$kbSetPoints    = $stage->setPoints();
$kbGroupSize    = (int) $stage->configValue('group_size', 4);

// Игроки, зарегистрированные на тур индивидуально, но ещё не попавшие
// ни в одну группу ЭТОЙ king_beach стадии — для ручного/случайного распределения.
$kbAssignedIds = KingBeachStanding::where('stage_id', $stage->id)->pluck('user_id');
$kbRegisteredIds = DB::table('event_registrations')
    ->where('event_id', $stage->event_id)
    ->when($stage->occurrence_id, fn($q) => $q->where('occurrence_id', $stage->occurrence_id))
    ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
    ->whereNotNull('user_id')
    ->distinct()
    ->pluck('user_id');
$kbUnassignedIds = $kbRegisteredIds->diff($kbAssignedIds)->values();
$kbUnassignedPlayers = User::whereIn('id', $kbUnassignedIds)->get()
    ->sortBy(fn($u) => trim(($u->last_name ?? '') . ($u->first_name ?? '')))
    ->values();
$kbCourts = array_values(array_filter((array) $stage->configValue('courts', [])));

// Дивизионы Hard/Medium/Lite после группового этапа
$kbGroupsCount = $stage->groups->count();
$kbDivisionNames = match (true) {
    $kbGroupsCount === 2 => ['Hard', 'Lite'],
    $kbGroupsCount === 3 => ['Hard', 'Medium', 'Lite'],
    $kbGroupsCount >= 4 => array_merge(
        ['Hard'],
        array_map(fn($i) => 'Medium-' . $i, range(1, max(0, $kbGroupsCount - 2))),
        ['Lite']
    ),
    default => [],
};
$kbHasSpawnedDivisions = $stage->event->tournamentStages()
    ->where('sort_order', '>', $stage->sort_order)
    ->whereIn('name', $kbDivisionNames)
    ->exists();
@endphp

<div class="ramka" id="stage_{{ $stage->id }}">
	{{-- Шапка стадии --}}
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
			<p>{{ __('tournaments.setup_stage_lbl_king_beach') }} · Bo1 · {{ __('tournaments.score_to_pts') }} {{ $kbSetPoints }} {{ __('tournaments.pub_pts_label') }} · {{ __('tournaments.setup_stage_kb_advance_label', ['n' => $kbAdvanceCount]) }}</p>
		</div>
		<div class="d-flex" style="gap:6px">
			@if($stage->isInProgress() || $stage->isCompleted())
			<form method="POST" action="{{ route('tournament.stages.revert', $stage) }}">
				@csrf
				<button class="btn btn-secondary f-12 btn-alert"
					data-title="{{ __('tournaments.setup_rollback_title') }}"
					data-icon="warning"
					data-confirm-text="{{ __('tournaments.setup_rollback_yes') }}"
					data-cancel-text="{{ __('tournaments.btn_cancel') }}">{{ __('tournaments.setup_btn_rollback') }}</button>
			</form>
			@endif
			<form method="POST" action="{{ route('tournament.stages.destroy', $stage) }}">
				@csrf @method('DELETE')
				<button class="btn btn-danger f-12 btn-alert"
					data-title="{{ __('tournaments.setup_delete_stage_title') }}"
					data-icon="warning"
					data-confirm-text="{{ __('tournaments.btn_delete') }}"
					data-cancel-text="{{ __('tournaments.btn_cancel') }}">{{ __('tournaments.setup_btn_delete_stage') }}</button>
			</form>
		</div>
	</div>

	{{-- Группы с индивидуальными таблицами --}}
	@if($stage->groups->isNotEmpty())
	@php
	$kbGroupsData = [];
	foreach ($stage->groups as $grp) {
		$standings = KingBeachStanding::where('group_id', $grp->id)->with('user')->orderBy('rank')->orderByDesc('total_points')->get();
		$groupMatches = $stage->matches->where('group_id', $grp->id)->sortBy('round')->values();

		// Карта очков per player per rotation
		$matchPointsMap = [];
		foreach ($groupMatches as $m) {
			$meta = $m->meta ?? [];
			if (!($meta['king_beach'] ?? false)) continue;
			$rot = (int)($meta['rotation'] ?? $m->round);
			foreach ($meta['home_players'] ?? [] as $uid) {
				$matchPointsMap[$uid][$rot] = $m->isCompleted() ? $m->total_points_home : null;
			}
			foreach ($meta['away_players'] ?? [] as $uid) {
				$matchPointsMap[$uid][$rot] = $m->isCompleted() ? $m->total_points_away : null;
			}
		}

		$kbGroupsData[$grp->id] = [
			'group'         => $grp,
			'standings'     => $standings,
			'matches'       => $groupMatches,
			'matchPointsMap' => $matchPointsMap,
		];
	}
	// Предзагрузим всех нужных пользователей разом
	$allPlayerIds = collect($kbGroupsData)->flatMap(fn($d) => $d['standings']->pluck('user_id'))->unique()->values()->toArray();
	$allMatchPlayerIds = [];
	foreach ($kbGroupsData as $d) {
		foreach ($d['matches'] as $m) {
			$meta = $m->meta ?? [];
			$allMatchPlayerIds = array_merge($allMatchPlayerIds, $meta['home_players'] ?? [], $meta['away_players'] ?? []);
		}
	}
	$usersMap = User::whereIn('id', array_unique(array_merge($allPlayerIds, $allMatchPlayerIds)))->get()->keyBy('id');
	$nameOf = fn($uid) => trim(($usersMap[$uid]->last_name ?? '') . ' ' . ($usersMap[$uid]->first_name ?? '')) ?: '?';
	@endphp

	@foreach($kbGroupsData as $gd)
	@php
	$grp          = $gd['group'];
	$standings    = $gd['standings'];
	$groupMatches = $gd['matches'];
	$ptsMap       = $gd['matchPointsMap'];
	$rotations    = $groupMatches->pluck('round')->unique()->sort()->values()->toArray();
	@endphp

	<div class="card mt-2">
		<div class="b-600 f-16 mb-2">{{ $grp->name }}</div>

		{{-- Таблица игроков --}}
		@if($standings->isNotEmpty())
		<div class="table-scrollable">
			<table class="table">
				<thead>
					<tr style="border-bottom:2px solid rgba(128,128,128,.2)">
						<th class="p-1" style="text-align:center;width:28px">#</th>
						<th class="p-1">{{ __('tournaments.setup_kb_col_player') }}</th>
						@foreach($rotations as $rot)
						<th class="p-1" style="text-align:center">{{ __('tournaments.setup_kb_col_game', ['n' => $rot]) }}</th>
						@endforeach
						<th class="p-1" style="text-align:center;font-weight:700">{{ __('tournaments.setup_kb_col_total') }}</th>
					</tr>
				</thead>
				<tbody>
					@foreach($standings as $kbs)
					@php
					$isAdvancing = $kbs->rank > 0 && $kbs->rank <= $kbAdvanceCount && $stage->isInProgress() && $groupMatches->every(fn($m) => $m->isCompleted());
					@endphp
					<tr style="{{ $isAdvancing ? 'background:rgba(16,185,129,.08)' : '' }}">
						<td style="text-align:center">
							@if($kbs->rank > 0)
							<span class="b-600" style="{{ $isAdvancing ? 'color:#059669' : '' }}">{{ $kbs->rank }}</span>
							@else
							—
							@endif
						</td>
						<td>
							@if($kbs->user)
							<a href="{{ route('users.show', $kbs->user) }}" class="b-600 blink">
								{{ $nameOf($kbs->user_id) }}
							</a>
							@else
							<span class="b-600">—</span>
							@endif
							@if($isAdvancing)
							<span class="f-14" style="color:#059669;margin-left:6px">→</span>
							@endif
						</td>
						@foreach($rotations as $rot)
						@php
						$pts = $ptsMap[$kbs->user_id][$rot] ?? null;
						@endphp
						<td style="text-align:center">
							@if($pts !== null)
							<span class="b-600">{{ $pts }}</span>
							@else
							<span style="color:#9ca3af">·</span>
							@endif
						</td>
						@endforeach
						<td style="text-align:center;font-weight:700">
							{{ $kbs->total_points > 0 || $groupMatches->some(fn($m) => $m->isCompleted()) ? $kbs->total_points : '—' }}
						</td>
					</tr>
					@endforeach
				</tbody>
			</table>
		</div>
		@endif

		{{-- Партии группы --}}
		<div class="mt-2">
			@foreach($groupMatches as $match)
			@php
			$meta       = $match->meta ?? [];
			$homeUids   = $meta['home_players'] ?? [];
			$awayUids   = $meta['away_players'] ?? [];
			$homeNames  = array_map($nameOf, $homeUids);
			$awayNames  = array_map($nameOf, $awayUids);
			@endphp
			<div style="display:flex;align-items:center;gap:8px;padding:8px 0;border-top:1px solid rgba(128,128,128,.12)">
				<div style="flex:0 0 70px;color:#6b7280;font-size:13px">{{ __('tournaments.setup_kb_match_label', ['n' => $match->round]) }}</div>
				<div style="flex:1;display:flex;align-items:center;gap:6px">
					<span class="b-600">{{ implode(' + ', $homeNames) }}</span>
					<span style="color:#6b7280;margin:0 4px">vs</span>
					<span class="b-600">{{ implode(' + ', $awayNames) }}</span>
				</div>
				<div style="flex:0 0 80px;text-align:center">
					@if($match->isCompleted())
					<span class="b-700 f-16">{{ $match->total_points_home }} : {{ $match->total_points_away }}</span>
					@else
					<span style="color:#9ca3af">—</span>
					@endif
				</div>
				<div style="flex:0 0 80px;text-align:right">
					@if($match->isCompleted())
					<a href="{{ route('tournament.matches.score.form', $match) }}?edit=1" class="btn btn-small btn-secondary">{{ __('tournaments.setup_match_fix_btn') }}</a>
					@elseif($stage->isInProgress())
					<a href="{{ route('tournament.matches.score.form', $match) }}" class="btn btn-small btn-primary">{{ __('tournaments.setup_match_btn_score') }}</a>
					@endif
				</div>
			</div>
			@endforeach
		</div>
	</div>
	@endforeach
	@endif {{-- groups not empty --}}

	{{-- Нераспределённые игроки + ручное/случайное формирование групп --}}
	@if(!$stage->isCompleted())
	<div class="card mt-2">
		<div class="b-600 f-16 mb-2">{{ __('tournaments.setup_kb_unassigned_h3', ['n' => $kbUnassignedPlayers->count()]) }}</div>

		@if($kbUnassignedPlayers->isEmpty())
		<div class="alert alert-info">{{ __('tournaments.setup_kb_unassigned_empty') }}</div>
		@else
		<form method="POST" action="{{ route('tournament.kingBeach.createGroup', $stage->event_id) }}" class="kb-create-group-form" data-stage="{{ $stage->id }}" data-group-size="{{ $kbGroupSize }}">
			@csrf
			<input type="hidden" name="stage_id" value="{{ $stage->id }}">
			<div class="row">
				@foreach($kbUnassignedPlayers as $p)
				@php
					$kbLevel = $stage->event->direction === 'beach' ? $p->beach_level : $p->classic_level;
					$kbLevel = !is_null($kbLevel) && $kbLevel !== '' ? (int) $kbLevel : null;
					$kbGenderColor = $p->gender === 'f' ? '#e5395e' : '#2967BA';
					$kbGenderSign = $p->gender === 'f' ? '♀' : '♂';
				@endphp
				<div class="col-md-6 col-xl-3">
					<div class="card" style="opacity:.9">
						<label style="display:flex;align-items:center;gap:10px;cursor:pointer;margin:0">
							<input type="checkbox" class="kb-player-cb" name="player_ids[]" value="{{ $p->id }}">
							<img src="{{ $p->profile_photo_url }}" alt="" loading="lazy" style="width:40px;height:40px;border-radius:50%;object-fit:cover;flex-shrink:0">
							<div style="min-width:0">
								<div class="b-600" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ trim(($p->last_name ?? '') . ' ' . ($p->first_name ?? '')) ?: ($p->name ?? '?') }}</div>
								<div class="f-13" style="opacity:.85">
									<span style="color:{{ $kbGenderColor }};font-weight:700">{{ $kbGenderSign }}</span> ·
									@if($kbLevel)
									<span class="levelmark levelmark--event level-{{ $kbLevel }}">{{ __('events.level_short_' . $kbLevel) }}</span>
									@else
									<span class="levelmark levelmark--event level-na">!?</span>
									@endif
								</div>
							</div>
						</label>
					</div>
				</div>
				@endforeach
			</div>

			<div class="mt-2 d-flex fvc" style="gap:10px;flex-wrap:wrap">
				@if(!empty($kbCourts))
				<select name="court">
					<option value="">{{ __('tournaments.setup_kb_court_none') }}</option>
					@foreach($kbCourts as $c)
					<option value="{{ $c }}">{{ $c }}</option>
					@endforeach
				</select>
				@endif
				<button type="submit" class="btn btn-secondary kb-create-group-submit">
					{{ __('tournaments.setup_kb_btn_create_group', ['n' => $kbGroupSize]) }}
				</button>
				<span class="f-13 kb-selected-count" style="opacity:.7">{{ __('tournaments.setup_kb_selected_count', ['n' => 0, 'total' => $kbGroupSize]) }}</span>
			</div>
		</form>

		<div class="mt-2">
			<details>
				<summary class="btn btn-secondary">{{ __('tournaments.setup_kb_btn_manual_table') }}</summary>
				<form method="POST" action="{{ route('tournament.kingBeach.assignManual', $stage->event_id) }}" class="mt-2">
					@csrf
					<input type="hidden" name="stage_id" value="{{ $stage->id }}">
					<p class="f-13" style="opacity:.7">{{ __('tournaments.setup_kb_manual_table_hint', ['n' => $kbGroupSize]) }}</p>
					<div class="table-scrollable" style="max-width:640px">
						<table class="table">
							<thead>
								<tr>
									<th class="p-1">{{ __('tournaments.setup_kb_col_player') }}</th>
									<th class="p-1" style="width:140px">{{ __('tournaments.setup_kb_col_group_label') }}</th>
								</tr>
							</thead>
							<tbody>
								@foreach($kbUnassignedPlayers as $p)
								@php
									$kbLevel2 = $stage->event->direction === 'beach' ? $p->beach_level : $p->classic_level;
									$kbLevel2 = !is_null($kbLevel2) && $kbLevel2 !== '' ? (int) $kbLevel2 : null;
									$kbGenderColor2 = $p->gender === 'f' ? '#e5395e' : '#2967BA';
									$kbGenderSign2 = $p->gender === 'f' ? '♀' : '♂';
								@endphp
								<tr>
									<td class="p-1">
										<div style="display:flex;align-items:center;gap:10px">
											<img src="{{ $p->profile_photo_url }}" alt="" loading="lazy" style="width:32px;height:32px;border-radius:50%;object-fit:cover;flex-shrink:0">
											<div style="min-width:0">
												<div class="b-600" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ trim(($p->last_name ?? '') . ' ' . ($p->first_name ?? '')) ?: ($p->name ?? '?') }}</div>
												<div class="f-13" style="opacity:.85">
													<span style="color:{{ $kbGenderColor2 }};font-weight:700">{{ $kbGenderSign2 }}</span>
													@if($kbLevel2)
													<span class="levelmark levelmark--event level-{{ $kbLevel2 }}">{{ __('events.level_short_' . $kbLevel2) }}</span>
													@else
													<span class="levelmark levelmark--event level-na">!?</span>
													@endif
												</div>
											</div>
										</div>
									</td>
									<td class="p-1">
										<input type="text" name="assign[{{ $p->id }}]" placeholder="A, Hard...">
									</td>
								</tr>
								@endforeach
							</tbody>
						</table>
					</div>
					<button type="submit" class="btn btn-primary mt-2">{{ __('tournaments.setup_kb_btn_save_manual') }}</button>
				</form>
			</details>
		</div>

		<div class="mt-1">
			<form method="POST" action="{{ route('tournament.kingBeach.distribute', $stage->event_id) }}" class="kb-distribute-form">
				@csrf
				<input type="hidden" name="stage_id" value="{{ $stage->id }}">
				<button type="button" class="btn btn-secondary kb-distribute-btn" data-count="{{ $kbUnassignedPlayers->count() }}">
					{{ __('tournaments.setup_kb_btn_distribute') }}
				</button>
			</form>
		</div>
		@endif
	</div>
	@endif

	{{-- Кнопка: следующий раунд --}}
	@if($stage->isInProgress() && $stage->matches->where('status', TournamentMatch::STATUS_COMPLETED)->count() === $stage->matches->count() && $stage->matches->count() > 0)
	<div class="p-3 mt-2" style="background:rgba(41,103,186,.08);border-radius:10px">
		<form method="POST" action="{{ route('tournament.stages.nextRound', $stage) }}" class="d-flex fvc" style="gap:10px">
			@csrf
			<div class="b-600">{{ __('tournaments.setup_kb_btn_next_round') }}</div>
			<button type="submit" class="btn btn-primary">{{ __('tournaments.setup_btn_next_arrow') }}</button>
		</form>
	</div>
	@endif

	{{-- Дивизионы Hard/Medium/Lite после завершения группового этапа --}}
	@if($stage->isCompleted() && count($kbDivisionNames) > 0 && !$kbHasSpawnedDivisions)
	<div class="p-3 mt-2" style="background:rgba(16,185,129,.08);border-radius:10px">
		<div class="b-600 mb-1">
			{{ __('tournaments.setup_kb_divisions_intro', ['names' => implode(', ', $kbDivisionNames)]) }}
		</div>
		<form method="POST" action="{{ route('tournament.kingBeach.formDivisions', $stage) }}" class="d-flex fvc" style="gap:10px;flex-wrap:wrap">
			@csrf
			<label class="f-13" style="margin:0">
				{{ __('tournaments.setup_groups_advance_to_div', ['name' => 'Hard']) }}
				<input name="advance_per_group" type="number" value="{{ $kbAdvanceCount }}" min="1" max="4" style="width:70px">
			</label>
			<button type="submit" class="btn btn-primary">{{ __('tournaments.setup_kb_btn_form_divisions') }}</button>
		</form>
	</div>
	@endif
</div>

@once
<script>
(function() {
	function updateSelectedCount(form) {
		var count = form.querySelectorAll('.kb-player-cb:checked').length;
		var total = parseInt(form.dataset.groupSize, 10) || 4;
		var label = form.querySelector('.kb-selected-count');
		if (label) {
			label.textContent = @json(__('tournaments.setup_kb_selected_count', ['n' => '__N__', 'total' => '__T__']))
				.replace('__N__', count).replace('__T__', total);
		}
	}

	document.addEventListener('change', function(e) {
		if (e.target.classList && e.target.classList.contains('kb-player-cb')) {
			var form = e.target.closest('.kb-create-group-form');
			if (form) updateSelectedCount(form);
		}
	});

	document.addEventListener('submit', function(e) {
		var form = e.target.closest('.kb-create-group-form');
		if (!form) return;
		var total = parseInt(form.dataset.groupSize, 10) || 4;
		var checked = form.querySelectorAll('.kb-player-cb:checked');
		if (checked.length !== total) {
			e.preventDefault();
			swal({
				title: 'Ошибка',
				text: @json(__('tournaments.setup_kb_select_exactly_n', ['n' => '__T__'])).replace('__T__', total),
				icon: 'error',
				button: 'Понятно'
			});
		}
	});

	document.addEventListener('click', function(e) {
		var distributeBtn = e.target.closest('.kb-distribute-btn');
		if (!distributeBtn) return;

		var form = distributeBtn.closest('.kb-distribute-form');
		var count = distributeBtn.dataset.count;

		swal({
			title: @json(__('tournaments.setup_kb_btn_distribute')),
			text: @json(__('tournaments.setup_kb_distribute_confirm', ['n' => '__N__'])).replace('__N__', count),
			icon: 'warning',
			buttons: {
				cancel: { text: @json(__('tournaments.btn_cancel')), value: null, visible: true, closeModal: true },
				confirm: { text: @json(__('tournaments.setup_kb_btn_distribute')), value: true, visible: true, closeModal: true },
			},
			dangerMode: true,
		}).then(function(confirmed) {
			if (confirmed && form) form.submit();
		});
	});
})();
</script>
@endonce
