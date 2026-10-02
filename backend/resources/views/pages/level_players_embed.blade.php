{{-- «Уровни игроков» во всплывающем окне (iframe, ?embed=1): без шапки сайта; тот же контент, что /level_players --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>{{ __('pages.lp_title') ?? 'Уровни игроков' }}</title>
    <link href="/assets/lib.css" rel="stylesheet">
    <link href="/assets/style.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { padding: 1.2rem 1.6rem 2.4rem; margin: 0; }
        .tab-highlight { display: none; }
        .tabs { display: flex; flex-wrap: wrap; gap: 1.6rem; border-bottom: 1px solid rgba(128,128,128,.25); margin-bottom: 1.4rem; }
        .tabs .tab { cursor: pointer; margin: 0 0 -1px; padding: .6rem .1rem; border-bottom: 2px solid transparent; font-size: 1.6rem; opacity: .6; }
        .tabs .tab.active { opacity: 1; color: #2967BA; border-bottom-color: #2967BA; }
        body.dark .tabs .tab.active { color: #E7612F; border-bottom-color: #E7612F; }
        .tab-pane { display: none; }
        .tab-pane.active { display: block; }
    </style>
</head>
<body>
<script>
    // подхватываем тёмную тему родителя (iframe на том же домене)
    try { if (window.parent && window.parent.document.body.classList.contains('dark')) document.body.classList.add('dark'); } catch (e) {}
</script>
@include('pages._level_players_body', ['levelScope' => $levelScope])
<script>
    // вкладки (в основном layout ими управляет script.js — тут минимальная версия)
    document.addEventListener('click', function (e) {
        var tab = e.target.closest('.tabs > .tab[data-tab]');
        if (!tab) return;
        var tabs = tab.parentElement, content = tabs.parentElement;
        tabs.querySelectorAll(':scope > .tab').forEach(function (t) { t.classList.toggle('active', t === tab); });
        var panes = content.querySelector(':scope > .tab-panes');
        if (panes) panes.querySelectorAll(':scope > .tab-pane').forEach(function (p) { p.classList.toggle('active', p.id === tab.dataset.tab); });
    });
</script>
</body>
</html>
