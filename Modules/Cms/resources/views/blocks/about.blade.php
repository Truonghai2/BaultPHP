{{-- About Block – Giao diện trang Giới thiệu (theme tối đồng bộ) --}}
<section class="about-block py-12 lg:py-16">
    {{-- Intro --}}
    <div class="max-w-3xl mx-auto text-center mb-16">
        <h2 class="text-4xl font-extrabold text-white sm:text-5xl">
            {{ $title ?? 'Về chúng tôi' }}
        </h2>
        <p class="mt-4 text-xl text-indigo-300 font-medium">
            {{ $subtitle ?? '' }}
        </p>
        <p class="mt-6 text-lg text-gray-300 leading-relaxed">
            {{ $intro ?? '' }}
        </p>
    </div>

    {{-- Mission --}}
    @if(!empty($mission_title) || !empty($mission_text))
    <div class="bg-gray-800/50 backdrop-blur rounded-2xl border border-white/5 p-8 lg:p-10 mb-12">
        <h3 class="text-2xl font-bold text-white mb-4">{{ $mission_title ?? 'Sứ mệnh' }}</h3>
        <p class="text-gray-300 leading-relaxed">{{ $mission_text ?? '' }}</p>
    </div>
    @endif

    {{-- Values --}}
    @if(!empty($values) && is_array($values))
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        @foreach($values as $item)
        <div class="bg-gray-800/50 rounded-xl border border-white/5 p-6 hover:border-indigo-500/30 transition-colors">
            <div class="flex items-center gap-3 mb-3">
                @if(!empty($item['icon']))
                <span class="flex items-center justify-center w-10 h-10 rounded-lg bg-indigo-500/20 text-indigo-400 text-lg">{{ $item['icon'] }}</span>
                @endif
                <h4 class="text-lg font-semibold text-white">{{ $item['title'] ?? '' }}</h4>
            </div>
            <p class="text-gray-400 text-sm leading-relaxed">{{ $item['text'] ?? '' }}</p>
        </div>
        @endforeach
    </div>
    @endif
</section>
