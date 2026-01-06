{{-- Homepage Hero Block --}}
<div class="hero-section mx-auto max-w-7xl px-6 pb-24 pt-10 sm:pb-32 lg:flex lg:px-8 lg:py-40 relative">
    
    {{-- Animated background gradient --}}
    <div class="hero-gradient-bg"></div>
    
    {{-- Left Column - Text Content --}}
    <div class="hero-content mx-auto max-w-2xl lg:mx-0 lg:max-w-xl lg:flex-shrink-0 lg:pt-8 relative z-10">
        
        {{-- Logo & Badge --}}
        <div class="hero-badge-container flex items-center gap-x-4 animate-fade-in-up">
            <picture>
                <img class="h-14 hero-logo transform hover:scale-110 transition-transform duration-300" src="{{ $logo_url }}" alt="BaultPHP">
            </picture>
            
            @if($show_badge)
                <div class="hero-badge rounded-full px-4 py-1.5 text-sm leading-6 text-white bg-gradient-to-r from-indigo-500/20 to-purple-500/20 ring-1 ring-white/10 backdrop-blur-sm hover:ring-white/20 transition-all duration-300">
                    <span class="font-medium">{{ $badge_text }}</span> 
                    <a href="{{ $badge_link_url }}" class="font-semibold bg-gradient-to-r from-indigo-400 to-purple-400 bg-clip-text text-transparent hover:from-indigo-300 hover:to-purple-300 transition-all">
                        {{ $badge_link_text }} <span class="inline-block transition-transform group-hover:translate-x-1" aria-hidden="true">→</span>
                    </a>
                </div>
            @endif
        </div>
        
        {{-- Title --}}
        <h1 class="hero-title mt-10 text-5xl font-extrabold tracking-tight sm:text-7xl animate-fade-in-up animation-delay-100">
            <span class="bg-gradient-to-r from-white via-blue-100 to-purple-200 bg-clip-text text-transparent">
                {{ $title }}
            </span>
        </h1>
        
        {{-- Description --}}
        <p class="hero-description mt-8 text-xl leading-relaxed text-gray-300 animate-fade-in-up animation-delay-200">
            {{ $description }}
        </p>
        
        {{-- Action Buttons --}}
        <div class="hero-cta mt-10 flex items-center gap-x-6 animate-fade-in-up animation-delay-300">
            <a href="{{ $primary_button_url }}" class="hero-primary-btn rounded-full bg-gradient-to-r from-indigo-600 to-purple-600 px-8 py-4 text-base font-semibold text-white shadow-lg hover:shadow-indigo-500/50 transition-all duration-300 hover:scale-105 hover:from-indigo-500 hover:to-purple-500">
                {{ $primary_button_text }}
            </a>
            <a href="{{ $secondary_button_url }}" class="hero-secondary-btn text-base font-semibold leading-6 text-white hover:text-indigo-300 transition-colors duration-300 group">
                {{ $secondary_button_text }} <span class="inline-block transition-transform group-hover:translate-x-2" aria-hidden="true">→</span>
            </a>
        </div>
    </div>
    
    {{-- Right Column - Code Preview --}}
    @if($show_code_preview)
        <div class="hero-code-preview mx-auto mt-16 flex max-w-2xl sm:mt-24 lg:ml-10 lg:mr-0 lg:mt-0 lg:max-w-none lg:flex-none xl:ml-32 relative z-10">
            <div class="code-container max-w-3xl flex-none sm:max-w-5xl lg:max-w-none">
                <div class="code-card group rounded-2xl bg-gradient-to-br from-gray-900/90 to-gray-800/90 p-4 ring-1 ring-white/10 backdrop-blur-xl hover:ring-white/20 transition-all duration-500 hover:shadow-2xl hover:shadow-indigo-500/20">
                    <div class="code-header flex items-center justify-between mb-4 pb-4 border-b border-white/10">
                        <div class="code-meta flex items-center gap-3">
                            <div class="code-dots flex gap-2">
                                <div class="w-3 h-3 rounded-full bg-red-500/80"></div>
                                <div class="w-3 h-3 rounded-full bg-yellow-500/80"></div>
                                <div class="w-3 h-3 rounded-full bg-green-500/80"></div>
                            </div>
                            <div class="code-filename text-sm text-gray-400">{{ $code_file_name }}</div>
                        </div>
                        <div class="code-badges flex gap-2">
                            <span class="code-badge px-3 py-1 text-xs font-medium rounded-full bg-gradient-to-r from-indigo-500/20 to-purple-500/20 text-indigo-300 ring-1 ring-white/10">
                                {{ $code_badge }}
                            </span>
                            <span class="code-label px-3 py-1 text-xs font-medium rounded-full bg-gradient-to-r from-purple-500/20 to-pink-500/20 text-purple-300 ring-1 ring-white/10">
                                {{ $code_label }}
                            </span>
                        </div>
                    </div>
                    <pre class="code-content overflow-x-auto"><code class="language-{{ $code_language }} text-sm leading-relaxed">{{ $code_content }}</code></pre>
                </div>
            </div>
        </div>
    @endif
</div>

