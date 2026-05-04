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
            $seat        = $seatMeta($occ);
            $isCancelled = !empty($occ->cancelled_at);
            $occBotRaw   = $occ->getRawOriginal('bot_assistant_enabled');
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
                <td class="text-center f-16">
                    @if($isCancelled)
                        <span class="f-16 p-1 pt-05 pb-05 alert-error">Отменено</span>
                    @elseif(!empty($isArchived))
                        <span class="f-16 p-1 pt-05 pb-05" style="background:#f3f4f6;color:#6b7280;">Завершено</span>
                    @else
                        <span class="f-16 p-1 pt-05 pb-05 alert-success">Активно</span>
                    @endif
                </td>
                <td class="align-top f-16">
                    <div class="b-600">{{ $seat['label'] }}</div>
                    <div>Записано: <strong>{{ (int)$seat['registered'] }}</strong></div>
                </td>
                <td class="text-center f-0">
                    <div class="d-flex gap-1 text-center">
                        @if($showBotToggle)
                        <button type="button"
                            data-url="{{ route('events.occurrences.toggle-bot', ['event' => (int)$event->id, 'occurrence' => (int)$occ->id]) }}"
                            data-enabled="{{ $effectiveBot ? '1' : '0' }}"
                            title="{{ $effectiveBot ? 'Бот включён (нажми чтобы выключить)' : 'Бот выключен (нажми чтобы включить)' }}"
                            class="{{ $effectiveBot ? 'occ-bot-toggle btn-danger btn btn-svg icon-stop' : 'occ-bot-toggle btn-success btn btn-svg icon-play' }}">
                        </button>
                        @endif
                        @if($event->format === 'tournament')
                        <a href="{{ route('tournament.setup', $event) }}"
                            class="btn btn-small btn-secondary"
                            title="Управление турниром">🏆</a>
                        @else
                        <a href="{{ route('events.registrations.index', ['event' => (int)$event->id, 'occurrence' => (int)$occ->id]) }}"
                            class="btn btn-svg icon-users"
                            title="Список участников"></a>
                        @endif
                        <a href="{{ route('events.occurrences.edit', ['event' => (int)$event->id, 'occurrence' => (int)$occ->id]) }}"
                            class="btn btn-svg icon-edit"
                            title="Редактировать дату"></a>
                        <form method="POST"
                            action="{{ route('occurrences.destroy', ['occurrence' => (int)$occ->id]) }}"
                            class="d-inline-block">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="delete_mode" value="single">
                            <button type="submit"
                                class="btn-alert btn btn-danger btn-svg icon-stop"
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
