<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>{{ $message }}</title>
    <style>
        body { margin: 0; padding: 12px; background: transparent; font-family: sans-serif; }
        .widget-unavailable { color: #888; font-size: 14px; padding: 12px; text-align: center; }
    </style>
</head>
<body>
    <div class="widget-unavailable">{{ $message }}</div>
</body>
</html>
