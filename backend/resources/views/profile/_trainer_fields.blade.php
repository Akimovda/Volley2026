@php
    $tp = $user->trainerProfile ?? null;
    $trainerOn = (bool) old('trainer.enabled', $tp?->is_public ?? false);
@endphp
<div class="card-ramka mb-2" id="trainer">
    <input type="hidden" name="trainer_present" value="1">

    <label class="checkbox-item">
        <input type="checkbox" name="trainer[enabled]" value="1" id="trainer-enabled" @checked($trainerOn)>
        <div class="custom-checkbox"></div>
        <span class="b-600">{{ __('trainers.cp_check') }}</span>
    </label>
    <div class="f-14" style="opacity:.65;margin-top:.4rem">{{ __('trainers.cp_hint') }}</div>

    <div id="trainer-fields" class="mt-2" style="{{ $trainerOn ? '' : 'display:none' }}">
        <div class="card mb-2">
            <label>{{ __('trainers.field_specialization') }}</label>
            <input type="text" name="trainer[specialization]" maxlength="255"
                   value="{{ old('trainer.specialization', $tp->specialization ?? '') }}">
            @error('trainer.specialization')<div class="red b-600 f-14">{{ $message }}</div>@enderror
        </div>

        <div class="card mb-2">
            <label>{{ __('trainers.field_experience_years') }}</label>
            <input type="number" name="trainer[experience_years]" min="0" max="80"
                   value="{{ old('trainer.experience_years', $tp->experience_years ?? '') }}">
            @error('trainer.experience_years')<div class="red b-600 f-14">{{ $message }}</div>@enderror
        </div>

        <div class="card">
            <label>{{ __('trainers.field_bio') }}</label>
            <input id="trainer_bio" type="hidden" name="trainer[bio]"
                   value="{{ old('trainer.bio', $tp?->bio ? $tp->bio_html : '') }}">
            <trix-editor input="trainer_bio" class="trix-content"
                         data-direct-upload-url="#" data-blob-url-template="#"></trix-editor>
            @error('trainer.bio')<div class="red b-600 f-14">{{ $message }}</div>@enderror
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var cb = document.getElementById('trainer-enabled');
    var box = document.getElementById('trainer-fields');
    if (!cb || !box) return;
    cb.addEventListener('change', function () { box.style.display = cb.checked ? '' : 'none'; });
    if (location.hash === '#trainer') {
        var el = document.getElementById('trainer');
        if (el) el.scrollIntoView({ block: 'start' });
    }
});
document.addEventListener('trix-file-accept', function (e) { e.preventDefault(); });
</script>
