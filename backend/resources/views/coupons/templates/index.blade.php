<x-voll-layout body_class="coupon-templates-page">
    <x-slot name="title">{{ __('subscriptions.coupon_tpl_title') }}</x-slot>
    <x-slot name="h1">{{ __('subscriptions.coupon_tpl_title') }}</x-slot>
    <x-slot name="d_description">
        <div class="d-flex gap-2 mt-2">
            <a href="{{ route('coupon_templates.create') }}" class="btn">{{ __('subscriptions.coupon_tpl_btn_create') }}</a>
        </div>
    </x-slot>
    <div class="container">
    <div class="row row2">
        <div class="col-lg-4 col-xl-3 order-2 d-none d-lg-block">
            <div class="sticky">
                <div class="card-ramka">
                    @include('profile._menu', [
                        'menuUser'   => auth()->user(),
                        'activeMenu' => 'coupon_templates',
                    ])
                </div>
            </div>
        </div>
        <div class="col-lg-8 col-xl-9 order-1">
        @if(session('status'))
            <div class="ramka"><div class="alert alert-success">{{ session('status') }}</div></div>
        @endif
        <div class="ramka">
            @if($templates->isEmpty())
                <div class="alert alert-info">{{ __('subscriptions.coupon_tpl_empty') }}</div>
            @else
            <div class="table-scrollable">
                <table class="table f-16">
                    <thead>
                        <tr><th>{{ __('subscriptions.coupon_tpl_col_name') }}</th><th>{{ __('subscriptions.coupon_tpl_col_discount') }}</th><th>{{ __('subscriptions.coupon_tpl_col_issued') }}</th><th>{{ __('subscriptions.coupon_tpl_col_limit') }}</th><th>{{ __('subscriptions.coupon_tpl_col_term') }}</th><th>{{ __('subscriptions.col_status') }}</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach($templates as $t)
                        <tr>
                            <td><div class="b-600">{{ $t->name }}</div></td>
                            <td class="f-20 b-700 cd">{{ $t->discount_pct }}%</td>
                            <td>{{ $t->issued_count }}</td>
                            <td>{{ $t->issue_limit ?? '∞' }}</td>
                            <td class="f-14">{{ $t->valid_until ? $t->valid_until->format('d.m.Y') : '∞' }}</td>
                            <td>@if($t->is_active)<span class="cs">✅</span>@else<span style="opacity:.5">❌</span>@endif</td>
                            <td class="nowrap">
                                <a href="{{ route('coupon_templates.edit', $t) }}" class="btn btn-secondary btn-small">✏️</a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $templates->links() }}
            @endif
        </div>
    </div>
{{-- Массовая выдача --}}
@if($templates->isNotEmpty())
<div class="ramka">
    <h2 class="-mt-05">{{ __('subscriptions.coupon_bulk_section') }}</h2>
    <div class="row row2">
        <div class="col-md-6">
            <div class="card">
                <h3 class="-mt-05">{{ __('subscriptions.coupon_bulk_to_users') }}</h3>
                <form method="POST" action="{{ route('coupon_templates.bulk_issue', $templates->first()->id) }}" id="bulkIssueForm">
                    @csrf
                    <label>{{ __('subscriptions.coupon_bulk_tpl_label') }}</label>
                    <select name="_template_id" id="bulkTemplateSelect" onchange="updateBulkAction(this)">
                        @foreach($templates as $t)
                        <option value="{{ $t->id }}" data-url="{{ route('coupon_templates.bulk_issue', $t->id) }}">
                            {{ $t->name }} ({{ $t->discount_pct }}%)
                        </option>
                        @endforeach
                    </select>
                    <label class="mt-1">{{ __('subscriptions.coupon_bulk_user_ids') }}</label>
                    <textarea name="user_ids" rows="3" placeholder="{{ __('subscriptions.coupon_bulk_user_ids_ph') }}"></textarea>
                    <label class="mt-1">{{ __('subscriptions.coupon_bulk_channel') }}</label>
                    <select name="channel">
                        <option value="manual">{{ __('subscriptions.coupon_channel_manual') }}</option>
                        <option value="inapp">{{ __('subscriptions.coupon_channel_inapp') }}</option>
                        <option value="telegram">Telegram</option>
                        <option value="vk">VK</option>
                        <option value="max">MAX</option>
                    </select>
                    <button type="submit" class="btn mt-2 w-100">{{ __('subscriptions.coupon_bulk_btn') }}</button>
                </form>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <h3 class="-mt-05">{{ __('subscriptions.coupon_links_section') }}</h3>
                <form method="POST" action="{{ route('coupon_templates.issue_link', $templates->first()->id) }}" id="issueLinkForm">
                    @csrf
                    <label>{{ __('subscriptions.coupon_bulk_tpl_label') }}</label>
                    <select name="_template_id" id="linkTemplateSelect" onchange="updateLinkAction(this)">
                        @foreach($templates as $t)
                        <option value="{{ $t->id }}" data-url="{{ route('coupon_templates.issue_link', $t->id) }}">
                            {{ $t->name }} ({{ $t->discount_pct }}%)
                        </option>
                        @endforeach
                    </select>
                    <label class="mt-1">{{ __('subscriptions.coupon_links_count') }}</label>
                    <input type="number" name="count" value="10" min="1" max="1000">
                    <label class="mt-1">{{ __('subscriptions.coupon_links_channel') }}</label>
                    <select name="channel">
                        <option value="telegram">Telegram</option>
                        <option value="vk">VK</option>
                        <option value="max">MAX</option>
                        <option value="inapp">{{ __('subscriptions.coupon_channel_inapp') }}</option>
                        <option value="manual">{{ __('subscriptions.coupon_channel_manual') }}</option>
                    </select>
                    <button type="submit" class="btn mt-2 w-100">{{ __('subscriptions.coupon_links_btn') }}</button>
                </form>
                @if(session('coupon_links'))
                <div class="mt-2">
                    <div class="b-600 mb-1">{{ __('subscriptions.coupon_links_created') }}</div>
                    <textarea class="w-100" rows="6" readonly>{{ collect(session('coupon_links'))->map(fn($l) => $l['url'])->implode("\n") }}</textarea>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endif
    <x-slot name="script">
    <script>
    function updateBulkAction(sel) {
        document.getElementById('bulkIssueForm').action = sel.options[sel.selectedIndex].dataset.url;
    }
    function updateLinkAction(sel) {
        document.getElementById('issueLinkForm').action = sel.options[sel.selectedIndex].dataset.url;
    }
    </script>
    </x-slot>
</x-voll-layout>
