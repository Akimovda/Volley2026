@php
$_activityGate = auth()->check()
    && (config('activity.recording_open') || auth()->user()->isAdmin());
@endphp
@if($_activityGate && isset($occurrence))
<div class="activity-record-entry" style="display:none">
    <a href="{{ route('activity.record', ['occurrence' => $occurrence->id]) }}"
       class="btn btn-secondary w-100 mt-1">
        {{ __('activity.record_btn') }}
    </a>
</div>
<script>
(function () {
    if (!document.documentElement.classList.contains('is-app') || !window.Capacitor) return;
    var btn = document.currentScript.previousElementSibling;
    if (btn) btn.style.display = '';
})();
</script>
@endif
