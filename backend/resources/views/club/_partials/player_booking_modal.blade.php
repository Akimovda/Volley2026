{{-- Модалка "Забронировать корт" — прямая бронь игроком (Фаза 5), НЕ владельцем.
     Переиспользует GET /locations/{id}/booking-windows (тот же endpoint, что форма
     создания события) — отдаёт только свободные слоты с ценой, без деталей чужих
     броней/событий (приватность занятости для игрока — см. CourtAvailabilityService).
     $location: Location с eager-loaded directions (is_active) + courts (is_active). --}}
@php
    $pbDirections = $location->directions->where('is_active', true)->values();
    $pbTz = $location->effectiveTimezone();
    $pbMinDate = now($pbTz)->toDateString();
    $pbMaxDate = now($pbTz)->addDays(30)->toDateString();
@endphp

<div id="playerBookingModalContent" class="booking-modal-content" style="display:none" data-single-direction="{{ $pbDirections->count() === 1 ? $pbDirections->first()->direction : '' }}">
    <div class="card" style="height:auto">
        <h3 class="-mt-05"><x-ico name="ball" /> {{ __('club.book_court') }}</h3>

        <form method="POST" action="{{ route('court_bookings.store') }}" id="pbForm">
            @csrf
            <input type="hidden" name="court_id" id="pbCourtId">
            <input type="hidden" name="time_from" id="pbTimeFrom">

            @if($pbDirections->count() > 1)
            <div class="mb-2">
                <label class="d-block mb-1 f-14 cd">{{ __('club.directions_title') }}</label>
                <select id="pbDirectionSelect" class="btn-small">
                    @foreach($pbDirections as $dir)
                    <option value="{{ $dir->direction }}">{{ $dir->direction === 'beach' ? __('club.direction_beach') : __('club.direction_classic') }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            <div class="mb-2">
                <label class="d-block mb-1 f-14 cd">{{ __('club.booking_date_label') }}</label>
                <input type="date" name="date" id="pbDate" class="btn-small" min="{{ $pbMinDate }}" max="{{ $pbMaxDate }}" value="{{ $pbMinDate }}">
            </div>

            <div class="mb-2">
                <label class="d-block mb-1 f-14 cd">{{ __('club.duration') }}</label>
                <select name="duration" id="pbDuration" class="btn-small">
                    <option value="30">{{ __('club.duration_30m') }}</option>
                    <option value="60" selected>{{ __('club.duration_1h') }}</option>
                    <option value="90">{{ __('club.duration_1h30m') }}</option>
                    <option value="120">{{ __('club.duration_2h') }}</option>
                    <option value="180">{{ __('club.duration_3h') }}</option>
                </select>
            </div>

            <div class="mb-1 f-14 cd">{{ __('club.available_slots') }}</div>
            <div id="pbLoading" class="alert alert-info" style="display:none">{{ __('club.loading_slots') }}</div>
            <div id="pbEmpty" class="alert alert-info" style="display:none">{{ __('club.no_free_slots') }}</div>
            <div id="pbPast" class="alert alert-info" style="display:none">{{ __('club.date_in_past') }}</div>
            <div id="pbGrid"></div>

            <div id="pbLegend" class="f-13 cd mt-2" style="display:none"></div>

            <div id="pbCostWrap" class="mt-2 mb-2" style="display:none;padding:12px 14px;border:2px solid var(--accent, #e7612f);border-radius:12px">
                <div class="f-14 cd" id="pbSummary"></div>
                <div class="f-20 b-700">{{ __('club.total_you') }}: <strong id="pbCostValue"></strong></div>
            </div>

            <button type="submit" class="btn btn-primary w-100" id="pbSubmitBtn" disabled>{{ __('club.send_request') }}</button>
        </form>
    </div>
</div>

<script>
    window.__cbIco = window.__cbIco || {
        indoor: {!! json_encode(ico_svg('building')) !!},
        outdoor: {!! json_encode(ico_svg('sun')) !!},
        esc: function (t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
    };
(function () {
    var content = document.getElementById('playerBookingModalContent');
    if (!content) return;

    var directionSelect = document.getElementById('pbDirectionSelect');
    var singleDirection = content.getAttribute('data-single-direction') || '';
    var dateInput = document.getElementById('pbDate');
    var durationSelect = document.getElementById('pbDuration');
    var loadingEl = document.getElementById('pbLoading');
    var emptyEl = document.getElementById('pbEmpty');
    var pastEl = document.getElementById('pbPast');
    var gridEl = document.getElementById('pbGrid');
    var costWrap = document.getElementById('pbCostWrap');
    var costValue = document.getElementById('pbCostValue');
    var summaryEl = document.getElementById('pbSummary');
    var legendEl = document.getElementById('pbLegend');
    var courtIdInput = document.getElementById('pbCourtId');
    var timeFromInput = document.getElementById('pbTimeFrom');
    var submitBtn = document.getElementById('pbSubmitBtn');

    var windowsUrl = @json(route('locations.booking_windows', $location));
    var freeLabel = @json(__('club.price_free'));
    var loc = @json(app()->getLocale() === 'en' ? 'en-GB' : 'ru-RU');
    var T = @json(['legend' => __('club.pb_legend_prices'), 'all' => __('club.tl_all_courts'), 'perHour' => __('club.tl_per_hour'), 'h' => __('club.tl_h'), 'min' => __('club.tl_min')]);

    function money(v) { return Number(v).toLocaleString(loc, { maximumFractionDigits: 0 }) + ' ₽'; }
    function hhmm(m) { return ('0' + Math.floor(m / 60)).slice(-2) + ':' + ('0' + (m % 60)).slice(-2); }
    function toMin(t) { var p = String(t).split(':'); return (+p[0]) * 60 + (+p[1]); }
    function durText(min) { var h = Math.floor(min / 60), m = min % 60; return (h ? h + ' ' + T.h : '') + (m ? (h ? ' ' : '') + m + ' ' + T.min : ''); }

    // Легенда «интервалы и стоимость» на выбранный день: корты с одинаковой сеткой цен объединены
    function renderLegend(data) {
        var courts = (data && data.courts) || [], rates = (data && data.rates) || {};
        var groups = {}, order = [];
        courts.forEach(function (c) {
            var segs = rates[c.id] || [];
            if (!segs.length) return;
            var sig = JSON.stringify(segs);
            if (!groups[sig]) { groups[sig] = { names: [], segs: segs }; order.push(sig); }
            groups[sig].names.push(c.name);
        });
        if (!order.length) { legendEl.style.display = 'none'; legendEl.innerHTML = ''; return; }
        var esc = window.__cbIco.esc;
        legendEl.innerHTML = '<div class="mb-1">' + esc(T.legend) + ':</div>' + order.map(function (sig) {
            var g = groups[sig];
            return '<div style="margin-bottom:4px">' + (order.length === 1 ? esc(T.all) : '<b>' + esc(g.names.join(', ')) + '</b>') + ': ' +
                g.segs.map(function (r) { return hhmm(r[0]) + '–' + hhmm(r[1]) + ' — <b>' + esc(money(r[2])) + esc(T.perHour) + '</b>'; }).join(' · ') + '</div>';
        }).join('');
        legendEl.style.display = '';
    }


    function clearSelection() {
        courtIdInput.value = '';
        timeFromInput.value = '';
        costWrap.style.display = 'none';
        submitBtn.disabled = true;
    }
    function hideLegend() { legendEl.style.display = 'none'; legendEl.innerHTML = ''; }

    function currentDirection() {
        return directionSelect ? directionSelect.value : singleDirection;
    }

    function renderGrid(data) {
        gridEl.innerHTML = '';
        clearSelection();
        renderLegend(data);

        if (data && data.is_past) {
            emptyEl.style.display = 'none';
            pastEl.style.display = '';
            return;
        }
        pastEl.style.display = 'none';

        var courts = (data && data.courts) || [];
        var slots = (data && data.slots) || {};
        var hasAny = courts.some(function (c) { return (slots[c.id] || []).length > 0; });

        emptyEl.style.display = hasAny ? 'none' : '';
        if (!hasAny) return;

        courts.forEach(function (court) {
            var courtSlots = slots[court.id] || [];
            if (!courtSlots.length) return;

            var block = document.createElement('div');
            block.className = 'mb-2';

            var title = document.createElement('div');
            title.className = 'b-600 f-16 mb-1';
            title.innerHTML = (court.is_indoor ? window.__cbIco.indoor : window.__cbIco.outdoor) + ' ' + window.__cbIco.esc(court.name);
            block.appendChild(title);

            var wrap = document.createElement('div');
            wrap.className = 'd-flex gap-1';
            wrap.style.flexWrap = 'wrap';

            courtSlots.forEach(function (slot) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-small btn-secondary';
                var priceText = (slot.price === null || slot.price === undefined) ? freeLabel : money(slot.price);
                btn.textContent = slot.start + ' · ' + priceText;
                btn.addEventListener('click', function () {
                    gridEl.querySelectorAll('button').forEach(function (b) { b.classList.remove('btn-primary'); b.classList.add('btn-secondary'); });
                    btn.classList.remove('btn-secondary');
                    btn.classList.add('btn-primary');

                    courtIdInput.value = String(court.id);
                    timeFromInput.value = slot.start;
                    costValue.textContent = priceText;
                    var dur = parseInt(durationSelect.value, 10) || 0;
                    summaryEl.textContent = court.name + ' · ' + dateInput.value + ' · ' + slot.start + '–' + hhmm(toMin(slot.start) + dur) + ' (' + durText(dur) + ')';
                    costWrap.style.display = '';
                    submitBtn.disabled = false;
                });
                wrap.appendChild(btn);
            });

            block.appendChild(wrap);
            gridEl.appendChild(block);
        });
    }

    function refresh() {
        var direction = currentDirection();
        var duration = durationSelect.value;
        var date = dateInput.value;

        gridEl.innerHTML = '';
        clearSelection();
        emptyEl.style.display = 'none';
        pastEl.style.display = 'none';
        hideLegend();

        if (!direction || !duration || !date) return;

        loadingEl.style.display = '';

        var url = windowsUrl + '?direction=' + encodeURIComponent(direction)
            + '&duration=' + encodeURIComponent(duration)
            + '&date=' + encodeURIComponent(date);

        fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                loadingEl.style.display = 'none';
                renderGrid(data);
            })
            .catch(function () {
                loadingEl.style.display = 'none';
                emptyEl.style.display = '';
            });
    }

    if (directionSelect) directionSelect.addEventListener('change', refresh);
    dateInput.addEventListener('change', refresh);
    durationSelect.addEventListener('change', refresh);

    window.__openPlayerBookingModal = function () {
        jQuery.fancybox.open({
            src: '#playerBookingModalContent',
            type: 'inline',
            opts: {
                baseClass: 'booking-modal-fancybox',
                hideScrollbar: false, touch: false, toolbar: false, smallBtn: true,
                animationEffect: 'zoom-in-out', transitionEffect: 'zoom-in-out', preventCaptionOverlap: false,
            }
        });
        refresh();
    };
})();
</script>
