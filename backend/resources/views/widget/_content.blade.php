{{-- Разметка виджета (iframe, shadow-root JS-варианта, предпросмотр). Ожидает: $events, $style (уже после forRender) --}}
@php
    $vwCount = count($events);
    $vwHeader = $style['show_icons']
        ? $style['header_text']
        : trim(preg_replace('/[\x{1F000}-\x{1FFFF}\x{2600}-\x{27BF}\x{FE0F}]+/u', '', $style['header_text']));
    $titleFirst = $style['card_order'] === 'title_first';
@endphp
<style>{!! \App\Services\WidgetStyleService::css($style, '.vw-root', $vwCount) !!}</style>
<div class="vw-root" part="root">
    @if($style['header_show'] && $vwHeader !== '')
        <div class="vw-header" part="header">{{ $vwHeader }}</div>
    @endif

    @if($vwCount === 0)
        <div class="vw-empty" part="empty">{{ __('events.widget_empty') }}</div>
    @else
    <div class="vw-cards" part="cards">
        @foreach($events as $ev)
        @php
            $x = $ev['extra'] ?? [];
            $isBeach  = ($ev['direction'] ?? '') === 'beach';
            $dirText  = ($style['show_icons'] ? ($isBeach ? '🏖 ' : '🏐 ') : '') . ($isBeach ? __('events.card_dir_beach') : __('events.card_dir_classic'));
            $withPhoto = $style['photo_show'] && $style['layout'] !== 'list';
            $headFirst = $titleFirst && $withPhoto;
            $ic = fn (string $e) => $style['show_icons'] ? '<span class="vw-ic">' . $e . '</span>' : '';
            $btnLabel = $style['button_text'] . ' — ' . $ev['title'];
            $priceBadge = !empty($ev['price']) ? $ev['price'] : (($style['show_free_label'] && !empty($x['free'])) ? __('events.card_price_free') : null);
            $showPrice = $style['show_price'] && $priceBadge;
            $weather = ($style['show_weather'] && $withPhoto) ? ($x['weather'] ?? null) : null;
            $tags = [];
            foreach (['subtype', 'gender', 'age', 'pay', 'rated'] as $k) {
                if (!empty($style['badge_' . $k]) && !empty($x['badges'][$k])) {
                    $tags[] = ['cls' => $k === 'rated' ? 'rated' : '', 'text' => $k === 'subtype' ? str_replace('x', '×', $x['badges'][$k]) : $x['badges'][$k]];
                }
            }
            if ($style['badge_status'] && !empty($x['status'])) {
                $tags[] = ['cls' => 'status-' . $x['status']['key'], 'text' => $x['status']['label']];
            }
            $s = $ev['slots_info'] ?? null;
            $pct = ($s && $s['max'] > 0) ? min(100, (int) round($s['taken'] / $s['max'] * 100)) : 0;
        @endphp
        <article class="vw-card {{ $withPhoto ? 'has-photo' : '' }} {{ $headFirst ? 'title-first' : '' }}" part="card">
            @if($headFirst)
            <div class="vw-head">
                <a href="{{ $ev['url'] }}" target="_blank" rel="noopener" class="vw-title" part="title">@if($style['show_icons'] && !empty($ev['is_private']))<span title="{{ __('events.card_private_title') }}">🙈</span> @endif{{ $ev['title'] }}</a>
                @if($style['show_location'] && !empty($ev['address']))
                <div class="vw-row">{!! $ic('📍') !!}<span>{{ $ev['address'] }}</span></div>
                @endif
            </div>
            @endif

            @if($withPhoto)
            <a class="vw-photo" part="photo" href="{{ $ev['url'] }}" target="_blank" rel="noopener" tabindex="-1" aria-hidden="true">
                <img src="{{ $ev['photo'] ?? '' }}" alt="" width="640" height="360" loading="lazy">
                @if($showPrice)
                    <span class="vw-badge vw-badge-price" part="badge">{{ $priceBadge }}</span>
                @endif
                @if($weather)
                    <span class="vw-badge vw-badge-weather" part="badge">{{ $weather['icon'] }} {{ $weather['temp'] }}@if($weather['pop'] >= 10) 💧{{ $weather['pop'] }}%@endif</span>
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

                    <div class="{{ $headFirst ? 'vw-body-head' : '' }}">
                        <a href="{{ $ev['url'] }}" target="_blank" rel="noopener" class="vw-title" part="title">@if($style['show_icons'] && !empty($ev['is_private']))<span title="{{ __('events.card_private_title') }}">🙈</span> @endif{{ $ev['title'] }}</a>
                        @if($headFirst && $style['show_location'] && !empty($ev['address']))
                        <div class="vw-row" style="margin-bottom:6px">{!! $ic('📍') !!}<span>{{ $ev['address'] }}</span></div>
                        @endif
                    </div>

                    <div class="vw-meta" part="meta">
                        <div class="vw-row">{!! $ic('📅') !!}<span>{{ $ev['date_long'] }}, {{ $ev['time_range'] }}</span></div>

                        @if(!$headFirst && $style['show_location'] && !empty($ev['address']))
                        <div class="vw-row">{!! $ic('📍') !!}<span>{{ $ev['address'] }}</span></div>
                        @endif

                        @if($style['show_level'] && (!is_null($ev['level_min']) || !is_null($ev['level_max'])))
                        <div class="vw-row">{!! $ic('🎚') !!}<span>
                            @if(!is_null($ev['level_min']))<span class="vw-lvl l{{ (int) $ev['level_min'] }}">{{ level_name_short($ev['level_min'], $ev['level_scope'] ?? 'standard') }}</span>@endif
                            @if(!is_null($ev['level_min']) && !is_null($ev['level_max']) && $ev['level_min'] != $ev['level_max']) — @endif
                            @if(!is_null($ev['level_max']) && $ev['level_min'] != $ev['level_max'])<span class="vw-lvl l{{ (int) $ev['level_max'] }}">{{ level_name_short($ev['level_max'], $ev['level_scope'] ?? 'standard') }}</span>@endif
                        </span></div>
                        @endif

                        @if($style['show_slots'] && $s)
                        <div class="vw-row">{!! $ic('👥') !!}<span class="vw-seats">
                            <strong>{{ $s['taken'] }}</strong>
                            {{ __('events.card_seats_of') }}
                            <strong>{{ $s['max'] }}</strong>{{ $s['unit'] === 'teams' ? __('events.card_seats_teams') : __('events.card_seats_players') }}
                            @if($s['reserve'] > 0){!! __('events.widget_reserve_suffix', ['count' => $s['reserve']]) !!}@endif
                            @if($style['show_progress'])<div class="vw-progress"><i style="width:{{ $pct }}%"></i></div>@endif
                        </span></div>
                        @endif

                        @if(!$withPhoto && $showPrice)
                        <div class="vw-row">{!! $ic('💰') !!}<span class="vw-price-inline">{{ $priceBadge }}</span></div>
                        @endif

                        @if($style['show_organizer'] && !empty($x['organizer']))
                        <div class="vw-row vw-org">{!! $ic('👤') !!}<span>{{ __('events.card_organizer') }} <a href="{{ $x['organizer']['url'] }}" target="_blank" rel="noopener">{{ $x['organizer']['name'] }}</a></span></div>
                        @endif
                    </div>

                    @if($tags)
                    <div class="vw-tags">
                        @foreach($tags as $t)<span class="vw-tag {{ $t['cls'] }}" part="badge">{{ $t['text'] }}</span>@endforeach
                    </div>
                    @endif
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
