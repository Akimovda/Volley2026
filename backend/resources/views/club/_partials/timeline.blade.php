{{-- Виджет "Таймлайн занятости кортов" — переиспользуется на locations/show и club/bookings,
     НЕ дублировать разметку/JS в этих страницах, только @include этот partial.
     $locations: непустая коллекция Location (owner_id-locations клуба), директории/корты
     eager-load не обязателен — сам виджет их не использует, только id/name/timezone.
     Если локаций > 1 — показывается select выбора локации (по умолчанию первая).
     Требует уже подключённые на той же странице club._partials.booking_modal
     (window.__openAddBookingModal и т.п.) и club._partials.booking_details_modal.
     $showAddButton (bool, default true) — скрыть кнопку "Добавить бронь", если она
     уже есть в тулбаре страницы-включателя (напр. club/bookings.blade.php). --}}
@php
    $timelineLocationsJs = $locations->map(fn ($loc) => [
        'id' => $loc->id,
        'name' => $loc->name,
        'tz' => $loc->effectiveTimezone(),
    ])->values();
    $showAddButton = $showAddButton ?? true;
@endphp

<div class="ramka" id="timelineSection">
    <div class="d-flex between fvc mb-2" style="flex-wrap:wrap;gap:10px">
        <h2 class="-mt-05" style="margin:0">{{ __('club.timeline') }}</h2>
        <div class="d-flex gap-1" style="flex-wrap:wrap">
            @if($showAddButton)
            <button type="button" class="btn btn-small btn-primary" onclick="window.__openAddBookingModal && window.__openAddBookingModal()">➕ {{ __('club.add_booking') }}</button>
            @endif
            <button type="button" class="btn btn-small btn-primary" id="tlSwitchList">{{ __('club.list_view') }}</button>
            <button type="button" class="btn btn-small btn-secondary" id="tlSwitchTimeline">{{ __('club.timeline') }}</button>
        </div>
    </div>

    @if($locations->count() > 1)
    <div class="mb-2">
        {{-- Вне .form — намеренно: .form select схлопывается в 1px и требует createCustomSelect()
             обвязки (см. CLAUDE.md), а это простой тулбар-контрол уровня #tlDatePicker. --}}
        <select id="tlLocationSelect" class="btn-small">
            @foreach($locations as $loc)
            <option value="{{ $loc->id }}">{{ $loc->name }}</option>
            @endforeach
        </select>
    </div>
    @endif

    <div id="timelinePanel">
        <div class="d-flex between fvc mb-2" style="flex-wrap:wrap;gap:10px">
            <div class="d-flex gap-1 fvc" style="flex-wrap:wrap">
                <button type="button" class="btn btn-small btn-secondary" id="tlPrev">{{ __('club.yesterday') }}</button>
                <span class="b-600" id="tlCurrentLabel">{{ __('club.today') }}</span>
                <input type="date" id="tlDatePicker" class="btn-small">
                <button type="button" class="btn btn-small btn-secondary" id="tlNext">{{ __('club.tomorrow') }}</button>
            </div>
            <div class="d-flex gap-1" id="tlModeToggleWrap">
                <button type="button" class="btn btn-small btn-primary" id="tlModeDay">{{ __('club.day_view') }}</button>
                <button type="button" class="btn btn-small btn-secondary" id="tlModeWeek">{{ __('club.week_view') }}</button>
            </div>
        </div>

        <div id="tlLoading" class="alert alert-info" style="display:none">…</div>
        <div id="tlDayGrid" class="timeline-day"></div>
        <div id="tlWeekGrid" class="timeline-week" style="display:none"></div>
        <div id="tlListGrid" style="display:none"></div>
    </div>
</div>

<script>
// ===== Таймлайн занятости кортов =====
(function () {
    const section = document.getElementById('timelineSection');
    if (!section) return;

    const locationsData = @json($timelineLocationsJs);
    let currentLocationId = locationsData.length ? locationsData[0].id : null;
    const locationSelect = document.getElementById('tlLocationSelect');

    function timelineUrlFor(id) { return '/locations/' + id + '/timeline'; }
    function currentLocationTz() {
        const loc = locationsData.find(l => l.id === currentLocationId);
        return loc ? loc.tz : 'Europe/Moscow';
    }

    const listBtn = document.getElementById('tlSwitchList');
    const tlBtn = document.getElementById('tlSwitchTimeline');
    const panel = document.getElementById('timelinePanel');
    const prevBtn = document.getElementById('tlPrev');
    const nextBtn = document.getElementById('tlNext');
    const datePicker = document.getElementById('tlDatePicker');
    const currentLabel = document.getElementById('tlCurrentLabel');
    const modeToggleWrap = document.getElementById('tlModeToggleWrap');
    const modeDayBtn = document.getElementById('tlModeDay');
    const modeWeekBtn = document.getElementById('tlModeWeek');
    const loadingEl = document.getElementById('tlLoading');
    const dayGrid = document.getElementById('tlDayGrid');
    const weekGrid = document.getElementById('tlWeekGrid');
    const listGrid = document.getElementById('tlListGrid');

    const directionLabels = {
        classic: @json(__('club.direction_classic')),
        beach: @json(__('club.direction_beach')),
    };
    const closedLabel = @json(__('club.closed_day'));
    const eventsCountTpl = @json(__('club.events_count', ['count' => '__N__']));
    const todayLabel = @json(__('club.today'));
    const statusLabels = {
        pending: @json(__('club.status_pending')),
        confirmed: @json(__('club.status_confirmed')),
        paid: @json(__('club.status_paid')),
    };
    const clampFromTpl = @json(__('club.timeline_clamped_from', ['time' => '__T__']));
    const clampUntilTpl = @json(__('club.timeline_clamped_until', ['time' => '__T__']));
    const allCourtsLabel = @json(__('club.list_all_courts'));
    const listEmptyLabel = @json(__('club.list_empty_day'));
    const openEventLabel = @json(__('club.list_open_event'));
    const freePriceLabel = @json(__('club.price_free'));
    const priceFieldLabel = @json(__('club.booking_price'));

    const state = { view: 'list', mode: 'day', date: new Date(), dayStart: null, dayEnd: null };
    const PX_PER_MIN = 1.5;
    // Высота .timeline-direction-label (18+8=26px) + .timeline-court-header (24px) —
    // ось времени должна начинаться с этим отступом, иначе подписи времени не
    // совпадают с реальным положением событий/линии текущего времени.
    const HEADER_OFFSET = 50;

    if (locationSelect) {
        locationSelect.addEventListener('change', function () {
            currentLocationId = parseInt(locationSelect.value, 10);
            load();
        });
    }

    function fmtDate(d) { return d.toISOString().slice(0, 10); }

    // Текущие дата (YYYY-MM-DD) и минуты с полуночи В ТАЙМЗОНЕ ЛОКАЦИИ.
    function nowInTz(tz) {
        const parts = new Intl.DateTimeFormat('en-CA', {
            timeZone: tz, hour12: false,
            year: 'numeric', month: '2-digit', day: '2-digit',
            hour: '2-digit', minute: '2-digit',
        }).formatToParts(new Date());
        const get = (type) => (parts.find(p => p.type === type) || {}).value || '0';
        const dateStr = get('year') + '-' + get('month') + '-' + get('day');
        const minutes = parseInt(get('hour'), 10) * 60 + parseInt(get('minute'), 10);
        return { dateStr, minutes };
    }

    // Перерисовать линию текущего времени поверх уже отрисованной сетки дня
    // (вызывается сразу после renderDay и затем раз в минуту по таймеру).
    function renderNowLine() {
        const axisEl = dayGrid.querySelector('.timeline-axis');
        const courtsWrapEl = dayGrid.querySelector('.timeline-courts');
        if (!axisEl || !courtsWrapEl) return;

        const oldLine = courtsWrapEl.querySelector('.timeline-now-line');
        if (oldLine) oldLine.remove();
        const oldDot = axisEl.querySelector('.timeline-now-dot');
        if (oldDot) oldDot.remove();

        if (state.view !== 'timeline' || state.mode !== 'day' || state.dayStart === null) return;

        const { dateStr, minutes } = nowInTz(currentLocationTz());
        if (dateStr !== fmtDate(state.date)) return; // выбран не сегодняшний день
        if (minutes < state.dayStart || minutes > state.dayEnd) return; // вне рабочих часов

        const top = HEADER_OFFSET + (minutes - state.dayStart) * PX_PER_MIN;

        const line = document.createElement('div');
        line.className = 'timeline-now-line';
        line.style.top = top + 'px';
        courtsWrapEl.appendChild(line);

        const dot = document.createElement('div');
        dot.className = 'timeline-now-dot';
        dot.style.top = top + 'px';
        axisEl.appendChild(dot);
    }

    function setActive(btnActive, btnInactive) {
        btnActive.classList.remove('btn-secondary'); btnActive.classList.add('btn-primary');
        btnInactive.classList.remove('btn-primary'); btnInactive.classList.add('btn-secondary');
    }

    // Режим "Список" — брони+события выбранного дня одним списком по времени
    // (день/неделя переключатель к нему не относится — список всегда за 1 день).
    function showList() {
        state.view = 'list';
        setActive(listBtn, tlBtn);
        modeToggleWrap.style.display = 'none';
        dayGrid.style.display = 'none';
        weekGrid.style.display = 'none';
        listGrid.style.display = '';
        load();
    }
    function showTimeline() {
        state.view = 'timeline';
        setActive(tlBtn, listBtn);
        modeToggleWrap.style.display = '';
        listGrid.style.display = 'none';
        setMode(state.mode);
    }
    listBtn.addEventListener('click', showList);
    tlBtn.addEventListener('click', showTimeline);

    function setMode(mode) {
        state.mode = mode;
        if (mode === 'day') {
            setActive(modeDayBtn, modeWeekBtn);
            dayGrid.style.display = ''; weekGrid.style.display = 'none';
        } else {
            setActive(modeWeekBtn, modeDayBtn);
            dayGrid.style.display = 'none'; weekGrid.style.display = '';
        }
        load();
    }
    modeDayBtn.addEventListener('click', () => setMode('day'));
    modeWeekBtn.addEventListener('click', () => setMode('week'));

    prevBtn.addEventListener('click', function () {
        const step = (state.view === 'timeline' && state.mode === 'week') ? 7 : 1;
        state.date.setDate(state.date.getDate() - step);
        load();
    });
    nextBtn.addEventListener('click', function () {
        const step = (state.view === 'timeline' && state.mode === 'week') ? 7 : 1;
        state.date.setDate(state.date.getDate() + step);
        load();
    });
    datePicker.addEventListener('change', function () {
        if (datePicker.value) { state.date = new Date(datePicker.value + 'T00:00:00'); load(); }
    });

    function updateLabel() {
        const isToday = fmtDate(state.date) === fmtDate(new Date());
        currentLabel.textContent = isToday
            ? todayLabel
            : state.date.toLocaleDateString('ru-RU', { day: 'numeric', month: 'long' });
        datePicker.value = fmtDate(state.date);
    }

    function timeToMin(t) {
        const parts = t.split(':').map(Number);
        return parts[0] * 60 + parts[1];
    }

    function renderDay(directions) {
        dayGrid.innerHTML = '';
        const openDirs = directions.filter(d => !d.is_closed);
        if (!openDirs.length) {
            dayGrid.innerHTML = '<div class="alert alert-info">' + closedLabel + '</div>';
            state.dayStart = null; state.dayEnd = null;
            return;
        }

        const dayStart = Math.min.apply(null, openDirs.map(d => timeToMin(d.opens_at)));
        const dayEnd = Math.max.apply(null, openDirs.map(d => timeToMin(d.closes_at)));
        const totalMin = dayEnd - dayStart;
        state.dayStart = dayStart;
        state.dayEnd = dayEnd;

        const wrap = document.createElement('div');
        wrap.className = 'timeline-scroll';

        const axis = document.createElement('div');
        axis.className = 'timeline-axis';
        axis.style.height = (totalMin * PX_PER_MIN + HEADER_OFFSET) + 'px';
        for (let m = dayStart; m <= dayEnd; m += 30) {
            const label = document.createElement('div');
            label.className = 'timeline-axis-label';
            label.style.top = (HEADER_OFFSET + (m - dayStart) * PX_PER_MIN) + 'px';
            label.textContent = String(Math.floor(m / 60)).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0');
            axis.appendChild(label);
        }

        const courtsWrap = document.createElement('div');
        courtsWrap.className = 'timeline-courts';

        directions.forEach(function (dir) {
            const group = document.createElement('div');
            group.className = 'timeline-direction-group';

            const label = document.createElement('div');
            label.className = 'timeline-direction-label';
            label.textContent = directionLabels[dir.direction] || dir.direction;
            group.appendChild(label);

            const row = document.createElement('div');
            row.className = 'timeline-courts-row';

            if (dir.is_closed) {
                const closed = document.createElement('div');
                closed.className = 'timeline-closed';
                closed.textContent = closedLabel;
                row.appendChild(closed);
            } else {
                dir.courts.forEach(function (court) {
                    const col = document.createElement('div');
                    col.className = 'timeline-court-col';

                    const header = document.createElement('div');
                    header.className = 'timeline-court-header';
                    header.textContent = (court.is_indoor ? '🏠 ' : '☀️ ') + court.name;
                    header.title = court.name;
                    col.appendChild(header);

                    const body = document.createElement('div');
                    body.className = 'timeline-court-body';
                    body.style.height = (totalMin * PX_PER_MIN) + 'px';

                    court.slots.forEach(function (slot) {
                        const rawStartMin = timeToMin(slot.starts_at);
                        const rawEndMin = timeToMin(slot.ends_at);
                        // Событие полностью вне рабочих часов направления — не показываем совсем.
                        if (rawEndMin <= dayStart || rawStartMin >= dayEnd) return;

                        const startMin = Math.max(rawStartMin, dayStart);
                        const endMin = Math.min(rawEndMin, dayEnd);
                        const isBooking = slot.type === 'booking';
                        const block = document.createElement(isBooking ? 'div' : 'a');
                        block.className = 'timeline-event-block' + (isBooking ? ' timeline-booking-block' : '');
                        if (!isBooking) block.href = '/events/' + slot.event_id;
                        block.style.top = ((startMin - dayStart) * PX_PER_MIN) + 'px';
                        block.style.height = Math.max(18, (endMin - startMin) * PX_PER_MIN) + 'px';
                        block.style.background = slot.color || '#4A9EFF';
                        const metaLabel = isBooking
                            ? (statusLabels[slot.status] || slot.status)
                            : (slot.organizer || '');
                        const clampNotes = [];
                        if (rawStartMin < dayStart) clampNotes.push(clampFromTpl.replace('__T__', slot.starts_at));
                        if (rawEndMin > dayEnd) clampNotes.push(clampUntilTpl.replace('__T__', slot.ends_at));
                        block.innerHTML = '<div class="timeline-event-title">' + (slot.title || '') + '</div>' +
                            '<div class="timeline-event-meta">' + slot.starts_at + '–' + slot.ends_at + (metaLabel ? ' · ' + metaLabel : '') + '</div>' +
                            (clampNotes.length ? '<div class="timeline-event-clamp-note">' + clampNotes.join(' · ') + '</div>' : '');

                        if (isBooking && typeof window.__openBookingDetails === 'function') {
                            block.style.cursor = 'pointer';
                            block.addEventListener('click', function () {
                                window.__openBookingDetails({
                                    id: slot.booking_id,
                                    court_id: slot.court_id,
                                    direction_id: slot.direction_id,
                                    date: slot.date,
                                    time_from: slot.starts_at,
                                    time_to: slot.ends_at,
                                    title: slot.raw_title,
                                    color: slot.raw_color,
                                    is_guest: slot.is_guest,
                                    organizer_id: slot.organizer_id,
                                    organizer_label: slot.organizer,
                                    guest_name: slot.guest_name,
                                    guest_phone: slot.guest_phone,
                                    booker_name: slot.booker_name,
                                    status: slot.status,
                                    price_total: slot.price_total,
                                    court_name: slot.court_name,
                                    parent_booking_id: slot.parent_booking_id,
                                    is_series: slot.is_series,
                                });
                            });
                        }

                        body.appendChild(block);
                    });

                    col.appendChild(body);
                    row.appendChild(col);
                });
            }

            group.appendChild(row);
            courtsWrap.appendChild(group);
        });

        wrap.appendChild(axis);
        wrap.appendChild(courtsWrap);
        dayGrid.appendChild(wrap);

        renderNowLine();
    }

    setInterval(renderNowLine, 60000);

    function renderWeek(days) {
        weekGrid.innerHTML = '';
        const directionsSet = new Set();
        days.forEach(d => d.directions.forEach(dd => directionsSet.add(dd.direction)));
        const directionsList = Array.from(directionsSet);
        const counts = days.flatMap(d => d.directions.map(dd => dd.events_count));
        const maxCount = Math.max(1, ...counts);

        const table = document.createElement('table');
        table.className = 'timeline-week-table';

        const thead = document.createElement('thead');
        const headRow = document.createElement('tr');
        headRow.appendChild(document.createElement('th'));
        days.forEach(function (day) {
            const th = document.createElement('th');
            th.textContent = day.day_label;
            th.className = 'timeline-week-day-header';
            th.addEventListener('click', function () {
                state.date = new Date(day.date + 'T00:00:00');
                setMode('day');
            });
            headRow.appendChild(th);
        });
        thead.appendChild(headRow);
        table.appendChild(thead);

        const tbody = document.createElement('tbody');
        directionsList.forEach(function (directionKey) {
            const row = document.createElement('tr');
            const th = document.createElement('th');
            th.textContent = directionLabels[directionKey] || directionKey;
            row.appendChild(th);

            days.forEach(function (day) {
                const dd = day.directions.find(x => x.direction === directionKey);
                const td = document.createElement('td');
                if (!dd || dd.is_closed) {
                    td.textContent = closedLabel;
                    td.className = 'timeline-week-closed';
                } else {
                    const intensity = dd.events_count / maxCount;
                    td.style.background = 'rgba(74,158,255,' + (0.08 + intensity * 0.5).toFixed(2) + ')';
                    td.textContent = eventsCountTpl.replace('__N__', dd.events_count);
                }
                row.appendChild(td);
            });

            tbody.appendChild(row);
        });
        table.appendChild(tbody);
        weekGrid.appendChild(table);
    }

    function escHtml(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    // Список броней+событий выбранного дня, хронологически. Данные те же, что
    // и для дневного таймлайна (directions -> courts -> slots), но события без
    // привязки к корту дублируются на каждый корт направления (Фаза 2) —
    // дедуплицируем по occurrence_id/booking_id перед выводом.
    function renderList(directions) {
        listGrid.innerHTML = '';
        const openDirs = directions.filter(d => !d.is_closed);
        if (!openDirs.length) {
            listGrid.innerHTML = '<div class="alert alert-info">' + closedLabel + '</div>';
            return;
        }

        const seenEvents = new Set();
        const seenBookings = new Set();
        const items = [];
        openDirs.forEach(function (dir) {
            dir.courts.forEach(function (court) {
                court.slots.forEach(function (slot) {
                    if (slot.type === 'event') {
                        const key = 'e:' + slot.occurrence_id;
                        if (seenEvents.has(key)) return;
                        seenEvents.add(key);
                        items.push({ slot: slot, direction: dir.direction, courtName: slot.court_id ? court.name : null });
                    } else {
                        const key = 'b:' + slot.booking_id;
                        if (seenBookings.has(key)) return;
                        seenBookings.add(key);
                        items.push({ slot: slot, direction: dir.direction, courtName: court.name });
                    }
                });
            });
        });

        if (!items.length) {
            listGrid.innerHTML = '<div class="alert alert-info">' + listEmptyLabel + '</div>';
            return;
        }

        items.sort(function (a, b) { return timeToMin(a.slot.starts_at) - timeToMin(b.slot.starts_at); });

        items.forEach(function (item) {
            const slot = item.slot;
            const isBooking = slot.type === 'booking';
            const card = document.createElement('div');
            card.className = 'card mb-2 timeline-list-card';
            card.style.borderLeft = '4px solid ' + (slot.color || '#4A9EFF');

            const courtLabel = item.courtName || allCourtsOrDirectionLabel(item.direction);
            const metaLabel = isBooking
                ? (statusLabels[slot.status] || slot.status)
                : (slot.organizer || '');
            const priceLabel = isBooking
                ? (priceFieldLabel + ': ' + ((slot.price_total === null || slot.price_total === undefined) ? freePriceLabel : (slot.price_total + ' ₽')))
                : '';

            card.innerHTML =
                '<div class="d-flex between fvc" style="flex-wrap:wrap;gap:8px">' +
                    '<div>' +
                        '<div class="b-700">' + slot.starts_at + '–' + slot.ends_at + ' · ' + escHtml(courtLabel) + '</div>' +
                        '<div class="f-14">' + escHtml(slot.title || '') + (metaLabel ? ' · ' + escHtml(metaLabel) : '') + '</div>' +
                        (priceLabel ? '<div class="f-14">' + escHtml(priceLabel) + '</div>' : '') +
                    '</div>' +
                    '<div class="d-flex gap-1 timeline-list-actions" style="flex-wrap:wrap"></div>' +
                '</div>';

            if (isBooking) {
                card.classList.add('timeline-list-card--clickable');
                const bookingPayload = {
                    id: slot.booking_id, court_id: slot.court_id, direction_id: slot.direction_id,
                    date: slot.date, time_from: slot.starts_at, time_to: slot.ends_at,
                    title: slot.raw_title, color: slot.raw_color, is_guest: slot.is_guest,
                    organizer_id: slot.organizer_id, organizer_label: slot.organizer,
                    guest_name: slot.guest_name, guest_phone: slot.guest_phone,
                    booker_name: slot.booker_name, status: slot.status, price_total: slot.price_total,
                    court_name: slot.court_name, parent_booking_id: slot.parent_booking_id, is_series: slot.is_series,
                };
                card.addEventListener('click', function () {
                    if (typeof window.__openBookingDetails === 'function') window.__openBookingDetails(bookingPayload);
                });
            } else {
                const link = document.createElement('a');
                link.href = '/events/' + slot.event_id;
                link.className = 'btn btn-small btn-secondary';
                link.textContent = openEventLabel;
                card.querySelector('.timeline-list-actions').appendChild(link);
            }

            listGrid.appendChild(card);
        });
    }

    function allCourtsOrDirectionLabel(directionKey) {
        return allCourtsLabel + ' (' + (directionLabels[directionKey] || directionKey) + ')';
    }

    async function load() {
        updateLabel();
        loadingEl.style.display = '';
        try {
            const mode = state.view === 'list' ? 'day' : state.mode;
            const url = timelineUrlFor(currentLocationId) + '?date=' + fmtDate(state.date) + '&mode=' + mode;
            const res = await fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
            const data = await res.json();
            if (state.view === 'list') { renderList(data); }
            else if (state.mode === 'day') { renderDay(data); }
            else { renderWeek(data); }
        } catch (e) {
            dayGrid.innerHTML = ''; weekGrid.innerHTML = ''; listGrid.innerHTML = '';
        } finally {
            loadingEl.style.display = 'none';
        }
    }

    showList();
})();
</script>
