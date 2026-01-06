<div class="recent-activity-block bg-gray-800/30 rounded-xl p-6">
    <h3 class="text-lg font-bold text-white mb-4 flex items-center gap-2">
        <span>📝</span>
        <span>Recent Activity</span>
    </h3>

    @if(empty($activities))
        <div class="empty-state text-center py-8 text-gray-400">
            <p>No recent activity</p>
        </div>
    @else
        <div class="activity-list space-y-3">
            @foreach($activities as $activity)
                <div class="activity-item flex items-start gap-3 p-3 rounded-lg hover:bg-gray-800/50 transition-all">
                    {{-- Type Icon --}}
                    @if($show_action_type)
                        <div class="activity-icon w-10 h-10 flex-shrink-0 rounded-lg flex items-center justify-center
                            {{ $activity['type'] === 'create' ? 'bg-green-500/20 text-green-400' : '' }}
                            {{ $activity['type'] === 'update' ? 'bg-blue-500/20 text-blue-400' : '' }}
                            {{ $activity['type'] === 'delete' ? 'bg-red-500/20 text-red-400' : '' }}
                        ">
                            @if($activity['type'] === 'create')
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                            @elseif($activity['type'] === 'update')
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            @else
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            @endif
                        </div>
                    @endif

                    {{-- Activity Details --}}
                    <div class="flex-1 min-w-0">
                        @if($show_user)
                            <div class="text-sm font-semibold text-white mb-1">
                                {{ $activity['user'] }}
                            </div>
                        @endif
                        
                        <div class="text-sm text-gray-300">
                            {{ $activity['action'] }} 
                            <span class="text-indigo-400 font-medium">{{ $activity['target'] }}</span>
                        </div>

                        @if($show_timestamp)
                            <div class="text-xs text-gray-500 mt-1">
                                {{ $activity['timestamp']->diffForHumans() }}
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

