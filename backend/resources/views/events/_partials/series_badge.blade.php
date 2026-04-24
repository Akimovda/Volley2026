{{--
    Partial: events._partials.series_badge

    Read-only плашка на странице occurrence-edit, показывает параметры серии
    (направление, формат, игровая схема), которые редактируются только на
    уровне серии, и ссылку на редактор серии.

    Expects in scope:
      - $event       (required)  — родительский event серии
      - $subtypeVal  (optional)  — человекочитаемое название игровой схемы
--}}
<div class="ramka" style="opacity:.7">
    <h2 class="-mt-05">Серия мероприятия</h2>
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <label>Направление</label>
                <input type="text" value="{{ direction_name($event->direction) }}" disabled>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <label>Тип мероприятия</label>
                <input type="text" value="{{ format_name($event->format) }}" disabled>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <label>Игровая схема</label>
                <input type="text" value="{{ $subtypeVal ?? '—' }}" disabled>
            </div>
        </div>
    </div>
    <div class="f-13" style="margin-top:.5rem">Эти параметры задаются на уровне серии и не редактируются per-дату. <a href="{{ route('events.event_management.edit', $event) }}">Изменить в настройках серии →</a></div>
</div>
