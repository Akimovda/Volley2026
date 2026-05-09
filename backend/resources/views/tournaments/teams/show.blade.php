<x-voll-layout body_class="tournament-team-page">
<x-slot name="title">{{ $team->name }} — команда</x-slot>
<x-slot name="h1">{{ $team->name }}</x-slot>

<x-slot name="breadcrumbs">
    <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
        <a href="{{ route('events.show', $event) }}" itemprop="item"><span itemprop="name">{{ $event->title }}</span></a>
        <meta itemprop="position" content="2">
    </li>
    <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
        <span itemprop="name">Команда</span>
        <meta itemprop="position" content="3">
    </li>
</x-slot>

<x-slot name="d_description">
    <div class="d-flex between fvc mt-1" style="flex-wrap:wrap;gap:1rem">
        <div class="f-15" style="opacity:.6">
            {{ $team->team_kind === 'classic_team' ? '🏐 Классическая команда' : '🏖 Пляжная команда' }}
        </div>
        <a href="{{ route('events.show', $event) }}" class="btn btn-secondary">← К турниру</a>
    </div>
</x-slot>

@php
$settings       = $team->event->tournamentSetting ?? null;
$confirmedCount = $team->members->where('confirmation_status','confirmed')->count();
$pendingCount   = ($team->invites ?? collect())->where('status','pending')->count();
$isCaptain      = (int)$team->captain_user_id === (int)auth()->id();
$isOrganizer    = auth()->check() && ((int)$event->organizer_id === (int)auth()->id() || (auth()->user()->isAdmin()));
$canManage      = $isCaptain || $isOrganizer;
$posLabels = ['setter'=>'Связующий','outside'=>'Доигровщик','opposite'=>'Диагональный','middle'=>'Центральный','libero'=>'Либеро'];
$roleLabels = ['captain'=>'Капитан','player'=>'Основной игрок','reserve'=>'Запасной'];
$stLabels = ['confirmed'=>'Подтверждён','joined'=>'Ожидает подтверждения','invited'=>'Приглашён','declined'=>'Отклонён','requested'=>'Запрос на вступление'];
$stColor  = ['confirmed'=>'#166534','joined'=>'#92400e','invited'=>'#1e40af','declined'=>'#9f1239','requested'=>'#5b21b6'];
$stBg     = ['confirmed'=>'#f0fdf4','joined'=>'#fff7e6','invited'=>'#dbeafe','declined'=>'#fff1f2','requested'=>'#f5f3ff'];
$invStLabels = ['pending'=>'Ожидает','accepted'=>'Принято','declined'=>'Отклонено','revoked'=>'Отозвано','expired'=>'Истекло'];
$invStColor  = ['accepted'=>'#166534','declined'=>'#9f1239','revoked'=>'#6b7280','expired'=>'#6b7280','pending'=>'#92400e'];
$invStBg     = ['accepted'=>'#f0fdf4','declined'=>'#fff1f2','revoked'=>'#f3f4f6','expired'=>'#f3f4f6','pending'=>'#fff7e6'];
$appStLabels = ['pending'=>'На рассмотрении','approved'=>'Принята','rejected'=>'Отклонена','incomplete'=>__('events.tapp_status_incomplete')];
$appStBg     = ['pending'=>'#fff7e6','approved'=>'#f0fdf4','rejected'=>'#fff1f2','incomplete'=>'#fef9c3'];
$appStColor  = ['pending'=>'#92400e','approved'=>'#166534','rejected'=>'#9f1239','incomplete'=>'#854d0e'];
$appStIcon   = ['pending'=>'⏳','approved'=>'✅','rejected'=>'❌','incomplete'=>'🟡'];
@endphp

<div class="container">

@if(session('success'))
<div class="alert alert-success">✅ {{ session('success') }}</div>
@endif
@if(session('error'))
<div class="alert alert-danger">❌ {{ session('error') }}</div>
@endif
@if($errors->any())
<div class="alert alert-danger">
    @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
</div>
@endif

