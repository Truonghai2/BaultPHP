<div class="user-profile-block {{ $layout === 'card' ? 'bg-gray-800/50 rounded-xl p-6' : '' }}">
    {{-- Avatar --}}
    @if($show_avatar)
        <div class="user-avatar-wrapper text-center mb-4">
            @if(!empty($user->avatar))
                <img 
                    src="{{ $user->avatar }}" 
                    alt="{{ $user->name }}"
                    class="w-24 h-24 rounded-full mx-auto ring-4 ring-indigo-500/30"
                >
            @else
                <div class="w-24 h-24 rounded-full mx-auto bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center ring-4 ring-indigo-500/30">
                    <span class="text-3xl font-bold text-white">
                        {{ strtoupper(substr($user->name ?? 'U', 0, 1)) }}
                    </span>
                </div>
            @endif
        </div>
    @endif

    {{-- User Info --}}
    <div class="user-info text-center mb-4">
        <h3 class="text-2xl font-bold text-white mb-1">
            {{ $user->name ?? 'User' }}
        </h3>
        
        @if(!empty($user->email))
            <p class="text-sm text-gray-400 mb-2">{{ $user->email }}</p>
        @endif

        @if(!empty($user->role))
            <span class="inline-block px-3 py-1 text-xs font-semibold text-indigo-400 bg-indigo-500/10 rounded-full ring-1 ring-indigo-500/20">
                {{ $user->role }}
            </span>
        @endif
    </div>

    {{-- Bio --}}
    @if($show_bio && !empty($user->bio))
        <div class="user-bio text-center text-gray-300 text-sm mb-4 pb-4 border-b border-white/10">
            {{ $user->bio }}
        </div>
    @endif

    {{-- Stats --}}
    @if($show_stats)
        <div class="user-stats grid grid-cols-3 gap-4 mb-4">
            <div class="stat-item text-center">
                <div class="stat-value text-2xl font-bold text-white">{{ $stats['posts'] ?? 0 }}</div>
                <div class="stat-label text-xs text-gray-400">Posts</div>
            </div>
            <div class="stat-item text-center">
                <div class="stat-value text-2xl font-bold text-white">{{ $stats['followers'] ?? 0 }}</div>
                <div class="stat-label text-xs text-gray-400">Followers</div>
            </div>
            <div class="stat-item text-center">
                <div class="stat-value text-2xl font-bold text-white">{{ $stats['following'] ?? 0 }}</div>
                <div class="stat-label text-xs text-gray-400">Following</div>
            </div>
        </div>
    @endif

    {{-- Social Links --}}
    @if($show_social_links && !empty($user->social_links))
        <div class="user-social flex justify-center gap-3 pt-4 border-t border-white/10">
            @foreach($user->social_links as $platform => $url)
                <a 
                    href="{{ $url }}" 
                    target="_blank"
                    rel="noopener"
                    class="social-link w-10 h-10 flex items-center justify-center rounded-lg bg-gray-700/50 hover:bg-gray-700 text-gray-400 hover:text-white transition-all hover:scale-110"
                >
                    {{-- Icon placeholder - replace with actual icons --}}
                    <span class="text-sm">{{ strtoupper(substr($platform, 0, 2)) }}</span>
                </a>
            @endforeach
        </div>
    @endif
</div>

