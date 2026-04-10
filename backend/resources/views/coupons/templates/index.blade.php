<x-voll-layout body_class="coupon-templates-page">
    <x-slot name="title">Шаблоны купонов</x-slot>
    <x-slot name="h1">Шаблоны купонов</x-slot>
    <x-slot name="d_description">
        <div class="d-flex gap-2 mt-2">
            <a href="{{ route('coupon_templates.create') }}" class="btn">+ Создать шаблон</a>
        </div>
    </x-slot>
    <div class="container">
        @if(session('status'))
            <div class="ramka"><div class="alert alert-success">{{ session('status') }}</div></div>
        @endif
        <div class="ramka">
            @if($templates->isEmpty())
                <div class="alert alert-info">Шаблонов купонов пока нет.</div>
            @else
            <div class="table-scrollable">
                <table class="table f-16">
                    <thead>
                        <tr><th>Название</th><th>Скидка</th><th>Выдано</th><th>Лимит</th><th>Срок</th><th>Статус</th><th></th></tr>
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
    <h2 class="-mt-05">📤 Массовая выдача купонов</h2>
    <div class="row row2">
        <div class="col-md-6">
            <div class="card">
                <h3 class="-mt-05">👥 Выдать конкретным игрокам</h3>
                <form method="POST" action="{{ route('coupon_templates.bulk_issue', $templates->first()->id) }}" id="bulkIssueForm">
                    @csrf
                    <label>Шаблон купона</label>
                    <select name="_template_id" id="bulkTemplateSelect" onchange="updateBulkAction(this)">
                        @foreach($templates as $t)
                        <option value="{{ $t->id }}" data-url="{{ route('coupon_templates.bulk_issue', $t->id) }}">
                            {{ $t->name }} ({{ $t->discount_pct }}%)
                        </option>
                        @endforeach
                    </select>
                    <label class="mt-1">ID пользователей (через запятую)</label>
                    <textarea name="user_ids" rows="3" placeholder="1, 2, 3, 42, 100"></textarea>
                    <label class="mt-1">Канал</label>
                    <select name="channel">
                        <option value="manual">Вручную</option>
                        <option value="inapp">В приложении</option>
                        <option value="telegram">Telegram</option>
                        <option value="vk">VK</option>
                        <option value="max">MAX</option>
                    </select>
                    <button type="submit" class="btn mt-2 w-100">📤 Выдать купоны</button>
                </form>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <h3 class="-mt-05">🔗 Создать купоны-ссылки</h3>
                <form method="POST" action="{{ route('coupon_templates.issue_link', $templates->first()->id) }}" id="issueLinkForm">
                    @csrf
                    <label>Шаблон купона</label>
                    <select name="_template_id" id="linkTemplateSelect" onchange="updateLinkAction(this)">
                        @foreach($templates as $t)
                        <option value="{{ $t->id }}" data-url="{{ route('coupon_templates.issue_link', $t->id) }}">
                            {{ $t->name }} ({{ $t->discount_pct }}%)
                        </option>
                        @endforeach
                    </select>
                    <label class="mt-1">Количество ссылок</label>
                    <input type="number" name="count" value="10" min="1" max="1000">
                    <label class="mt-1">Канал рассылки</label>
                    <select name="channel">
                        <option value="telegram">Telegram</option>
                        <option value="vk">VK</option>
                        <option value="max">MAX</option>
                        <option value="inapp">В приложении</option>
                        <option value="manual">Вручную</option>
                    </select>
                    <button type="submit" class="btn mt-2 w-100">🔗 Создать ссылки</button>
                </form>
                @if(session('coupon_links'))
                <div class="mt-2">
                    <div class="b-600 mb-1">Созданные ссылки:</div>
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
