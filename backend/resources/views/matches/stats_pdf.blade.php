<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<title>{{ __('match_stats.page_title') }}</title>
<style>
    html { font-size: 62.5%; }
    .mx-print .mx-chart { max-width: 460px; }
    body { font-family: 'DejaVu Sans', Arial, sans-serif; color:#1a1a2e; margin:0; font-size:12px; }
    .ramka { border:1px solid #e3e6ee; border-radius:10px; padding:12px 14px; margin:0 0 10px; page-break-inside:avoid; }
    .ramka h2 { margin:0 0 8px; font-size:15px; text-transform:uppercase; color:#2967BA; }
    .ms-bars { display:grid; gap:6px; }
    .ms-bar-row { display:grid; grid-template-columns:28px 1fr 130px 1fr 28px; align-items:center; gap:6px; }
    .ms-bar-value { font-weight:700; font-size:12px; } .ms-bar-value--home { text-align:right; }
    .ms-bar-track { height:8px; border-radius:5px; background:#eceff5; display:flex; overflow:hidden; } .ms-bar-track--away { justify-content:flex-end; }
    .ms-bar-fill { height:100%; } .ms-bar-fill--home { background:#2967BA; } .ms-bar-fill--away { background:#E7612F; }
    .ms-bar-label { text-align:center; font-size:11px; font-weight:700; }
    .mx-origin-sub .ms-bar-label { font-weight:400; opacity:.7; }
    .mx-table { min-width:0 !important; }
    .mx-table th:first-child, .mx-table td:first-child { position:static !important; max-width:none !important; }
    .mx-chart-wrap { display:block !important; page-break-inside:avoid; margin-bottom:8px; }
    .mx-set-h { font-weight:700; margin:6px 0 2px; }
    .mx-head { display:flex; align-items:center; justify-content:space-between; gap:10px; margin:6px 0 4px; }
    .mx-head { display:flex !important; }
    .mx-head > div { flex:1; }
    .mx-head-score { flex:0 0 auto !important; }
    h1 { font-size:18px; margin:0 0 4px; }
    .foot { color:#7a8197; font-size:10px; margin-top:6px; }
</style>
@include('matches.stats._css')
</head>
<body class="mx-page mx-print">
<div class="mx-page mx-print">
    <h1>{{ __('match_stats.page_title') }}</h1>
    <div class="ramka">@include('matches.stats._head', ['isLive' => false, 'print' => true])</div>

    @include('matches.stats._overview', ['uid' => 'pdf', 'print' => true])

    <div class="ramka">
        <h2>{{ __('match_stats.tab_players') }}</h2>
        @include('matches.stats._players', ['print' => true])
    </div>

    @if($stats['lineup'])
    <div class="ramka">
        <h2>{{ __('match_stats.lu_subs') }}</h2>
        @foreach($stats['lineup']['sets'] as $sn => $ls)
            @foreach($ls['events'] as $e)
            @php
                $text = match ($e['type']) {
                    'substitution' => __('match_stats.lu_sub', ['in' => $e['in'] ?? '—', 'out' => $e['out'] ?? '—']),
                    'libero_in'    => __('match_stats.lu_libero_in', ['in' => $e['in'] ?? '—', 'out' => $e['out'] ?? '—']),
            'rotate'       => __('match_stats.lu_rotate', ['delta' => ($e['delta'] ?? 0) > 0 ? '+' . $e['delta'] : (string) ($e['delta'] ?? 0)]),
            'swap'         => __('match_stats.lu_swap', ['a' => $e['out'] ?? '—', 'b' => $e['in'] ?? '—']),
                    default        => __('match_stats.lu_libero_out', ['in' => $e['in'] ?? '—', 'out' => $e['out'] ?? '—']),
                };
            @endphp
            <div>{{ __('match_stats.lu_event_line', ['set' => $sn, 'score' => $e['score_home'] . ':' . $e['score_away'], 'text' => $text]) }} ({{ $stats['teams'][$e['side']]['name'] }})</div>
            @endforeach
        @endforeach
        <h2 style="margin-top:10px">{{ __('match_stats.lu_sets_played') }}</h2>
        @foreach(['home', 'away'] as $side)
        <div><b>{{ $stats['teams'][$side]['name'] }}:</b>
            {{ collect($stats['lineup']['sets_played'][$side])->map(fn ($p) => $p['name'] . ' — ' . $p['sets'])->implode(', ') }}</div>
        @endforeach
    </div>
    @endif

    <div class="foot">{{ __('match_stats.pdf_generated', ['date' => now()->format('d.m.Y H:i')]) }} · {{ config('app.url') }}</div>
</div>
</body>
</html>
