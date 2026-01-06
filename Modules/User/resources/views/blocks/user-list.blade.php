<div class="user-list-block">
    @if($users->isEmpty())
        <div class="empty-state text-center py-12 text-gray-400">
            <span class="text-6xl mb-4 block">👥</span>
            <p>No users found</p>
        </div>
    @else
        <div class="user-list {{ $layout === 'grid' ? 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4' : 'space-y-4' }}">
            @foreach($users as $user)
                <div class="user-item bg-gray-800/30 rounded-lg p-4 hover:bg-gray-800/50 transition-all group">
                    <div class="flex items-center gap-4">
                        {{-- Avatar --}}
                        @if($show_avatar)
                            @if(!empty($user->avatar))
                                <img 
                                    src="{{ $user->avatar }}" 
                                    alt="{{ $user->name }}"
                                    class="w-12 h-12 rounded-full ring-2 ring-indigo-500/30 group-hover:ring-indigo-500/50 transition-all"
                                >
                            @else
                                <div class="w-12 h-12 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center ring-2 ring-indigo-500/30 group-hover:ring-indigo-500/50 transition-all flex-shrink-0">
                                    <span class="text-lg font-bold text-white">
                                        {{ strtoupper(substr($user->name ?? 'U', 0, 1)) }}
                                    </span>
                                </div>
                            @endif
                        @endif

                        {{-- User Info --}}
                        <div class="flex-1 min-w-0">
                            <h4 class="text-base font-semibold text-white truncate group-hover:text-indigo-400 transition-colors">
                                {{ $user->name ?? 'User' }}
                            </h4>
                            
                            <div class="flex items-center gap-2 mt-1">
                                @if($show_role && !empty($user->role))
                                    <span class="text-xs text-indigo-400">
                                        {{ $user->role }}
                                    </span>
                                @endif

                                @if($show_joined_date && !empty($user->created_at))
                                    <span class="text-xs text-gray-500">
                                        {{ $user->created_at->diffForHumans() }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        {{-- View Profile Link --}}
                        <a 
                            href="/users/{{ $user->id }}" 
                            class="opacity-0 group-hover:opacity-100 transition-opacity text-indigo-400 hover:text-indigo-300"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

