{{-- Модалка "Добавить/редактировать/копировать бронь" — переиспользуется на locations/show и club/bookings.
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
                'courts' => $dir->courts->map(fn($c) => ['id' => $c->id, 'name' => $c->name, 'is_indoor' => (bool) $c->is_indoor])->values(),
            ];
        })->values(),
    ];
})->values();
@endphp

<div id="addBookingModalContent" style="display:none; max-width: 42rem">
    <div class="card" style="height:auto;max-height:80vh;overflow-y:auto">
        <h3 class="-mt-05" id="abModalTitle">{{ __('club.add_booking') }}</h3>

        @if(session('error'))
        <div class="alert alert-error">{{ session('error') }}</div>
        @endif

        <form method="POST" action="{{ route('club.bookings.storeManual') }}" class="form" id="addBookingForm"
              data-store-url="{{ route('club.bookings.storeManual') }}"
              data-update-url-template="{{ route('club.bookings.update', ['booking' => '__ID__']) }}">
            @csrf
            <input type="hidden" name="_method" id="abMethod" value="">

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

            {{-- Редактирование: один корт (бронь атомарна) --}}
            <div class="mb-2" id="abCourtSelectWrap" style="display:none">
                <label>{{ __('club.court_label') }}</label>
                <select name="court_id" id="abCourtSelect"></select>
            </div>

            {{-- Добавление/копирование: можно выбрать несколько кортов сразу (напр. под турнир) --}}
            <div class="mb-2" id="abCourtCheckboxWrap">
                <label>{{ __('club.courts_label') }}</label>
                <div id="abCourtCheckboxes" class="d-flex gap-1" style="flex-wrap:wrap"></div>
                <div class="f-14 cd mt-1" id="abCourtCheckboxError" style="display:none">{{ __('club.select_at_least_one_court') }}</div>
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
                <label>{{ __('club.booking_title_label') }}</label>
                <input type="text" name="title" id="abTitle" maxlength="150" placeholder="{{ __('club.booking_title_placeholder') }}">
            </div>

            <div class="mb-2">
                <label>{{ __('club.booking_color') }}</label>
                @include('club._partials.color_palette', ['name' => 'color', 'selected' => null, 'inputId' => 'abColor'])
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

            <div class="mb-2" id="abRepeatBlock">
                <label>{{ __('club.repeat_label') }}</label>
                <select name="repeat" id="abRepeat">
                    <option value="none">{{ __('club.repeat_none') }}</option>
                    <option value="daily">{{ __('club.repeat_daily') }}</option>
                    <option value="weekly">{{ __('club.repeat_weekly') }}</option>
                    <option value="biweekly">{{ __('club.repeat_biweekly') }}</option>
                </select>
            </div>
            <div class="mb-2" id="abRepeatUntilWrap" style="display:none">
                <label>{{ __('club.repeat_until') }}</label>
                <input type="date" name="repeat_until" id="abRepeatUntil">
            </div>

            <button type="submit" class="btn btn-primary w-100" id="abSubmitBtn">{{ __('admin.btn_save_changes') }}</button>
        </form>
    </div>
</div>

