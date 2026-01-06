{{-- Homepage Features Block --}}
<div class="features-section mx-auto mt-32 max-w-7xl px-6 sm:mt-40 lg:px-8 relative">
    
    {{-- Decorative elements --}}
    <div class="features-decoration absolute -top-24 left-1/2 -translate-x-1/2 w-96 h-96 bg-gradient-to-r from-indigo-500/20 to-purple-500/20 rounded-full blur-3xl pointer-events-none"></div>
    
    {{-- Section Header --}}
    <div class="features-header mx-auto max-w-2xl lg:text-center relative z-10">
        <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-gradient-to-r from-indigo-500/10 to-purple-500/10 ring-1 ring-white/10 mb-4">
            <span class="text-sm font-bold bg-gradient-to-r from-indigo-400 to-purple-400 bg-clip-text text-transparent">{{ $section_title }}</span>
        </div>
        <h2 class="mt-2 text-4xl font-extrabold tracking-tight sm:text-5xl">
            <span class="bg-gradient-to-r from-white to-gray-300 bg-clip-text text-transparent">
                {{ $main_title }}
            </span>
        </h2>
        <p class="mt-6 text-xl leading-relaxed text-gray-400">
            {{ $description }}
        </p>
    </div>
    
    {{-- Features Grid --}}
    <div class="features-grid mx-auto mt-20 max-w-2xl sm:mt-24 lg:mt-28 lg:max-w-none">
        <dl class="grid max-w-xl grid-cols-1 gap-8 lg:max-w-none lg:grid-cols-3">
            @foreach($features as $index => $feature)
                <div class="feature-card group relative flex flex-col rounded-2xl bg-gradient-to-br from-gray-800/60 to-gray-900/60 p-8 ring-1 ring-white/10 backdrop-blur-sm hover:ring-white/20 transition-all duration-500 hover:scale-105 hover:shadow-2xl hover:shadow-indigo-500/20 animate-fade-in-up animation-delay-{{ $index * 100 }}">
                    
                    {{-- Gradient overlay --}}
                    <div class="feature-card-gradient absolute inset-0 rounded-2xl bg-gradient-to-br from-indigo-500/10 via-purple-500/10 to-pink-500/10 opacity-0 group-hover:opacity-100 transition-opacity duration-500 pointer-events-none"></div>
                    
                    {{-- Icon --}}
                    <dt class="relative z-10">
                        <div class="feature-icon-wrapper mb-6 inline-flex p-4 rounded-xl bg-gradient-to-br from-indigo-500/20 to-purple-500/20 ring-1 ring-white/10 group-hover:ring-white/20 group-hover:scale-110 transition-all duration-300">
                            <div class="feature-icon w-6 h-6 group-hover:rotate-12 transition-transform duration-300">
                                {!! $feature['icon'] !!}
                            </div>
                        </div>
                        <div class="feature-title text-xl font-bold text-white group-hover:text-indigo-300 transition-colors duration-300">
                            {{ $feature['title'] }}
                        </div>
                    </dt>
                    
                    {{-- Description --}}
                    <dd class="relative z-10 mt-4 flex flex-auto flex-col">
                        <p class="feature-description flex-auto text-base leading-relaxed text-gray-400 group-hover:text-gray-300 transition-colors duration-300">
                            {{ $feature['description'] }}
                        </p>
                        
                        {{-- Learn more link --}}
                        <div class="feature-link mt-6 flex items-center text-sm font-semibold text-indigo-400 opacity-0 group-hover:opacity-100 transform translate-y-2 group-hover:translate-y-0 transition-all duration-300">
                            <span>Learn more</span>
                            <svg class="ml-2 w-4 h-4 group-hover:translate-x-2 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </div>
                    </dd>
                </div>
            @endforeach
        </dl>
    </div>
</div>

