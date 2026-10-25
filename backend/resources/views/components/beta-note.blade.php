@if(config('broadcast.beta', true) && !preg_match('/VolleyPlayApp|SVSVolleyApp/', (string) request()->userAgent()))
<div class="beta-note">{{ __('stream.beta_note') }}@if($u = config('broadcast.feedback_url')) <a class="blink b-600" target="_blank" rel="noopener" href="{{ $u }}">{{ __('stream.beta_note_link') }}</a>@endif</div>
@endif
