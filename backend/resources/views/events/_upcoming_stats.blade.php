{{-- Статистика блока «Скоро» для организатора: показы/переходы/скрытия за 30 дней. @include('events._upcoming_stats', ['event' => $event]) --}}
@php
	$__fmt = (string) ($event->format ?? '');
	$__isPromo = in_array($__fmt, ['camp', 'master_class'], true) || str_starts_with($__fmt, 'tournament');
@endphp
@if($__isPromo)
@php
	$__st = \Illuminate\Support\Facades\DB::table('upcoming_block_stats')
		->where('event_id', $event->id)->where('day', '>=', now()->subDays(30)->toDateString())
		->selectRaw('coalesce(sum(impressions),0) as imp, coalesce(sum(clicks),0) as clk, coalesce(sum(dismissals),0) as dis')->first();
	$__imp = (int) ($__st->imp ?? 0); $__clk = (int) ($__st->clk ?? 0); $__dis = (int) ($__st->dis ?? 0);
@endphp
<div class="ramka">
	<h2 class="-mt-05">{{ __('events.upcoming_stats_title') }}</h2>
	@if($__imp + $__clk + $__dis === 0)
		<p class="f-14 text-muted">{{ __('events.upcoming_stats_empty') }}</p>
	@else
		<div class="d-flex flex-wrap gap-2 mb-1">
			<div><div class="f-13 text-muted">{{ __('events.upcoming_stats_impressions') }}</div><div class="b-700" style="font-size:2.4rem">{{ number_format($__imp, 0, '.', ' ') }}</div></div>
			<div><div class="f-13 text-muted">{{ __('events.upcoming_stats_clicks') }}</div><div class="b-700" style="font-size:2.4rem">{{ number_format($__clk, 0, '.', ' ') }}</div></div>
			<div><div class="f-13 text-muted">{{ __('events.upcoming_stats_ctr') }}</div><div class="b-700" style="font-size:2.4rem">{{ $__imp > 0 ? number_format($__clk * 100 / $__imp, 1, '.', '') : '—' }}</div></div>
			<div><div class="f-13 text-muted">{{ __('events.upcoming_stats_dismissals') }}</div><div class="b-700" style="font-size:2.4rem">{{ number_format($__dis, 0, '.', ' ') }}</div></div>
		</div>
	@endif
	<p class="f-13 text-muted mb-0">{{ __('events.upcoming_stats_hint') }}</p>
</div>
@endif
