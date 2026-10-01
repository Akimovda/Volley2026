@php
    /** @var string $mode create|edit; @var bool $defaultOn */
    $me = auth()->user();
    $meTrainer = $me && $me->trainerProfile && $me->trainerProfile->is_public;
    $meLabel = $me ? (trim(($me->last_name ?? '') . ' ' . ($me->first_name ?? '')) ?: ($me->name ?? ('#' . $me->id))) : '';
@endphp
@if($meTrainer)
<div class="mb-1" id="trainer-self-wrap">
    <label class="checkbox-item">
        <input type="checkbox" id="trainer-self-cb">
        <div class="custom-checkbox"></div>
        <span>{{ __('events.trainer_self_label') }}</span>
    </label>
</div>
<script>
(function () {
    var meId = {{ (int) $me->id }};
    var meLabel = @json($meLabel);
    var mode = @json($mode);
    var defaultOn = @json((bool) ($defaultOn ?? false));
    var cb = document.getElementById('trainer-self-cb');
    if (!cb) return;

    var chips = document.getElementById(mode === 'edit' ? 'mgmt_trainer_chips' : 'trainer_chips');

    function has() {
        var sel = mode === 'edit' ? 'input[data-mgmt-trainer-hidden="' : 'input[data-trainer-hidden="';
        return !!(chips && chips.querySelector(sel + meId + '"]'));
    }
    function add() {
        if (mode === 'edit') { window.__mgmtTrainerApi && window.__mgmtTrainerApi.add(meId, meLabel); }
        else if (window.addTrainerChip) { window.addTrainerChip(meId, meLabel); }
    }
    function remove() {
        if (mode === 'edit') { window.__mgmtTrainerApi && window.__mgmtTrainerApi.rm(meId); return; }
        var b = chips && chips.querySelector('.trainer-chip-remove[data-id="' + meId + '"]');
        if (b) b.click();
    }
    function ready() {
        return mode === 'edit' ? !!window.__mgmtTrainerApi : !!window.addTrainerChip;
    }

    function boot() {
        if (!ready() || !chips) return setTimeout(boot, 150);
        if (defaultOn && chips.querySelectorAll('input[type=hidden]').length === 0) add();
        cb.checked = has();
        new MutationObserver(function () { cb.checked = has(); }).observe(chips, { childList: true });
        cb.addEventListener('change', function () { cb.checked ? add() : remove(); });
    }
    boot();
})();
</script>
@endif
