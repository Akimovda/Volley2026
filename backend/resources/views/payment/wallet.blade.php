{{-- resources/views/payment/wallet.blade.php --}}
<x-voll-layout body_class="wallet-page">

    <x-slot name="title">Мой кошелёк</x-slot>
    <x-slot name="h1">Мой кошелёк</x-slot>
    <x-slot name="t_description">Виртуальные средства от возвратов за мероприятия</x-slot>

    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('profile.show') }}" itemprop="item"><span itemprop="name">Профиль</span></a>
            <meta itemprop="position" content="2">
        </li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <span itemprop="name">Кошелёк</span>
            <meta itemprop="position" content="3">
        </li>
    </x-slot>

    <div class="container">

        @if($wallets->isEmpty())
            <div class="ramka">
                <div class="alert alert-info">
                    У вас пока нет виртуальных средств.<br>
                    Средства появляются при возврате оплаты за отменённые мероприятия.
                </div>
            </div>
        @else
            @foreach($wallets as $wallet)
            <div class="ramka">
                <div class="d-flex between fvc mb-2">
                    <h2 class="-mt-05">
                        👛 {{ trim($wallet->organizer?->first_name . ' ' . $wallet->organizer?->last_name) ?: 'Организатор #'.$wallet->organizer_id }}
                    </h2>
                    <div class="text-right">
                        <div class="f-32 b-700 cs">{{ number_format($wallet->balance_minor/100, 2) }} ₽</div>
                        <div class="f-14" style="opacity:.6">доступно</div>
                    </div>
                </div>

                <ul class="list f-16 mb-2">
                    <li>Средства можно потратить на оплату мероприятий этого организатора</li>
                    <li>Вывод средств недоступен — только на оплату игр</li>
                </ul>

                {{-- История операций --}}
                @if($wallet->transactions->isNotEmpty())
                <div class="table-scrollable mb-0">
                    <table class="table f-16">
                        <thead>
                            <tr>
                                <th>Дата</th>
                                <th>Операция</th>
                                <th>Сумма</th>
                                <th>Причина</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($wallet->transactions as $tx)
                            <tr>
                                <td class="nowrap">{{ $tx->created_at->format('d.m.Y H:i') }}</td>
                                <td>
                                    @if($tx->type === 'credit')
                                        <span class="cs b-600">+ Пополнение</span>
                                    @else
                                        <span class="red b-600">- Списание</span>
                                    @endif
                                </td>
                                <td class="b-600">
                                    {{ $tx->type === 'credit' ? '+' : '-' }}{{ number_format($tx->amount_minor/100, 2) }} ₽
                                </td>
                                <td>
                                    @php $reasonLabels = [
                                        'refund_quorum'    => '↩️ Отмена по кворуму',
                                        'refund_organizer' => '↩️ Возврат от организатора',
                                        'payment'          => '💳 Оплата мероприятия',
                                        'manual'           => '✏️ Ручная операция',
                                    ]; @endphp
                                    {{ $reasonLabels[$tx->reason] ?? $tx->reason }}
                                    @if($tx->event_id)
                                        · <a href="{{ route('events.show', $tx->event_id) }}">мероприятие #{{ $tx->event_id }}</a>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
            @endforeach
        @endif

    </div>

</x-voll-layout>
