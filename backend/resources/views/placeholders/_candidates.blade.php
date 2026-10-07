@forelse($users as $u)
	@php
	$fill = $service->fieldsToFill($placeholder, $u);
	$conf = $service->conflicts($placeholder, $u);
	$samePhone = !empty($placeholder->phone) && $placeholder->phone === $u->phone;
	@endphp
	<div class="card mb-2" style="height:auto;">
		<div class="d-flex between gap-1 fvc card-actions-row">
			<div class="card-actions-info">
				<b>{{ trim(($u->last_name ?? '') . ' ' . ($u->first_name ?? '')) ?: ($u->name ?: '#' . $u->id) }}</b>
				<span class="f-13" style="opacity:.7;">· ID {{ $u->id }}@if($samePhone) · ✅ {{ __('placeholders.cand_match_phone') }}@endif</span>
				@if($u->phone)<div class="f-14">{{ $u->phone }}</div>@endif
				<div class="f-13 mt-1">
					{{ __('placeholders.cand_fill') }}:
					@if($fill)
						{{ collect($fill)->map(fn ($f) => __('placeholders.field_' . $f))->implode(', ') }}
					@else
						<span style="opacity:.7;">{{ __('placeholders.cand_nofill') }}</span>
					@endif
				</div>
				<div class="f-13">
					{{ __('placeholders.cand_conflicts') }}:
					@if($conf > 0)<b style="color:#c0392b;">{{ $conf }}</b>@else<span style="opacity:.7;">{{ __('placeholders.cand_none') }}</span>@endif
				</div>
			</div>
			<form method="POST" action="{{ route('placeholders.merge.do', $placeholder) }}"
			      onsubmit="return confirm(@js(__('placeholders.confirm_merge')))">
				@csrf
				<input type="hidden" name="real_user_id" value="{{ $u->id }}">
				<button type="submit" class="btn btn-outline-primary btn-sm">{{ __('placeholders.merge_into_btn') }}</button>
			</form>
		</div>
	</div>
@empty
	<p class="mb-0">{{ $empty }}</p>
@endforelse
