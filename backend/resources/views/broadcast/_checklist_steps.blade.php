{{-- Шаги чек-листа трансляции. Нужны: $clSteps, $clMatchesUrl, $canManageChannels --}}
@foreach($clSteps as $i => $st)
<div class="bc-cl-step {{ $st['done'] ? 'done' : '' }}" data-cl="{{ $st['key'] }}">
    <span class="bc-cl-mark" aria-hidden="true">{{ $st['done'] ? '✓' : $i + 1 }}</span>
    <div class="bc-cl-body">
        <div class="b-600">{{ $st['title'] }}</div>
        @if(!$st['done'] && $st['note'])<div class="bc-hint">{{ $st['note'] }}</div>@endif
    </div>
    @unless($st['done'])
    <div class="bc-actions" style="margin:0">
        @if($st['key'] === 'matches')
        <a href="{{ $clMatchesUrl }}" class="btn btn-small">{{ __('stream.cl_btn_matches') }}</a>
        @elseif($st['key'] === 'link')
        <button type="button" class="btn btn-small" id="bc-cl-issue">{{ __('stream.link_issue') }}</button>
        @elseif($canManageChannels)
        <a href="{{ route('broadcast.channels') }}" class="btn btn-small">{{ __('stream.cl_btn_channel') }}</a>
        @else
        <span class="bc-hint">{{ __('stream.cl_channel_ask_owner') }}</span>
        @endif
    </div>
    @endunless
</div>
@endforeach
<div class="bc-cl-step" data-cl="howto">
    <span class="bc-cl-mark" aria-hidden="true">4</span>
    <div class="bc-cl-body">
        <div class="b-600">{{ __('stream.cl_howto') }}</div>
        <div class="bc-hint">{!! __('stream.cl_howto_app') !!}<br>{!! __('stream.cl_howto_obs') !!}</div>
    </div>
</div>
