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
        $level = $dup['level'];
        $label = $dup['label'];
        $icon = $level === 'red' ? '🔴' : '🟡';
    @endphp

    <div class="card dup-card {{ $level }} mb-2">
        <div class="d-flex between fvc mb-1">
            <span class="f-14 b-600">{{ $icon }} {{ $label }}</span>
        </div>

        <div class="row row2">
            {{-- Пользователь 1 --}}
            <div class="col-md-5">
                <div class="f-15 b-600 mb-1">
                    <a href="{{ route('admin.users.show', $u1) }}" class="cd" target="_blank">
                        #{{ $u1->id }} {{ $u1->name }}
                    </a>
                </div>
                <div class="dup-field"><span class="label">Фамилия</span><span>{{ $u1->last_name ?? '—' }}</span></div>
                <div class="dup-field"><span class="label">Имя</span><span>{{ $u1->first_name ?? '—' }}</span></div>
                <div class="dup-field"><span class="label">Отчество</span><span>{{ $u1->patronymic ?? '—' }}</span></div>
                <div class="dup-field"><span class="label">Телефон</span>
                    <span class="{{ $u1->phone && $u1->phone === $u2->phone ? 'dup-match' : '' }}">
                        {{ $u1->phone ?? '—' }}
                    </span>
                </div>
                <div class="dup-field"><span class="label">Дата рожд.</span><span>{{ $u1->birth_date ?? '—' }}</span></div>
                <div class="dup-field"><span class="label">Роль</span><span>{{ $u1->role ?? 'user' }}</span></div>
                <div class="dup-providers mt-1">
                    @if($u1->telegram_id)<span>TG</span>@endif
                    @if($u1->vk_id)<span>VK</span>@endif
                    @if($u1->yandex_id)<span>YA</span>@endif
                </div>
                <div class="f-13 mt-1" style="opacity:.5">
                    Регистраций: {{ \DB::table('event_registrations')->where('user_id',$u1->id)->count() }}
                    · Создан: {{ $u1->created_at->format('d.m.Y') }}
                </div>
            </div>

            {{-- Разделитель --}}
            <div class="col-md-2 d-flex" style="align-items:center;justify-content:center;font-size:2rem;opacity:.3">
                ↔
            </div>

            {{-- Пользователь 2 --}}
            <div class="col-md-5">
                <div class="f-15 b-600 mb-1">
                    <a href="{{ route('admin.users.show', $u2) }}" class="cd" target="_blank">
                        #{{ $u2->id }} {{ $u2->name }}
                    </a>
                </div>
                <div class="dup-field"><span class="label">Фамилия</span>
                    <span class="{{ $u1->last_name && $u1->last_name === $u2->last_name ? 'dup-match' : '' }}">
                        {{ $u2->last_name ?? '—' }}
                    </span>
                </div>
                <div class="dup-field"><span class="label">Имя</span><span>{{ $u2->first_name ?? '—' }}</span></div>
                <div class="dup-field"><span class="label">Отчество</span><span>{{ $u2->patronymic ?? '—' }}</span></div>
                <div class="dup-field"><span class="label">Телефон</span>
                    <span class="{{ $u1->phone && $u1->phone === $u2->phone ? 'dup-match' : '' }}">
                        {{ $u2->phone ?? '—' }}
                    </span>
                </div>
                <div class="dup-field"><span class="label">Дата рожд.</span><span>{{ $u2->birth_date ?? '—' }}</span></div>
                <div class="dup-field"><span class="label">Роль</span><span>{{ $u2->role ?? 'user' }}</span></div>
                <div class="dup-providers mt-1">
                    @if($u2->telegram_id)<span>TG</span>@endif
                    @if($u2->vk_id)<span>VK</span>@endif
                    @if($u2->yandex_id)<span>YA</span>@endif
                </div>
                <div class="f-13 mt-1" style="opacity:.5">
                    Регистраций: {{ \DB::table('event_registrations')->where('user_id',$u2->id)->count() }}
                    · Создан: {{ $u2->created_at->format('d.m.Y') }}
                </div>
            </div>
        </div>

        {{-- Кнопки слияния --}}
        <div class="d-flex gap-1 mt-2 flex-wrap">
            <form method="POST" action="{{ route('admin.users.duplicates.merge') }}"
                  onsubmit="return confirm('Объединить #{{ $u1->id }} ← #{{ $u2->id }}? Это действие необратимо.')">
                @csrf
                <input type="hidden" name="primary_id" value="{{ $u1->id }}">
                <input type="hidden" name="secondary_id" value="{{ $u2->id }}">
                <button class="btn btn-small">
                    ← Основной #{{ $u1->id }} · удалить #{{ $u2->id }}
                </button>
            </form>
            <form method="POST" action="{{ route('admin.users.duplicates.merge') }}"
                  onsubmit="return confirm('Объединить #{{ $u2->id }} ← #{{ $u1->id }}? Это действие необратимо.')">
                @csrf
                <input type="hidden" name="primary_id" value="{{ $u2->id }}">
                <input type="hidden" name="secondary_id" value="{{ $u1->id }}">
                <button class="btn btn-small btn-secondary">
                    → Основной #{{ $u2->id }} · удалить #{{ $u1->id }}
                </button>
            </form>
        </div>
    </div>
    @endforeach
    @endif
</div>

</div>
</x-voll-layout>
