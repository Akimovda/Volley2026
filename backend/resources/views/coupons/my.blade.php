<x-voll-layout body_class="coupons-my-page">
    <x-slot name="title">{{ __('subscriptions.coupon_my_title') }}</x-slot>
    <x-slot name="h1">{{ __('subscriptions.coupon_my_title') }}</x-slot>

    <div class="container">
    <div class="row row2">
        <div class="col-lg-4 col-xl-3 order-2 d-none d-lg-block">
            <div class="sticky">
                <div class="card-ramka">
                    @include('profile._menu', [
                        'menuUser'   => auth()->user(),
                        'activeMenu' => 'coupons',
                    ])
                </div>
            </div>
        </div>
        <div class="col-lg-8 col-xl-9 order-1">
        @if(session('status'))
            <div class="ramka"><div class="alert alert-success">{{ session('status') }}</div></div>
        @endif

        @if($coupons->isEmpty())
            <div class="ramka">
                <div class="alert alert-info">{{ __('subscriptions.coupon_my_empty') }}</div>
            </div>
        @else
        <div class="ramka">
            <div class="row row2">
                @foreach($coupons as $coupon)
                <div class="col-md-6">
                    <div class="card">
                        <div class="d-flex between fvc mb-1">
                            <div class="b-600 f-18">{{ $coupon->template->name }}</div>
                            @if($coupon->status === 'active')
                                <span class="cs b-600">{{ __('subscriptions.coupon_status_active') }}</span>
                            @elseif($coupon->status === 'used')
                                <span style="opacity:.5">{{ __('subscriptions.coupon_status_used') }}</span>
                            @elseif($coupon->status === 'expired')
                                <span style="opacity:.5">{{ __('subscriptions.coupon_status_expired') }}</span>
                            @endif
                        </div>

                        <div class="f-32 b-700 cd text-center mb-1">{{ $coupon->getDiscountPct() }}%</div>
                        <div class="f-14 text-center mb-2" style="opacity:.6">{{ __('subscriptions.coupon_pct_label') }}</div>

                        <div class="card" style="background:var(--bg2);text-align:center">
                            <div class="f-13 mb-05" style="opacity:.6">{{ __('subscriptions.coupon_code_label') }}</div>
                            <div class="f-20 b-700 cs" style="letter-spacing:3px">{{ $coupon->code }}</div>
                        </div>

                        <div class="d-flex between f-14 mt-2" style="opacity:.6">
                            <span>{{ __('subscriptions.coupon_uses_label') }} {{ $coupon->uses_used }} / {{ $coupon->uses_total }}</span>
                            <span>{{ __('subscriptions.coupon_until_label') }} {{ $coupon->expires_at ? $coupon->expires_at->format('d.m.Y') : '∞' }}</span>
                        </div>

                        @if($coupon->status === 'active' && $coupon->template->transfer_enabled)
                        <div class="mt-2">
                            <button class="btn btn-secondary btn-small w-100"
                                onclick="toggleTransferCoupon({{ $coupon->id }})">
                                {{ __('subscriptions.coupon_btn_transfer') }}
                            </button>
                            <div id="transfer_coupon_{{ $coupon->id }}" style="display:none" class="mt-1">
                                <form method="POST" action="{{ route('coupons.transfer', $coupon) }}">
                                    @csrf
                                    <div class="d-flex gap-2">
                                        <input type="number" name="to_user_id" placeholder="{{ __('subscriptions.coupon_transfer_user_ph') }}">
                                        <button type="submit" class="btn btn-small"
                                            data-title="{{ __('subscriptions.coupon_transfer_title') }}" data-text="{{ __('subscriptions.coupon_transfer_text') }}" data-confirm-text="{{ __('subscriptions.coupon_transfer_yes') }}" data-cancel-text="{{ __('subscriptions.cancel') }}" class="btn btn-small btn-alert">→</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    <x-slot name="script">
    <script>
    function toggleTransferCoupon(id) {
        const el = document.getElementById('transfer_coupon_' + id);
        el.style.display = el.style.display === 'none' ? '' : 'none';
    }
    </script>
    </x-slot>
</x-voll-layout>
