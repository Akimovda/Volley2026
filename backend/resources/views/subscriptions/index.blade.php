<x-voll-layout body_class="subscriptions-page">
    <x-slot name="title">{{ __('subscriptions.org_title') }}</x-slot>
    <x-slot name="h1">{{ __('subscriptions.org_h1') }}</x-slot>
    <x-slot name="d_description">
        <div class="d-flex gap-2 mt-2">
            <a href="{{ route('subscription_templates.index') }}" class="btn btn-secondary">{{ __('subscriptions.org_btn_templates') }}</a>
        </div>
    </x-slot>
    <x-slot name="style">
    <style>
        .sub-st{font-weight:600;white-space:nowrap}
        .sub-st--active{color:#1e9e3e}
        .sub-st--ending{color:#e7812f}
        .sub-st--cancelled{color:#d93636}
        .sub-st--frozen{color:#2967BA}
        .sub-st--expired,.sub-st--exhausted{opacity:.5}
        body.dark .sub-st--active{color:#5ee68c}
        body.dark .sub-st--ending{color:#FFB171}
        body.dark .sub-st--cancelled{color:#ff7b7b}
        body.dark .sub-st--frozen{color:#8db6ff}
    </style>
    </x-slot>
    <div class="container">
    <div class="row">
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
        @if(session('error'))
            <div class="ramka"><div class="alert alert-danger">{{ session('error') }}</div></div>
        @endif
        <div class="ramka">
            <form method="GET" action="{{ route('subscriptions.index') }}" class="form">
                <div class="row">
                    <div class="col-md-4">
                        <label>{{ __('subscriptions.flt_q') }}</label>
                        <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="{{ __('subscriptions.flt_q_ph') }}">
                    </div>
                    <div class="col-md-3">
                        <label>{{ __('subscriptions.col_status') }}</label>
                        <select name="status">
                            <option value="">{{ __('subscriptions.flt_all') }}</option>
                            @foreach(['active','ending','frozen','expired','exhausted','cancelled'] as $st)
                                <option value="{{ $st }}" @selected($filters['status']===$st)>{{ __('subscriptions.st_'.$st) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label>{{ __('subscriptions.col_template') }}</label>
                        <select name="template">
                            <option value="0">{{ __('subscriptions.flt_all') }}</option>
                            @foreach($templates as $t)
                                <option value="{{ $t->id }}" @selected($filters['template']===$t->id)>{{ $t->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="d-flex gap-1 mt-2" style="flex-wrap:wrap">
                    <button type="submit" class="btn btn-outline btn-small">{{ __('subscriptions.flt_apply') }}</button>
                    @if($filters['q']!=='' || $filters['status']!=='' || $filters['template']>0)
                        <a href="{{ route('subscriptions.index') }}" class="btn btn-outline btn-small">{{ __('subscriptions.flt_reset') }}</a>
                    @endif
                    @php $exportQs = array_filter($filters); @endphp
                    <a href="{{ route('subscriptions.export_xlsx', $exportQs) }}" class="btn btn-outline btn-small">{{ __('subscriptions.export_xlsx') }}</a>
                    <a href="{{ route('subscriptions.export_pdf', $exportQs) }}" class="btn btn-outline btn-small">{{ __('subscriptions.export_pdf') }}</a>
                </div>
            </form>
        </div>
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
                            <td><a href="{{ route('users.show', $sub->user_id) }}">{{ $sub->user->name ?? '#'.$sub->user_id }}</a>@if($sub->user?->phone)<br><span class="f-14" style="opacity:.7">{{ $sub->user->formatted_phone ?: $sub->user->phone }}</span>@endif</td>
                            <td>{{ $sub->template->name }}</td>
                            <td>{{ $sub->visits_remaining }} / {{ $sub->visits_total }}</td>
                            <td>
                                @php $ds = $sub->displayStatus(); @endphp<span class="sub-st sub-st--{{ $ds }}">{{ __('subscriptions.st_'.$ds) }}</span>
                            </td>
                            <td>{{ $sub->expires_at ? $sub->expires_at->format('d.m.Y') : '∞' }}</td>
                            <td class="nowrap">
                                <a href="{{ route('subscriptions.usages', $sub) }}" class="btn btn-secondary btn-small">📋</a>
                                <form method="POST" action="{{ route('subscriptions.extend', $sub) }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="days" value="30">
                                    <button class="btn btn-small btn-alert" data-title="{{ __('subscriptions.sub_extend_title') }}" data-confirm-text="{{ __('subscriptions.sub_extend_yes') }}" data-cancel-text="{{ __('subscriptions.cancel') }}">{{ __('subscriptions.sub_extend_30') }}</button>
                                </form>
                                @if(in_array($sub->status, ['active','frozen'], true))
                                <form method="POST" action="{{ route('subscriptions.cancel', $sub) }}" class="d-inline">
                                    @csrf
                                    <button class="btn btn-danger btn-svg icon-stop btn-alert" title="{{ __('subscriptions.sub_cancel') }}" data-title="{{ __('subscriptions.sub_cancel_title') }}" data-confirm-text="{{ __('subscriptions.sub_cancel_yes') }}" data-cancel-text="{{ __('subscriptions.cancel') }}"></button>
                                </form>
                                @endif
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
                <div class="row">
                    <div class="col-md-4">
                        <label>{{ __('subscriptions.issue_label_template') }}</label>
                        <select name="template_id" required>
                            @foreach(\App\Models\SubscriptionTemplate::active()->when(!auth()->user()->isAdmin(), fn($q)=>$q->whereIn('organizer_id', app(\App\Services\EventAccessService::class)->subsOrganizerIds(auth()->user())))->get() as $t)
                            <option value="{{ $t->id }}">{{ $t->name }} {{ __('subscriptions.tpl_visits_short', ['n' => $t->visits_total]) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label>{{ __('subscriptions.issue_label_user_id') }}</label>
                        <div class="ac-box">
                            <div id="issueUserChips" class="mb-1"></div>
                            <input type="text" id="issueUserSearch" placeholder="{{ __('subscriptions.issue_ph_user_search') }}" autocomplete="off">
                            <div id="issueUserDd" class="form-select-dropdown"></div>
                        </div>
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

    <x-slot name="script">
    <script src="/js/user-search-picker.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        if (!window.initUserSearchPicker) return;
        window.initUserSearchPicker({
            inputId: 'issueUserSearch',
            dropdownId: 'issueUserDd',
            chipsId: 'issueUserChips',
            hiddenName: 'user_id',
            multi: false,
        });
        var form = document.getElementById('issueUserSearch')?.closest('form');
        if (form) {
            form.addEventListener('submit', function (e) {
                if (!document.getElementById('issueUserChips').querySelector('input[data-user-hidden]')) {
                    e.preventDefault();
                    swal({ title: 'Ошибка', text: 'Выберите игрока из списка', icon: 'error', button: 'Понятно' });
                }
            });
        }
    });
    </script>
    </x-slot>
</x-voll-layout>
