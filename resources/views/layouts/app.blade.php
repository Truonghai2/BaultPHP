@if (!app(\Psr\Http\Message\ServerRequestInterface::class)->hasHeader('X-SPA-NAVIGATE'))
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-gray-900 no-js">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Welcome') - {{ config('app.name', 'BaultPHP') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo/BaultPHP-icon.png') }}">

    {{-- This script prevents FOUC (Flash of Unstyled Content) by adding a 'js' class
         to the html tag if JavaScript is enabled, allowing CSS to style the initial state correctly. --}}
    <script>(function(H){H.className=H.className.replace(/\bno-js\b/,'js')})(document.documentElement)</script>

    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
    <script src="{{ asset('assets/js/app.js') }}" type="module" defer></script>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    @yield('styles')
    
    <style>
        /* Hide body initially to prevent FOUC, but only if JS is enabled */
        .js body {
            opacity: 0;
            transition: opacity 0.2s ease-in;
        }

        /* When the page is loaded (or SPA is ready), this class will be removed by JS */
        .js body.loaded {
            opacity: 1;
        }

        /* --- SPA Transition Effects --- */
        #app-content.spa-content-entering {
            animation: spa-fade-in 0.3s ease-out;
        }

        @keyframes spa-fade-in {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        #app-content.spa-content-exiting {
            opacity: 0;
            transition: opacity 0.2s ease-in;
        }
    </style>
</head>
<body class="h-full bg-gray-900 text-gray-100 antialiased">
    <div class="min-h-full">
        @include('layouts.partials.header') 
@endif
        <title class="spa-title">@yield('title', 'Welcome') - {{ config('app.name', 'BaultPHP') }}</title>
        <main id="app-content">
            @yield('content')
        </main>

@if (!app(\Psr\Http\Message\ServerRequestInterface::class)->hasHeader('X-SPA-NAVIGATE'))
        @include('layouts.partials.footer')

    </div>

    @include('debug.bar')
    @if (config('app.env') === 'production')
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('{{ asset("assets/js/sw.js") }}')
                    .then(registration => {
                        console.log('Service Worker registered successfully with scope: ', registration.scope);
                    })
                    .catch(error => {
                        console.log('Service Worker registration failed: ', error);
                    });
            });
        }
    </script>
    @endif
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.body.classList.add('loaded');
        });
    </script>
    @stack('scripts')
</body>
</html>
@endif