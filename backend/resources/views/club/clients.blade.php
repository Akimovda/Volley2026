<x-voll-layout body_class="club-clients-page">
<x-slot name="title">{{ __('club.clients_title') }}</x-slot>
<x-slot name="h1">{{ __('club.clients_title') }}</x-slot>

<x-slot name="breadcrumbs">
    <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
        <span itemprop="name">{{ __('club.clients_title') }}</span>
        <meta itemprop="position" content="2">
    </li>
</x-slot>

<div class="container">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    <div class="card">
        <style>
            .cl-search{display:grid;grid-template-columns:1fr auto;gap:10px;margin-bottom:12px}
            .cl-search input{min-width:0}
            .cl-back{margin-bottom:12px}
        </style>
        <form method="GET" action="{{ route('club.clients.index') }}" class="cl-search">
            <input type="text" name="q" value="{{ $q }}" placeholder="{{ __('club.clients_search') }}">
            <button class="btn btn-small btn-primary" type="submit">{{ __('club.clients_find') }}</button>
        </form>
        <div class="cl-back"><a class="btn btn-small btn-outline" href="{{ route('club.bookings.index') }}"><x-ico name="calendar-check" /> {{ __('club.bookings_title') }}</a></div>
        <p class="f-13 cd">{{ __('club.clients_hint') }}</p>
        @if($clients->isEmpty())
            <div class="alert alert-info">{{ __('club.clients_empty') }}</div>
        @else
            <div style="overflow-x:auto">
            <table class="table" style="width:100%">
                <thead><tr>
                    <th>{{ __('club.clients_name') }}</th><th>{{ __('club.clients_contacts') }}</th>
                    <th>{{ __('club.clients_bookings') }}</th><th>{{ __('club.clients_spent') }}</th>
                    <th>{{ __('club.clients_last') }}</th><th>{{ __('club.clients_notes') }}</th><th></th>
                </tr></thead>
                <tbody>
                @foreach($clients as $c)
                    <tr>
                        <td><b>{{ $c->name }}</b></td>
                        <td>{{ $c->phoneFormatted() }}<br><span class="f-12 cd">{{ $c->email }}</span></td>
                        <td>{{ $c->bookings_count }}</td>
                        <td>{{ number_format((float) $c->spent, 0, ',', ' ') }} ₽</td>
                        <td>{{ $c->last_at ? \Illuminate\Support\Carbon::parse($c->last_at)->format('d.m.Y') : '—' }}</td>
                        <td>
                            <form method="POST" action="{{ route('club.clients.update', $c) }}" style="display:flex;gap:6px">@csrf @method('PUT')
                                <input type="text" name="notes" value="{{ $c->notes }}" maxlength="1000" style="min-width:160px">
                                <button class="btn btn-small btn-outline" type="submit">{{ __('club.clients_save') }}</button>
                            </form>
                        </td>
                        <td>
                            <form method="POST" action="{{ route('club.clients.destroy', $c) }}" onsubmit="return confirm('{{ __('club.clients_delete_confirm') }}')">@csrf @method('DELETE')
                                <button class="btn btn-small btn-outline-danger" type="submit">{{ __('club.clients_delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            </div>
            {{ $clients->links() }}
        @endif
    </div>
</div>
</x-voll-layout>
