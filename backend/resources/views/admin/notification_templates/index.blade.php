{{--  body_class - класс для body --}}
<x-voll-layout body_class="note-page">

    <x-slot name="title">{{ __('admin.nt_title') }}</x-slot>
    <x-slot name="description">{{ __('admin.nt_t_description') }}</x-slot>
    <x-slot name="h1">{{ __('admin.nt_title') }}</x-slot>
    <x-slot name="t_description">{{ $templates->count() }} {{ __('admin.nt_templates_count') }} · {{ $templates->where('is_active', true)->count() }} {{ __('admin.nt_active_count') }}</x-slot>

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
                'Лист ожидания' => ['waitlist_joined','waitlist_spot_freed','waitlist_auto_booked','waitlist_removed_by_organizer'],
                'Авто-запись' => ['auto_booking_created','auto_booking_failed','auto_booking_unconfirmed','premium_auto_booking_created','premium_auto_booking_failed','premium_auto_booking_unconfirmed'],
                'Команды' => ['team_join_request','team_join_accepted','team_join_declined','team_member_left','team_disbanded','team_captain_transferred','team_reserve_spot_offered','tournament_application_received','tournament_organizer_added'],
                'Брони кортов' => ['court_booking_requested','court_booking_confirmed','court_booking_paid','court_booking_changed','court_booking_cancelled','court_booking_rejected','court_booking_expired','court_booking_refunded','court_booking_reminder'],
                'Приглашения'  => ['event_invite','group_invite','tournament_team_invite'],
                'Мероприятия'  => ['event_reminder','event_cancelled','event_cancelled_quorum','friend_joined_event','new_event_in_city','weekly_digest'],
                'Платежи'      => ['payment_confirmed','payment_cancelled','payment_rejected','payment_user_confirmed','cash_payment_reminder','cash_payment_confirmed','cash_payment_control_reminder','cash_payment_banned'],
                'Турниры'      => ['tournament_match_upcoming','tournament_match_result','tournament_advancement','tournament_completed','tournament_started','tournament_photos','tournament_application_incomplete','tournament_application_completed','tournament_application_auto_rejected'],
                'Лиги и сезоны'=> ['season_promotion','season_elimination','season_reserve_activated','season_confirm_participation','promotion','reserve_spot_offered'],
                'Социальное'   => ['user_level_voted','user_play_liked','followed_player_registered'],
                'Тренеры'      => ['trainer_assigned', 'trainer_rating_request'],
                'Абонементы'   => ['subscription_low_visits'],
                'Уведомления организатору' => ['organizer_player_registered','organizer_player_cancelled','organizer_player_auto_booked','organizer_player_waitlisted','organizer_registered_player','organizer_cancelled_player','organizer_deleted_player','reserve_spot_offered_organizer','organizer_broadcast','organizer_player_waitlist_left'],
            'Организатор Pro' => ['organizer_pro_expiring','organizer_pro_expired','organizer_pro_payment_pending','organizer_pro_paid_activated','organizer_pro_activated','organizer_pro_granted','organizer_pro_deactivated'],
            'Premium' => ['premium_expiring','premium_expired','premium_payment_pending','premium_activated','premium_deactivated'],
            'Администрирование' => ['ad_event_payment_pending','ad_event_payment_result','admin_broadcast','organizer_request','personal_bot_revoked'],
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
