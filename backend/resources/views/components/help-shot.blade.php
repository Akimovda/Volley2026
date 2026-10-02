@props(['name', 'alt' => ''])
@php
    // Скриншот-иллюстрация для /help. Файл — public/images/help/{name}; если файла ещё нет — ничего не выводим,
    // поэтому разметку можно выкатывать раньше, чем появятся картинки.
    $helpShotPath = public_path('images/help/' . $name);
    $helpShotSize = is_file($helpShotPath) ? @getimagesize($helpShotPath) : false;
@endphp
@if($helpShotSize)
    @once
    <style>
        .help-shot { margin: 1.5rem auto; max-width: 26rem; text-align: center; }
        .help-shot img { display: block; width: 100%; height: auto; border-radius: 1.6rem; border: 0.1rem solid rgba(0,0,0,.12); box-shadow: 0 .6rem 2rem rgba(0,0,0,.12); }
        body.dark .help-shot img { border-color: rgba(255,255,255,.12); }
        @media (min-width: 768px) {
            .help-shot { float: right; margin: .5rem 0 1.5rem 2.5rem; max-width: 22rem; }
        }
        body.help h2 { clear: both; }
    </style>
    @endonce
    <figure class="help-shot">
        <a href="{{ asset('images/help/' . $name) }}?v={{ filemtime($helpShotPath) }}" class="fancybox" data-fancybox="help">
            <img src="{{ asset('images/help/' . $name) }}?v={{ filemtime($helpShotPath) }}" alt="{{ $alt }}"
                 width="{{ $helpShotSize[0] }}" height="{{ $helpShotSize[1] }}" loading="lazy" decoding="async">
        </a>
    </figure>
@endif
