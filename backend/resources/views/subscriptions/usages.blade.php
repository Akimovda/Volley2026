<x-voll-layout body_class="subscription-usages-page">
    <x-slot name="title">История посещений</x-slot>
    <x-slot name="h1">История посещений абонемента</x-slot>
    <div class="container">
    <div class="row row2">
        <div class="col-lg-4 col-xl-3 order-2 d-none d-lg-block">
            <div class="sticky">
                <div class="card-ramka">
                    @include('profile._menu', [
                        'menuUser'   => auth()->user(),
                        'activeMenu' => 'subscriptions',
                    ])
                </div>
            </div>
        </div>
        <div class="col-lg-8 col-xl-9 order-1">
        <div class="ramka">
            <h2 class="-mt-05">{{ $subscription->template->name }}</h2>
            <div class="f-16">Осталось: <strong>{{ $subscription->visits_remaining }}</strong> / {{ $subscription->visits_total }}</div>
        </div>
        <div class="ramka">
            @if($usages->isEmpty())
                <div class="alert alert-info">Посещений пока нет.</div>
            @else
            <table class="table f-16">
                <thead><tr><th>Дата</th><th>Мероприятие</th><th>Действие</th></tr></thead>
                <tbody>
                    @foreach($usages as $u)
                    <tr>
                        <td>{{ $u->used_at->format('d.m.Y H:i') }}</td>
                        <td>
                            @if($u->event)
                                <a href="{{ route('events.show', $u->event_id) }}?occurrence={{ $u->occurrence_id }}">{{ $u->event->title }}</a>
                            @else
                                #{{ $u->event_id }}
                            @endif
                        </td>
                        <td>
                            @if($u->action==='used') <span class="cs">✅ Использовано</span>
                            @elseif($u->action==='returned') <span class="cd">↩️ Возвращено</span>
                            @elseif($u->action==='burned') <span class="red">🔥 Сгорело</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>
</x-voll-layout>
