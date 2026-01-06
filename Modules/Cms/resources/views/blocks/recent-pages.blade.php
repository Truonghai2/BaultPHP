<div class="block-recent-pages">
    @if($pages->isEmpty())
        <div class="empty-state text-center py-8 text-gray-400">
            <p>No pages found</p>
        </div>
    @else
        <ul class="pages-list space-y-4">
            @foreach($pages as $page)
                <li class="page-item bg-gray-800/30 rounded-lg p-4 hover:bg-gray-800/50 transition-all">
                    <h4 class="page-title mb-2">
                        <a 
                            href="/pages/{{ $page->slug }}" 
                            class="text-lg font-semibold text-white hover:text-indigo-400 transition-colors"
                        >
                            {{ $page->name }}
                        </a>
                    </h4>
                    
                    @if($show_date || $show_author)
                        <div class="page-meta flex items-center gap-4 text-sm text-gray-400 mb-2">
                            @if($show_date)
                                @php
                                    $date = $order_by === 'updated_at' ? $page->updated_at : $page->created_at;
                                @endphp
                                @if($date)
                                    <span class="page-date">📅 {{ $date->format('M d, Y') }}</span>
                                @endif
                            @endif
                            
                            @if($show_author && $page->user_id)
                                <span class="page-author">✍️ User #{{ $page->user_id }}</span>
                            @endif
                        </div>
                    @endif
                    
                    @if($excerpt_length > 0)
                        {{-- TODO: Extract excerpt from page content --}}
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>

