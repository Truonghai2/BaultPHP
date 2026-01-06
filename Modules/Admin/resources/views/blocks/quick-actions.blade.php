<div class="quick-actions-block">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        @foreach($actions as $action)
            <a 
                href="{{ $action['url'] }}" 
                class="action-button group relative overflow-hidden bg-gradient-to-br from-{{ $action['color'] }}-500/10 to-{{ $action['color'] }}-600/10 hover:from-{{ $action['color'] }}-500/20 hover:to-{{ $action['color'] }}-600/20 rounded-xl p-6 ring-1 ring-{{ $action['color'] }}-500/20 hover:ring-{{ $action['color'] }}-500/40 transition-all hover:scale-105"
            >
                {{-- Gradient Overlay --}}
                <div class="absolute inset-0 bg-gradient-to-br from-{{ $action['color'] }}-500/0 to-{{ $action['color'] }}-600/0 group-hover:from-{{ $action['color'] }}-500/10 group-hover:to-{{ $action['color'] }}-600/10 transition-all duration-300"></div>

                {{-- Content --}}
                <div class="relative z-10 text-center">
                    <div class="text-4xl mb-3 transform group-hover:scale-110 transition-transform duration-300">
                        {{ $action['icon'] }}
                    </div>
                    
                    <div class="text-sm font-semibold text-white group-hover:text-{{ $action['color'] }}-300 transition-colors">
                        {{ $action['label'] }}
                    </div>
                </div>

                {{-- Hover Arrow --}}
                <div class="absolute bottom-2 right-2 opacity-0 group-hover:opacity-100 transition-all transform translate-x-2 group-hover:translate-x-0">
                    <svg class="w-4 h-4 text-{{ $action['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </div>
            </a>
        @endforeach
    </div>
</div>

