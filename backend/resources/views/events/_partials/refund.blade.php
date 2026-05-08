{{--
    Partial: events._partials.refund

    Блок "Условия возврата" для occurrence_edit: три числовых поля —
    полный возврат (часов), частичный возврат (часов), процент частичного.

    Expects in scope:
      - $refundFull    (int|null) — часов до начала для полного возврата
      - $refundPartial (int|null) — часов до начала для частичного
      - $refundPct     (int|null) — процент частичного возврата (0..100)
--}}
<div class="ramka">
    <h2 class="-mt-05">{{ __('events.occ_refund_title') }}</h2>
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <label>{{ __('events.occ_refund_full') }}</label>
                <input type="number" name="refund_hours_full" min="0" max="720" value="{{ old('refund_hours_full', $refundFull) }}">
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <label>{{ __('events.occ_refund_partial') }}</label>
                <input type="number" name="refund_hours_partial" min="0" max="720" value="{{ old('refund_hours_partial', $refundPartial) }}">
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <label>{{ __('events.occ_refund_pct') }}</label>
                <input type="number" name="refund_partial_pct" min="0" max="100" value="{{ old('refund_partial_pct', $refundPct) }}">
            </div>
        </div>
    </div>
</div>
