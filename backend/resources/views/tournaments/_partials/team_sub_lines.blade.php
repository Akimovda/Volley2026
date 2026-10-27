{{-- Подпись под названием команды: пляжка — «Фамилия Имя» игроков, классика — «Капитан: Фамилия Имя» (тот же источник, что на /broadcast/camera).
     Нужны: $team; необязательно $inline (span вместо div — для текста внутри кнопки). --}}
@php $subLines = ($team ?? null) ? app(\App\Services\TeamDisplayNames::class)->cameraLines($team) : []; @endphp
@foreach($subLines as $subLine)
@if(!empty($inline))<span style="display:block;font-size:.8em;font-weight:500;opacity:.85;line-height:1.25">{{ $subLine }}</span>@else<div class="f-13" style="opacity:.75;line-height:1.35">{{ $subLine }}</div>@endif
@endforeach
