{{-- resources/views/payment/event_control.blade.php --}}
<x-voll-layout body_class="payment-control-page">

    <x-slot name="title">{{ __('profile.pay_ctrl_title') }} — {{ $event->title }}</x-slot>
    <x-slot name="h1">{{ __('profile.pay_ctrl_title') }}</x-slot>
    <x-slot name="t_description">{{ __('profile.pay_ctrl_t_description') }}</x-slot>

    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('profile.show') }}" itemprop="item"><span itemprop="name">{{ __('profile.nch_breadcrumb') }}</span></a>
            <meta itemprop="position" content="2">
        </li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('profile.transactions') }}" itemprop="item"><span itemprop="name">{{ __('profile.pay_tx_title') }}</span></a>
            <meta itemprop="position" content="3">
        </li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <span itemprop="name">{{ $event->title }}</span>
            <meta itemprop="position" content="4">
        </li>
    </x-slot>

    <div class="container">

        @if(session('status'))
            <div class="ramka"><div class="alert alert-success">{{ session('status') }}</div></div>
        @endif
        @if(session('error'))
            <div class="ramka"><div class="alert alert-warning">{{ session('error') }}</div></div>
        @endif

        <div class="ramka">
            <h2 class="-mt-05">
                <a href="{{ route('events.show', $event->id) }}?occurrence={{ $occurrence->id }}">{{ $event->title }}</a>
            </h2>
            <div class="f-14" style="opacity:.7">
                {{ \Carbon\Carbon::parse($occurrence->starts_at)->setTimezone('Europe/Moscow')->format('d.m.Y H:i') }}
            </div>

            @if($rows->isEmpty())
                <div class="alert alert-info mt-2">{{ __('profile.pay_ctrl_empty') }}</div>
            @else
                <form method="POST" action="{{ route('payments.event_control.save', $event->id) }}" id="cash-tracking-form" class="form mt-2">
                    @csrf
                    <input type="hidden" name="occurrence_id" value="{{ $occurrence->id }}">

                    <div class="table-scrollable mb-0">
                        <table class="table f-16">
                            <thead>
                                <tr>
                                    <th>{{ __('profile.pay_ctrl_col_player') }}</th>
                                    <th>{{ __('profile.pay_ctrl_col_amount') }}</th>
                                    <th>{{ __('profile.pay_ctrl_col_status') }}</th>
                                    <th>{{ __('profile.pay_ctrl_col_paid') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($rows as $row)
                                    @php
                                        $rUser = $row['user'];
                                        $rPayment = $row['payment'];
                                        $isBanned = !empty($rPayment->cash_banned_at) && !$rPayment->isPaid();
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="d-flex fvc gap-1">
                                                @if($rUser)
                                                    <img src="{{ $rUser->profile_photo_url }}" alt="" style="width:28px;height:28px;border-radius:50%;object-fit:cover;flex-shrink:0">
                                                    <a href="{{ route('users.show', $rUser->id) }}">{{ trim($rUser->first_name . ' ' . $rUser->last_name) ?: '#'.$rUser->id }}</a>
                                                @else
                                                    #{{ $row['registration']->user_id }}
                                                @endif
                                            </div>
                                        </td>
                                        <td class="b-600">{{ number_format($rPayment->amount_minor / 100, 2) }} ₽</td>
                                        <td>
                                            @if($rPayment->isPaid())
                                                <span class="cs b-600">{{ __('profile.pay_ctrl_status_paid') }}</span>
                                            @elseif($isBanned)
                                                <span class="red b-600">{{ __('profile.pay_ctrl_status_banned') }}</span>
                                            @else
                                                <span style="opacity:.6">{{ __('profile.pay_ctrl_status_pending') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            <label class="checkbox-item">
                                                <input type="checkbox" name="paid_user_ids[]" value="{{ $row['registration']->user_id }}"
                                                    @checked($rPayment->isPaid())>
                                                <div class="custom-checkbox"></div>
                                            </label>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <ul class="list f-14 mt-2">
                        <li>{{ __('profile.pay_ctrl_hint') }}</li>
                    </ul>

                    <button type="submit" class="btn w-100 mt-2">{{ __('profile.pay_ctrl_btn_save') }}</button>
                </form>
            @endif
        </div>

    </div>

    <script>
        (function() {
            var form = document.getElementById('cash-tracking-form');
            if (!form) return;
            var submitted = false;
            form.addEventListener('submit', function(e) {
                if (submitted) return;
                e.preventDefault();
                swal({
                    title: @json(__('profile.pay_ctrl_confirm_title')),
                    text: @json(__('profile.pay_ctrl_confirm_text')),
                    icon: 'warning',
                    buttons: {
                        cancel: { text: @json(__('profile.pay_ctrl_confirm_no')), value: null, visible: true, closeModal: true },
                        confirm: { text: @json(__('profile.pay_ctrl_confirm_yes')), value: true, visible: true, closeModal: true }
                    }
                }).then(function(value) {
                    if (value) {
                        submitted = true;
                        form.submit();
                    }
                });
            });
        })();
    </script>

</x-voll-layout>
