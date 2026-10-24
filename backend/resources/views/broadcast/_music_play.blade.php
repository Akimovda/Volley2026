{{-- Иконка-кнопка предпрослушивания трека. Нужны: $url; опционально $id, $hidden --}}
<button type="button" class="bc-music-play"@isset($id) id="{{ $id }}"@endisset data-url="{{ $url }}" aria-label="{{ __('stream.music_listen') }}" title="{{ __('stream.music_listen') }}"@if(!empty($hidden)) style="display:none"@endif>
    <svg class="ic-play" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4.5v15a1 1 0 0 0 1.5.86l12.5-7.5a1 1 0 0 0 0-1.72L8.5 3.64A1 1 0 0 0 7 4.5z"/></svg>
    <svg class="ic-stop" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="5" width="14" height="14" rx="2"/></svg>
</button>
