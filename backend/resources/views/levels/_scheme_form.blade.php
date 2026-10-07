{{-- Форма своей схемы 7 уровней. Параметры: $action, $owner ('brand'|'organizer'), $ownerId, $backTo (необяз.) --}}
@php
    $cur = \App\Services\LevelLabelService::scheme($owner, (int) $ownerId) ?? [];
@endphp
<div class="ramka">
    <h2 class="-mt-05">{{ __('levelscheme.h2') }}</h2>
    <p>{{ __('levelscheme.hint') }}</p>
    <form method="POST" action="{{ $action }}" class="form">
        @csrf
        @for($l = 1; $l <= 7; $l++)
            @php
                $r = $cur[$l] ?? [];
                $col = old("levels.$l.color", $r['color'] ?? level_color($l));
                $fg = old("levels.$l.text_color", $r['text_color'] ?? '#FFFFFF');
            @endphp
            <div style="display:flex; flex-wrap:wrap; align-items:center; gap:1rem; margin-bottom:1.4rem; padding-bottom:1.4rem; border-bottom:0.1rem solid rgba(128,128,128,.2);">
                <strong style="width:2.4rem;">{{ $l }}</strong>
                <input type="text" name="levels[{{ $l }}][name_ru]" value="{{ old("levels.$l.name_ru", $r['name_ru'] ?? '') }}" placeholder="{{ level_name($l) }}" maxlength="60" style="flex:2; min-width:16rem;">
                <input type="text" name="levels[{{ $l }}][name_en]" value="{{ old("levels.$l.name_en", $r['name_en'] ?? '') }}" placeholder="{{ __('levelscheme.name_en') }}" maxlength="60" style="flex:2; min-width:16rem;">
                <input type="text" name="levels[{{ $l }}][short_ru]" value="{{ old("levels.$l.short_ru", $r['short_ru'] ?? '') }}" placeholder="{{ __('levelscheme.short') }}" maxlength="20" style="width:11rem;">
                <input type="text" name="levels[{{ $l }}][short_en]" value="{{ old("levels.$l.short_en", $r['short_en'] ?? '') }}" placeholder="{{ __('levelscheme.short_en') }}" maxlength="20" style="width:11rem;">
                <label style="display:flex; align-items:center; gap:.6rem;">{{ __('levelscheme.pill') }}
                    <input type="color" name="levels[{{ $l }}][color]" value="{{ $col }}" style="width:5rem; height:4rem; padding:.2rem;">
                </label>
                <label style="display:flex; align-items:center; gap:.6rem;">{{ __('levelscheme.text') }}
                    <input type="color" name="levels[{{ $l }}][text_color]" value="{{ $fg }}" style="width:5rem; height:4rem; padding:.2rem;">
                </label>
            </div>
        @endfor
        <div style="display:flex; flex-wrap:wrap; gap:1.5rem;">
            <button type="submit" class="btn btn-primary">{{ __('levelscheme.save') }}</button>
            @if($cur)
                <button type="submit" name="reset" value="1" class="btn btn-secondary">{{ __('levelscheme.reset') }}</button>
            @endif
        </div>
    </form>
</div>
