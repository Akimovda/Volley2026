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
            @php $filterLabelStyle = 'display:block;margin-bottom:.4rem;font-weight:600;font-size:1.2rem;opacity:.7'; @endphp
            <form method="GET" action="{{ route('payments.cash_control_index') }}" class="form d-flex flex-wrap gap-2 mb-2" style="align-items:flex-end;justify-content:center">
                <div>
                    <label style="{{ $filterLabelStyle }}">{{ __('profile.pay_ccidx_filter_search_label') }}</label>
                    <input type="text" name="q" value="{{ $search }}" placeholder="{{ __('profile.pay_ccidx_filter_search_ph') }}">
                </div>
                <div>
                    <label style="{{ $filterLabelStyle }}">{{ __('profile.pay_ccidx_filter_range_label') }}</label>
                    <select name="range">
                        <option value="current" @selected($range === 'current')>{{ __('profile.pay_ccidx_filter_range_current') }}</option>
                        <option value="archive" @selected($range === 'archive')>{{ __('profile.pay_ccidx_filter_range_archive') }}</option>
                        <option value="all" @selected($range === 'all')>{{ __('profile.pay_ccidx_filter_range_all') }}</option>
                    </select>
                </div>
                <div>
                    <label class="checkbox-item" style="margin:0 0 .9rem">
                        <input type="checkbox" name="unpaid_only" value="1" @checked($unpaidOnly)>
                        <div class="custom-checkbox"></div>
                        <span>{{ __('profile.pay_ccidx_filter_unpaid_only') }}</span>
                    </label>
                </div>
                <div>
                    <label style="{{ $filterLabelStyle }};opacity:0" aria-hidden="true">&nbsp;</label>
                    <button type="submit" class="btn btn-outline-primary" style="padding:1.2rem 1.8rem" title="{{ __('profile.pay_ccidx_filter_submit') }}">
                        <x-menu-icon name="search" style="width:1.8rem;height:1.8rem" />
                    </button>
                </div>
                @if($search !== '' || $range !== 'current' || $unpaidOnly)
                    <div>
                        <label style="{{ $filterLabelStyle }};opacity:0" aria-hidden="true">&nbsp;</label>
                        <a href="{{ route('payments.cash_control_index') }}" class="btn btn-outline-danger" style="padding:1.2rem 1.8rem" title="{{ __('profile.pay_ccidx_filter_reset') }}">
                            <x-menu-icon name="trash" style="width:1.8rem;height:1.8rem" />
                        </a>
                    </div>
                @endif
            </form>

            @if($occurrences->isEmpty())
                <div class="alert alert-info">{{ __('profile.pay_ccidx_empty') }}</div>
            @else
                <div class="table-scrollable mb-0">
                    <table class="table f-16">
                        <thead>
                            <tr>
                                <th>{{ __('profile.pay_ccidx_col_num') }}</th>
                                <th class="text-center">{{ __('profile.pay_ccidx_col_date') }}</th>
                                <th>{{ __('profile.pay_ccidx_col_title') }}</th>
                                <th>{{ __('profile.pay_ccidx_col_location') }}</th>
                                <th class="text-center">{{ __('profile.pay_ccidx_col_payment') }}</th>
                                <th class="text-center">{{ __('profile.pay_ccidx_col_action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($occurrences as $i => $occ)
                            @php
                                $loc = $occ->location ?? $occ->event->location;
                                $unpaidCount = (int) ($occ->unpaid_count ?? 0);
                            @endphp
                            <tr>
                                <td>{{ $occurrences->firstItem() + $i }}</td>
                                <td class="text-center nowrap">
                                    <div>{{ $occ->starts_at->setTimezone('Europe/Moscow')->locale('ru')->translatedFormat('j F Y') }}</div>
                                    <div>{{ $occ->starts_at->setTimezone('Europe/Moscow')->format('H:i') }}</div>
                                </td>
                                <td>
                                    <a href="{{ route('events.show', $occ->event_id) }}?occurrence={{ $occ->id }}"
                                        @if($unpaidCount > 0) class="red" style="text-decoration:underline" title="{{ __('profile.pay_ccidx_title_unpaid_hint') }}" @endif
                                    >{{ $occ->event->title }}</a>
                                </td>
                                <td>{{ $loc->name ?? '—' }}</td>
                                <td class="text-center nowrap">
                                    @if($unpaidCount > 0)
                                        <span class="red b-600">⚠ {{ __('profile.pay_ccidx_unpaid_badge', ['n' => $unpaidCount]) }}</span>
                                    @else
                                        <span class="text-muted">✅ {{ __('profile.pay_ccidx_paid_ok') }}</span>
                                    @endif
                                </td>
                                <td class="text-center">
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
