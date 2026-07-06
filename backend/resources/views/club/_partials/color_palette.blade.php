{{-- Общая палитра цветов (события в таймлайне / название и цвет брони).
     Параметры: $name (radio input name), $selected (текущее значение), $inputId (опционально, префикс id) --}}
@php
$paletteColors = ['#4A9EFF','#34C759','#FFD60A','#E7612F','#AF52DE','#FF3B30','#5AC8FA','#8E8E93'];
$inputId = $inputId ?? $name;
@endphp
<div class="d-flex gap-1" style="flex-wrap:wrap">
    @foreach($paletteColors as $swatch)
    <label class="color-swatch" style="background:{{ $swatch }}">
        <input type="radio" name="{{ $name }}" id="{{ $inputId }}_{{ $loop->index }}" value="{{ $swatch }}" @checked(($selected ?? null) === $swatch)>
    </label>
    @endforeach
</div>
