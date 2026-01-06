@if (!app(\Psr\Http\Message\ServerRequestInterface::class)->hasHeader('X-SPA-NAVIGATE'))
<!DOCTYPE html>
<html lang="{{ $page->language_code ?? str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-gray-900 scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    {{-- SEO Title --}}
    <title>{{ $page->getSeoTitle() ?? $page->name }} - {{ config('app.name', 'BaultPHP') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo/BaultPHP-icon.png') }}">
    
    {{-- SEO Meta Tags --}}
    @if($page->meta_description ?? false)
    <meta name="description" content="{{ $page->meta_description }}">
    @endif
    
    @if($page->meta_keywords ?? false)
    <meta name="keywords" content="{{ $page->meta_keywords }}">
    @endif
    
    @if($page->canonical_url ?? false)
    <link rel="canonical" href="{{ $page->canonical_url }}">
    @endif
    
    <meta name="robots" content="{{ $page->robots ?? 'index,follow' }}">
    
    {{-- Open Graph Tags --}}
    <meta property="og:title" content="{{ $page->getSeoTitle() ?? $page->name }}">
    <meta property="og:description" content="{{ $page->getSeoDescription() }}">
    <meta property="og:type" content="{{ $page->og_type ?? 'website' }}">
    <meta property="og:url" content="{{ url($_SERVER['REQUEST_URI'] ?? '/') }}">
    @if($page->og_image ?? false)
    <meta property="og:image" content="{{ $page->og_image }}">
    @endif
    
    {{-- Twitter Card Tags --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $page->getSeoTitle() ?? $page->name }}">
    <meta name="twitter:description" content="{{ $page->getSeoDescription() }}">
    @if($page->og_image ?? false)
    <meta name="twitter:image" content="{{ $page->og_image }}">
    @endif
    
    {{-- Stylesheets --}}
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/blocks.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/blocks-enhanced.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/spa-animations.css') }}">
    
    @stack('styles')
</head>
<body class="h-full bg-gray-900 text-gray-100 antialiased">
    <div class="min-h-full">
@endif
        {{-- Draft Mode Indicator (Admin Only) --}}
        @if(($isDraft ?? false) && auth()->check() && auth()->user()->can('cms.pages.view'))
        <div class="draft-banner bg-yellow-500 text-black px-4 py-3 text-center font-medium z-50 sticky top-0" style="z-index: 9999;">
            <div class="container mx-auto flex items-center justify-center gap-4">
                <span class="font-bold">DRAFT MODE</span>
                <span class="hidden sm:inline">This page is not visible to the public.</span>
                <a href="/admin/pages/{{ $page->id }}/edit" class="inline-flex items-center px-4 py-1 bg-black hover:bg-gray-800 text-white rounded-lg text-sm font-medium transition-colors">
                    Edit Page
                </a>
            </div>
        </div>
        @endif
        
        {{-- Header Region (Global Blocks) --}}
        @php
            // Header uses GLOBAL blocks (BlockInstances), not page blocks
            $headerContent = render_block_region('header');
        @endphp
        
        <div data-no-spa>
            @if($headerContent)
                <div class="header-region">
                    {!! $headerContent !!}
                </div>
            @else
                @include('layouts.partials.header')
            @endif
        </div>
        
        {{-- SPA Content Container --}}
        <div id="page-content">
            {{-- Hero Region --}}
            @php
                $heroContent = render_page_blocks($page, 'hero', null, $userRoles ?? null);
            @endphp
            
            @if($heroContent)
                <div class="hero-region">
                    {!! $heroContent !!}
                </div>
            @endif
            
            {{-- Main Content Layout --}}
            <div class="container mx-auto px-4 py-12">
                <div class="flex flex-col lg:flex-row gap-8">
                    {{-- Sidebar Left --}}
                    @php
                        $sidebarLeftContent = render_page_blocks($page, 'sidebar-left', null, $userRoles ?? null);
                    @endphp
                    
                    @if($sidebarLeftContent)
                        <aside class="w-full lg:w-80 order-2 lg:order-1">
                            {!! $sidebarLeftContent !!}
                        </aside>
                    @endif
                    
                    {{-- Main Content --}}
                    <main class="flex-1 order-1 lg:order-2">
                        @php
                            $contentHtml = render_page_blocks($page, 'content', null, $userRoles ?? null);
                        @endphp
                        
                        @if($contentHtml)
                            {!! $contentHtml !!}
                        @else
                            <div class="empty-state bg-gray-800/30 rounded-xl p-12 text-center">
                                <div class="text-6xl mb-4">📄</div>
                                <h2 class="text-2xl font-bold text-white mb-2">No Content Yet</h2>
                                <p class="text-gray-400 mb-6">This page doesn't have any content blocks.</p>
                                
                                @if(auth()->check() && auth()->user()->can('cms.pages.update'))
                                    <a href="/admin/pages/{{ $page->id }}/editor" 
                                       class="inline-block px-6 py-3 bg-gradient-to-r from-indigo-500 to-purple-600 hover:from-indigo-600 hover:to-purple-700 text-white rounded-lg font-semibold transition-all hover:scale-105 shadow-lg shadow-indigo-500/30">
                                        ✏️ Add Content
                                    </a>
                                @endif
                            </div>
                        @endif
                    </main>
                    
                    {{-- Sidebar Right --}}
                    @php
                        $sidebarRightContent = render_page_blocks($page, 'sidebar', null, $userRoles ?? null);
                    @endphp
                    
                    @if($sidebarRightContent)
                        <aside class="w-full lg:w-80 order-3">
                            {!! $sidebarRightContent !!}
                        </aside>
                    @endif
                </div>
            </div>
        </div>

@if (!app(\Psr\Http\Message\ServerRequestInterface::class)->hasHeader('X-SPA-NAVIGATE'))
        {{-- Footer Region (Global Blocks) --}}
        @php
            // Footer uses GLOBAL blocks (BlockInstances), not page blocks
            $footerContent = render_block_region('footer');
        @endphp
        
        <div data-no-spa>
            @if($footerContent)
                <div class="footer-region mt-20">
                    {!! $footerContent !!}
                </div>
            @else
                @include('layouts.partials.footer')
            @endif
        </div>
    </div>
    
    {{-- Edit Button For Admins --}}
    @if(auth()->check() && auth()->user()->can('cms.pages.update'))
        <div class="fixed bottom-6 right-6 z-50">
            <a href="/admin/pages/{{ $page->id }}/editor" 
               class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-indigo-500 to-purple-600 hover:from-indigo-600 hover:to-purple-700 text-white rounded-xl font-semibold shadow-2xl shadow-indigo-500/50 hover:scale-105 transition-all duration-300"
               data-no-spa>
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                <span>Edit Page</span>
            </a>
        </div>
    @endif
    
    <script src="{{ asset('assets/js/app.js') }}" type="module" defer></script>
    @include('debug.bar')
    @stack('scripts')
</body>
</html>
@endif
