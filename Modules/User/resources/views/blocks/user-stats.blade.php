<div class="user-stats-block">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        {{-- Total Users --}}
        @if($show_total)
            <div class="stat-card bg-gradient-to-br from-blue-500/10 to-blue-600/10 rounded-xl p-6 ring-1 ring-blue-500/20">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-gray-400">Total Users</span>
                    <span class="text-2xl">👥</span>
                </div>
                <div class="text-3xl font-bold text-white mb-1">
                    {{ number_format($stats['total']) }}
                </div>
                <div class="text-xs text-gray-400">All time</div>
            </div>
        @endif

        {{-- Active Users --}}
        @if($show_active)
            <div class="stat-card bg-gradient-to-br from-green-500/10 to-green-600/10 rounded-xl p-6 ring-1 ring-green-500/20">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-gray-400">Active</span>
                    <span class="text-2xl">✅</span>
                </div>
                <div class="text-3xl font-bold text-white mb-1">
                    {{ number_format($stats['active']) }}
                </div>
                <div class="text-xs text-gray-400">Last {{ $period }}</div>
            </div>
        @endif

        {{-- New Users --}}
        @if($show_new)
            <div class="stat-card bg-gradient-to-br from-purple-500/10 to-purple-600/10 rounded-xl p-6 ring-1 ring-purple-500/20">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-gray-400">New Users</span>
                    <span class="text-2xl">🆕</span>
                </div>
                <div class="text-3xl font-bold text-white mb-1">
                    {{ number_format($stats['new']) }}
                </div>
                <div class="text-xs text-gray-400">Last {{ $period }}</div>
            </div>
        @endif

        {{-- Online Users --}}
        @if($show_online)
            <div class="stat-card bg-gradient-to-br from-indigo-500/10 to-indigo-600/10 rounded-xl p-6 ring-1 ring-indigo-500/20">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-gray-400">Online Now</span>
                    <span class="text-2xl">🟢</span>
                </div>
                <div class="text-3xl font-bold text-white mb-1">
                    {{ number_format($stats['online']) }}
                </div>
                <div class="text-xs text-gray-400">Last 5 min</div>
            </div>
        @endif
    </div>
</div>

