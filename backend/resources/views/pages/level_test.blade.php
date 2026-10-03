{{-- resources/views/pages/level_test.blade.php --}}
<x-voll-layout body_class="level">
	<x-slot name="title">{{ __('leveltest.title') }}</x-slot>
	<x-slot name="description">{{ __('leveltest.description') }}</x-slot>
	<x-slot name="canonical">{{ route('level_test') }}</x-slot>
	<x-slot name="breadcrumbs">
		<li itemprop="itemListElement" itemscope="" itemtype="http://schema.org/ListItem">
			<a href="{{ route('level_test') }}" itemprop="item"><span itemprop="name">{{ __('leveltest.breadcrumb') }}</span></a>
			<meta itemprop="position" content="2">
		</li>
	</x-slot>
	<x-slot name="h1">{{ __('leveltest.title') }}</x-slot>

	<x-slot name="style">
		<style>
			.lt-box{max-width:72rem;margin:0 auto}
			.lt-opts{display:flex;flex-direction:column;gap:1rem;margin:2rem 0}
			.lt-opt{display:block;width:100%;text-align:left;padding:1.4rem 1.8rem;border-radius:1.2rem;border:2px solid rgba(41,103,186,.35);background:transparent;color:inherit;font:inherit;cursor:pointer;transition:background .15s,border-color .15s}
			.lt-opt:hover,.lt-opt:focus-visible{border-color:#2967ba;background:rgba(41,103,186,.08)}
			.lt-opt.is-picked{border-color:#E7612F;background:rgba(231,97,47,.1)}
			.lt-progress{height:.6rem;border-radius:.3rem;background:rgba(41,103,186,.18);overflow:hidden;margin:1rem 0 2rem}
			.lt-progress i{display:block;height:100%;background:#E7612F;width:0;transition:width .25s}
			.lt-disc{display:flex;flex-wrap:wrap;gap:1.2rem;margin-top:2rem}
			.lt-actions{display:flex;flex-wrap:wrap;gap:1.6rem;align-items:center;margin-top:2rem}
			.lt-level{font-size:3.2rem;font-weight:700;margin:.6rem 0 1rem}
			.lt-note{opacity:.75;margin-top:1rem}
		</style>
	</x-slot>

	<div class="container">
		<div class="ramka lt-box">
			<div id="lt-start">
				<p>{{ __('leveltest.intro') }}</p>
				<h3 class="mt-2">{{ __('leveltest.choose_discipline') }}</h3>
				<div class="lt-disc">
					<button type="button" class="btn" data-disc="classic">🏐 {{ __('leveltest.classic') }}</button>
					<button type="button" class="btn" data-disc="beach">🏖 {{ __('leveltest.beach') }}</button>
				</div>
			</div>

			<div id="lt-quiz" style="display:none">
				<div class="lt-progress"><i id="lt-bar"></i></div>
				<div id="lt-count" style="opacity:.7"></div>
				<h3 id="lt-title" style="margin-top:.4rem"></h3>
				<div class="lt-opts" id="lt-opts"></div>
				<div class="lt-actions"><a class="blink b-600" href="javascript:void(0)" id="lt-back">← {{ __('leveltest.back') }}</a></div>
			</div>

			<div id="lt-result" style="display:none">
				<div>{{ __('leveltest.your_result') }}</div>
				<div class="lt-level" id="lt-level"></div>
				<p id="lt-text"></p>
				<div class="lt-note" id="lt-points"></div>
				<div class="lt-note" id="lt-capped" style="display:none">{{ __('leveltest.capped_note') }}</div>
				<div class="lt-actions">
					<a class="btn btn-small" href="{{ route('events.index') }}">{{ __('leveltest.find_events') }}</a>
					<a class="blink b-600" href="{{ route('level_players') }}">{{ __('leveltest.levels_info') }} →</a>
					<a class="blink b-600" href="javascript:void(0)" id="lt-restart">{{ __('leveltest.restart') }}</a>
				</div>
			</div>
		</div>
	</div>

	<x-slot name="script">
		@php
			$ltI18n = [
				'count' => __('leveltest.question_n', ['n' => ':n', 'total' => ':total']),
				'points' => __('leveltest.points', ['score' => ':score', 'max' => ':max']),
				'error' => __('leveltest.error'),
			];
		@endphp
		<script>
		(function () {
			var Q = @json($questions);
			var I18N = @json($ltI18n);
			var URL_RESULT = @json(route('level_test.result'));
			var disc = null, step = 0, answers = [];
			var $ = jQuery;

			function show(id) { $('#lt-start,#lt-quiz,#lt-result').hide(); $(id).show(); }

			function render() {
				var list = Q[disc], q = list[step];
				$('#lt-bar').css('width', (step / list.length * 100) + '%');
				$('#lt-count').text(I18N.count.replace(':n', step + 1).replace(':total', list.length));
				$('#lt-title').text(q.title);
				var $o = $('#lt-opts').empty();
				q.options.forEach(function (text, i) {
					var $b = $('<button type="button" class="lt-opt"></button>').text(text).attr('data-score', i);
					if (answers[step] === i) $b.addClass('is-picked');
					$o.append($b);
				});
				$('#lt-back').toggle(step > 0);
			}

			function finish() {
				$.ajax({
					url: URL_RESULT, method: 'POST', dataType: 'json',
					headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
					data: { discipline: disc, answers: answers }
				}).done(function (r) {
					$('#lt-level').text(r.name);
					$('#lt-text').text(r.text);
					$('#lt-points').text(I18N.points.replace(':score', r.score).replace(':max', r.max));
					$('#lt-capped').toggle(!!r.capped);
					show('#lt-result');
				}).fail(function () {
					alert(I18N.error);
					step = answers.length - 1; answers.pop(); render();
				});
			}

			$('[data-disc]').on('click', function () {
				disc = $(this).data('disc'); step = 0; answers = [];
				show('#lt-quiz'); render();
			});
			$('#lt-opts').on('click', '.lt-opt', function () {
				answers[step] = parseInt($(this).attr('data-score'), 10);
				if (step + 1 < Q[disc].length) { step++; render(); } else { finish(); }
			});
			$('#lt-back').on('click', function () { if (step > 0) { step--; render(); } });
			$('#lt-restart').on('click', function () { disc = null; step = 0; answers = []; show('#lt-start'); window.scrollTo(0, 0); });
		})();
		</script>
	</x-slot>
</x-voll-layout>
