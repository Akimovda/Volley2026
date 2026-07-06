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
        <h3 class="-mt-05">🏐 {{ __('club.book_court') }}</h3>

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

            <div id="pbCostWrap" class="mt-2 mb-2 f-16" style="display:none">
                {{ __('club.cost') }}: <strong id="pbCostValue"></strong>
            </div>

            <button type="submit" class="btn btn-primary w-100" id="pbSubmitBtn" disabled>{{ __('club.send_request') }}</button>
        </form>
    </div>
</div>

<script>
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
    var courtIdInput = document.getElementById('pbCourtId');
    var timeFromInput = document.getElementById('pbTimeFrom');
    var submitBtn = document.getElementById('pbSubmitBtn');

    var windowsUrl = @json(route('locations.booking_windows', $location));
    var freeLabel = @json(__('club.price_free'));

    function clearSelection() {
        courtIdInput.value = '';
        timeFromInput.value = '';
        costWrap.style.display = 'none';
        submitBtn.disabled = true;
    }

    function currentDirection() {
        return directionSelect ? directionSelect.value : singleDirection;
    }

    function renderGrid(data) {
        gridEl.innerHTML = '';
        clearSelection();

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
            title.textContent = (court.is_indoor ? '🏠 ' : '☀️ ') + court.name;
            block.appendChild(title);

            var wrap = document.createElement('div');
            wrap.className = 'd-flex gap-1';
            wrap.style.flexWrap = 'wrap';

            courtSlots.forEach(function (slot) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-small btn-secondary';
                var priceText = (slot.price === null || slot.price === undefined) ? freeLabel : (Number(slot.price) + ' ₽');
                btn.textContent = slot.start + ' · ' + priceText;
                btn.addEventListener('click', function () {
                    gridEl.querySelectorAll('button').forEach(function (b) { b.classList.remove('btn-primary'); b.classList.add('btn-secondary'); });
                    btn.classList.remove('btn-secondary');
                    btn.classList.add('btn-primary');

                    courtIdInput.value = String(court.id);
                    timeFromInput.value = slot.start;
                    costValue.textContent = priceText;
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