<div class="row row2">
<div class="col-lg-8">

    {{-- Состав --}}
    <div class="ramka">
        <h2 class="-mt-05">👥 Состав команды</h2>
        <div class="f-15 mb-2" style="opacity:.6">
            Капитан: <strong>{{ $team->captain->name ?? ('#'.$team->captain_user_id) }}</strong>
            @if($team->occurrence)
                · {{ \Carbon\Carbon::parse($team->occurrence->starts_at)->format('d.m H:i') }}
            @endif
        </div>

        @php
            $activeMembers = $team->members->where('confirmation_status', '!=', 'requested');
            $joinRequests  = $team->members->where('confirmation_status', 'requested');
        @endphp
        @forelse($activeMembers as $member)
        <div class="card d-flex between fvc mb-1" style="flex-wrap:wrap;gap:1rem">
            <div class="d-flex fvc" style="gap:.8rem">
                <img src="{{ $member->user->profile_photo_url ?? '' }}" alt="" style="width:36px;height:36px;border-radius:50%;object-fit:cover;flex-shrink:0;">
                <div>
                    <div class="b-600 f-16">{{ $member->user->name ?? ('#'.$member->user_id) }}</div>
                    <div class="f-13" style="opacity:.6">
                        {{ $roleLabels[$member->team_role] ?? $member->team_role }}
                        @if($team->team_kind==='classic_team' && $member->position_code)
                            · {{ $posLabels[$member->position_code] ?? $member->position_code }}
                        @endif
                    </div>
                </div>
            </div>
            <div class="d-flex gap-1 fvc">
                <span style="display:inline-block;padding:2px 10px;border-radius:10px;font-size:12px;font-weight:600;background:{{ $stBg[$member->confirmation_status] ?? '#f3f4f6' }};color:{{ $stColor[$member->confirmation_status] ?? '#6b7280' }}">
                    {{ $stLabels[$member->confirmation_status] ?? $member->confirmation_status }}
                </span>
                @if($isCaptain && (int)$member->user_id !== (int)$team->captain_user_id)
                    @if($member->confirmation_status !== 'confirmed')
                    <form method="POST" action="{{ route('tournamentTeams.members.confirm',[$event,$team,$member]) }}">
                        @csrf
                        <button class="btn btn-small">✅</button>
                    </form>
                    @endif
                    @if($member->confirmation_status !== 'declined')
                    <form method="POST" action="{{ route('tournamentTeams.members.decline',[$event,$team,$member]) }}">
                        @csrf
                        <button class="btn btn-small btn-secondary">✗</button>
                    </form>
                    @endif
                    <form method="POST" action="{{ route('tournamentTeams.members.destroy',[$event,$team,$member]) }}"
                          onsubmit="return confirm('Удалить из команды?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-small btn-secondary">🗑</button>
                    </form>
                @endif
            </div>
        </div>
        @empty
        <div class="card text-center" style="padding:2rem;opacity:.5">Состав пока пуст</div>
        @endforelse

        {{-- Вакантный слот (beach_pair) --}}
        @if($team->team_kind === 'beach_pair' && $activeMembers->where('confirmation_status','confirmed')->count() < 2)
        <div class="card d-flex fvc mb-1" style="gap:.8rem;opacity:.55;border:2px dashed var(--border-color,#ddd)">
            <div style="width:36px;height:36px;border-radius:50%;background:var(--bg2,#f5f5f5);flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:1.8rem;color:#aaa;">?</div>
            <span class="f-15" style="font-style:italic">Место партнёра свободно</span>
        </div>
        @endif
    </div>

    {{-- Запросы на вступление --}}
    @if($canManage && $joinRequests->isNotEmpty())
    <div class="ramka">
        <h2 class="-mt-05">🙋 Запросы на вступление ({{ $joinRequests->count() }})</h2>
        @foreach($joinRequests as $member)
        <div class="card d-flex between fvc mb-1" style="flex-wrap:wrap;gap:1rem">
            <div class="d-flex fvc" style="gap:.8rem">
                <img src="{{ $member->user->profile_photo_url ?? '' }}" alt="" style="width:40px;height:40px;border-radius:50%;object-fit:cover;flex-shrink:0;">
                <div>
                    <div class="b-600 f-16">{{ $member->user->name ?? ('#'.$member->user_id) }}</div>
                    <div class="f-13" style="opacity:.6">{{ $member->joined_at?->format('d.m.Y H:i') }}</div>
                </div>
            </div>
            <div class="d-flex gap-1 fvc">
                <form method="POST" action="{{ route('tournamentTeams.members.confirm',[$event,$team,$member]) }}">
                    @csrf
                    <button class="btn btn-small">✅ Принять</button>
                </form>
                <form method="POST" action="{{ route('tournamentTeams.members.decline',[$event,$team,$member]) }}">
                    @csrf
                    <button class="btn btn-small btn-secondary">✗ Отклонить</button>
                </form>
            </div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Созданные приглашения --}}
    @if($canManage)
    <div class="ramka">
        <h2 class="-mt-05">📨 Созданные приглашения</h2>
        @forelse(($team->invites ?? collect())->sortByDesc('id') as $inv)
        @php
            $invMeta = is_array($inv->meta) ? $inv->meta : [];
            $invUrl  = $invMeta['invite_url'] ?? route('tournamentTeamInvites.show',['token'=>$inv->token]);
            $channels = collect($invMeta['sent_channels'] ?? [])->filter()->values();
        @endphp
        <div class="card mb-1">
            <div class="d-flex between fvc mb-05" style="flex-wrap:wrap;gap:.5rem">
                <div>
                    <span class="b-600">{{ $inv->invitedUser->name ?? $inv->invitedUser->email ?? ('#'.$inv->invited_user_id) }}</span>
                    <span class="f-13 ml-1" style="opacity:.6">
                        {{ $roleLabels[$inv->team_role] ?? $inv->team_role }}
                        @if($inv->position_code) · {{ $posLabels[$inv->position_code] ?? $inv->position_code }} @endif
                    </span>
                </div>
                <span style="display:inline-block;padding:2px 10px;border-radius:10px;font-size:12px;font-weight:600;background:{{ $invStBg[$inv->status] ?? '#fff7e6' }};color:{{ $invStColor[$inv->status] ?? '#92400e' }}">
                    {{ $invStLabels[$inv->status] ?? $inv->status }}
                </span>
            </div>
            <div class="f-13 mb-1" style="opacity:.5">
                {{ $channels->isNotEmpty() ? 'Отправлено: '.$channels->join(', ') : 'Ссылка создана' }}
                · {{ $inv->created_at?->format('d.m.Y H:i') }}
            </div>
            <div class="d-flex gap-1" style="flex-wrap:wrap">
                <a href="{{ $invUrl }}" target="_blank" class="btn btn-small btn-secondary">🔗 Открыть ссылку</a>
                @if($inv->status === 'pending')
                <form method="POST" action="{{ route('tournamentTeamInvites.revoke', [$event, $team, $inv]) }}"
                      onsubmit="return confirm(@json(__('events.tinv_revoke_confirm')))">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-small btn-danger">{{ __('events.tinv_revoke_btn') }}</button>
                </form>
                @endif
            </div>
        </div>
        @empty
        <div class="f-15" style="opacity:.5">Приглашений пока нет</div>
        @endforelse
    </div>

    {{-- Создать приглашение --}}
    <div class="ramka">
        <h2 class="-mt-05">➕ Создать ссылку-приглашение</h2>
        <div class="f-15 mb-2" style="opacity:.6">
            Игрок получит персональную ссылку с ролью и позицией.
        </div>
        <form method="POST" action="{{ route('tournamentTeamInvites.store',[$event,$team]) }}" class="form">
            @csrf
            <div class="row row2">
                <div class="col-md-5">
                    <label>Игрок</label>
                    <div style="position:relative" id="ti-wrap">
                        <input type="text" id="ti-input" autocomplete="off" placeholder="Имя или email…">
                        <input type="hidden" name="invited_user_id" id="ti-userid">
                        <div id="ti-dd" style="display:none;position:absolute;left:0;right:0;top:100%;margin-top:.4rem;z-index:50;background:var(--bg-card,#fff);border:.1rem solid var(--border-color,#eee);border-radius:1.2rem;box-shadow:0 1rem 3rem rgba(0,0,0,.1);max-height:22rem;overflow-y:auto"></div>
                    </div>
                    <div id="ti-selected" class="f-13 mt-05" style="color:#4caf50;display:none"></div>
                </div>
                <div class="col-md-3">
                    <label>Роль</label>
                    <select name="team_role" id="ti-role">
                        <option value="player">Основной</option>
                        <option value="reserve">Запасной</option>
                    </select>
                </div>
                @if($team->team_kind==='classic_team')
                <div class="col-md-4" id="ti-pos-wrap">
                    <label>Позиция</label>
                    <select name="position_code" id="ti-position">
                        <option value="">— без —</option>
                        @foreach($positionOptions ?? [] as $code => $label)
                        <option value="{{ $code }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
            </div>
            <button type="submit" class="btn mt-1">Создать ссылку</button>
        </form>
    </div>
    @endif

    {{-- Заявка --}}
    <div class="ramka">
        <h2 class="-mt-05">📋 Подача заявки</h2>
        @if($team->application)
            @php $appSt = $team->application->status ?? 'pending'; @endphp
            <div class="d-flex fvc" style="gap:.6rem;margin-bottom:.75rem;padding:.6rem .9rem;border-radius:8px;background:{{ $appStBg[$appSt] ?? '#f8f9fa' }}">
                <span style="font-size:1.1rem">{{ $appStIcon[$appSt] ?? '📋' }}</span>
                <div>
                    <span class="f-13" style="opacity:.6">Статус заявки</span>
                    <div class="b-600 f-15" style="color:{{ $appStColor[$appSt] ?? '#333' }}">{{ $appStLabels[$appSt] ?? $appSt }}</div>
                </div>
            </div>

            @if($canManage && in_array($appSt, ['incomplete','pending'], true))
                <form method="POST" action="{{ route('tournamentTeams.revokeApplication',[$event,$team]) }}"
                      onsubmit="return confirm(@json(__('events.tapp_revoke_confirm')))" class="mb-2">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-small btn-danger">{{ __('events.tapp_revoke_btn') }}</button>
                </form>
            @endif

            {{-- Блок оплаты (после подачи заявки) --}}
            @php
                $payService = app(\App\Services\TournamentPaymentService::class);
                $payInfo = $payService->getPaymentInfo($team);
            @endphp

            @if($payInfo['required'])
                <div class="card mt-2" id="payment-block">
                    <h4 class="f-16 fw-bold mb-2">💳 Оплата участия</h4>

                    @if($payInfo['mode'] === 'team')
                        {{-- Режим: капитан за всю команду --}}
                        <div class="f-14 mb-2">
                            Стоимость: <strong>{{ number_format($payInfo['amount'] / 100, 0, ',', ' ') }} {{ $payInfo['currency'] }}</strong>
                            · Оплачивает капитан за всю команду
                        </div>

                        @if($payInfo['team_status'] === 'paid' || $payInfo['team_status'] === 'subscription')
                            <div class="alert alert-success f-14">✅ Оплата подтверждена</div>
                        @elseif($payInfo['team_status'] === 'link_pending')
                            <div class="alert alert-warning f-14">⏳ Ожидает подтверждения организатором</div>
                        @elseif($canManage)
                            @if($payInfo['method'] === 'cash')
                                <div class="alert alert-info f-14">💵 Оплата наличными</div>
                            @else
                                @if($team->payment_id)
                                    <form method="POST" action="{{ route('payments.user_confirm', $team->payment_id) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-warning"
                                                onclick="return confirm('Подтверждаете что перевод выполнен?')">
                                            Я оплатил
                                        </button>
                                    </form>
                                    @if($event->payment_link)
                                        <a href="{{ $event->payment_link }}" target="_blank" class="btn btn-outline-primary mt-1">
                                            Перейти к оплате →
                                        </a>
                                    @endif
                                @endif
                            @endif
                        @else
                            <div class="f-14" style="opacity:.6">Ожидаем оплату от капитана</div>
                        @endif

                    @elseif($payInfo['mode'] === 'per_player')
                        {{-- Режим: каждый сам --}}
                        <div class="f-14 mb-2">
                            Стоимость: <strong>{{ number_format($payInfo['amount'] / 100, 0, ',', ' ') }} {{ $payInfo['currency'] }}</strong> с каждого игрока
                        </div>

                        @if($payInfo['team_paid'])
                            <div class="alert alert-success f-14">✅ Все участники оплатили</div>
                        @endif

                        @if(!empty($payInfo['members']))
                            <table class="table table-sm f-14 mt-1">
                                <tbody>
                                @foreach($payInfo['members'] as $pm)
                                    <tr>
                                        <td>{{ $pm['name'] ?: 'Игрок #'.$pm['user_id'] }}</td>
                                        <td class="text-end">
                                            @if($pm['paid'])
                                                <span class="badge bg-success">Оплачено</span>
                                            @elseif($pm['payment_status'] === 'link_pending')
                                                <span class="badge bg-warning">Ожидает</span>
                                            @elseif($pm['user_id'] === auth()->id())
                                                @php
                                                    $memberModel = \App\Models\EventTeamMember::find($pm['id']);
                                                @endphp
                                                @if($memberModel && $memberModel->payment_id)
                                                    <form method="POST" action="{{ route('payments.user_confirm', $memberModel->payment_id) }}" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="btn btn-warning btn-sm py-0"
                                                                onclick="return confirm('Подтверждаете что перевод выполнен?')">
                                                            Я оплатил
                                                        </button>
                                                    </form>
                                                @else
                                                    <span class="badge bg-danger">Не оплачено</span>
                                                @endif
                                            @else
                                                <span class="badge bg-danger">Не оплачено</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        @endif
                    @endif
                </div>
            @endif

        @elseif($canManage)
            <div class="f-15 mb-2" style="opacity:.6">Если состав готов — подайте заявку на турнир.</div>

            @if($team->is_complete && $team->status === 'ready')
                <form method="POST" action="{{ route('tournamentTeams.submit',[$event,$team]) }}"
                      onsubmit="return confirm('Подать заявку команды на турнир?')">
                    @csrf
                    <button type="submit" class="btn">{{ __('events.tapp_submit_btn') }}</button>
                </form>
            @else
                @php
                    $hasCaptain = (bool) $team->captain_user_id;
                    $allowIncomplete = (bool) ($settings?->allow_incomplete_application ?? false);
                    $canEarlySubmit = $allowIncomplete && $hasCaptain;
                @endphp
                <div class="alert alert-warning f-14 mb-2">
                    ⚠️ Состав ещё не готов — обычная подача недоступна.
                </div>
                @if($canEarlySubmit)
                    <div class="f-13 mb-1" style="opacity:.7">{{ __('events.tapp_submit_early_warn') }}</div>
                    <form method="POST" action="{{ route('tournamentTeams.submit',[$event,$team]) }}"
                          onsubmit="return confirm(@json(__('events.tapp_submit_early_btn') . '?'))">
                        @csrf
                        <input type="hidden" name="allow_incomplete" value="1">
                        <button type="submit" class="btn btn-secondary">{{ __('events.tapp_submit_early_btn') }}</button>
                    </form>
                @endif
            @endif
        @else
            <div class="f-15" style="opacity:.5">Заявка ещё не подана</div>
        @endif
    </div>

