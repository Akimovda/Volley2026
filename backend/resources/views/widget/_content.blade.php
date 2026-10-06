{{-- Разметка виджета (iframe, shadow-root JS-варианта, предпросмотр). Ожидает: $events, $style (уже после forRender) --}}
@php
    $vwCount = count($events);
    $vwHeader = $style['show_icons']
        ? $style['header_text']
        : trim(preg_replace('/[\x{1F000}-\x{1FFFF}\x{2600}-\x{27BF}\x{FE0F}]+/u', '', $style['header_text']));
@endphp
<style>{!! \App\Services\WidgetStyleService::css($style, '.vw-root', $vwCount) !!}</style>
<div class="vw-root" part="root">
    @if($style['header_show'] && $vwHeader !== '')
        <div class="vw-header" part="header">{{ $vwHeader }}</div>
    @endif

    @if(count($events) === 0)
        <div class="vw-empty" part="empty">Нет запланированных мероприятий.</div>
    @else
    <div class="vw-cards" part="cards">
        @foreach($events as $ev)
        @php
            $isBeach  = ($ev['direction'] ?? '') === 'beach';
            $dirText  = ($style['show_icons'] ? ($isBeach ? '🏖 ' : '🏐 ') : '') . ($isBeach ? 'Пляжка' : 'Классика');
            $withPhoto = $style['photo_show'] && $style['layout'] !== 'list';
            $ic = fn (string $e) => $style['show_icons'] ? '<span class="vw-ic">' . $e . '</span>' : '';
            $btnLabel = $style['button_text'] . ' — ' . $ev['title'];
        @endphp
        <article class="vw-card {{ $withPhoto ? 'has-photo' : '' }}" part="card">
            @if($withPhoto)
            <a class="vw-photo" part="photo" href="{{ $ev['url'] }}" target="_blank" rel="noopener" tabindex="-1" aria-hidden="true">
                <img src="{{ $ev['photo'] ?? '' }}" alt="" width="640" height="360" loading="lazy">
                @if($style['show_price'] && !empty($ev['price']))
                    <span class="vw-badge vw-badge-price" part="badge">{{ $ev['price'] }}</span>
                @endif
                @if($style['show_direction'])
                    <span class="vw-badge vw-badge-dir {{ $isBeach ? 'beach' : 'classic' }}" part="badge">{{ $dirText }}</span>
                @endif
            </a>
            @endif

            <div class="vw-main">
                <div class="vw-body">
                    @if(!$withPhoto && $style['show_direction'])
                        <span class="vw-badge vw-badge-dir vw-dir-inline {{ $isBeach ? 'beach' : 'classic' }}" part="badge">{{ $dirText }}</span>
                    @endif

                    <a href="{{ $ev['url'] }}" target="_blank" rel="noopener" class="vw-title" part="title">
                        @if($style['show_icons'] && !empty($ev['is_private']))<span title="Приватное">🙈</span> @endif{{ $ev['title'] }}
                    </a>

                    <div class="vw-meta" part="meta">
                        <div class="vw-row">{!! $ic('📅') !!}<span>{{ $ev['date_long'] }}, {{ $ev['time_range'] }}</span></div>

                        @if($style['show_location'] && !empty($ev['address']))
                        <div class="vw-row">{!! $ic('📍') !!}<span>{{ $ev['address'] }}</span></div>
                        @endif

                        @if($style['show_level'] && (!is_null($ev['level_min']) || !is_null($ev['level_max'])))
                        <div class="vw-row">{!! $ic('🎚') !!}<span>
                            @if(!is_null($ev['level_min']))<span class="vw-lvl l{{ (int) $ev['level_min'] }}">{{ level_name_short($ev['level_min'], $ev['level_scope'] ?? 'standard') }}</span>@endif
                            @if(!is_null($ev['level_min']) && !is_null($ev['level_max']) && $ev['level_min'] != $ev['level_max']) — @endif
                            @if(!is_null($ev['level_max']) && $ev['level_min'] != $ev['level_max'])<span class="vw-lvl l{{ (int) $ev['level_max'] }}">{{ level_name_short($ev['level_max'], $ev['level_scope'] ?? 'standard') }}</span>@endif
                        </span></div>
                        @endif

                        @if($style['show_slots'] && !empty($ev['slots_info']))
                        <div class="vw-row">{!! $ic('👥') !!}<span>
                            <strong>{{ $ev['slots_info']['taken'] }}</strong>
                            {{ __('events.card_seats_of') }}
                            <strong>{{ $ev['slots_info']['max'] }}</strong>{{ $ev['slots_info']['unit'] === 'teams' ? __('events.card_seats_teams') : __('events.card_seats_players') }}
                            @if($ev['slots_info']['reserve'] > 0){!! __('events.widget_reserve_suffix', ['count' => $ev['slots_info']['reserve']]) !!}@endif
                        </span></div>
                        @endif

                        @if(!$withPhoto && $style['show_price'] && !empty($ev['price']))
                        <div class="vw-row">{!! $ic('💰') !!}<span class="vw-price-inline">{{ $ev['price'] }}</span></div>
                        @endif
                    </div>
                </div>

                @if($style['button_show'])
                <a href="{{ $ev['url'] }}" target="_blank" rel="noopener" class="vw-btn" part="button" aria-label="{{ $btnLabel }}">{{ $style['button_text'] }}</a>
                @endif
            </div>
        </article>
        @endforeach
    </div>
    @endif

    @php
        $widgetAppUrl   = rtrim((string) config('app.url'), '/');
        $widgetHost     = parse_url($widgetAppUrl, PHP_URL_HOST) ?: $widgetAppUrl;
        $widgetHostLink = '<a href="' . e($widgetAppUrl) . '" target="_blank" rel="noopener">' . e($widgetHost) . '</a>';
    @endphp
    <div class="vw-footer" part="footer">{!! __('profile.widget_powered_by', ['host' => $widgetHostLink]) !!}</div>
</div>
