{{-- resources/views/events/event_management_occurrences.blade.php --}}
@php
    $tz = $event->timezone ?: 'UTC';
    $isAdmin = (auth()->user()?->role ?? null) === 'admin';

    $fmtLocation = function ($event) {
        $parts = array_filter([
            $event->location?->name,
            $event->location?->city?->name,
            $event->location?->address,
        ]);

        return $parts ? implode(', ', $parts) : '—';
    };

    $fmtOccurrenceDt = function ($occ, $tz) {
        $s = $occ->starts_at ? \Carbon\Carbon::parse($occ->starts_at, 'UTC')->setTimezone($tz) : null;
        if (!$s) return '—';

        $e = null;
        if (!empty($occ->duration_sec)) {
            $e = $s->copy()->addSeconds((int)$occ->duration_sec);
        }

        return $s->format('d.m.Y') . ' · ' . $s->format('H:i') . ($e ? '–' . $e->format('H:i') : '') . ' (' . $tz . ')';
    };

    $seatMeta = function ($occ) {
        $max = (int)($occ->max_players ?? $occ->event?->max_players ?? 0);
        $registered = (int)($occ->active_regs ?? 0);

        if (!(bool)($occ->event?->allow_registration ?? false)) {
            return ['label' => 'Регистрация выключена', 'registered' => $registered];
        }

        if ($max <= 0) {
            return ['label' => 'Мест: —', 'registered' => $registered];
        }

        $free = max(0, $max - $registered);

        return [
            'label' => "Мест: {$free}/{$max}",
            'registered' => $registered,
        ];
    };
@endphp

