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
    $usedPct   = $total > 0 ? (int) max(0, min(100, round($used / $total * 100))) : 0;   // доля использованных занятий
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

    <div class="sub-card__bar sub-card__bar--{{ $barTone }}" role="img" aria-label="{{ __('subscriptions.my_card_used', ['pct' => $usedPct]) }}"><span style="width: {{ $usedPct }}%"></span></div>

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
