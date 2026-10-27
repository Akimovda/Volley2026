{{-- Пошаговый чек-лист страницы игры. Нужны: $checklist, $event, $occurrence --}}
@php
    $setupUrl = route('broadcast.setup', $event) . '?occurrence_id=' . $occurrence->id;
@endphp
@foreach($checklist['steps'] as $i => $st)
<div class="gm-cl-step {{ $st['done'] ? 'done' : '' }}" data-cl="{{ $st['key'] }}">
    <span class="gm-cl-mark" aria-hidden="true">{{ $st['done'] ? '✓' : $i + 1 }}</span>
    <div class="gm-cl-body">
        <div class="b-600">{{ __('games.cl_' . $st['key']) }}@if(!empty($st['optional'])) <span class="gm-cl-opt">· {{ __('games.cl_optional') }}</span>@endif</div>
        <div class="gm-cl-note">
            @switch($st['key'])
                @case('format') {{ $st['sentence'] }} @break
                @case('teams')
                    @if($st['complete'] >= 2 || $st['done']) {{ __('games.cl_teams_ready') }}@if($st['partial'] > 0) · ⚠️ {{ __('games.cl_teams_incomplete', ['n' => $st['partial']]) }}@endif
                    @elseif($st['partial'] > 0) ⚠️ {{ __('games.cl_teams_incomplete', ['n' => $st['partial']]) }}
                    @else {{ __('games.cl_teams_roster', ['n' => $st['roster']]) }} @endif
                    @break
                @case('court')
                    @if($st['done']) {{ $st['name'] ?: __('games.cl_court_ok') }}
                    @elseif(!$st['any']) {{ __('games.cl_court_none') }}
                    @else {{ __('games.cl_court_unassigned') }} @endif
                    @break
                @case('match') {{ $st['done'] ? __('games.cl_match_n', ['n' => $st['count']]) : __('games.cl_match_none') }} @break
                @case('score') {{ $st['done'] ? __('games.cl_score_started') : ($st['match'] ? __('games.cl_score_hint') : __('games.cl_score_nomatch')) }} @break
                @case('stream') {{ $st['done'] ? __('games.cl_stream_ok') : __('games.cl_stream_no') }} @break
            @endswitch
        </div>
    </div>
    <div class="gm-cl-actions">
        @switch($st['key'])
            @case('format') <a href="#gm-cfg" class="btn btn-small btn-outline">{{ __('games.cl_btn_change') }}</a> @break
            @case('teams') <a href="#gm-new" class="btn btn-small {{ $st['done'] ? 'btn-outline' : '' }}">{{ __('games.cl_btn_teams') }}</a> @break
            @case('court') <a href="{{ $setupUrl }}" class="btn btn-small btn-outline">{{ __('games.cl_btn_change') }}</a> @break
            @case('match') <a href="#gm-new" class="btn btn-small {{ $st['done'] ? 'btn-outline' : '' }}">{{ __('games.cl_btn_match') }}</a> @break
            @case('score')
                @if($st['match'])<a href="{{ route('tournament.matches.rally.form', $st['match']) }}" class="btn btn-small">{{ __('games.cl_btn_score') }}</a>@endif
                <a href="{{ $setupUrl }}" class="btn btn-small btn-outline">{{ __('games.cl_btn_secretary') }}</a>
                @break
            @case('stream') <a href="{{ $setupUrl }}" class="btn btn-small btn-outline">{{ __('games.cl_btn_stream') }}</a> @break
        @endswitch
    </div>
</div>
@endforeach
