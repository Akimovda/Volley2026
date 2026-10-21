{{-- Шапка ведущего: Турнир · Площадка · Ведущий: Имя [Сменить имя] --}}
<div class="card p-2 mb-2 scorer-head" style="height:auto">
	<div class="f-13" style="opacity:.7">{{ $event->title }}</div>
	<div class="b-700 f-16">🏐 {{ $court->name }}</div>
	<div class="f-13 mt-05">
		{{ __('broadcast.scorer_label') }}: <b>{{ $name ?: '—' }}</b>
		@if(!$link->assigned_user_id)
		· <a href="{{ route('scorer.hub', [$link->token, 'change' => 1]) }}" class="blink">{{ __('broadcast.scorer_change_name') }}</a>
		@endif
	</div>
</div>
