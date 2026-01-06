@if (!app(\Psr\Http\Message\ServerRequestInterface::class)->hasHeader('X-SPA-NAVIGATE'))
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-gray-900 no-js">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Welcome') - {{ config('app.name', 'BaultPHP') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo/BaultPHP-icon.png') }}">

    {{-- Prevent FOUC --}}
    <script>(function(H){H.className=H.className.replace(/\bno-js\b/,'js')})(document.documentElement)</script>

    {{-- Stylesheets --}}
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/blocks.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/blocks-enhanced.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/header-blocks.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/spa-animations.css') }}">
    
    {{-- JavaScript --}}
    <script src="{{ asset('assets/js/app.js') }}" type="module" defer></script>
    <script src="{{ asset('assets/js/block-inline-editor.js') }}" defer></script>
    
    {{-- Fonts --}}
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    
    @auth
    <meta name="user-authenticated" content="true">
    @endauth
    
    @yield('styles')
    
    <style>
        .js body { opacity: 0; transition: opacity 0.2s ease-in; }
        .js body.loaded { opacity: 1; }
    </style>
</head>
<body class="h-full bg-gray-900 text-gray-100 antialiased"@if(config('app.debug')) data-debug @endif>
    <div class="min-h-full">
        {{-- Static Header --}}
        <div data-no-spa>
            @include('layouts.partials.header')
        </div>
@endif
        
        <div class="container mx-auto px-4">
            <div class="flex flex-col lg:flex-row gap-6">
                {{-- Sidebar Left --}}
                @if(has_blocks_in_region('sidebar-left'))
                <aside class="w-full lg:w-80 order-2 lg:order-1">
                    {!! render_block_region('sidebar-left') !!}
                </aside>
                @endif
                
                {{-- Main Content - SPA Container --}}
                <main id="app-content" class="flex-1 order-1 lg:order-2">
                    {{-- Header Region Blocks (Dynamic) --}}
                    @if(has_blocks_in_region('header'))
                    <div class="mb-6">
                        {!! render_block_region('header') !!}
                    </div>
                    @endif
                    
                    @yield('content')
                    
                    {{-- Content Region Blocks --}}
                    @if(has_blocks_in_region('content'))
                    <div class="mt-6">
                        {!! render_block_region('content') !!}
                    </div>
                    @endif
                </main>
                
                {{-- Sidebar Right --}}
                @if(has_blocks_in_region('sidebar'))
                <aside class="w-full lg:w-80 order-3">
                    {!! render_block_region('sidebar') !!}
                </aside>
                @endif
            </div>
        </div>

@if (!app(\Psr\Http\Message\ServerRequestInterface::class)->hasHeader('X-SPA-NAVIGATE'))
        {{-- Footer Region (Dynamic) --}}
        @if(has_blocks_in_region('footer'))
        <div class="container mx-auto px-4 mt-8">
            {!! render_block_region('footer') !!}
        </div>
        @endif
        
        {{-- Static Footer --}}
        <div data-no-spa>
            @include('layouts.partials.footer')
        </div>
    </div>

    @include('debug.bar')
    
    {{-- Service Worker (Production Only) --}}
    @if (config('app.env') === 'production')
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('{{ asset("assets/js/sw.js") }}')
                    .then(registration => console.log('Service Worker registered:', registration.scope))
                    .catch(error => console.log('Service Worker registration failed:', error));
            });
        }
    </script>
    @endif
    
    {{-- Page Loaded --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.body.classList.add('loaded');
        });
    </script>
    
    @stack('scripts')
</body>
</html>
@endif
