<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Мероприятия</title>
    <style>html,body{margin:0;background:transparent}</style>
</head>
<body>
@include('widget._content')
<script>
    // Сообщаем родителю высоту содержимого (iframe можно подгонять под неё)
    (function () {
        function send() {
            var h = document.documentElement.scrollHeight;
            try { parent.postMessage({ type: 'volley-widget-height', height: h }, '*'); } catch (e) {}
        }
        window.addEventListener('load', send);
        window.addEventListener('resize', send);
        document.querySelectorAll('img').forEach(function (i) { i.addEventListener('load', send); });
    })();
</script>
</body>
</html>
