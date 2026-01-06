{{-- Homepage Stats Block --}}
<div class="stats-section mx-auto mt-32 max-w-7xl px-6 sm:mt-40 lg:px-8 relative">
    <div class="mx-auto max-w-2xl lg:max-w-none">
        
        {{-- Decorative gradient --}}
        <div class="stats-decoration absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-gradient-to-r from-indigo-500/10 via-purple-500/10 to-pink-500/10 rounded-full blur-3xl pointer-events-none"></div>
        
        {{-- Section Header --}}
        <div class="stats-header text-center relative z-10">
            <h2 class="text-4xl font-extrabold tracking-tight sm:text-5xl">
                <span class="bg-gradient-to-r from-white via-blue-100 to-purple-200 bg-clip-text text-transparent">
                    {{ $title }}
                </span>
            </h2>
            <p class="mt-6 text-xl leading-8 text-gray-400">
                {{ $description }}
            </p>
        </div>
        
        {{-- Stats Grid --}}
        <dl class="stats-grid mt-20 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4 relative z-10">
            @foreach($stats as $index => $stat)
                @include('cms::blocks.components.stat-card', [
                    'stat' => $stat,
                    'index' => $index,
                    'color' => $colors[$index % count($colors)]
                ])
            @endforeach
        </dl>
    </div>
</div>

