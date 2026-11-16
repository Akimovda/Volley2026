{{-- Виджет аренды кортов (iframe, shadow-root JS-варианта, предпросмотр). Ожидает: $locations, $style (после forRender) --}}
@php
    $vwCount = count($locations);
    $titleFirst = $style['card_order'] === 'title_first';
    $fmtMoney = fn ($v) => number_format((float) $v, 0, ',', ' ');
    $ic = fn (string $name) => $style['show_icons'] ? '<span class="vw-ic">' . ico_svg($name, '1.15em') . '</span>' : '';
@endphp
<style>{!! \App\Services\WidgetStyleService::css($style, '.vw-root', $vwCount) !!}
.vw-root .vw-dirs{position:absolute;right:8px;bottom:8px;display:flex;flex-wrap:wrap;gap:6px;justify-content:flex-end;max-width:calc(100% - 16px)}
.vw-root .vw-dirs .vw-badge-dir{position:static}
.vw-root .vw-dot{display:inline-block;width:9px;height:9px;border-radius:50%;margin-top:.4em;flex-shrink:0}
.vw-root .vw-dot.classic{background:var(--_dir-classic)}
.vw-root .vw-dot.beach{background:var(--_dir-beach)}
.vw-root .vw-row .vw-ic svg{width:1.15em;height:1.15em;display:block}
.vw-root .vw-desc{font-size:{{ (int) $style['meta_size'] }}px;color:var(--_meta-color);margin:0 0 8px}
.vw-root .vw-open{color:#16a34a;font-weight:600}
.vw-root .vw-closed{color:#dc2626;font-weight:600}
</style>
<div class="vw-root" part="root">
    @if($style['header_show'] && $style['header_text'] !== '')
        <div class="vw-header" part="header">{{ $style['header_text'] }}</div>
    @endif

    @if($vwCount === 0)
        <div class="vw-empty" part="empty">{{ __('club.rw_empty') }}</div>
    @else
    <div class="vw-cards" part="cards">
        @foreach($locations as $loc)
        @php
            $withPhoto = $style['photo_show'] && $style['layout'] !== 'list';
            $headFirst = $titleFirst && $withPhoto;
            $showPrice = $style['show_price'] && $style['show_price_from'] && !empty($loc['price_from']);
            $btnLabel = $style['button_text'] . ' — ' . $loc['name'];
            $priceText = $showPrice ? __('club.rw_price_from', ['price' => $fmtMoney($loc['price_from'])]) : null;
        @endphp
        <article class="vw-card {{ $withPhoto ? 'has-photo' : '' }} {{ $headFirst ? 'title-first' : '' }}" part="card">
            @if($headFirst)
            <div class="vw-head">
                <a href="{{ $loc['url'] }}" target="_blank" rel="noopener" class="vw-title" part="title">{{ $loc['name'] }}</a>
                @if($style['show_location'] && !empty($loc['address']))
                <div class="vw-row">{!! $ic('pin') !!}<span>{{ $loc['address'] }}</span></div>
                @endif
            </div>
            @endif

            @if($withPhoto)
            <a class="vw-photo" part="photo" href="{{ $loc['url'] }}" target="_blank" rel="noopener" tabindex="-1" aria-hidden="true">
                <img src="{{ $loc['photo'] }}" alt="" width="640" height="360" loading="lazy">
                @if($priceText)
                    <span class="vw-badge vw-badge-price" part="badge">{{ $priceText }}</span>
                @endif
                @if($style['show_direction'])
                <span class="vw-dirs">
                    @foreach($loc['directions'] as $d)
                    <span class="vw-badge vw-badge-dir {{ $d['direction'] === 'beach' ? 'beach' : 'classic' }}" part="badge">{{ $d['direction'] === 'beach' ? __('events.card_dir_beach') : __('events.card_dir_classic') }}</span>
                    @endforeach
                </span>
                @endif
            </a>
            @endif

            <div class="vw-main">
                <div class="vw-body">
                    @if(!$withPhoto && $style['show_direction'])
                        @foreach($loc['directions'] as $d)
                        <span class="vw-badge vw-badge-dir vw-dir-inline {{ $d['direction'] === 'beach' ? 'beach' : 'classic' }}" part="badge">{{ $d['direction'] === 'beach' ? __('events.card_dir_beach') : __('events.card_dir_classic') }}</span>
                        @endforeach
                    @endif

                    <div class="{{ $headFirst ? 'vw-body-head' : '' }}">
                        <a href="{{ $loc['url'] }}" target="_blank" rel="noopener" class="vw-title" part="title">{{ $loc['name'] }}</a>
                        @if($headFirst && $style['show_location'] && !empty($loc['address']))
                        <div class="vw-row" style="margin-bottom:6px">{!! $ic('pin') !!}<span>{{ $loc['address'] }}</span></div>
                        @endif
                    </div>

                    @if($style['show_desc'] && $loc['desc'] !== '')
                    <p class="vw-desc">{{ $loc['desc'] }}</p>
                    @endif

                    <div class="vw-meta" part="meta">
                        @if(!$headFirst && $style['show_location'] && !empty($loc['address']))
                        <div class="vw-row">{!! $ic('pin') !!}<span>{{ $loc['address'] }}</span></div>
                        @endif

                        @if($style['show_hours'])
                        <div class="vw-row">{!! $ic('clock') !!}<span>
                            @if($loc['open_today'])
                                {{ __('club.rw_today') }} <span class="vw-open">{{ $loc['hours_today']['from'] }}–{{ $loc['hours_today']['to'] }}</span>
                            @else
                                <span class="vw-closed">{{ __('club.rw_closed_today') }}</span>
                            @endif
                        </span></div>
                        @endif

                        @if($style['show_courts'])
                        @foreach($loc['directions'] as $d)
                        <div class="vw-row">
                            @if($style['show_icons'])<span class="vw-dot {{ $d['direction'] === 'beach' ? 'beach' : 'classic' }}"></span>@endif
                            <span>
                                <strong>{{ $d['direction'] === 'beach' ? __('events.card_dir_beach') : __('events.card_dir_classic') }}</strong>:
                                {{ trans_choice('club.rw_courts', $d['courts'], ['n' => $d['courts']]) }}@if($style['show_price_from'] && $d['price_from'] !== null), {{ __('club.rw_price_from', ['price' => $fmtMoney($d['price_from'])]) }}@endif
                            </span>
                        </div>
                        @endforeach
                        @endif
                    </div>

                    @if($style['show_court_names'])
                    <div class="vw-tags">
                        @foreach($loc['directions'] as $d)@foreach($d['court_names'] as $cn)<span class="vw-tag" part="badge">{{ $cn }}</span>@endforeach @endforeach
                    </div>
                    @endif
                </div>

                @if($style['button_show'])
                <a href="{{ $loc['url'] }}" target="_blank" rel="noopener" class="vw-btn" part="button" aria-label="{{ $btnLabel }}">{{ $style['button_text'] }}</a>
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
