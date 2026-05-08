{{-- resources/views/payment/transactions.blade.php --}}
<x-voll-layout body_class="transactions-page">

    <x-slot name="title">{{ __('profile.pay_tx_title') }}</x-slot>
    <x-slot name="h1">{{ __('profile.pay_tx_title') }}</x-slot>
    <x-slot name="t_description">{{ __('profile.pay_tx_t_description') }}</x-slot>

    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('profile.show') }}" itemprop="item"><span itemprop="name">{{ __('profile.nch_breadcrumb') }}</span></a>
            <meta itemprop="position" content="2">
        </li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <span itemprop="name">{{ __('profile.pay_tx_title') }}</span>
            <meta itemprop="position" content="3">
        </li>
    </x-slot>

    <x-slot name="d_description">
        <div class="d-flex gap-2 mt-2">
            <a href="{{ route('profile.payment_settings') }}" class="btn btn-secondary">⚙️ {{ __('profile.pay_settings_title') }}</a>
        </div>
    </x-slot>

    <div class="container">

        @if(session('status'))
            <div class="ramka"><div class="alert alert-success">{{ session('status') }}</div></div>
        @endif

        {{-- СТАТИСТИКА --}}
        <div class="ramka">
            <h2 class="-mt-05">📊 Сводка</h2>
            <div class="row row2">
                <div class="col-6 col-md-3">
                    <div class="card text-center">
                        <div class="f-14">Получено (₽)</div>
                        <div class="f-32 b-700 cs">{{ number_format($stats['total_paid'], 2) }}</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card text-center">
                        <div class="f-14">Ожидают оплаты</div>
                        <div class="f-32 b-700 cd">{{ $stats['total_pending'] }}</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card text-center">
                        <div class="f-14">Требуют подтверждения</div>
                        <div class="f-32 b-700 red">{{ $stats['link_pending'] }}</div>
                        <div class="f-13" style="opacity:.6">игрок нажал «Я оплатил»</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ТАБЛИЦА --}}
        <div class="ramka">
            <h2 class="-mt-05">📋 История платежей</h2>

            @if($payments->isEmpty())
                <div class="alert alert-info">{{ __('profile.pay_tx_empty') }}</div>
            @else
                <div class="table-scrollable mb-0">
                    <table class="table f-16">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>{{ __('profile.col_player') }}</th>
                                <th>{{ __('profile.col_event') }}</th>
                                <th>Метод</th>
                                <th>Сумма</th>
                                <th>Статус</th>
                                <th>Дата</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($payments as $p)
                            <tr>
                                <td>#{{ $p->id }}</td>
                                <td>
                                    @if($p->user)
                                        <a href="{{ route('users.show', $p->user->id) }}">
                                            {{ trim($p->user->first_name . ' ' . $p->user->last_name) ?: '#'.$p->user_id }}
                                        </a>
                                    @else —
                                    @endif
                                </td>
                                <td>
                                    @if($p->event)
                                        <a href="{{ route('events.show', $p->event_id) }}">{{ $p->event->title }}</a>
                                    @else —
                                    @endif
                                </td>
                                <td>
                                    @php $methodLabels = ['cash'=>'💵 Нал','tbank_link'=>'🏦 Т-Банк','sber_link'=>'💚 Сбер','yoomoney'=>'🟡 ЮМани','wallet'=>'👛 Кошелёк']; @endphp
                                    {{ $methodLabels[$p->method] ?? $p->method }}
                                </td>
                                <td class="b-600">{{ number_format($p->amount_minor/100, 2) }} ₽</td>
                                <td>
                                    @if($p->status === 'paid')
                                        <span class="cs b-600">✅ Оплачено</span>
                                    @elseif($p->status === 'pending' && $p->user_confirmed && !$p->org_confirmed)
                                        <span class="cd b-600">👀 Проверьте</span>
                                    @elseif($p->status === 'pending')
                                        <span style="opacity:.6">⏳ Ожидание</span>
                                    @elseif($p->status === 'refunded')
                                        <span class="cd">↩️ Возврат</span>
                                    @elseif($p->status === 'expired')
                                        <span style="opacity:.5">⌛ Истёк</span>
                                    @elseif($p->status === 'cancelled')
                                        <span style="opacity:.5">❌ Отменён</span>
                                    @else
                                        {{ $p->status }}
                                    @endif
                                </td>
                                <td class="nowrap">{{ $p->created_at->setTimezone('Europe/Moscow')->format('d.m.Y H:i') }}</td>
                                <td class="nowrap">
                                    @if($p->status === 'pending' && $p->user_confirmed && !$p->org_confirmed)
                                        <form method="POST" action="{{ route('payments.org_confirm', $p->id) }}" class="d-inline">
                                            @csrf
                                            <button class="btn btn-small">✅ Подтвердить</button>
                                        </form>
                                        <form method="POST" action="{{ route('payments.org_reject', $p->id) }}" class="d-inline">
                                            @csrf
                                            <button class="btn btn-small btn-secondary">❌</button>
                                        </form>
                                    @elseif($p->status === 'paid')
                                        <form method="POST" action="{{ route('payments.refund', $p->id) }}" class="d-inline">
                                            @csrf
                                            <button class="btn btn-small btn-secondary"
                                                onclick="return confirm('Вернуть {{ number_format($p->amount_minor/100,2) }} ₽ на виртуальный счёт игрока?')">
                                                ↩️ Возврат
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-2">{{ $payments->links() }}</div>
            @endif
        </div>

    </div>

</x-voll-layout>
