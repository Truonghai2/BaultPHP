{{--
    Header Partial - Block-Based Navigation
    
    Supports TWO modes:
    1. Global Blocks (default): render_block_region('header-nav')
    2. Page-Specific Blocks: render_page_blocks($page, 'header')
    
    Usage:
    - @include('layouts.partials.header') → Global blocks
    - @include('layouts.partials.header', ['page' => $page]) → Page-specific blocks
--}}

<header class="site-header bg-gray-900/90 backdrop-blur-xl sticky top-0 z-50 border-b border-white/5">
    <nav class="mx-auto flex max-w-7xl items-center justify-between p-4 lg:px-8" aria-label="Global">
        {{-- Logo with Enhanced Styling --}}
        <div class="flex lg:flex-1 items-center">
            <a href="{{ route('home') }}" class="flex items-center group">
                <div class="relative">
                    <div class="absolute inset-0 bg-gradient-to-r from-indigo-500/20 to-purple-500/20 rounded-full blur-lg opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                    <img class="relative h-9 w-auto lg:h-11 xl:h-12 transform group-hover:scale-110 transition-transform duration-300" src="{{ asset('images/logo/BaultPHP.png') }}" alt="BaultPHP">
                </div>
            </a>
        </div>

        {{-- Navigation - Block-Based --}}
        <div class="hidden lg:flex lg:gap-x-2 items-center flex-1 justify-center">
            @if(isset($page))
                {{-- Page-specific navigation blocks --}}
                {!! render_page_blocks($page, 'header-nav') !!}
            @endif
            
            {{-- Global navigation blocks (fallback or additional) --}}
            @php
                $globalNav = render_block_region('header-nav');
            @endphp
            
            @if(!empty($globalNav))
                {!! $globalNav !!}
            @else
                {{-- Default Fallback Navigation --}}
                <a href="{{ route('home') }}" class="nav-link text-sm font-semibold leading-6 text-gray-300 hover:text-white transition-colors px-3 py-2 rounded-lg hover:bg-white/5">
                    Home
                </a>
                <a href="/about" class="nav-link text-sm font-semibold leading-6 text-gray-300 hover:text-white transition-colors px-3 py-2 rounded-lg hover:bg-white/5">
                    About
                </a>
                <a href="/contact" class="nav-link text-sm font-semibold leading-6 text-gray-300 hover:text-white transition-colors px-3 py-2 rounded-lg hover:bg-white/5">
                    Contact
                </a>
            @endif
        </div>

        {{-- User Menu - Block-Based --}}
        <div class="hidden lg:flex lg:flex-1 lg:justify-end">
            @if(isset($page))
                {{-- Page-specific user menu blocks --}}
                {!! render_page_blocks($page, 'header-user') !!}
            @endif
            
            {{-- Global user menu blocks (fallback or additional) --}}
            @php
                $globalUserMenu = render_block_region('header-user');
            @endphp
            
            @if(!empty($globalUserMenu))
                {!! $globalUserMenu !!}
            @else
                {{-- Default Fallback User Menu --}}
                @auth
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" class="flex items-center gap-x-2 text-sm font-semibold leading-6 text-gray-300 hover:text-white transition-colors px-3 py-2 rounded-lg hover:bg-white/5">
                            {{ auth()->user()->name }}
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        
                        <div x-show="open" @click.away="open = false" x-cloak class="absolute right-0 z-10 mt-2 w-48 origin-top-right rounded-lg bg-gray-800 py-1 shadow-lg ring-1 ring-white/10">
                            <a href="/profile" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-white">Profile</a>
                            <a href="/settings" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-white">Settings</a>
                            <a href="{{ route('logout') }}" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-white">Logout</a>
                        </div>
                    </div>
                @else
                    <a href="{{ route('login') }}" class="text-sm font-semibold leading-6 text-gray-300 hover:text-white transition-colors px-4 py-2 rounded-lg hover:bg-white/5">
                        Log in <span aria-hidden="true">&rarr;</span>
                    </a>
                @endauth
            @endif
        </div>

        {{-- Mobile Menu Button --}}
        <div class="flex lg:hidden">
            <button id="mobile-menu-button" type="button" class="inline-flex items-center justify-center rounded-lg p-2.5 text-gray-400 hover:text-white hover:bg-white/5 transition-all">
                <span class="sr-only">Open main menu</span>
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                </svg>
            </button>
        </div>
    </nav>

    {{-- Mobile Menu (Block-Based) --}}
    <div id="mobile-menu" class="hidden lg:hidden border-t border-white/5 bg-gray-900/95 backdrop-blur-xl">
        <div class="px-4 py-6 space-y-4">
            @if(isset($page))
                {!! render_page_blocks($page, 'header-nav') !!}
            @endif
            
            @if(!empty($globalNav))
                {!! $globalNav !!}
            @else
                {{-- Mobile Fallback Navigation --}}
                <a href="{{ route('home') }}" class="block text-base font-semibold leading-7 text-gray-300 hover:text-white hover:bg-white/5 px-3 py-2 rounded-lg transition-colors">Home</a>
                <a href="/about" class="block text-base font-semibold leading-7 text-gray-300 hover:text-white hover:bg-white/5 px-3 py-2 rounded-lg transition-colors">About</a>
                <a href="/contact" class="block text-base font-semibold leading-7 text-gray-300 hover:text-white hover:bg-white/5 px-3 py-2 rounded-lg transition-colors">Contact</a>
            @endif
            
            <div class="pt-4 border-t border-white/5">
                @if(isset($page))
                    {!! render_page_blocks($page, 'header-user') !!}
                @endif
                
                @if(!empty($globalUserMenu))
                    {!! $globalUserMenu !!}
                @else
                    {{-- Mobile User Menu Fallback --}}
                    @auth
                        <div class="space-y-2">
                            <div class="px-3 py-2 text-sm font-medium text-gray-400">{{ auth()->user()->name }}</div>
                            <a href="/profile" class="block text-base font-semibold leading-7 text-gray-300 hover:text-white hover:bg-white/5 px-3 py-2 rounded-lg transition-colors">Profile</a>
                            <a href="/settings" class="block text-base font-semibold leading-7 text-gray-300 hover:text-white hover:bg-white/5 px-3 py-2 rounded-lg transition-colors">Settings</a>
                            <a href="{{ route('logout') }}" class="block text-base font-semibold leading-7 text-gray-300 hover:text-white hover:bg-white/5 px-3 py-2 rounded-lg transition-colors">Logout</a>
                        </div>
                    @else
                        <a href="{{ route('login') }}" class="block text-base font-semibold leading-7 text-gray-300 hover:text-white hover:bg-white/5 px-3 py-2 rounded-lg transition-colors">
                            Log in <span aria-hidden="true">&rarr;</span>
                        </a>
                    @endauth
                @endif
            </div>
        </div>
    </div>
</header>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const menuButton = document.getElementById('mobile-menu-button');
    const mobileMenu = document.getElementById('mobile-menu');
    
    if (menuButton && mobileMenu) {
        menuButton.addEventListener('click', function() {
            mobileMenu.classList.toggle('hidden');
        });
    }
});
</script>