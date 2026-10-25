@if(config('broadcast.beta', true))<span class="beta-badge">{{ preg_match('/VolleyPlayApp|SVSVolleyApp/', (string) request()->userAgent()) ? __('stream.new') : __('stream.beta') }}</span>@endif
