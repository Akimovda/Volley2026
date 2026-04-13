<x-voll-layout body_class="subscription-templates-page">
    <x-slot name="title">Шаблоны абонементов</x-slot>
    <x-slot name="h1">Шаблоны абонементов</x-slot>

    <x-slot name="d_description">
        <div class="d-flex gap-2 mt-2">
            <a href="{{ route('subscription_templates.create') }}" class="btn">+ Создать шаблон</a>
        </div>
    </x-slot>

    <div class="container">
    <div class="row row2">
        <div class="col-lg-4 col-xl-3 order-2 d-none d-lg-block">
            <div class="sticky">
                <div class="card-ramka">
                    @include('profile._menu', [
                        'menuUser'   => auth()->user(),
                        'activeMenu' => 'sub_templates',
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
                <div class="alert alert-info">Шаблонов абонементов пока нет.</div>
            @else
            <div class="table-scrollable">
                <table class="table f-16">
                    <thead>
                        <tr>
                            <th>Название</th>
                            <th>Посещений</th>
                            <th>Цена</th>
                            <th>Продаж</th>
                            <th>Срок</th>
                            <th>Статус</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($templates as $t)
                        <tr>
                            <td>
                                <div class="b-600">{{ $t->name }}</div>
                                @if($t->description)
                                    <div class="f-13" style="opacity:.6">{{ Str::limit($t->description, 60) }}</div>
                                @endif
                            </td>
                            <td>{{ $t->visits_total }}</td>
                            <td>{{ $t->price_minor > 0 ? number_format($t->price_minor/100, 0, '.', ' ').' ₽' : 'Бесплатно' }}</td>
                            <td>
                                {{ $t->sold_count }}
                                @if($t->sale_limit) / {{ $t->sale_limit }} @endif
                            </td>
                            <td class="f-14">
                                @php
                                    $dm = (int)($t->duration_months ?? 0);
                                    $dd = (int)($t->duration_days ?? 0);
                                @endphp
                                @if($dm > 0 || $dd > 0)
                                    @if($dm > 0){{ $dm }} мес. @endif
                                    @if($dd > 0){{ $dd }} дн. @endif
                                @else
                                    ♾ Бессрочно
                                @endif
                            </td>
                            <td>
                                @if($t->is_active)
                                    <span class="cs b-600">✅ Активен</span>
                                @else
                                    <span style="opacity:.5">❌ Неактивен</span>
                                @endif
                            </td>
                            <td class="nowrap">
                                <a href="{{ route('subscription_templates.edit', $t) }}" class="btn btn-secondary btn-small">✏️</a>
                                {{-- Деактивировать --}}
                                <form method="POST" action="{{ route('subscription_templates.destroy', $t) }}" class="d-inline">
                                    @csrf @method('DELETE')
                                    <button type="submit"
                                        class="btn-alert btn btn-secondary btn-small"
                                        data-title="Деактивировать шаблон?"
                                        data-text="{{ $t->name }}"
                                        data-confirm-text="Да"
                                        data-cancel-text="Отмена"
                                        title="Деактивировать">🚫</button>
                                </form>
                                {{-- Удалить --}}
                                <form method="POST" action="{{ route('subscription_templates.force_delete', $t) }}" class="d-inline force-delete-form" id="force-delete-{{ $t->id }}">
                                    @csrf @method('DELETE')
                                    <input type="hidden" name="force_code" class="force-code-input" value="">
                                    <button type="button"
                                        class="btn btn-danger btn-small js-force-delete"
                                        data-form="force-delete-{{ $t->id }}"
                                        data-name="{{ $t->name }}"
                                        title="Удалить навсегда">✖️</button>
                                </form>
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
    <x-slot name="script">
    <script src="/assets/fas.js"></script>
    <script>
    document.querySelectorAll('.js-force-delete').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var formId = btn.dataset.form;
            var code = window.prompt('Удалить шаблон "' + btn.dataset.name + '"?\nВведите код подтверждения:');
            if (code !== null && code !== '') {
                var form = document.getElementById(formId);
                form.querySelector('.force-code-input').value = code;
                form.submit();
            }
        });
    });
    </script>
    </x-slot>

</x-voll-layout>
