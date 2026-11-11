<x-voll-layout body_class="subscription-usages-page">
    <x-slot name="title">{{ __('subscriptions.usages_title') }}</x-slot>
    <x-slot name="h1">{{ __('subscriptions.usages_h1') }}</x-slot>
    <div class="container">
    <div class="row">
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
            <h2 class="-mt-05 h2-ico"><x-ico name="clipboard" size="1.2em" /> {{ $subscription->template->name }}</h2>
            <div class="f-16">{{ __('subscriptions.usages_left') }} <strong>{{ $subscription->visits_remaining }}</strong> / {{ $subscription->visits_total }}</div>
        </div>
        <div class="ramka">
            @if($usages->isEmpty())
                <div class="alert alert-info">{{ __('subscriptions.usages_empty') }}</div>
            @else
            <table class="table f-16">
                <thead><tr><th>{{ __('subscriptions.usages_col_date') }}</th><th>{{ __('subscriptions.usages_col_event') }}</th><th>{{ __('subscriptions.usages_col_action') }}</th></tr></thead>
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
                            @if($u->action==='used') <span class="usage-pill usage-pill--used"><x-ico name="check" size="1.5rem" color="#16a34a" /> {{ __('subscriptions.usages_action_used') }}</span>
                            @elseif($u->action==='returned') <span class="usage-pill usage-pill--returned"><x-ico name="repeat" size="1.5rem" color="#2563eb" /> {{ __('subscriptions.usages_action_returned') }}</span>
                            @elseif($u->action==='burned') <span class="usage-pill usage-pill--burned"><x-ico name="flame" size="1.5rem" color="#ea580c" /> {{ __('subscriptions.usages_action_burned') }}</span>
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
