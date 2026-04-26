{{--  body_class - класс для body --}}
<x-voll-layout body_class="note-page">

    <x-slot name="title">Шаблоны уведомлений</x-slot>
    <x-slot name="description">Шаблоны уведомлений</x-slot>
    <x-slot name="h1">Шаблоны уведомлений</x-slot>
    <x-slot name="t_description">{{ $templates->count() }} шаблонов · {{ $templates->where('is_active', true)->count() }} активных</x-slot>

    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('admin.dashboard') }}" itemprop="item"><span itemprop="name">Админ-панель</span></a>
            <meta itemprop="position" content="2">
        </li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <span itemprop="name">Шаблоны уведомлений</span>
            <meta itemprop="position" content="3">
        </li>
    </x-slot>

    <div class="container">

        @if(session('status'))
            <div class="ramka"><div class="alert alert-success">{{ session('status') }}</div></div>
        @endif

        @php
            $groups = [
                'Регистрация'  => ['registration_created','registration_cancelled','registration_cancelled_by_organizer','registration_failed'],
                'Лист ожидания' => ['waitlist_joined','waitlist_spot_freed'],
                'Приглашения'  => ['event_invite','group_invite','tournament_team_invite'],
                'Мероприятия'  => ['event_reminder','event_cancelled','event_cancelled_quorum','friend_joined_event'],
                'Платежи'      => ['payment_confirmed','payment_cancelled','payment_rejected','payment_user_confirmed'],
                'Турниры'      => ['tournament_match_upcoming','tournament_match_result','tournament_advancement','tournament_completed','tournament_started','tournament_photos'],
                'Лиги и сезоны'=> ['season_promotion','season_elimination','season_reserve_activated','season_confirm_participation'],
                'Социальное'   => ['user_level_voted','user_play_liked'],
                'Администрирование' => ['ad_event_payment_pending','admin_broadcast'],
            ];

            $byCode = $templates->keyBy('code');
            $listed = collect();
        @endphp

        @foreach($groups as $groupName => $codes)
        @php
            $groupRows = collect($codes)->map(fn($c) => $byCode->get($c))->filter();
            $groupRows->each(fn($r) => $listed->push($r->code));
        @endphp
        @if($groupRows->isNotEmpty())
        <div class="ramka">
            <h3 class="mt-0 mb-1">{{ $groupName }}</h3>
            <div class="table-scrollable mb-0">
                <div class="table-drag-indicator"></div>
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:3rem">ID</th>
                            <th style="min-width:18rem">Код</th>
                            <th>Канал</th>
                            <th style="min-width:22rem">Название</th>
                            <th style="width:6rem">Активен</th>
                            <th style="width:4rem"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($groupRows as $row)
                        <tr class="{{ $row->is_active ? '' : 'text-muted' }}">
                            <td class="text-center f-13">{{ $row->id }}</td>
                            <td><code class="f-13">{{ $row->code }}</code></td>
                            <td class="f-13">{{ $row->channel ?: 'общий' }}</td>
                            <td class="f-14">
                                {{ $row->name }}
                                @if(!$row->is_active && !$row->title_template && !$row->body_template)
                                    <div class="f-12 text-muted">Содержание задаётся динамически — шаблон не применяется</div>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($row->is_active)
                                    <span class="badge badge-green">да</span>
                                @else
                                    <span class="badge badge-red">нет</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <a href="{{ route('admin.notification_templates.edit', $row->id) }}"
                                   class="icon-edit btn btn-svg"></a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
        @endforeach

        {{-- Прочие (без группы) --}}
        @php $others = $templates->whereNotIn('code', $listed->toArray()); @endphp
        @if($others->isNotEmpty())
        <div class="ramka">
            <h3 class="mt-0 mb-1">Прочие</h3>
            <div class="table-scrollable mb-0">
                <div class="table-drag-indicator"></div>
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:3rem">ID</th>
                            <th style="min-width:18rem">Код</th>
                            <th>Канал</th>
                            <th style="min-width:22rem">Название</th>
                            <th style="width:6rem">Активен</th>
                            <th style="width:4rem"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($others as $row)
                        <tr class="{{ $row->is_active ? '' : 'text-muted' }}">
                            <td class="text-center f-13">{{ $row->id }}</td>
                            <td><code class="f-13">{{ $row->code }}</code></td>
                            <td class="f-13">{{ $row->channel ?: 'общий' }}</td>
                            <td class="f-14">{{ $row->name }}</td>
                            <td class="text-center">
                                @if($row->is_active)
                                    <span class="badge badge-green">да</span>
                                @else
                                    <span class="badge badge-red">нет</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <a href="{{ route('admin.notification_templates.edit', $row->id) }}"
                                   class="icon-edit btn btn-svg"></a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

    </div>
</x-voll-layout>
