@extends('layouts.app')

@section('title', 'Welcome to BaultPHP')

@section('content')

<div class="relative isolate overflow-hidden">
    {{-- Enhanced Background Effects with Multiple Gradients --}}
    <div class="absolute inset-x-0 -top-40 -z-10 transform-gpu overflow-hidden blur-3xl sm:-top-80" aria-hidden="true">
        <div class="relative left-[calc(50%-11rem)] aspect-[1155/678] w-[36.125rem] -translate-x-1/2 rotate-[30deg] bg-gradient-to-tr from-[#ff80b5] via-[#9089fc] to-[#4f46e5] opacity-25 sm:left-[calc(50%-30rem)] sm:w-[72.1875rem] animate-gradient-shift"></div>
    </div>
    
    {{-- Additional floating gradient orbs --}}
    <div class="absolute top-1/4 right-0 -z-10 w-96 h-96 bg-gradient-to-br from-purple-500/20 to-pink-500/20 rounded-full blur-3xl animate-float"></div>
    <div class="absolute bottom-1/4 left-0 -z-10 w-96 h-96 bg-gradient-to-tr from-cyan-500/20 to-blue-500/20 rounded-full blur-3xl animate-float" style="animation-delay: 2s;"></div>

    @php
        $homePage = \Modules\Cms\Infrastructure\Models\Page::where('slug', 'home')->first();
        $userRoles = auth()->check() ? (auth()->user()->getRoles() ?? []) : null;
    @endphp

    @if($homePage)
        {{-- HERO SECTION - Render blocks from page 'hero' region --}}
        @php
            $heroContent = render_page_blocks($homePage, 'hero', null, $userRoles);
        @endphp
        @if($heroContent)
            {!! $heroContent !!}
        @else
            {{-- Fallback to static region if no page blocks --}}
            {!! render_block_region('homepage-hero') !!}
        @endif

        {{-- CONTENT SECTION - Render blocks from page 'content' region --}}
        {!! render_page_blocks($homePage, 'content', null, $userRoles) !!}

        {{-- SIDEBAR SECTION - Render blocks from page 'sidebar' region --}}
        {!! render_page_blocks($homePage, 'sidebar', null, $userRoles) !!}
    @else
        {{-- Fallback: Use static block regions if no 'home' page configured --}}
        {!! render_block_region('homepage-hero') !!}
        {!! render_block_region('homepage-features') !!}
        {!! render_block_region('homepage-stats') !!}
    @endif

    {{-- Enhanced Background Effects Bottom with Animation --}}
    <div class="absolute inset-x-0 top-[calc(100%-13rem)] -z-10 transform-gpu overflow-hidden blur-3xl sm:top-[calc(100%-30rem)]" aria-hidden="true">
        <div class="relative left-[calc(50%+3rem)] aspect-[1155/678] w-[36.125rem] -translate-x-1/2 bg-gradient-to-tr from-[#ff80b5] via-[#9089fc] to-[#4f46e5] opacity-25 sm:left-[calc(50%+36rem)] sm:w-[72.1875rem] animate-gradient-shift" style="animation-delay: 1s;"></div>
    </div>
</div>
@endsection

@push('styles')
<style>
/* Additional page-specific optimizations */
.relative.isolate.overflow-hidden {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}
</style>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', (event) => {
        // Syntax highlighting
        if (window.Prism) {
            Prism.highlightAll();
        }

        // Scroll animations for elements
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -100px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('in-view');
                    // Remove will-change after animation completes
                    setTimeout(() => {
                        entry.target.classList.add('animation-complete');
                    }, 1000);
                }
            });
        }, observerOptions);

        // Observe all animated elements
        document.querySelectorAll('.scroll-animate, .animate-fade-in-up').forEach(el => {
            observer.observe(el);
        });

        // Counter animation for stats
        const animateCounter = (element) => {
            const target = element.textContent;
            const isNumber = /\d/.test(target);
            
            if (!isNumber) return;
            
            const numMatch = target.match(/[\d,.]+/);
            if (!numMatch) return;
            
            const num = parseFloat(numMatch[0].replace(/,/g, ''));
            const suffix = target.replace(numMatch[0], '');
            const duration = 2000;
            const steps = 60;
            const increment = num / steps;
            let current = 0;
            let step = 0;
            
            const timer = setInterval(() => {
                current += increment;
                step++;
                
                if (step >= steps) {
                    clearInterval(timer);
                    current = num;
                }
                
                element.textContent = Math.floor(current).toLocaleString() + suffix;
            }, duration / steps);
        };

        // Trigger counter animation when stats come into view
        const statsObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && !entry.target.dataset.animated) {
                    entry.target.dataset.animated = 'true';
                    const counter = entry.target.querySelector('.counter-animate');
                    if (counter) {
                        animateCounter(counter);
                    }
                }
            });
        }, { threshold: 0.5 });

        document.querySelectorAll('.stat-card').forEach(card => {
            statsObserver.observe(card);
        });
    });

    document.addEventListener('spa:content-replaced', () => {
        if (window.Prism) {
            Prism.highlightAll();
        }
        
        const event = new Event('DOMContentLoaded');
        document.dispatchEvent(event);
    });
</script>
@endpush
