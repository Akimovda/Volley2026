<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="@yield('description', 'Описание сайта')">
    <title>@yield('title', config('app.name', 'Laravel'))</title>
    <link href="/assets/lib.css" rel="stylesheet">
    <link href="/assets/style.css" rel="stylesheet">
    @yield('styles')
    @livewireStyles
</head>
<body>
    <header>
        Моя шапка
    </header>

    <main>
        {{ $slot }}
    </main>
    
    <footer>
        Мой подвал
    </footer>
    
    <script src="/assets/script.js"></script>    
    @yield('scripts')   
    @livewireScripts
</body>
</html>