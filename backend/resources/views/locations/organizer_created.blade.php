{{-- Ответ модалки после создания локации: сообщаем мастеру (parent) и закрываем окно --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('locations.org_create_title') }}</title>
    <link href="/assets/lib.css" rel="stylesheet">
    <link href="/assets/style.css" rel="stylesheet">
</head>
<body style="padding:1.6rem">
<div class="alert alert-success">{{ __('locations.org_created_ok', ['name' => $location->name]) }}</div>
<script>
    (function () {
        var msg = { type: 'location-created', id: @json((int) $location->id), cityId: @json((int) $location->city_id) };
        try { window.parent.postMessage(msg, window.location.origin); } catch (e) {}
    })();
</script>
</body>
</html>
