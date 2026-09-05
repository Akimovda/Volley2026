{{-- resources/views/payment/cash_control_index.blade.php --}}
<x-voll-layout body_class="payment-cash-control-page">

    <x-slot name="title">{{ __('profile.pay_ccidx_title') }}</x-slot>
    <x-slot name="h1">{{ __('profile.pay_ccidx_title') }}</x-slot>
    <x-slot name="t_description">{{ __('profile.pay_ccidx_t_description') }}</x-slot>

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
            <span itemprop="name">{{ __('profile.pay_ccidx_title') }}</span>
            <meta itemprop="position" content="4">
        </li>
    </x-slot>

    <div class="container">

        <div class="ramka">
            @if($occurrences->isEmpty())
                <div class="alert alert-info">{{ __('profile.pay_ccidx_empty') }}</div>
            @else
                <div class="table-scrollable mb-0">
                    <table class="table f-16">
                        <thead>
                            <tr>
                                <th>{{ __('profile.pay_ccidx_col_num') }}</th>
                                <th>{{ __('profile.pay_ccidx_col_date') }}</th>
                                <th>{{ __('profile.pay_ccidx_col_title') }}</th>
                                <th>{{ __('profile.pay_ccidx_col_action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($occurrences as $i => $occ)
                            <tr>
                                <td>{{ $occurrences->firstItem() + $i }}</td>
                                <td class="nowrap">{{ $occ->starts_at->setTimezone('Europe/Moscow')->format('d.m.Y H:i') }}</td>
                                <td>
                                    <a href="{{ route('events.show', $occ->event_id) }}?occurrence={{ $occ->id }}">{{ $occ->event->title }}</a>
                                </td>
                                <td>
                                    <a href="{{ route('payments.event_control', ['event' => $occ->event_id, 'occurrence' => $occ->id]) }}" class="btn btn-outline-primary btn-sm" title="{{ __('profile.pay_ctrl_title') }}">
                                        <x-menu-icon name="check" style="width:1.4rem;height:1.4rem" />
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-2">
                    {{ $occurrences->links() }}
                </div>
            @endif
        </div>

    </div>

</x-voll-layout>
