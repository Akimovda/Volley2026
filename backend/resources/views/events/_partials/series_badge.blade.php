{{--
    Partial: events._partials.series_badge

    Read-only плашка на occurrence-edit — параметры серии, которые
    НЕ редактируются per-дату: направление и тип мероприятия.

    Expects in scope:
      - $event (required)

    Примечание: игровая схема (subtype) убрана из этой плашки,
    т.к. её МОЖНО редактировать per-дату → живёт в team_config partial.
--}}
<div class="ramka" style="opacity:.7">
    <h2 class="-mt-05">Серия мероприятия</h2>
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <label>Направление</label>
                <input type="text" value="{{ direction_name($event->direction) }}" disabled>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <label>Тип мероприятия</label>
                <input type="text" value="{{ format_name($event->format) }}" disabled>
            </div>
        </div>
    </div>
    <div class="f-13" style="margin-top:.5rem">Эти параметры задаются на уровне серии и не редактируются per-дату. <a href="{{ route('events.event_management.edit', $event) }}">Изменить в настройках серии →</a></div>
</div>
