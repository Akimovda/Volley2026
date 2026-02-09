<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <script>
        (function () {
          try {
            const isAuthed = {{ auth()->check() ? 'true' : 'false' }};
            if (!isAuthed) return;
        
            const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
            if (!tz) return;
        
            const key = 'user_tz_last_sent';
            if (localStorage.getItem(key) === tz) return;
        
            fetch('/profile/timezone', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
              },
              body: JSON.stringify({ timezone: tz }),
              credentials: 'same-origin',
            }).then(() => {
              localStorage.setItem(key, tz);
            }).catch(() => {});
          } catch (e) {}
        })();
        </script>
        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <!-- Styles -->
        @livewireStyles
    </head>

    <body class="font-sans antialiased">
        <x-banner />

        <div class="min-h-screen bg-gray-100 flex flex-col">
            @livewire('navigation-menu')

            <!-- Page Heading -->
            @if (isset($header))
                <header class="bg-white shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endif

            <!-- Page Content -->
            <main class="flex-1">
                {{ $slot }}
            </main>

            <!-- Global Footer -->
            <footer class="bg-white border-t">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <div class="text-sm text-gray-500">
                            © {{ date('Y') }} {{ config('app.name', 'Volley') }}
                        </div>

                        <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
                            <a href="{{ url('/personal_data_agreement') }}"
                               class="text-gray-600 hover:text-gray-900 underline-offset-4 hover:underline">
                                Обработка персональных данных
                            </a>
                        </div>
                    </div>
                </div>
            </footer>
        </div>

        @stack('modals')
        @livewireScripts
    </body>
</html>
