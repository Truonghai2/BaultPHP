<div class="team-block">
    @if(empty($team))
        <div class="empty-state text-center py-12 text-gray-400">
            <p>No team members to display</p>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-{{ $columns }} gap-8">
            @foreach($team as $member)
                <div class="team-member bg-gray-800/50 rounded-lg p-6 text-center hover:bg-gray-800/70 transition-all">
                    @if($show_avatar && !empty($member['avatar']))
                        <img 
                            src="{{ $member['avatar'] }}" 
                            alt="{{ $member['name'] ?? '' }}" 
                            class="w-24 h-24 rounded-full mx-auto mb-4 ring-2 ring-indigo-500"
                        >
                    @endif
                    
                    <h3 class="text-xl font-semibold text-white mb-2">
                        {{ $member['name'] ?? 'Unknown' }}
                    </h3>
                    
                    @if($show_role && !empty($member['role']))
                        <p class="text-indigo-400 text-sm mb-2">{{ $member['role'] }}</p>
                    @endif
                    
                    @if($show_bio && !empty($member['bio']))
                        <p class="text-gray-300 text-sm">{{ $member['bio'] }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>

