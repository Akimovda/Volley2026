<x-voll-layout body_class="subscriptions-page">
    <x-slot name="title">{{ __('subscriptions.org_title') }}</x-slot>
    <x-slot name="h1">{{ __('subscriptions.org_h1') }}</x-slot>
    <x-slot name="d_description">
        <div class="d-flex gap-2 mt-2">
            <a href="{{ route('subscription_templates.index') }}" class="btn btn-secondary">{{ __('subscriptions.org_btn_templates') }}</a>
        </div>
    </x-slot>
    <div class="container">
    <div class="row row2">
        <div class="col-lg-4 col-xl-3 order-2 d-none d-lg-block">
            <div class="sticky">
                <div class="card-ramka">
                    @include('profile._menu', [
                        'menuUser'   => auth()->user(),
                        'activeMenu' => 'org_subscriptions',
                    ])
                </div>
            </div>
        </div>
        <div class="col-lg-8 col-xl-9 order-1">
        @if(session('status'))
            <div class="ramka"><div class="alert alert-success">{{ session('status') }}</div></div>
        @endif
        <div class="ramka">
            @if($subs->isEmpty())
                <div class="alert alert-info">{{ __('subscriptions.org_empty') }}</div>
            @else
            <div class="table-scrollable">
                <table class="table f-16">
                    <thead>
                        <tr><th>{{ __('subscriptions.col_id') }}</th><th>{{ __('subscriptions.col_player') }}</th><th>{{ __('subscriptions.col_template') }}</th><th>{{ __('subscriptions.col_visits') }}</th><th>{{ __('subscriptions.col_status') }}</th><th>{{ __('subscriptions.col_expires') }}</th><th>{{ __('subscriptions.col_actions') }}</th></tr>
                    </thead>
                    <tbody>
                        @foreach($subs as $sub)
                        <tr>
                            <td>#{{ $sub->id }}</td>
                            <td><a href="{{ route('users.show', $sub->user_id) }}">{{ $sub->user->name ?? '#'.$sub->user_id }}</a></td>
                            <td>{{ $sub->template->name }}</td>
                            <td>{{ $sub->visits_remaining }} / {{ $sub->visits_total }}</td>
                            <td>
                                @if($sub->status==='active') <span class="cs">{{ __('subscriptions.status_active') }}</span>
                                @elseif($sub->status==='frozen') <span class="cd">{{ __('subscriptions.status_frozen') }}</span>
                                @elseif($sub->status==='expired') <span style="opacity:.5">{{ __('subscriptions.status_expired') }}</span>
                                @elseif($sub->status==='exhausted') <span style="opacity:.5">{{ __('subscriptions.status_exhausted') }}</span>
                                @else {{ $sub->status }} @endif
                            </td>
                            <td>{{ $sub->expires_at ? $sub->expires_at->format('d.m.Y') : '∞' }}</td>
                            <td class="nowrap">
                                <a href="{{ route('subscriptions.usages', $sub) }}" class="btn btn-secondary btn-small">📋</a>
                                <form method="POST" action="{{ route('subscriptions.extend', $sub) }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="days" value="30">
                                    <button class="btn btn-small btn-alert" data-title="{{ __('subscriptions.sub_extend_title') }}" data-confirm-text="{{ __('subscriptions.sub_extend_yes') }}" data-cancel-text="{{ __('subscriptions.cancel') }}">{{ __('subscriptions.sub_extend_30') }}</button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $subs->links() }}
            @endif
        </div>

        {{-- Форма выдачи --}}
        <div class="ramka">
            <h2 class="-mt-05">{{ __('subscriptions.issue_h2') }}</h2>
            <form method="POST" action="{{ route('subscriptions.issue') }}" class="form">
                @csrf
                <div class="row row2">
                    <div class="col-md-4">
                        <label>{{ __('subscriptions.issue_label_template') }}</label>
                        <select name="template_id" required>
                            @foreach(\App\Models\SubscriptionTemplate::active()->when(!auth()->user()->isAdmin(), fn($q)=>$q->where('organizer_id',auth()->id()))->get() as $t)
                            <option value="{{ $t->id }}">{{ $t->name }} {{ __('subscriptions.tpl_visits_short', ['n' => $t->visits_total]) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label>{{ __('subscriptions.issue_label_user_id') }}</label>
                        <input type="number" name="user_id" required>
                    </div>
                    <div class="col-md-4">
                        <label>{{ __('subscriptions.issue_label_reason') }}</label>
                        <input type="text" name="reason" placeholder="{{ __('subscriptions.issue_ph_reason') }}">
                    </div>
                </div>
                <button type="submit" class="btn mt-2">{{ __('subscriptions.issue_btn') }}</button>
            </form>
        </div>
    </div>
</x-voll-layout>
