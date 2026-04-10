<x-voll-layout body_class="coupons-my-page">
    <x-slot name="title">Мои купоны</x-slot>
    <x-slot name="h1">Мои купоны</x-slot>

    <div class="container">
        @if(session('status'))
            <div class="ramka"><div class="alert alert-success">{{ session('status') }}</div></div>
        @endif

        @if($coupons->isEmpty())
            <div class="ramka">
                <div class="alert alert-info">У вас нет купонов.</div>
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
                                <span class="cs b-600">✅ Активен</span>
                            @elseif($coupon->status === 'used')
                                <span style="opacity:.5">✔️ Использован</span>
                            @elseif($coupon->status === 'expired')
                                <span style="opacity:.5">⌛ Истёк</span>
                            @endif
                        </div>

                        <div class="f-32 b-700 cd text-center mb-1">{{ $coupon->getDiscountPct() }}%</div>
                        <div class="f-14 text-center mb-2" style="opacity:.6">скидка на мероприятие</div>

                        <div class="card" style="background:var(--bg2);text-align:center">
                            <div class="f-13 mb-05" style="opacity:.6">Код купона</div>
                            <div class="f-20 b-700 cs" style="letter-spacing:3px">{{ $coupon->code }}</div>
                        </div>

                        <div class="d-flex between f-14 mt-2" style="opacity:.6">
                            <span>Использований: {{ $coupon->uses_used }} / {{ $coupon->uses_total }}</span>
                            <span>До: {{ $coupon->expires_at ? $coupon->expires_at->format('d.m.Y') : '∞' }}</span>
                        </div>

                        @if($coupon->status === 'active' && $coupon->template->transfer_enabled)
                        <div class="mt-2">
                            <button class="btn btn-secondary btn-small w-100"
                                onclick="toggleTransferCoupon({{ $coupon->id }})">
                                🔄 Передать купон
                            </button>
                            <div id="transfer_coupon_{{ $coupon->id }}" style="display:none" class="mt-1">
                                <form method="POST" action="{{ route('coupons.transfer', $coupon) }}">
                                    @csrf
                                    <div class="d-flex gap-2">
                                        <input type="number" name="to_user_id" placeholder="ID игрока">
                                        <button type="submit" class="btn btn-small"
                                            onclick="return confirm('Передать купон? Необратимо.')">→</button>
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