</div>
<div class="col-lg-4">

    {{-- Статус --}}
    <div class="ramka">
        <h2 class="-mt-05">📊 Состав</h2>
        <div class="card">
            <div class="f-15 mb-05">Подтверждено: <strong>{{ $confirmedCount }}</strong></div>
            <div class="f-15 mb-05">Ожидают: <strong>{{ $pendingCount }}</strong></div>
            <div class="f-15 mb-1">Всего: <strong>{{ $team->members->count() }}</strong></div>
            @if($settings)
            <div class="f-13" style="opacity:.6;line-height:1.8">
                Схема: <strong>{{ $settings->game_scheme ?? '—' }}</strong>
                @if($team->team_kind === 'classic_team')
                <br>Мин.: <strong>{{ $settings->team_size_min ?? '—' }}</strong> ·
                Макс.: <strong>{{ $settings->total_players_max ?? $settings->team_size_max ?? '—' }}</strong>
                <br>Запасных: <strong>{{ $settings->reserve_players_max ?? '—' }}</strong>
                <br>Либеро: <strong>{{ $settings->require_libero ? 'Да' : 'Нет' }}</strong>
                @endif
                @if($settings->max_rating_sum)<br>Лимит рейтинга: <strong>{{ $settings->max_rating_sum }}</strong>@endif
            </div>
            @endif
        </div>
        <div class="mt-1">
            @if($team->is_complete)
            <div class="alert alert-success">✅ Состав готов</div>
            @else
            <div class="alert alert-warning">⚠️ Состав не готов</div>
            @endif

            {{-- Кнопки выхода --}}
            @auth
                @php
                    $isMember = $team->members->contains('user_id', auth()->id());
                    $isCaptainSelf = (int)$team->captain_user_id === (int)auth()->id();
                @endphp

                @if($isMember && !$isCaptainSelf)
                    <form method="POST" action="{{ route('tournamentTeams.leave', [$event, $team]) }}" class="mt-1">
                        @csrf
                        <button type="submit" class="btn btn-secondary btn-alert w-100"
                                data-title="Покинуть команду {{ $team->name }}?"
                                data-text="Вы будете удалены из состава. Если оплата была произведена — средства вернутся на ваш внутренний счёт."
                                data-icon="warning"
                                data-confirm-text="Да, покинуть"
                                data-cancel-text="Отмена"
                                style="color:#dc2626">
                            🚪 Покинуть команду
                        </button>
                    </form>
                @endif

                @if($isCaptainSelf)
                    <form method="POST" action="{{ route('tournamentTeams.disband', [$event, $team]) }}" class="mt-1">
                        @csrf
                        <button type="submit" class="btn btn-secondary btn-alert w-100"
                                data-title="Расформировать команду {{ $team->name }}?"
                                data-text="Команда будет удалена. Все участники получат уведомление. Оплата будет возвращена на внутренний счёт."
                                data-icon="warning"
                                data-confirm-text="Да, расформировать"
                                data-cancel-text="Отмена"
                                style="color:#dc2626">
                            💥 Расформировать команду
                        </button>
                    </form>
                @endif
            @endauth

        </div>
    </div>

