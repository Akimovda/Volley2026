{{-- Модалка "Добавить бронь" — переиспользуется на locations/show и club/bookings.
     $locations: коллекция Location с eager-loaded directions.courts (is_active) --}}
@php
$locationsJs = $locations->map(function ($loc) {
    return [
        'id' => $loc->id,
        'name' => $loc->name,
        'directions' => $loc->directions->map(function ($dir) {
            return [
                'id' => $dir->id,
                'direction' => $dir->direction,
                'courts' => $dir->courts->map(fn($c) => ['id' => $c->id, 'name' => $c->name])->values(),
            ];
        })->values(),
    ];
})->values();
@endphp

<div id="addBookingModalContent" style="display:none; max-width: 42rem">
    <div class="card">
        <h3 class="-mt-05">{{ __('club.add_booking') }}</h3>

        @if(session('error'))
        <div class="alert alert-error">{{ session('error') }}</div>
        @endif

        <form method="POST" action="{{ route('club.bookings.storeManual') }}" class="form" id="addBookingForm">
            @csrf

            @if($locations->count() > 1)
            <div class="mb-2">
                <label>{{ __('club.booking_location_label') }}</label>
                <select id="abLocationSelect">
                    @foreach($locationsJs as $loc)
                    <option value="{{ $loc['id'] }}">{{ $loc['name'] }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            <div class="mb-2" id="abDirectionWrap">
                <label>{{ __('club.directions_title') }}</label>
                <select id="abDirectionSelect"></select>
            </div>

            <div class="mb-2">
                <label>{{ __('club.court_label') }}</label>
                <select name="court_id" id="abCourtSelect" required></select>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="mb-2">
                        <label>{{ __('club.booking_date_label') }}</label>
                        <input type="date" name="date" id="abDate" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-2">
                        <label>{{ __('club.time_from') }}</label>
                        <select name="time_from" id="abTimeFrom" required></select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-2">
                        <label>{{ __('club.time_to') }}</label>
                        <select name="time_to" id="abTimeTo" required></select>
                    </div>
                </div>
            </div>

            <div class="mb-2">
                <label class="b-600 d-block mb-1">{{ __('club.client') }}</label>
                <div class="d-flex gap-1 mb-1">
                    <label class="d-flex fvc gap-1">
                        <input type="radio" name="client_kind" value="user" id="abClientUser" checked>
                        {{ __('club.platform_user') }}
                    </label>
                    <label class="d-flex fvc gap-1">
                        <input type="radio" name="client_kind" value="guest" id="abClientGuest">
                        {{ __('club.guest') }}
                    </label>
                </div>

                <div id="abUserBlock" style="position:relative">
                    <input type="text" id="abUserSearchInput" autocomplete="off" class="form-control"
                        placeholder="{{ __('club.trust_search_placeholder') }}">
                    <div id="abUserSearchDd" class="form-select-dropdown trainer_dd"></div>
                    <input type="hidden" name="organizer_id" id="abOrganizerId">
                </div>

                <div id="abGuestBlock" style="display:none">
                    <input type="text" name="guest_name" id="abGuestName" maxlength="150" placeholder="{{ __('club.guest_name') }}" class="mb-1">
                    <input type="text" name="guest_phone" id="abGuestPhone" maxlength="30" placeholder="{{ __('club.guest_phone') }} ({{ __('club.optional_hint') }})">
                </div>
            </div>

            <div class="mb-2">
                <label>{{ __('club.booking_status_label') }}</label>
                <select name="status" id="abStatus">
                    <option value="confirmed">{{ __('club.status_confirmed') }}</option>
                    <option value="paid">{{ __('club.status_paid') }}</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary w-100">{{ __('admin.btn_save_changes') }}</button>
        </form>
    </div>
</div>

<script>
(function () {
    const locations = @json($locationsJs);
    const locSelect = document.getElementById('abLocationSelect');
    const dirSelect = document.getElementById('abDirectionSelect');
    const dirWrap = document.getElementById('abDirectionWrap');
    const courtSelect = document.getElementById('abCourtSelect');
    const dateInput = document.getElementById('abDate');
    const timeFrom = document.getElementById('abTimeFrom');
    const timeTo = document.getElementById('abTimeTo');
    const directionLabels = {
        classic: @json(__('club.direction_classic')),
        beach: @json(__('club.direction_beach')),
    };

    function fillTimeSelect(sel) {
        sel.innerHTML = '';
        for (let m = 0; m <= 23 * 60 + 30; m += 30) {
            const h = String(Math.floor(m / 60)).padStart(2, '0');
            const mm = String(m % 60).padStart(2, '0');
            const opt = document.createElement('option');
            opt.value = h + ':' + mm;
            opt.textContent = h + ':' + mm;
            sel.appendChild(opt);
        }
    }
    fillTimeSelect(timeFrom);
    fillTimeSelect(timeTo);
    timeTo.value = '10:00';

    function currentLocation() {
        const id = locSelect ? parseInt(locSelect.value, 10) : (locations[0] && locations[0].id);
        return locations.find(l => l.id === id) || locations[0] || null;
    }

    function fillDirections() {
        const loc = currentLocation();
        dirSelect.innerHTML = '';
        if (!loc) return;
        loc.directions.forEach(d => {
            const opt = document.createElement('option');
            opt.value = d.id;
            opt.textContent = directionLabels[d.direction] || d.direction;
            dirSelect.appendChild(opt);
        });
        dirWrap.style.display = loc.directions.length > 1 ? '' : 'none';
        fillCourts();
    }

    function fillCourts() {
        const loc = currentLocation();
        courtSelect.innerHTML = '';
        if (!loc) return;
        const dirId = parseInt(dirSelect.value, 10);
        const dir = loc.directions.find(d => d.id === dirId) || loc.directions[0];
        if (!dir) return;
        dir.courts.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.id;
            opt.textContent = c.name;
            courtSelect.appendChild(opt);
        });
    }

    if (locSelect) locSelect.addEventListener('change', fillDirections);
    dirSelect.addEventListener('change', fillCourts);
    fillDirections();

    const today = new Date();
    dateInput.value = today.toISOString().slice(0, 10);

    // Переключатель "пользователь платформы" / "гость"
    const userBlock = document.getElementById('abUserBlock');
    const guestBlock = document.getElementById('abGuestBlock');
    const clientUser = document.getElementById('abClientUser');
    const clientGuest = document.getElementById('abClientGuest');
    function updateClientKind() {
        const isUser = clientUser.checked;
        userBlock.style.display = isUser ? '' : 'none';
        guestBlock.style.display = isUser ? 'none' : '';
    }
    clientUser.addEventListener('change', updateClientKind);
    clientGuest.addEventListener('change', updateClientKind);
    updateClientKind();

    // Автокомплит поиска пользователя (паттерн блока "Доверенные организаторы")
    const uInput = document.getElementById('abUserSearchInput');
    const uDd = document.getElementById('abUserSearchDd');
    const uHidden = document.getElementById('abOrganizerId');
    let uTimer = null;

    function escHtml(s) { return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }
    function showDd() { uDd.classList.add('form-select-dropdown--active'); }
    function hideDd() { uDd.classList.remove('form-select-dropdown--active'); }

    function pickUser(id, label) {
        uHidden.value = id;
        uInput.value = label;
        hideDd();
        uDd.innerHTML = '';
    }

    uInput.addEventListener('input', function () {
        clearTimeout(uTimer);
        uHidden.value = '';
        const q = uInput.value.trim();
        if (q.length < 2) { hideDd(); return; }

        uTimer = setTimeout(function () {
            fetch('/ajax/users/search?q=' + encodeURIComponent(q), {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            })
            .then(r => r.json())
            .then(data => {
                const items = data.items || [];
                uDd.innerHTML = '';
                if (!items.length) {
                    uDd.innerHTML = '<div class="city-message">' + @json(__('ui.not_found')) + '</div>';
                    showDd();
                    return;
                }
                items.forEach(function (item) {
                    const div = document.createElement('div');
                    div.className = 'trainer-item form-select-option';
                    div.innerHTML = '<div class="text-sm text-gray-900">' + escHtml(item.label || item.name) + '</div>';
                    div.addEventListener('click', function () { pickUser(item.id, item.label || item.name); });
                    uDd.appendChild(div);
                });
                showDd();
            })
            .catch(function () { hideDd(); });
        }, 250);
    });

    document.addEventListener('click', function (e) {
        if (userBlock && !userBlock.contains(e.target)) hideDd();
    });

    window.__openAddBookingModal = function (presetDate) {
        if (presetDate) dateInput.value = presetDate;
        jQuery.fancybox.open({
            src: '#addBookingModalContent',
            type: 'inline',
            opts: { hideScrollbar: false, touch: false, toolbar: false, smallBtn: true, animationEffect: 'zoom-in-out', transitionEffect: 'zoom-in-out', preventCaptionOverlap: false }
        });
    };
})();
</script>
