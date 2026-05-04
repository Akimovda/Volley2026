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
.dup-card { border-left: 4px solid #e5e7eb; }
.dup-card.red    { border-left-color: #ef4444; }
.dup-card.yellow { border-left-color: #f59e0b; }

.dup-user {
    border: 1.5px solid #e5e7eb;
    border-radius: 10px;
    padding: 1.2rem 1.4rem;
    height: 100%;
    box-sizing: border-box;
}
.dup-user.is-primary   { border-color: #22c55e; background: #f0fdf4; }
.dup-user.is-secondary { border-color: #fca5a5; background: #fff5f5; }

.dup-badge {
    display: inline-flex; align-items: center; gap: .35rem;
    font-size: 1.2rem; padding: .25rem .75rem;
    border-radius: 2rem; font-weight: 600; margin-bottom: .7rem;
}
.dup-badge.primary   { background: #dcfce7; color: #15803d; }
.dup-badge.secondary { background: #fee2e2; color: #b91c1c; }

.dup-field { display: flex; gap: .6rem; font-size: 1.35rem; margin-bottom: .3rem; align-items: baseline; }
.dup-field .lbl { color: #9ca3af; width: 8rem; flex-shrink: 0; font-size: 1.25rem; }

.dup-providers { display: flex; flex-wrap: wrap; gap: .4rem; margin: .6rem 0; }
.dup-providers .prov {
    display: inline-flex; align-items: center;
    background: rgba(41,103,186,.08); color: #2967BA;
    font-size: 1.15rem; padding: .2rem .65rem;
    border-radius: 2rem; font-weight: 600;
}

.dup-stat { font-size: 1.3rem; color: #6b7280; margin-top: .5rem; line-height: 1.7; }
.dup-stat strong { color: #111; }

.dup-arrow {
    display: flex; align-items: center; justify-content: center;
    font-size: 2.4rem; color: #d1d5db; padding: 0 .5rem;
}

.dup-phone-badge {
    display: inline-flex; align-items: center; gap: .4rem;
    background: #f3f4f6; color: #374151;
    font-size: 1.3rem; padding: .25rem .85rem;
    border-radius: .5rem; font-weight: 500;
}
.dup-count-badge {
    display: inline-flex; align-items: center;
    background: #fef3c7; color: #92400e;
    font-size: 1.2rem; padding: .2rem .7rem;
    border-radius: 2rem; font-weight: 700;
}
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
    <div class="d-flex between fvc mb-3">
        <div class="f-16">
            Найдено групп дублей: <strong>{{ count($duplicates) }}</strong>
        </div>
        <div class="f-13" style="color:#9ca3af;">
            🔴 фамилия + телефон &nbsp;·&nbsp; 🟡 только телефон &nbsp;·&nbsp; 🟡 имя + фамилия
        </div>
    </div>

    @if(empty($duplicates))
        <div class="alert alert-success">✅ Дублей не найдено</div>
    @else

    @foreach($duplicates as $dup)
    @php
        $users       = $dup['users'];
        $stats       = $dup['stats'];
        $rec         = $dup['recommended_primary'];
        $level       = $dup['level'];
        $count       = $users->count();
        $primary     = $users->firstWhere('id', $rec);
        $secondaries = $users->where('id', '!=', $rec)->values();
        $secondaryIds = $secondaries->pluck('id')->toArray();
        $icon        = $level === 'red' ? '🔴' : '🟡';

        $mergeTitle = 'Объединить ' . ($count > 2 ? "все {$count} аккаунта" : 'аккаунты') . '?';
        $mergeText  = "Основной: #{$primary->id} {$primary->name}\nБудут удалены: " .
            $secondaries->map(fn($s) => "#{$s->id} {$s->name}")->implode(', ') .
            "\n\nВсе данные (платежи, записи, кошелёк) будут перенесены. Действие необратимо.";
    @endphp

    <div class="card dup-card {{ $level }} mb-3">

        {{-- Заголовок --}}
        <div class="d-flex fvc gap-1 mb-2 flex-wrap">
            <span class="f-14 b-600">{{ $icon }} {{ $dup['label'] }}</span>
            @if($dup['phone'])<span class="dup-phone-badge">📞 {{ $dup['phone'] }}</span>@endif
            @if($count > 2)
                <span class="dup-count-badge">{{ $count }} аккаунта</span>
            @endif
        </div>

        {{-- Карточки пользователей --}}
        <div class="d-flex gap-2 flex-wrap mb-2" style="align-items:stretch;">

            {{-- Рекомендованный основной --}}
            <div style="flex:1;min-width:220px;">
                <div class="dup-user is-primary">
                    <div class="dup-badge primary">⭐ Основной</div>
                    <div class="f-15 b-600 mb-1">
                        <a href="{{ route('admin.users.show', $primary) }}" target="_blank" class="cd">
                            #{{ $primary->id }} {{ $primary->name ?: '—' }}
                        </a>
                    </div>
                    <div class="dup-field"><span class="lbl">Фамилия</span><span>{{ $primary->last_name ?? '—' }}</span></div>
                    <div class="dup-field"><span class="lbl">Имя</span><span>{{ $primary->first_name ?? '—' }}</span></div>
                    <div class="dup-field"><span class="lbl">Отчество</span><span>{{ $primary->patronymic ?? '—' }}</span></div>
                    <div class="dup-field"><span class="lbl">Email</span><span style="font-size:1.2rem;color:#6b7280;">{{ $primary->email }}</span></div>
                    <div class="dup-field"><span class="lbl">Профиль</span><span>{{ $stats[$primary->id]['profile_complete'] ? '✅ заполнен' : '—' }}</span></div>
                    <div class="dup-providers">
                        @if($primary->telegram_id)<span class="prov">TG</span>@endif
                        @if($primary->vk_id)<span class="prov">VK</span>@endif
                        @if($primary->yandex_id)<span class="prov">YA</span>@endif
                    </div>
                    <div class="dup-stat">
                        Записей: <strong>{{ $stats[$primary->id]['registrations'] }}</strong> ·
                        Платежей: <strong>{{ $stats[$primary->id]['payments'] }}</strong>
                        @if($stats[$primary->id]['wallet_balance'] > 0)
                            · Кошелёк: <strong>{{ number_format($stats[$primary->id]['wallet_balance'] / 100, 0, '.', ' ') }} ₽</strong>
                        @endif
                        @if(($stats[$primary->id]['upcoming'] ?? 0) > 0)
                            <br><span style="color:#0d6efd;">📅 Предстоящих: <strong>{{ $stats[$primary->id]['upcoming'] }}</strong></span>
                        @endif
                        <br><span style="font-size:1.2rem;color:#9ca3af;">с {{ $primary->created_at->format('d.m.Y') }}</span>
                    </div>
                </div>
            </div>

            @if($count === 2)
            <div class="dup-arrow">←</div>
            @endif

            {{-- Вторичные аккаунты --}}
            @foreach($secondaries as $sec)
            <div style="flex:1;min-width:220px;">
                <div class="dup-user is-secondary">
                    <div class="dup-badge secondary">🗑 Будет удалён</div>
                    <div class="f-15 b-600 mb-1">
                        <a href="{{ route('admin.users.show', $sec) }}" target="_blank" class="cd">
                            #{{ $sec->id }} {{ $sec->name ?: '—' }}
                        </a>
                    </div>
                    <div class="dup-field"><span class="lbl">Фамилия</span><span>{{ $sec->last_name ?? '—' }}</span></div>
                    <div class="dup-field"><span class="lbl">Имя</span><span>{{ $sec->first_name ?? '—' }}</span></div>
                    <div class="dup-field"><span class="lbl">Отчество</span><span>{{ $sec->patronymic ?? '—' }}</span></div>
                    <div class="dup-field"><span class="lbl">Email</span><span style="font-size:1.2rem;color:#6b7280;">{{ $sec->email }}</span></div>
                    <div class="dup-field"><span class="lbl">Профиль</span><span>{{ $stats[$sec->id]['profile_complete'] ? '✅ заполнен' : '—' }}</span></div>
                    <div class="dup-providers">
                        @if($sec->telegram_id)<span class="prov">TG</span>@endif
                        @if($sec->vk_id)<span class="prov">VK</span>@endif
                        @if($sec->yandex_id)<span class="prov">YA</span>@endif
                    </div>
                    <div class="dup-stat">
                        Записей: <strong>{{ $stats[$sec->id]['registrations'] }}</strong> ·
                        Платежей: <strong>{{ $stats[$sec->id]['payments'] }}</strong>
                        @if($stats[$sec->id]['wallet_balance'] > 0)
                            · Кошелёк: <strong>{{ number_format($stats[$sec->id]['wallet_balance'] / 100, 0, '.', ' ') }} ₽</strong>
                        @endif
                        @if(($stats[$sec->id]['upcoming'] ?? 0) > 0)
                            <br><span style="color:#0d6efd;">📅 Предстоящих: <strong>{{ $stats[$sec->id]['upcoming'] }}</strong></span>
                        @endif
                        @php $conflicts = $dup['conflicts'][$sec->id] ?? 0; @endphp
                        @if($conflicts > 0)
                            <br><span style="color:#dc2626;">⚠️ Конфликт с осн.: <strong>{{ $conflicts }}</strong> зап. будут отменены</span>
                        @endif
                        <br><span style="font-size:1.2rem;color:#9ca3af;">с {{ $sec->created_at->format('d.m.Y') }}</span>
                    </div>
                </div>
            </div>
            @endforeach

        </div>

        {{-- Кнопки --}}
        <div class="d-flex gap-1 flex-wrap mt-1">

            {{-- Рекомендованное слияние --}}
            <form method="POST" action="{{ route('admin.users.duplicates.merge') }}">
                @csrf
                <input type="hidden" name="primary_id" value="{{ $primary->id }}">
                @foreach($secondaryIds as $sid)
                    <input type="hidden" name="secondary_ids[]" value="{{ $sid }}">
                @endforeach
                <button type="submit"
                    class="btn btn-small btn-alert"
                    style="background:#22c55e;color:#fff;border-color:#22c55e;"
                    data-title="{{ $mergeTitle }}"
                    data-text="{{ $mergeText }}"
                    data-icon="warning"
                    data-confirm-text="Объединить"
                    data-cancel-text="Отмена">
                    ⭐ Объединить{{ $count > 2 ? " все {$count}" : '' }} → #{{ $primary->id }}
                </button>
            </form>

            {{-- Альтернативный основной --}}
            @foreach($secondaries as $altPrimary)
            @php
                $altSecIds = $users->where('id', '!=', $altPrimary->id)->pluck('id')->toArray();
                $altTitle  = "Сделать основным #{$altPrimary->id}?";
                $altText   = "Основной: #{$altPrimary->id} {$altPrimary->name}\nБудут удалены: " .
                    $users->where('id', '!=', $altPrimary->id)->map(fn($u) => "#{$u->id} {$u->name}")->implode(', ') .
                    "\n\nДействие необратимо.";
            @endphp
            <form method="POST" action="{{ route('admin.users.duplicates.merge') }}">
                @csrf
                <input type="hidden" name="primary_id" value="{{ $altPrimary->id }}">
                @foreach($altSecIds as $sid)
                    <input type="hidden" name="secondary_ids[]" value="{{ $sid }}">
                @endforeach
                <button type="submit"
                    class="btn btn-small btn-secondary btn-alert"
                    data-title="{{ $altTitle }}"
                    data-text="{{ $altText }}"
                    data-icon="warning"
                    data-confirm-text="Выбрать"
                    data-cancel-text="Отмена">
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
