{{-- Карточка абонемента в стиле членской карты. Ожидает: $sub, $card (цвета и логотип бренда организатора или VolleyClub) --}}
@php
    $total     = (int) $sub->visits_total;
    $left      = max(0, (int) $sub->visits_remaining);
    $used      = max(0, $total - $left);
    $ending    = $sub->status === 'active' && $left > 0 && $left <= \App\Models\Subscription::ENDING_VISITS;
    $stateKey  = match (true) {
        $sub->status === 'frozen'    => 'frozen',
        $sub->status === 'expired'   => 'expired',
        $sub->status === 'exhausted' => 'exhausted',
        $sub->status === 'cancelled' => 'cancelled',
        $ending                      => 'ending',
        default                      => 'active',
    };
    $dim       = in_array($stateKey, ['expired', 'exhausted', 'cancelled'], true);
    $leftPct   = $total > 0 ? ($left / $total * 100) : 0;
    // цвет полосы по остатку: от 50% — зелёная, 49–30% — жёлтая, ниже 30% или 1–2 занятия — красная
    $barTone   = ($left <= \App\Models\Subscription::ENDING_VISITS || $leftPct < 30) ? 'red' : ($leftPct < 50 ? 'yellow' : 'green');
    $holder    = \App\Support\Translit::cardName($sub->user);
    $leftBar   = $total > 0 ? (int) max(0, min(100, round($left / $total * 100))) : 0;   // ширина полосы = остаток занятий (полная в начале, убывает справа налево)
@endphp
<div class="sub-card sub-card--{{ $stateKey }} {{ $dim ? 'is-dim' : '' }}"
     style="--sc-from: {{ $card['from'] }}; --sc-to: {{ $card['to'] }}; --sc-accent: {{ $card['accent'] }}">
    @if(!empty($card['logo']))
    <img class="sub-card__wm" src="{{ $card['logo'] }}" alt="" aria-hidden="true">
    @endif
    <div class="sub-card__top">
        <span class="sub-card__kind">{{ __('subscriptions.my_card_kind') }} · {{ $sub->template->name }}</span>
        <span class="sub-card__state">{{ __('subscriptions.my_state_' . $stateKey) }}</span>
    </div>
    <div class="sub-card__big">{{ trans_choice('subscriptions.my_card_visits', $total, ['n' => $total]) }}</div>

    @php $tpl = $sub->template; @endphp
    @if($tpl->transfer_enabled || $tpl->freeze_enabled || $tpl->auto_booking_enabled)
    <div class="sub-card__feats">
        @if($tpl->transfer_enabled)<span class="sub-card__feat" title="{{ __('subscriptions.feat_transfer') }}"><x-ico name="swap" size="1.4rem" color="#5eead4" /><span>{{ __('subscriptions.feat_transfer') }}</span></span>@endif
        @if($tpl->freeze_enabled)<span class="sub-card__feat" title="{{ __('subscriptions.feat_freeze') }}"><x-ico name="clock" size="1.4rem" color="#7dd3fc" /><span>{{ __('subscriptions.feat_freeze') }}</span></span>@endif
        @if($tpl->auto_booking_enabled)<span class="sub-card__feat" title="{{ __('subscriptions.feat_auto') }}"><x-ico name="robot" size="1.4rem" color="#c4b5fd" /><span>{{ __('subscriptions.feat_auto') }}</span></span>@endif
    </div>
    @endif

    <div class="sub-card__bar sub-card__bar--{{ $barTone }}" role="img" aria-label="{{ __('subscriptions.my_card_left', ['left' => $left, 'total' => $total]) }}"><span style="width: {{ $leftBar }}%"></span></div>

    @if($holder !== '')
    <div class="sub-card__holder">{{ $holder }}</div>
    @endif

    <div class="sub-card__foot">
        <span>{!! __('subscriptions.my_card_left', ['left' => '<b>' . $left . '</b>', 'total' => $total]) !!}</span>
        <span>
            @if($sub->status === 'frozen' && $sub->frozen_until)
                {{ __('subscriptions.my_card_frozen_until', ['date' => $sub->frozen_until->translatedFormat('j F')]) }}
            @elseif($sub->expires_at)
                {{ __('subscriptions.my_card_until', ['date' => $sub->expires_at->translatedFormat('j F')]) }}
            @else
                {{ __('subscriptions.my_card_unlimited') }}
            @endif
        </span>
    </div>
</div>
