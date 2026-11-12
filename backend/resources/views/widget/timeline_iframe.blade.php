<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>{{ __('club.tl_title') }}</title>
    <style>html,body{margin:0;background:transparent}</style>
</head>
<body>
<div id="vrt-host"></div>
<script>{!! $app !!}</script>
<script>
    window.VolleyRentalTimeline.mount(document.getElementById('vrt-host'), {!! json_encode($options, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}, { iframe: true });
</script>
</body>
</html>
