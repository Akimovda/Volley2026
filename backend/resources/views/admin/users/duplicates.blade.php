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
.dup-user { border: 1.5px solid #e5e7eb; border-radius: 8px; padding: 1rem; position: relative; }
.dup-user.is-primary { border-color: #22c55e; background: #f0fdf4; }
.dup-user.is-secondary { border-color: #fca5a5; background: #fff5f5; }
.dup-badge { display: inline-flex; align-items: center; gap: .3rem; font-size: 1.2rem; padding: .2rem .6rem; border-radius: 2rem; font-weight: 600; }
.dup-badge.primary   { background: #dcfce7; color: #16a34a; }
.dup-badge.secondary { background: #fee2e2; color: #dc2626; }
.dup-field { display: flex; gap: .8rem; font-size: 1.35rem; margin-bottom: .3rem; }
.dup-field .label { opacity: .5; width: 10rem; flex-shrink: 0; }
.dup-providers span { display: inline-block; background: rgba(41,103,186,.1); color: #2967BA; font-size: 1.2rem; padding: .15rem .6rem; border-radius: 2rem; margin-right: .3rem; }
.dup-stat { font-size: 1.3rem; color: #555; margin-top: .4rem; }
.dup-stat strong { color: #111; }
.dup-phone { font-size: 1.3rem; background: #f3f4f6; padding: .2rem .7rem; border-radius: .4rem; }
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
        <span class="f-16">Найдено групп дублей: <strong>{{ count($duplicates) }}</strong></span>
        <span class="f-13" style="opacity:.5">🔴 Фамилия+телефон &nbsp; 🟡 Только телефон</span>
    </div>

    @if(empty($duplicates))
    <div class="alert alert-success">✅ Дублей не найдено</div>
    @else

    @foreach($duplicates as $dup)
    @php
        $users  = $dup['users'];
        $stats  = $dup['stats'];
        $rec    = $dup['recommended_primary'];
        $level  = $dup['level'];
        $label  = $dup['label'];
        $icon   = $level === 'red' ? '🔴' : '🟡';
        $count  = $users->count();
        // primary — рекомендованный, secondaries — все остальные
        $primary    = $users->firstWhere('id', $rec);
        $secondaries = $users->where('id', '!=', $rec)->values();
        $secondaryIds = $secondaries->pluck('id')->toArray();
    @endphp

    <div class="card dup-card {{ $level }} mb-3">
        {{-- Заголовок группы --}}
        <div class="d-flex between fvc mb-2">
            <div class="d-flex fvc gap-1">
                <span class="f-14 b-600">{{ $icon }} {{ $label }}</span>
                <span class="dup-phone">{{ $dup['phone'] }}</span>
                @if($count > 2)
                <span class="f-13" style="background:#fef3c7;color:#92400e;padding:.2rem .6rem;border-radius:2rem;font-weight:600;">
                    {{ $count }} аккаунта
                </span>
                @endif
            </div>
        </div>

        {{-- Карточки пользователей --}}
        <div class="row row{{ min($count, 3) }}">

            {{-- Рекомендованный основной --}}
            <div class="col-md-{{ $count === 2 ? 5 : 4 }}">
                <div class="dup-user is-primary">
                    <div class="dup-badge primary mb-1">⭐ Основной</div>
                    <div class="f-15 b-600 mb-1">
                        <a href="{{ route('admin.users.show', $primary) }}" class="cd" target="_blank">
                            #{{ $primary->id }} {{ $primary->name ?: '—' }}
                        </a>
                    </div>
                    <div class="dup-field"><span class="label">Фамилия</span><span>{{ $primary->last_name ?? '—' }}</span></div>
                    <div class="dup-field"><span class="label">Имя</span><span>{{ $primary->first_name ?? '—' }}</span></div>
                    <div class="dup-field"><span class="label">Отчество</span><span>{{ $primary->patronymic ?? '—' }}</span></div>
                    <div class="dup-field"><span class="label">Email</span><span style="opacity:.6;font-size:1.2rem">{{ $primary->email }}</span></div>
                    <div class="dup-field"><span class="label">Профиль</span><span>{{ $stats[$primary->id]['profile_complete'] ? '✅' : '—' }}</span></div>
                    <div class="dup-providers mt-05 mb-05">
                        @if($primary->telegram_id)<span>TG</span>@endif
                        @if($primary->vk_id)<span>VK</span>@endif
                        @if($primary->yandex_id)<span>YA</span>@endif
                    </div>
                    <div class="dup-stat">
                        Записей: <strong>{{ $stats[$primary->id]['registrations'] }}</strong> ·
                        Платежей: <strong>{{ $stats[$primary->id]['payments'] }}</strong>
                        @if($stats[$primary->id]['wallet_balance'] > 0)
                        · Кошелёк: <strong>{{ number_format($stats[$primary->id]['wallet_balance'] / 100, 0, '.', ' ') }} ₽</strong>
                        @endif
                    </div>
                    <div class="f-12 mt-05" style="opacity:.4">с {{ $primary->created_at->format('d.m.Y') }}</div>
                </div>
            </div>

            @if($count === 2)
            <div class="col-md-2 d-flex" style="align-items:center;justify-content:center;font-size:2rem;opacity:.25">←</div>
            @endif

            {{-- Вторичные аккаунты --}}
            @foreach($secondaries as $sec)
            <div class="col-md-{{ $count === 2 ? 5 : 4 }}">
                <div class="dup-user is-secondary">
                    <div class="dup-badge secondary mb-1">🗑 Будет удалён</div>
                    <div class="f-15 b-600 mb-1">
                        <a href="{{ route('admin.users.show', $sec) }}" class="cd" target="_blank">
                            #{{ $sec->id }} {{ $sec->name ?: '—' }}
                        </a>
                    </div>
                    <div class="dup-field"><span class="label">Фамилия</span><span>{{ $sec->last_name ?? '—' }}</span></div>
                    <div class="dup-field"><span class="label">Имя</span><span>{{ $sec->first_name ?? '—' }}</span></div>
                    <div class="dup-field"><span class="label">Отчество</span><span>{{ $sec->patronymic ?? '—' }}</span></div>
                    <div class="dup-field"><span class="label">Email</span><span style="opacity:.6;font-size:1.2rem">{{ $sec->email }}</span></div>
                    <div class="dup-field"><span class="label">Профиль</span><span>{{ $stats[$sec->id]['profile_complete'] ? '✅' : '—' }}</span></div>
                    <div class="dup-providers mt-05 mb-05">
                        @if($sec->telegram_id)<span>TG</span>@endif
                        @if($sec->vk_id)<span>VK</span>@endif
                        @if($sec->yandex_id)<span>YA</span>@endif
                    </div>
                    <div class="dup-stat">
                        Записей: <strong>{{ $stats[$sec->id]['registrations'] }}</strong> ·
                        Платежей: <strong>{{ $stats[$sec->id]['payments'] }}</strong>
                        @if($stats[$sec->id]['wallet_balance'] > 0)
                        · Кошелёк: <strong>{{ number_format($stats[$sec->id]['wallet_balance'] / 100, 0, '.', ' ') }} ₽</strong>
                        @endif
                    </div>
                    <div class="f-12 mt-05" style="opacity:.4">с {{ $sec->created_at->format('d.m.Y') }}</div>
                </div>
            </div>
            @endforeach

        </div>

        {{-- Кнопки --}}
        <div class="d-flex gap-1 mt-2 flex-wrap">
            {{-- Рекомендованное слияние (все вторичные → в основной) --}}
            <form method="POST" action="{{ route('admin.users.duplicates.merge') }}"
                  onsubmit="return confirm('Объединить все {{ $count }} аккаунта?\n\nОсновной: #{{ $primary->id }} {{ $primary->name }}\nБудут удалены: {{ $secondaries->map(fn($s)=>"#".$s->id." ".$s->name)->implode(", ") }}\n\nВсе данные (платежи, записи, кошелёк) перенесутся. Действие необратимо.')">
                @csrf
                <input type="hidden" name="primary_id" value="{{ $primary->id }}">
                @foreach($secondaryIds as $sid)
                <input type="hidden" name="secondary_ids[]" value="{{ $sid }}">
                @endforeach
                <button class="btn btn-small" style="background:#22c55e;color:#fff;border-color:#22c55e;">
                    ⭐ Объединить {{ $count > 2 ? "все {$count}" : '' }} → основной #{{ $primary->id }}
                </button>
            </form>

            {{-- Выбор другого основного --}}
            @foreach($secondaries as $altPrimary)
            @php
                $altSecondaryIds = $users->where('id', '!=', $altPrimary->id)->pluck('id')->toArray();
            @endphp
            <form method="POST" action="{{ route('admin.users.duplicates.merge') }}"
                  onsubmit="return confirm('Сделать основным #{{ $altPrimary->id }}?\n\nДействие необратимо.')">
                @csrf
                <input type="hidden" name="primary_id" value="{{ $altPrimary->id }}">
                @foreach($altSecondaryIds as $sid)
                <input type="hidden" name="secondary_ids[]" value="{{ $sid }}">
                @endforeach
                <button class="btn btn-small btn-secondary">
                    Основной #{{ $altPrimary->id }}
                </button>
            </form>
            @endforeach
        </div>
    </div>
    @endforeach

    @endif
</div>

</div>
</x-voll-layout>
