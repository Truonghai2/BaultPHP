<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-gray-900 no-js">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Welcome') - {{ config('app.name', 'BaultPHP') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo/BaultPHP-icon.png') }}">
    <link rel="manifest" href="{{ asset('manifest.json') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    @yield('styles')
    
    <style>
        body {
            opacity: 0;
            transition: opacity 0.2s ease-in;
        }
        .js body {
            opacity: 1;
        }
    </style>

    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js').then(registration => {
                    console.log('ServiceWorker registration successful with scope: ', registration.scope);
                }, err => {
                    console.log('ServiceWorker registration failed: ', err);
                });
            });
        }
    </script>
</head>
<body class="h-full bg-gray-900 text-gray-100 antialiased">
    <div class="min-h-full">
        <header class="bg-gray-900/80 backdrop-blur-sm sticky top-0 z-40">
            <nav class="mx-auto flex max-w-7xl items-center justify-between p-4 lg:px-8" aria-label="Global">
                <div class="flex lg:flex-1 items-center">
                    <a href="{{ route('home') }}" class="flex items-center group hover:opacity-90 transition-opacity">
                        <div class="relative">
                            {{-- <div class="absolute -inset-2 bg-gradient-to-r from-indigo-500 to-purple-500 rounded-lg blur opacity-20 group-hover:opacity-40 transition duration-500"></div> --}}
                            <img class="relative h-8 w-auto lg:h-10 xl:h-12" src="{{ asset('images/logo/BaultPHP.png') }}" alt="BaultPHP">
                        </div>
                    </a>
                </div>

                {{-- Desktop Navigation --}}
                <div class="hidden lg:flex lg:gap-x-12 items-center">
                    <a href="{{ route('home') }}" class="text-sm font-semibold leading-6 text-white hover:text-indigo-400 transition-colors">
                        {{ __('home.home') }}
                    </a>
                    <a href="{{ route('about') }}" class="text-sm font-semibold leading-6 text-white hover:text-indigo-400 transition-colors">
                        {{ __('home.features') }}
                    </a>
                    <a href="#docs" class="text-sm font-semibold leading-6 text-white hover:text-indigo-400 transition-colors">
                        {{ __('home.documentation') }}
                    </a>
                    <a href="https://github.com/Truonghai2/BaultPHP" target="_blank" 
                       class="flex items-center gap-x-2 text-sm font-semibold leading-6 text-white hover:text-indigo-400 transition-colors">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                        </svg>
                        GitHub
                    </a>
                </div>

                {{-- Login Button --}}
                <div class="hidden lg:flex lg:flex-1 lg:justify-end">
                    @guest
                        <a href="{{ route('auth.login.view') }}"
                           class="group relative inline-flex items-center gap-x-2 rounded-full px-4 py-2 text-sm font-semibold text-white ring-1 ring-gray-700 hover:ring-gray-600 transition-all duration-200">
                            <span>Log in</span>
                            <span class="inline-block transition-transform group-hover:translate-x-1" aria-hidden="true">→</span>
                        </a>
                    @endguest
                    @auth
                        <form action="{{ route('auth.logout') }}" method="POST">
                            @csrf
                            <button type="submit" class="group relative inline-flex items-center gap-x-2 rounded-full px-4 py-2 text-sm font-semibold text-white ring-1 ring-gray-700 hover:ring-gray-600 transition-all duration-200">
                                <span>Log out</span>
                            </button>
                        </form>
                    @endauth
                </div>

                {{-- Mobile Menu Button --}}
                <div class="flex lg:hidden">
                    <button id="mobile-menu-button" type="button" class="inline-flex items-center justify-center rounded-md p-2.5 text-gray-400 hover:text-white transition-colors">
                        <span class="sr-only">Open main menu</span>
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                    </button>
                </div>
            </nav>
        </header>
    
        <main>
            @yield('content')
        </main>
    
        <footer class="bg-gray-900 border-t border-white/10 mt-24">
            <div class="mx-auto max-w-7xl overflow-hidden px-6 py-12 sm:py-16 lg:px-8">
                <p class="text-center text-xs leading-5 text-gray-400">&copy; {{ date('Y') }} Bault.dev. All rights reserved.</p>
            </div>
        </footer>
    </div>

    @include('debug.bar')
    <script>
        document.documentElement.classList.replace('no-js','js');
        // fade-in body
        document.addEventListener("DOMContentLoaded", () => {
            document.body.style.opacity = 1;
        });

        // toggle mobile menu (simple demo)
        document.getElementById("mobile-menu-button")?.addEventListener("click", () => {
            alert("TODO: mở menu mobile ở đây!");
        });
    </script>
    @stack('scripts')
</body>
</html>