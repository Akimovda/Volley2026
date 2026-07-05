{{-- Модалка деталей брони (клик по блоку в таймлайне) + подмодалка отмены.
     Требует уже подключённый club._partials.booking_modal на той же странице
     (использует window.__openBookingModalForEdit/ForCopy). --}}
<div id="bookingDetailsModalContent" style="display:none; max-width: 32rem">
    <div class="card">
        <h3 class="-mt-05" id="bdTitle"></h3>
        <div class="f-16 mb-1"><span class="cd">{{ __('club.booking_by') }}:</span> <span id="bdClient"></span></div>
        <div class="f-16 mb-1"><span class="cd">{{ __('club.court_label') }}:</span> <span id="bdCourt"></span></div>
        <div class="f-16 mb-1"><span class="cd">{{ __('club.booking_date_label') }}:</span> <span id="bdDateTime"></span></div>
        <div class="f-16 mb-1"><span class="cd">{{ __('club.booking_status_label') }}:</span> <span id="bdStatus"></span></div>
        <div class="f-16 mb-2"><span class="cd">{{ __('club.booking_price') }}:</span> <span id="bdPrice"></span></div>

        <div class="d-flex gap-1" style="flex-wrap:wrap">
            <button type="button" class="btn btn-small btn-primary" id="bdEditBtn">{{ __('club.edit_booking') }}</button>
            <button type="button" class="btn btn-small btn-secondary" id="bdCopyBtn">{{ __('club.copy_booking') }}</button>
            <button type="button" class="btn btn-small btn-secondary" id="bdCancelBtn">{{ __('club.cancel_booking') }}</button>
        </div>
    </div>
</div>

<div id="bookingCancelModalContent" style="display:none; max-width: 28rem">
    <div class="card">
        <h3 class="-mt-05">{{ __('club.cancel_booking') }}</h3>
        <form method="POST" class="form" id="bcForm" data-cancel-url-template="{{ route('club.bookings.cancel', ['booking' => '__ID__']) }}">
            @csrf
            <div class="mb-2" id="bcScopeWrap" style="display:none">
                <label class="d-flex fvc gap-1 mb-1">
                    <input type="radio" name="scope" value="only_this" checked>
                    {{ __('club.only_this') }}
                </label>
                <label class="d-flex fvc gap-1">
                    <input type="radio" name="scope" value="this_and_following">
                    {{ __('club.this_and_following') }}
                </label>
            </div>
            <div class="mb-2">
                <label>{{ __('club.cancel_reason') }}</label>
                <textarea name="reason" id="bcReason" rows="3" placeholder="{{ __('club.cancel_reason') }} ({{ __('club.optional_hint') }})"></textarea>
            </div>
            <button type="submit" class="btn btn-alert w-100">{{ __('club.cancel_booking') }}</button>
        </form>
    </div>
</div>

<script>
(function () {
    const detailsEl = document.getElementById('bookingDetailsModalContent');
    if (!detailsEl) return;

    const bdTitle = document.getElementById('bdTitle');
    const bdClient = document.getElementById('bdClient');
    const bdCourt = document.getElementById('bdCourt');
    const bdDateTime = document.getElementById('bdDateTime');
    const bdStatus = document.getElementById('bdStatus');
    const bdPrice = document.getElementById('bdPrice');
    const bdEditBtn = document.getElementById('bdEditBtn');
    const bdCopyBtn = document.getElementById('bdCopyBtn');
    const bdCancelBtn = document.getElementById('bdCancelBtn');

    const bcForm = document.getElementById('bcForm');
    const bcScopeWrap = document.getElementById('bcScopeWrap');
    const cancelUrlTemplate = bcForm.getAttribute('data-cancel-url-template');

    const statusLabels = {
        pending: @json(__('club.status_pending')),
        confirmed: @json(__('club.status_confirmed')),
        paid: @json(__('club.status_paid')),
    };
    const freeLabel = @json(__('club.price_free'));

    let currentBooking = null;

    function closeFancybox() {
        if (window.jQuery && jQuery.fancybox) jQuery.fancybox.close();
    }

    window.__openBookingDetails = function (booking) {
        currentBooking = booking;
        bdTitle.textContent = booking.title || booking.booker_name || '';
        bdClient.textContent = booking.booker_name + (booking.is_guest && booking.guest_phone ? ' (' + booking.guest_phone + ')' : '');
        bdCourt.textContent = booking.court_name || '';
        bdDateTime.textContent = (booking.date || '') + ' ' + (booking.time_from || booking.starts_at || '') + '–' + (booking.time_to || booking.ends_at || '');
        bdStatus.textContent = statusLabels[booking.status] || booking.status || '';
        bdPrice.textContent = (booking.price_total === null || booking.price_total === undefined) ? freeLabel : (booking.price_total + ' ₽');

        jQuery.fancybox.open({
            src: '#bookingDetailsModalContent',
            type: 'inline',
            opts: { hideScrollbar: false, touch: false, toolbar: false, smallBtn: true, animationEffect: 'zoom-in-out', transitionEffect: 'zoom-in-out', preventCaptionOverlap: false }
        });
    };

    bdEditBtn.addEventListener('click', function () {
        closeFancybox();
        setTimeout(function () { window.__openBookingModalForEdit(currentBooking); }, 200);
    });

    bdCopyBtn.addEventListener('click', function () {
        closeFancybox();
        setTimeout(function () { window.__openBookingModalForCopy(currentBooking); }, 200);
    });

    window.__openBookingCancelModal = function (booking) {
        bcForm.setAttribute('action', cancelUrlTemplate.replace('__ID__', booking.id));
        bcScopeWrap.style.display = booking.is_series ? '' : 'none';
        jQuery.fancybox.open({
            src: '#bookingCancelModalContent',
            type: 'inline',
            opts: { hideScrollbar: false, touch: false, toolbar: false, smallBtn: true, animationEffect: 'zoom-in-out', transitionEffect: 'zoom-in-out', preventCaptionOverlap: false }
        });
    };

    bdCancelBtn.addEventListener('click', function () {
        closeFancybox();
        setTimeout(function () { window.__openBookingCancelModal(currentBooking); }, 200);
    });
})();
</script>
