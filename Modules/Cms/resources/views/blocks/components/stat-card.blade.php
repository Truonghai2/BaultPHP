{{-- Stat Card Component --}}
@php
    $animationDelay = $index * 100;
    $colorFrom = $color['from'];
    $colorTo = $color['to'];
@endphp

<div class="stat-card group relative overflow-hidden rounded-2xl bg-gradient-to-br from-gray-800/70 to-gray-900/70 p-8 ring-1 ring-white/10 backdrop-blur-sm hover:ring-white/20 hover:scale-105 transition-all duration-500 hover:shadow-2xl hover:shadow-{{ $colorFrom }}-500/20 animate-fade-in-up animation-delay-{{ $animationDelay }}">
    
    {{-- Gradient background overlay --}}
    <div class="stat-gradient absolute inset-0 bg-gradient-to-br from-{{ $colorFrom }}-500/10 to-{{ $colorTo }}-500/10 opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
    
    {{-- Animated icon/decoration in background --}}
    <div class="stat-icon-bg absolute -top-8 -right-8 w-32 h-32 bg-gradient-to-br from-{{ $colorFrom }}-500/20 to-{{ $colorTo }}-500/20 rounded-full blur-2xl group-hover:scale-150 transition-transform duration-700"></div>
    
    {{-- Content --}}
    <div class="relative z-10">
        
        {{-- Value with counter animation effect --}}
        <dd class="stat-value order-first text-5xl font-extrabold tracking-tight mb-3">
            <span class="bg-gradient-to-r from-{{ $colorFrom }}-400 to-{{ $colorTo }}-400 bg-clip-text text-transparent group-hover:from-{{ $colorFrom }}-300 group-hover:to-{{ $colorTo }}-300 transition-all duration-300 counter-animate">
                {{ $stat['value'] }}
            </span>
        </dd>
        
        {{-- Label --}}
        <dt class="stat-label text-sm font-bold text-gray-400 group-hover:text-gray-300 transition-colors duration-300 uppercase tracking-wider">
            {{ $stat['label'] }}
        </dt>
        
        {{-- Decorative progress bar --}}
        <div class="stat-bar mt-4 h-1 w-full bg-gray-700/50 rounded-full overflow-hidden">
            <div class="stat-bar-fill h-full bg-gradient-to-r from-{{ $colorFrom }}-500 to-{{ $colorTo }}-500 rounded-full transform -translate-x-full group-hover:translate-x-0 transition-transform duration-1000 ease-out"></div>
        </div>
        
    </div>
</div>

