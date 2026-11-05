{{-- Самостоятельная страница ошибки без общего layout (при сбое БД/layout сам layout мог бы не отрисоваться).
     Параметры: $icon, $refresh (секунд до автообновления или 0), $ru: [...], $en: [...] с ключами title,h1,text,reload,back,home,code --}}
@php
    $isRu = true;
    try { $isRu = app()->getLocale() !== 'en'; } catch (\Throwable $e) {}
    $t = $isRu ? $ru : $en;
@endphp
<!DOCTYPE html>
<html lang="{{ $isRu ? 'ru' : 'en' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    @if(!empty($refresh))<meta http-equiv="refresh" content="{{ (int) $refresh }}">@endif
    <title>{{ $t['title'] }} — VolleyClub</title>
    <link href="/assets/style.css" rel="stylesheet">
    <style>
        .err500{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2.4rem;box-sizing:border-box;text-align:center;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:#f5f8ff;color:#222}
        .err500 .card{max-width:56rem;background:#fff;border-radius:2rem;padding:3.2rem 2.4rem;box-shadow:0 1rem 3rem rgba(41,103,186,.12)}
        .err500 .ico{font-size:6rem;line-height:1}
        .err500 h1{font-size:2.6rem;margin:1.2rem 0 1rem}
        .err500 p{font-size:1.6rem;line-height:1.5;margin:0 0 2rem;opacity:.85}
        .err500 .code{font-size:1.3rem;opacity:.5;margin-top:2rem}
        .err500 .row{display:flex;flex-wrap:wrap;gap:1rem;justify-content:center}
        .err500 .b{display:inline-block;padding:1.1rem 2.2rem;border-radius:.8rem;font-size:1.5rem;font-weight:600;text-decoration:none;cursor:pointer;border:.15rem solid #2967BA;background:#2967BA;color:#fff}
        .err500 .b.sec{background:#fff;color:#2967BA}
        @media (prefers-color-scheme:dark){.err500{background:#15161f;color:#eee}.err500 .card{background:#222333;box-shadow:none}.err500 .b.sec{background:transparent;color:#9bbcf0;border-color:#9bbcf0}}
    </style>
</head>
<body>
    <div class="err500">
        <div class="card">
            <div class="ico">{{ $icon }}</div>
            <h1>{{ $t['h1'] }}</h1>
            <p>{{ $t['text'] }}</p>
            <div class="row">
                <a class="b" href="javascript:location.reload()">{{ $t['reload'] }}</a>
                <a class="b sec" href="javascript:history.back()">{{ $t['back'] }}</a>
                <a class="b sec" href="/">{{ $t['home'] }}</a>
            </div>
            <div class="code">{{ $t['code'] }}</div>
        </div>
    </div>
</body>
</html>
