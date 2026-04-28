<x-voll-layout body_class="admin-duplicates-page">
<x-slot name="title">Дубли игроков</x-slot>
<x-slot name="h1">👥 Дубли игроков</x-slot>

<x-slot name="breadcrumbs">
    <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
        <a href="{{ route('admin.dashboard') }}" itemprop="item"><span itemprop="name">Админ</span></a>
        <meta itemprop="position" content="2">
    </li>
    <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
        <a href="{{ route('admin.users.index') }}" itemprop="item"><span itemprop="name">Пользователи</span></a>
        <meta itemprop="position" content="3">
    </li>
    <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
        <span itemprop="name">Дубли</span>
        <meta itemprop="position" content="4">
    </li>
</x-slot>

<x-slot name="style">
<style>
.dup-card { border-left: 4px solid #eee; }
.dup-card.red    { border-left-color: #e74c3c; }
.dup-card.yellow { border-left-color: #f39c12; }
.dup-field { display: flex; gap: 1rem; font-size: 1.4rem; margin-bottom: .4rem; }
.dup-field .label { opacity: .5; width: 12rem; flex-shrink: 0; }
.dup-match { color: #e74c3c; font-weight: 600; }
.dup-providers span { display: inline-block; background: rgba(41,103,186,.1); color: #2967BA; font-size: 1.2rem; padding: .2rem .7rem; border-radius: 2rem; margin-right: .3rem; }
.dup-recommended { background: #f0fdf4; border: 1.5px solid #22c55e; border-radius: 8px; padding: .5rem .8rem; display: inline-flex; align-items: center; gap: .4rem; font-size: 1.3rem; color: #16a34a; font-weight: 600; }
.dup-stat { font-size: 1.3rem; color: #555; }
.dup-stat strong { color: #111; }
</style>
</x-slot>

<div class="container">

@if(session('status'))
<div class="alert alert-success mb-2">{{ session('status') }}</div>
@endif
@if(session('error'))
<div class="alert alert-danger mb-2">{{ session('error') }}</div>
@endif

<div class="ramka">
    <div class="d-flex between fvc mb-2">
        <div>
            <span class="f-16">Найдено дублей: <strong>{{ count($duplicates) }}</strong></span>
        </div>
        <div class="d-flex gap-1">
            <span class="f-13" style="opacity:.5">
                🔴 Фамилия+телефон &nbsp; 🟡 Только телефон
            </span>
        </div>
    </div>

    @if(empty($duplicates))
    <div class="alert alert-success">✅ Дублей не найдено</div>
    @else

    @foreach($duplicates as $dup)
    @php
        $u1 = $dup['user1'];
        $u2 = $dup['user2'];
        $s1 = $dup['stats1'];
        $s2 = $dup['stats2'];
        $rec = $dup['recommended_primary'];
        $level = $dup['level'];
        $label = $dup['label'];
        $icon = $level === 'red' ? '🔴' : '🟡';
        // primary — тот кого рекомендуем, secondary — кто сливается
        $primary   = $rec === $u1->id ? $u1 : $u2;
        $secondary = $rec === $u1->id ? $u2 : $u1;
        $sp        = $rec === $u1->id ? $s1 : $s2;
        $ss        = $rec === $u1->id ? $s2 : $s1;
    @endphp

    <div class="card dup-card {{ $level }} mb-2">
        <div class="d-flex between fvc mb-2">
            <span class="f-14 b-600">{{ $icon }} {{ $label }}</span>
        </div>

        <div class="row row2">
            {{-- Рекомендованный основной --}}
            <div class="col-md-5">
                <div class="dup-recommended mb-1">⭐ Рекомендуем основным</div>
                <div class="f-15 b-600 mb-1 mt-1">
                    <a href="{{ route('admin.users.show', $primary) }}" class="cd" target="_blank">
                        #{{ $primary->id }} {{ $primary->name }}
                    </a>
                </div>
                <div class="dup-field"><span class="label">Email</span><span class="f-13" style="opacity:.6">{{ $primary->email }}</span></div>
                <div class="dup-field"><span class="label">Телефон</span>
                    <span class="{{ $u1->phone && $u1->phone === $u2->phone ? 'dup-match' : '' }}">
                        {{ $primary->phone ?? '—' }}
                    </span>
                </div>
                <div class="dup-field"><span class="label">Профиль</span>
                    <span>{{ $sp['profile_complete'] ? '✅ заполнен' : '— не заполнен' }}</span>
                </div>
                <div class="dup-providers mt-1 mb-1">
                    @if($primary->telegram_id)<span>TG</span>@endif
                    @if($primary->vk_id)<span>VK</span>@endif
                    @if($primary->yandex_id)<span>YA</span>@endif
                </div>
                <div class="dup-stat">
                    Записей: <strong>{{ $sp['registrations'] }}</strong> ·
                    Платежей: <strong>{{ $sp['payments'] }}</strong>
                    @if($sp['wallet_balance'] > 0)
                        · Кошелёк: <strong>{{ number_format($sp['wallet_balance'] / 100, 0, '.', ' ') }} ₽</strong>
                    @endif
                </div>
                <div class="f-13 mt-05" style="opacity:.5">Создан: {{ $primary->created_at->format('d.m.Y') }}</div>
            </div>

            {{-- Разделитель --}}
            <div class="col-md-2 d-flex" style="align-items:center;justify-content:center;font-size:2rem;opacity:.3">
                ←
            </div>

            {{-- Вторичный (будет удалён) --}}
            <div class="col-md-5">
                <div class="f-13 mb-1" style="color:#e74c3c;">будет деактивирован</div>
                <div class="f-15 b-600 mb-1">
                    <a href="{{ route('admin.users.show', $secondary) }}" class="cd" target="_blank">
                        #{{ $secondary->id }} {{ $secondary->name }}
                    </a>
                </div>
                <div class="dup-field"><span class="label">Email</span><span class="f-13" style="opacity:.6">{{ $secondary->email }}</span></div>
                <div class="dup-field"><span class="label">Телефон</span><span>{{ $secondary->phone ?? '—' }}</span></div>
                <div class="dup-field"><span class="label">Профиль</span>
                    <span>{{ $ss['profile_complete'] ? '✅ заполнен' : '— не заполнен' }}</span>
                </div>
                <div class="dup-providers mt-1 mb-1">
                    @if($secondary->telegram_id)<span>TG</span>@endif
                    @if($secondary->vk_id)<span>VK</span>@endif
                    @if($secondary->yandex_id)<span>YA</span>@endif
                </div>
                <div class="dup-stat">
                    Записей: <strong>{{ $ss['registrations'] }}</strong> ·
                    Платежей: <strong>{{ $ss['payments'] }}</strong>
                    @if($ss['wallet_balance'] > 0)
                        · Кошелёк: <strong>{{ number_format($ss['wallet_balance'] / 100, 0, '.', ' ') }} ₽</strong>
                    @endif
                </div>
                <div class="f-13 mt-05" style="opacity:.5">Создан: {{ $secondary->created_at->format('d.m.Y') }}</div>
            </div>
        </div>

        {{-- Кнопки --}}
        <div class="d-flex gap-1 mt-2 flex-wrap">
            {{-- Рекомендованное слияние --}}
            <form method="POST" action="{{ route('admin.users.duplicates.merge') }}"
                  onsubmit="return confirm('Объединить: основной #{{ $primary->id }}, удалить #{{ $secondary->id }}?\n\nВсе данные (платежи, записи, кошелёк) будут перенесены. Действие необратимо.')">
                @csrf
                <input type="hidden" name="primary_id" value="{{ $primary->id }}">
                <input type="hidden" name="secondary_id" value="{{ $secondary->id }}">
                <button class="btn btn-small" style="background:#22c55e;color:#fff;border-color:#22c55e;">
                    ⭐ Объединить (рекомендовано)
                </button>
            </form>

            {{-- Обратное слияние --}}
            <form method="POST" action="{{ route('admin.users.duplicates.merge') }}"
                  onsubmit="return confirm('Объединить наоборот: основной #{{ $secondary->id }}, удалить #{{ $primary->id }}?\n\nДействие необратимо.')">
                @csrf
                <input type="hidden" name="primary_id" value="{{ $secondary->id }}">
                <input type="hidden" name="secondary_id" value="{{ $primary->id }}">
                <button class="btn btn-small btn-secondary">
                    Наоборот: основной #{{ $secondary->id }}
                </button>
            </form>
        </div>
    </div>
    @endforeach
    @endif
</div>

</div>
</x-voll-layout>
