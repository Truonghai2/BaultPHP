<div class="admin-dashboard-stats-block">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        {{-- Users Stats --}}
        @if($show_users)
            <div class="stat-category bg-gradient-to-br from-blue-500/10 to-blue-600/10 rounded-xl p-6 ring-1 ring-blue-500/20">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-semibold text-gray-300">Users</h3>
                    <span class="text-3xl">👥</span>
                </div>
                
                <div class="space-y-3">
                    <div>
                        <div class="text-3xl font-bold text-white">{{ number_format($stats['users']['total']) }}</div>
                        <div class="text-xs text-gray-400">Total Users</div>
                    </div>
                    <div class="flex justify-between text-sm pt-3 border-t border-white/10">
                        <span class="text-gray-400">Active Today:</span>
                        <span class="text-green-400 font-semibold">{{ $stats['users']['active_today'] }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-400">New Today:</span>
                        <span class="text-blue-400 font-semibold">{{ $stats['users']['new_today'] }}</span>
                    </div>
                </div>
            </div>
        @endif

        {{-- Pages Stats --}}
        @if($show_pages)
            <div class="stat-category bg-gradient-to-br from-purple-500/10 to-purple-600/10 rounded-xl p-6 ring-1 ring-purple-500/20">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-semibold text-gray-300">Pages</h3>
                    <span class="text-3xl">📄</span>
                </div>
                
                <div class="space-y-3">
                    <div>
                        <div class="text-3xl font-bold text-white">{{ number_format($stats['pages']['total']) }}</div>
                        <div class="text-xs text-gray-400">Total Pages</div>
                    </div>
                    <div class="flex justify-between text-sm pt-3 border-t border-white/10">
                        <span class="text-gray-400">Published:</span>
                        <span class="text-green-400 font-semibold">{{ $stats['pages']['published'] }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-400">Draft:</span>
                        <span class="text-yellow-400 font-semibold">{{ $stats['pages']['draft'] }}</span>
                    </div>
                </div>
            </div>
        @endif

        {{-- Blocks Stats --}}
        @if($show_blocks)
            <div class="stat-category bg-gradient-to-br from-indigo-500/10 to-indigo-600/10 rounded-xl p-6 ring-1 ring-indigo-500/20">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-semibold text-gray-300">Blocks</h3>
                    <span class="text-3xl">🧩</span>
                </div>
                
                <div class="space-y-3">
                    <div>
                        <div class="text-3xl font-bold text-white">{{ number_format($stats['blocks']['total']) }}</div>
                        <div class="text-xs text-gray-400">Block Types</div>
                    </div>
                    <div class="flex justify-between text-sm pt-3 border-t border-white/10">
                        <span class="text-gray-400">Page Blocks:</span>
                        <span class="text-indigo-400 font-semibold">{{ $stats['blocks']['page_blocks'] }}</span>
                    </div>
                </div>
            </div>
        @endif

        {{-- System Stats --}}
        @if($show_system)
            <div class="stat-category bg-gradient-to-br from-green-500/10 to-green-600/10 rounded-xl p-6 ring-1 ring-green-500/20">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-semibold text-gray-300">System</h3>
                    <span class="text-3xl">⚙️</span>
                </div>
                
                <div class="space-y-3">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-400">PHP:</span>
                        <span class="text-white font-semibold">{{ $stats['system']['php_version'] }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-400">Memory:</span>
                        <span class="text-white font-semibold">{{ $stats['system']['memory_usage'] }}</span>
                    </div>
                    <div class="flex justify-between text-sm pt-3 border-t border-white/10">
                        <span class="text-gray-400">Uptime:</span>
                        <span class="text-green-400 font-semibold">{{ $stats['system']['uptime'] }}</span>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

