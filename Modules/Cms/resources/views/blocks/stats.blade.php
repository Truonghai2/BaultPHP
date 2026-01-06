<div class="stats-block stats-cols-{{ $columns }} grid grid-cols-1 md:grid-cols-{{ $columns }} gap-6">
    @foreach($stats as $stat)
        <div class="stat-card stat-card-{{ $stat['color'] ?? 'blue' }} bg-gradient-to-br from-gray-800/60 to-gray-900/60 rounded-xl p-6 ring-1 ring-white/10 hover:ring-white/20 transition-all hover:scale-105">
            @if($show_icons && !empty($stat['icon']))
                <div class="stat-icon text-4xl mb-3">{{ $stat['icon'] }}</div>
            @endif
            
            <div class="stat-value text-3xl font-bold text-white mb-2">
                {{ $stat['value'] }}
            </div>
            
            <div class="stat-label text-sm text-gray-400">
                {{ $stat['label'] }}
            </div>
        </div>
    @endforeach
</div>