<script>
(function () {
    const locations = @json($locationsJs);
    const form = document.getElementById('addBookingForm');
    const methodInput = document.getElementById('abMethod');
    const modalTitle = document.getElementById('abModalTitle');
    const submitBtn = document.getElementById('abSubmitBtn');
    const locSelect = document.getElementById('abLocationSelect');
    const dirSelect = document.getElementById('abDirectionSelect');
    const dirWrap = document.getElementById('abDirectionWrap');
    const courtSelect = document.getElementById('abCourtSelect');
    const courtSelectWrap = document.getElementById('abCourtSelectWrap');
    const courtCheckboxWrap = document.getElementById('abCourtCheckboxWrap');
    const courtCheckboxes = document.getElementById('abCourtCheckboxes');
    const courtCheckboxError = document.getElementById('abCourtCheckboxError');
    let currentMode = 'add';
    const dateInput = document.getElementById('abDate');
    const timeFrom = document.getElementById('abTimeFrom');
    const timeTo = document.getElementById('abTimeTo');
    const titleInput = document.getElementById('abTitle');
    const repeatBlock = document.getElementById('abRepeatBlock');
    const repeatSelect = document.getElementById('abRepeat');
    const repeatUntilWrap = document.getElementById('abRepeatUntilWrap');
    const repeatUntilInput = document.getElementById('abRepeatUntil');
    const storeUrl = form.getAttribute('data-store-url');
    const updateUrlTemplate = form.getAttribute('data-update-url-template');
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

    function findLocationByCourtId(courtId) {
        for (const loc of locations) {
            for (const dir of loc.directions) {
                if (dir.courts.some(c => c.id === courtId)) return { loc, dir };
            }
        }
        return null;
    }

    // createCustomSelect() снимает копию <option> ОДИН раз в момент инициализации.
    // Когда fillDirections()/fillCourts() позже переписывают options через innerHTML,
    // кастомная обёртка (видимый дропдаун) остаётся со СТАРЫМ набором опций —
    // именно поэтому список кортов визуально "не обновлялся" при смене направления.
    // Пересоздаём обёртку каждый раз после того, как реально поменяли <option>ы.
    function rebuildCustomSelect(selectEl) {
        if (!selectEl || !window.jQuery) return;
        var $el = jQuery(selectEl);
        if (window.customSelect && typeof window.customSelect.destroy === 'function') {
            window.customSelect.destroy(selectEl.id);
        }
        $el.off('change'); // снимаем jQuery-обработчик, оставшийся от предыдущей обёртки
        if (typeof window.createCustomSelect === 'function') {
            window.createCustomSelect($el);
            $el.data('custom-initialized', true);
        }
    }

    function fillDirections(preselectDirId) {
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
        if (preselectDirId) dirSelect.value = String(preselectDirId);
        rebuildCustomSelect(dirSelect);
        fillCourts();
    }

    function fillCourts(preselectCourtId) {
        const loc = currentLocation();
        courtSelect.innerHTML = '';
        courtCheckboxes.innerHTML = '';
        if (!loc) return;
        const dirId = parseInt(dirSelect.value, 10);
        const dir = loc.directions.find(d => d.id === dirId) || loc.directions[0];
        if (!dir) return;
        dir.courts.forEach(c => {
            const label = (c.is_indoor ? '🏠 ' : '☀️ ') + c.name;

            const opt = document.createElement('option');
            opt.value = c.id;
            opt.textContent = label;
            courtSelect.appendChild(opt);

            const cbLabel = document.createElement('label');
            cbLabel.className = 'd-flex fvc gap-1 checkbox-item';
            const cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.name = 'court_ids[]';
            cb.value = String(c.id);
            if (preselectCourtId && c.id === preselectCourtId) cb.checked = true;
            cbLabel.appendChild(cb);
            cbLabel.appendChild(document.createTextNode(' ' + label));
            courtCheckboxes.appendChild(cbLabel);
        });
        if (preselectCourtId) courtSelect.value = String(preselectCourtId);
        applyCourtFieldMode();
        rebuildCustomSelect(courtSelect);
    }

    // В режиме edit активен select (name=court_id), чекбоксы отключены (не должны
    // попасть в POST); в add/copy — наоборот, select отключён, активны чекбоксы.
    function applyCourtFieldMode() {
        const isEdit = currentMode === 'edit';
        courtSelectWrap.style.display = isEdit ? '' : 'none';
        courtCheckboxWrap.style.display = isEdit ? 'none' : '';
        courtSelect.disabled = !isEdit;
        courtSelect.required = isEdit;
        courtCheckboxes.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
            cb.disabled = isEdit;
        });
        if (!isEdit) courtCheckboxError.style.display = 'none';
    }

    if (locSelect) locSelect.addEventListener('change', function () { fillDirections(); });
    dirSelect.addEventListener('change', function () { fillCourts(); });
    fillDirections();

    function todayStr() {
        const d = new Date();
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }
    dateInput.value = todayStr();

    function updateRepeatUntilMax() {
        if (!dateInput.value) return;
        const d = new Date(dateInput.value + 'T00:00:00');
        d.setMonth(d.getMonth() + 3);
        repeatUntilInput.max = d.toISOString().slice(0, 10);
        repeatUntilInput.min = dateInput.value;
    }
    dateInput.addEventListener('change', updateRepeatUntilMax);
    updateRepeatUntilMax();

    // fancybox (type:'inline') замеряет высоту контента один раз при открытии и не
    // пересчитывает её при последующих изменениях DOM — нужно явно дёргать update(),
    // иначе выросший блок (repeat_until, гость/пользователь) наезжает на кнопку "Сохранить".
    function refreshFancyboxSize() {
        if (window.jQuery && jQuery.fancybox && typeof jQuery.fancybox.getInstance === 'function') {
            var inst = jQuery.fancybox.getInstance();
            if (inst && typeof inst.update === 'function') inst.update();
        }
    }

    repeatSelect.addEventListener('change', function () {
        repeatUntilWrap.style.display = repeatSelect.value === 'none' ? 'none' : '';
        refreshFancyboxSize();
    });

    // Переключатель "пользователь платформы" / "гость"
    const userBlock = document.getElementById('abUserBlock');
    const guestBlock = document.getElementById('abGuestBlock');
    const clientUser = document.getElementById('abClientUser');
    const clientGuest = document.getElementById('abClientGuest');
    function updateClientKind() {
        const isUser = clientUser.checked;
        userBlock.style.display = isUser ? '' : 'none';
        guestBlock.style.display = isUser ? 'none' : '';
        refreshFancyboxSize();
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

    function resetFormDefaults() {
        form.reset();
        dateInput.value = todayStr();
        timeTo.value = '10:00';
        methodInput.value = '';
        fillDirections();
        updateClientKind();
        repeatUntilWrap.style.display = 'none';
        updateRepeatUntilMax();
    }

    function openModal() {
        jQuery.fancybox.open({
            src: '#addBookingModalContent',
            type: 'inline',
            opts: { hideScrollbar: false, touch: false, toolbar: false, smallBtn: true, animationEffect: 'zoom-in-out', transitionEffect: 'zoom-in-out', preventCaptionOverlap: false }
        });
    }

    /**
     * mode: 'add' | 'edit' | 'copy'
     * booking: null (чистое добавление) или объект с полями брони (см. TimelineService::fetchCourtBookingSlots
     * и club/bookings.blade.php renderBooking) — id, location_id, direction_id, court_id, date, time_from,
     * time_to, title, color, client_kind, organizer_id, organizer_label, guest_name, guest_phone, status.
     */
    window.__openBookingModal = function (mode, booking) {
        currentMode = mode;
        resetFormDefaults();

        if (mode === 'edit') {
            modalTitle.textContent = @json(__('club.edit_booking'));
            submitBtn.textContent = @json(__('admin.btn_save_changes'));
            methodInput.value = 'PUT';
            form.setAttribute('action', updateUrlTemplate.replace('__ID__', booking.id));
            repeatBlock.style.display = 'none';
            repeatUntilWrap.style.display = 'none';
        } else if (mode === 'copy') {
            modalTitle.textContent = @json(__('club.copy_booking'));
            submitBtn.textContent = @json(__('club.add_booking'));
            methodInput.value = '';
            form.setAttribute('action', storeUrl);
            repeatBlock.style.display = '';
        } else {
            modalTitle.textContent = @json(__('club.add_booking'));
            submitBtn.textContent = @json(__('admin.btn_save_changes'));
            methodInput.value = '';
            form.setAttribute('action', storeUrl);
            repeatBlock.style.display = '';
        }

        if (!booking) {
            openModal();
            return;
        }

        if (booking.court_id && locSelect) {
            const found = findLocationByCourtId(booking.court_id);
            if (found) locSelect.value = String(found.loc.id);
        }
        fillDirections(booking.direction_id);
        fillCourts(booking.court_id);

        if (mode !== 'copy' && booking.date) dateInput.value = booking.date;
        if (booking.time_from) timeFrom.value = booking.time_from;
        if (booking.time_to) timeTo.value = booking.time_to;
        titleInput.value = booking.title || '';

        if (booking.color) {
            const colorRadio = form.querySelector('input[name="color"][value="' + booking.color + '"]');
            if (colorRadio) colorRadio.checked = true;
        }

        if (booking.client_kind === 'guest' || booking.is_guest) {
            clientGuest.checked = true;
            document.getElementById('abGuestName').value = booking.guest_name || '';
            document.getElementById('abGuestPhone').value = booking.guest_phone || '';
        } else {
            clientUser.checked = true;
            uHidden.value = booking.organizer_id || '';
            uInput.value = booking.organizer_label || booking.booker_name || '';
        }
        updateClientKind();

        if (booking.status) document.getElementById('abStatus').value = booking.status;

        updateRepeatUntilMax();
        openModal();
    };

    courtCheckboxes.addEventListener('change', function (e) {
        if (e.target && e.target.type === 'checkbox' && e.target.checked) {
            courtCheckboxError.style.display = 'none';
        }
    });

    form.addEventListener('submit', function (e) {
        if (currentMode === 'edit') return;
        const anyChecked = Array.prototype.some.call(
            courtCheckboxes.querySelectorAll('input[type="checkbox"]'),
            function (cb) { return cb.checked; }
        );
        if (!anyChecked) {
            e.preventDefault();
            courtCheckboxError.style.display = '';
            refreshFancyboxSize();
        }
    });

    window.__openAddBookingModal = function (presetDate) {
        window.__openBookingModal('add', presetDate ? { date: presetDate } : null);
    };

    window.__openBookingModalForEdit = function (booking) {
        window.__openBookingModal('edit', booking);
    };

    window.__openBookingModalForCopy = function (booking) {
        window.__openBookingModal('copy', booking);
    };
})();
</script>
