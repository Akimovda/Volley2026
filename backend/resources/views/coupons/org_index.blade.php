<x-voll-layout body_class="coupons-org-page">
    <x-slot name="title">Купоны</x-slot>
    <x-slot name="h1">Выданные купоны</x-slot>
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
        <div class="ramka">
            @if($coupons->isEmpty())
                <div class="alert alert-info">Купонов пока нет.</div>
            @else
            <div class="table-scrollable">
                <table class="table f-16">
                    <thead><tr><th>Код</th><th>Игрок</th><th>Шаблон</th><th>Скидка</th><th>Использований</th><th>Статус</th><th>Истекает</th></tr></thead>
                    <tbody>
                        @foreach($coupons as $c)
                        <tr>
                            <td class="b-600 cs">{{ $c->code }}</td>
                            <td><a href="{{ route('users.show', $c->user_id) }}">{{ $c->user->name ?? '#'.$c->user_id }}</a></td>
                            <td>{{ $c->template->name }}</td>
                            <td class="b-600 cd">{{ $c->template->discount_pct }}%</td>
                            <td>{{ $c->uses_used }} / {{ $c->uses_total }}</td>
                            <td>
                                @if($c->status==='active')<span class="cs">✅</span>
                                @elseif($c->status==='used')<span style="opacity:.5">✔️</span>
                                @else<span style="opacity:.5">⌛</span>@endif
                            </td>
                            <td>{{ $c->expires_at ? $c->expires_at->format('d.m.Y') : '∞' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $coupons->links() }}
            @endif
        </div>
    </div>
</x-voll-layout>
