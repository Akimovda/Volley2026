<x-voll-layout body_class="tournament-invite-page">
<x-slot name="title">Приглашение в команду</x-slot>
<x-slot name="h1">Приглашение в команду</x-slot>

<x-slot name="breadcrumbs">
    @if(isset($event))
    <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
        <a href="{{ route('events.show', $event) }}" itemprop="item"><span itemprop="name">{{ $event->title ?? 'Турнир' }}</span></a>
        <meta itemprop="position" content="2">
    </li>
    @endif
    <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
        <span itemprop="name">Приглашение</span>
        <meta itemprop="position" content="3">
    </li>
</x-slot>

@php
$team     = $invite->team ?? $team ?? null;
$event    = $invite->event ?? $event ?? ($team?->event);
$captain  = $team?->captain;
$settings = $event?->tournamentSetting;
$members  = $team?->members ?? collect();
$confirmedMembers = $members->where('confirmation_status','confirmed');
$pendingMembers   = $members->whereIn('confirmation_status',['invited','joined']);
$posLabels  = ['setter'=>'Связующий','outside'=>'Доигровщик','opposite'=>'Диагональный','middle'=>'Центральный','libero'=>'Либеро'];
$roleLabels = ['captain'=>'Капитан','player'=>'Основной игрок','reserve'=>'Запасной'];
$stLabels   = ['confirmed'=>'Подтверждён','joined'=>'Ожидает подтверждения','invited'=>'Приглашён','declined'=>'Отклонён'];
$stColors   = ['confirmed'=>'#4caf50','joined'=>'#ff9800','invited'=>'#2967BA','declined'=>'#f44336'];
$inviteRole     = $invite->team_role ?? null;
$invitePosition = $invite->position_code ?? null;
$canRespond     = in_array((string)($invite->status ?? 'pending'), ['pending'], true);
$st = (string)($invite->status ?? 'pending');
$stAllColor  = ['accepted'=>'#166534','declined'=>'#9f1239','revoked'=>'#6b7280','expired'=>'#6b7280','pending'=>'#92400e','cancelled'=>'#6b7280'];
$stAllBg     = ['accepted'=>'#f0fdf4','declined'=>'#fff1f2','revoked'=>'#f3f4f6','expired'=>'#f3f4f6','pending'=>'#fff7e6','cancelled'=>'#f3f4f6'];
$stAllIcon   = ['accepted'=>'✅','declined'=>'❌','revoked'=>'↩️','expired'=>'⌛','pending'=>'⏳','cancelled'=>'🚫'];
$stAllLabels = ['accepted'=>'Принято','declined'=>'Отклонено','cancelled'=>'Отменено','revoked'=>'Отозвано','expired'=>'Истекло','pending'=>'Ожидает ответа'];
$locationLine = collect([$event?->location?->city?->name,$event?->location?->name,$event?->location?->address])->filter()->implode(', ');
@endphp

<div class="container">