</div>
</div>
</div>

<x-slot name="script">
<script>
(function(){
    var input=document.getElementById('ti-input'),dd=document.getElementById('ti-dd'),
        hid=document.getElementById('ti-userid'),sel=document.getElementById('ti-selected'),
        role=document.getElementById('ti-role'),posW=document.getElementById('ti-pos-wrap'),
        timer=null;
    if(!input)return;
    function syncPos(){if(posW)posW.style.display=(role&&role.value==='reserve')?'none':'';}
    if(role){role.addEventListener('change',syncPos);syncPos();}
    function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
    input.addEventListener('input',function(){
        clearTimeout(timer);hid.value='';sel.style.display='none';
        var q=input.value.trim();
        if(q.length<2){dd.style.display='none';return;}
        dd.innerHTML='<div style="padding:1rem 1.6rem;opacity:.5;font-size:1.5rem">Поиск…</div>';dd.style.display='block';
        timer=setTimeout(function(){
            fetch('/api/users/search?exclude_bots=1&q='+encodeURIComponent(q),{headers:{'Accept':'application/json'},credentials:'same-origin'})
            .then(function(r){return r.json();}).then(function(data){
                var items=data.items||[];dd.innerHTML='';
                if(!items.length){dd.innerHTML='<div style="padding:1rem 1.6rem;opacity:.5;font-size:1.5rem">Ничего не найдено</div>';return;}
                items.forEach(function(item){
                    var div=document.createElement('div');
                    div.style='padding:1rem 1.6rem;cursor:pointer;font-size:1.5rem;border-bottom:.1rem solid var(--border-color,#eee)';
                    div.innerHTML='<span class="b-600">'+esc(item.label||item.name)+'</span>';
                    div.addEventListener('mouseover',function(){this.style.background='var(--bg2,#f5f5f5)';});
                    div.addEventListener('mouseout',function(){this.style.background='';});
                    div.addEventListener('click',function(){
                        hid.value=item.id;input.value=item.label||item.name;
                        sel.textContent='✅ '+input.value;sel.style.display='block';dd.style.display='none';
                    });
                    dd.appendChild(div);
                });
            });
        },250);
    });
    document.addEventListener('click',function(e){if(!document.getElementById('ti-wrap').contains(e.target))dd.style.display='none';});
})();
</script>
</x-slot>
</x-voll-layout>