<x-voll-layout>
    <x-slot name="title">Повторы мероприятия</x-slot>
    <x-slot name="h1">Повторы мероприятия</x-slot>

    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('events.create.event_management') }}" itemprop="item">
                <span itemprop="name">Управление мероприятиями</span>
            </a>
            <meta itemprop="position" content="2">
        </li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ url('/events/' . (int)$event->id) }}" itemprop="item">
                <span itemprop="name">#{{ (int)$event->id }} {{ $event->title }}</span>
            </a>
            <meta itemprop="position" content="3">
        </li>
    </x-slot>

    <div class="container">
        @if (session('status'))
            <div class="ramka">
                <div class="alert alert-success">{{ session('status') }}</div>
            </div>
        @endif

        @if (session('error'))
            <div class="ramka">
                <div class="alert alert-error">{{ session('error') }}</div>
            </div>
        @endif

        <div class="ramka mb-2">
            <div class="d-flex gap-2 flex-wrap align-items-center justify-content-between">
                <div>
                    <div class="b-600">{{ $event->title }}</div>
                    <div class="f-16 pt-1">
                        {{ strtoupper((string)$event->direction) }} · {{ (string)$event->format }}
                    </div>
                    <div class="f-16 pt-1">
                        📍 {{ $fmtLocation($event) }}
                    </div>
                </div>

                <div class="d-flex gap-2 flex-wrap">
                    <a href="{{ route('events.create.event_management') }}" class="btn btn-secondary">
                        ← Назад
                    </a>

                    <a href="{{ route('events.event_management.edit', ['event' => (int)$event->id]) }}"
                       class="btn btn-secondary">
                        Изменить серию
                    </a>

                    <a href="{{ url('/events/' . (int)$event->id) }}"
                       class="btn btn-secondary">
                        Открыть мероприятие
                    </a>
                </div>
            </div>
        </div>

        <div class="ramka">
            @if(empty($occurrences) || $occurrences->isEmpty())
                <div class="p-6 text-sm text-gray-600">
                    Повторов пока нет.
                </div>
            @else
                <div class="form table-scrollable mb-0">
                    <div class="table-drag-indicator"></div>

                    <table class="table">
                        <colgroup>
                            <col style="width:7rem" />
                            <col style="width:24%" />
                            <col style="width:18%" />
                            <col style="width:18%" />
                            <col style="width:18rem" />
                        </colgroup>

                        <thead class="bg-gray-50 text-gray-600">
                            <tr>
                                <th>ID</th>
                                <th>Дата и время</th>
                                <th>Статус</th>
                                <th>Регистрация</th>
                                <th>Действия</th>
                            </tr>
                        </thead>

                        <tbody>
                            @php
                            $showBotToggle = ($event->format ?? 'game') !== 'tournament'
                                && ($event->registration_type ?? 'individual') !== 'team'
                                && (bool)($event->allow_registration ?? false);
                        @endphp
                        @foreach($occurrences as $occ)
                                @php
                                    $seat = $seatMeta($occ);
                                    $isCancelled = !empty($occ->cancelled_at);
                                    $occBotRaw = $occ->getRawOriginal('bot_assistant_enabled');
                                    $effectiveBot = $occBotRaw === null
                                        ? (bool)($event->bot_assistant_enabled ?? false)
                                        : (bool)$occBotRaw;
                                @endphp

                                <tr>
                                    <td class="align-top nowrap">
                                        #{{ (int)$occ->id }}
                                    </td>

                                    <td class="align-top f-16">
                                        {{ $fmtOccurrenceDt($occ, $tz) }}
                                    </td>

                                    <td class="align-top f-16">
                                        @if($isCancelled)
                                            <span class="cd">Отменено</span>
                                        @else
                                            <span class="cs">Активно</span>
                                        @endif
                                    </td>

                                    <td class="align-top f-16">
                                        <div class="b-600">{{ $seat['label'] }}</div>
                                        <div class="mt-1">Записано: <strong>{{ (int)$seat['registered'] }}</strong></div>
                                    </td>



                                    <td class="align-top nowrap f-0">
                                        <div class="d-flex">
                                            @if($showBotToggle)
                                            <button type="button"
                                                class="btn btn-small btn-secondary mr-1 occ-bot-toggle"
                                                data-url="{{ route('events.occurrences.toggle-bot', ['event' => (int)$event->id, 'occurrence' => (int)$occ->id]) }}"
                                                data-enabled="{{ $effectiveBot ? '1' : '0' }}"
                                                title="{{ $effectiveBot ? 'Бот включён (нажми чтобы выключить)' : 'Бот выключен (нажми чтобы включить)' }}"
                                                @if($effectiveBot) style="border-color:#10b981;color:#10b981" @endif>
                                                🤖
                                            </button>
                                            @endif
                                            @if($event->format === 'tournament')
                                            <a href="{{ route('tournament.setup', $event) }}"
                                               class="btn btn-small btn-secondary mr-1"
                                               title="Управление турниром">
                                                🏆
                                            </a>
                                        @else
                                            <a href="{{ route('events.registrations.index', ['event' => (int)$event->id, 'occurrence' => (int)$occ->id]) }}"
                                               class="btn btn-small btn-secondary mr-1"
                                               title="Список участников">
                                                🧑‍🧑‍🧒‍🧒
                                            </a>
                                        @endif
                                            <a href="{{ route('events.occurrences.edit', ['event' => (int)$event->id, 'occurrence' => (int)$occ->id]) }}"
                                               class="btn btn-small btn-secondary mr-1"
                                               title="Редактировать дату">
                                                ⚙️
                                            </a>

                                            <form method="POST"
                                                  action="{{ route('occurrences.destroy', ['occurrence' => (int)$occ->id]) }}"
                                                  class="d-inline-block">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="delete_mode" value="single">

                                                <button type="submit"
                                                        class="btn-alert btn btn-danger btn-svg icon-stop mr-1"
                                                        data-title="Отменить повтор?"
                                                        data-text="Будет отменена только эта дата. История сохранится."
                                                        data-confirm-text="Да, отменить"
                                                        data-cancel-text="Отмена">
                                                </button>
                                            </form>

                                            @if($isAdmin)
                                                <form method="POST"
                                                      action="{{ route('occurrences.destroy', ['occurrence' => (int)$occ->id]) }}"
                                                      class="d-inline-block">
                                                    @csrf
                                                    @method('DELETE')
                                                    <input type="hidden" name="delete_mode" value="force">

                                                    <button type="submit"
                                                            class="btn-alert btn btn-danger btn-svg icon-delete"
                                                            data-title="Удалить повтор навсегда?"
                                                            data-text="Будет удалена только эта дата без возможности восстановления."
                                                            data-confirm-text="Да, удалить"
                                                            data-cancel-text="Отмена">
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <x-slot name="script">
    <script>
    $(function() {
        $(document).on('click', '.occ-bot-toggle', function() {
            var btn = $(this);
            var url = btn.data('url');
            btn.prop('disabled', true);

            $.ajax({
                url: url,
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                dataType: 'json',
                success: function(data) {
                    var on = !!data.enabled;
                    btn.data('enabled', on ? '1' : '0');
                    if (on) {
                        btn.css({'border-color': '#10b981', 'color': '#10b981'});
                    } else {
                        btn.css({'border-color': '', 'color': ''});
                    }
                    btn.attr('title', on ? 'Бот включён (нажми чтобы выключить)' : 'Бот выключен (нажми чтобы включить)');
                    swal({
                        title: on ? '🤖 Бот включён' : '🤖 Бот выключен',
                        text: on ? 'Помощник записи активирован для этой даты' : 'Помощник записи отключён для этой даты',
                        icon: on ? 'success' : 'info',
                        button: 'OK',
                    });
                },
                error: function() {
                    swal({ title: 'Ошибка', text: 'Не удалось изменить статус бота', icon: 'error', button: 'OK' });
                },
                complete: function() {
                    btn.prop('disabled', false);
                }
            });
        });
    });
    </script>
    </x-slot>

</x-voll-layout>