@if(session('success'))<div class="alert alert-success">✅ {{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">❌ {{ session('error') }}</div>@endif
@if($errors->any())
<div class="alert alert-danger">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
@endif

<div class="row row2">
<div class="col-lg-8">

    {{-- Инфо о приглашении --}}
    <div class="ramka">
        <div class="d-flex between fvc mb-1" style="flex-wrap:wrap;gap:.5rem">
            <h2 class="-mt-05">{{ $team?->name ?? 'Команда' }}</h2>
            <span style="display:inline-flex;align-items:center;gap:.3rem;padding:3px 12px;border-radius:12px;font-size:13px;font-weight:600;background:{{ $stAllBg[$st] ?? '#f3f4f6' }};color:{{ $stAllColor[$st] ?? '#6b7280' }}">
                {{ $stAllIcon[$st] ?? '' }} {{ $stAllLabels[$st] ?? $st }}
            </span>
        </div>

        @if($event)
        <div class="f-15 mb-05">🏆 <strong>{{ $event->title }}</strong></div>
        @endif
        @if($locationLine)
        <div class="f-14 mb-05" style="opacity:.6">📍 {{ $locationLine }}</div>
        @endif
        @if(!empty($event?->starts_at))
        <div class="f-14 mb-2" style="opacity:.6">
            📅 {{ \Carbon\Carbon::parse($event->starts_at)->timezone($event->timezone ?? config('app.timezone'))->format('d.m.Y H:i') }}
        </div>
        @endif

        <div class="card">
            <div class="b-600 f-15 mb-05">Вас приглашают на роль:</div>
            <div class="f-16">
                @if($inviteRole) <span class="b-600">{{ $roleLabels[$inviteRole] ?? $inviteRole }}</span> @endif
                @if($invitePosition) · {{ $posLabels[$invitePosition] ?? $invitePosition }} @endif
            </div>
            <div class="f-13 mt-1" style="opacity:.6">После принятия вас добавят в состав команды.</div>
        </div>
    </div>

    {{-- Кто приглашает --}}
    <div class="ramka">
        <h2 class="-mt-05">👤 Кто приглашает</h2>
        <div class="card d-flex fvc gap-2">
            <div style="width:4.8rem;height:4.8rem;border-radius:50%;background:rgba(41,103,186,.12);display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:700;color:#2967BA;flex-shrink:0">
                {{ mb_strtoupper(mb_substr($captain->name ?? $captain->email ?? 'C', 0, 1)) }}
            </div>
            <div>
                <div class="b-600 f-16">{{ $captain->name ?? $captain->email ?? ('#'.($team?->captain_user_id ?? '—')) }}</div>
                <div class="f-13" style="opacity:.6">Капитан команды</div>
            </div>
        </div>
    </div>

    {{-- Состав --}}
    <div class="ramka">
        <h2 class="-mt-05">👥 Текущий состав ({{ $members->count() }})</h2>
        @forelse($members as $member)
        <div class="card d-flex between fvc mb-1" style="flex-wrap:wrap;gap:.5rem;padding:1rem 1.5rem">
            <div>
                <div class="b-600">{{ $member->user->name ?? $member->user->email ?? ('#'.$member->user_id) }}</div>
                <div class="f-13" style="opacity:.6">
                    {{ $roleLabels[$member->team_role] ?? $member->team_role }}
                    @if(!empty($member->position_code)) · {{ $posLabels[$member->position_code] ?? $member->position_code }} @endif
                </div>
            </div>
            <span class="f-13 b-600" style="color:{{ $stColors[$member->confirmation_status] ?? '#999' }}">
                {{ $stLabels[$member->confirmation_status] ?? $member->confirmation_status }}
            </span>
        </div>
        @empty
        <div class="f-15" style="opacity:.5">Состав пока пуст</div>
        @endforelse
    </div>

    {{-- Инфо о турнире --}}
    @if($settings)
    <div class="ramka">
        <h2 class="-mt-05">📋 О турнире</h2>
        <div class="card f-14" style="line-height:2">
            <div>Формат: <strong>{{ match($settings->registration_mode ?? '') {'team_classic'=>'Классическая команда','team_beach'=>'Пляжная команда / пара',default=>'—'} }}</strong></div>
            <div>Схема: <strong>{{ $settings->game_scheme ?? ($settings->getGameScheme() ?? '—') }}</strong></div>
            <div>Мин. игроков: <strong>{{ $settings->team_size_min ?? '—' }}</strong></div>
            <div>Макс. игроков: <strong>{{ $settings->total_players_max ?? $settings->team_size_max ?? '—' }}</strong></div>
            <div>Макс. запасных: <strong>{{ $settings->reserve_players_max ?? '—' }}</strong></div>
            <div>Либеро обязателен: <strong>{{ !empty($settings->require_libero) ? 'Да' : 'Нет' }}</strong></div>
            @if(!is_null($settings->max_rating_sum))
            <div>Лимит суммы рейтинга: <strong>{{ $settings->max_rating_sum }}</strong></div>
            @endif
        </div>
    </div>
    @endif

</div>
<div class="col-lg-4">

    {{-- Ответ --}}
    <div class="ramka">
        <h2 class="-mt-05">✉️ Ваше решение</h2>
        @if($canRespond)
        <div class="f-15 mb-2" style="opacity:.6">Примите приглашение или откажитесь.</div>
        <form method="POST" action="{{ route('tournamentTeamInvites.accept', $invite->token) }}" class="mb-1">
            @csrf
            <button type="submit" class="btn w-100">✅ Принять приглашение</button>
        </form>
        <form method="POST" action="{{ route('tournamentTeamInvites.decline', $invite->token) }}">
            @csrf
            <button type="submit" class="btn btn-secondary w-100">❌ Отклонить</button>
        </form>
        @else
        <div class="card">
            <div class="f-15">Статус:
                <span class="b-600" style="color:{{ $stAllColors[$st] ?? '#999' }}">
                    {{ $stAllLabels[$st] ?? $st }}
                </span>
            </div>
        </div>
        @endif
    </div>

    {{-- Сводка --}}
    <div class="ramka">
        <h2 class="-mt-05">📊 Сводка</h2>
        <div class="card f-15" style="line-height:2">
            <div>Подтверждено: <strong>{{ $confirmedMembers->count() }}</strong></div>
            <div>Ожидают ответа: <strong>{{ $pendingMembers->count() }}</strong></div>
            <div>Всего в составе: <strong>{{ $members->count() }}</strong></div>
        </div>
    </div>

</div>
</div>
</div>
</x-voll-layout>
