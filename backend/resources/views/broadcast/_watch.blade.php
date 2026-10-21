{{-- Кнопка «Смотреть трансляцию» (публичные страницы). Только ссылки на эфир — токен оверлея сюда не попадает. Нужен $event; $occurrenceId — необязателен. --}}
@php
    $__streams = app(\App\Services\Broadcast\StreamLinksService::class)->forEvent((int) $event->id, isset($occurrenceId) ? (int) $occurrenceId : null);
    $__labels = ['vk' => __('stream.p_vk'), 'rutube' => __('stream.p_rutube'), 'youtube' => __('stream.p_youtube'), 'other' => __('stream.p_other')];
    $__total = collect($__streams)->sum(fn ($c) => count($c['links']));
@endphp
@if($__total > 0 && ($wrap ?? false))<div class="ramka">@endif
@if($__total === 1)
    @php $__one = collect($__streams)->first()['links'][0]; @endphp
    <a href="{{ $__one['url'] }}" target="_blank" rel="noopener nofollow" class="btn btn-secondary">📡 {{ __('stream.btn_watch') }}</a>
@elseif($__total > 1)
    <span class="stream-watch-wrap" style="display:inline-block">
        <button type="button" class="btn btn-secondary" onclick="var n=this.nextElementSibling;n.style.display=n.style.display==='block'?'none':'block'">📡 {{ __('stream.btn_watch') }}</button>
        <div style="display:none;margin-top:.8rem;text-align:left">
            @foreach($__streams as $__c)
            <div style="margin-bottom:.6rem"><b>{{ $__c['name'] }}</b>:
                @foreach($__c['links'] as $__l)
                <a href="{{ $__l['url'] }}" target="_blank" rel="noopener nofollow" class="blink b-600" style="margin-left:.6rem">{{ $__labels[$__l['platform']] ?? $__l['platform'] }}</a>
                @endforeach
            </div>
            @endforeach
        </div>
    </span>
@endif
@if($__total > 0 && ($wrap ?? false))</div>@endif
