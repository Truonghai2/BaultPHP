<nav class="block-menu block-menu-{{ $style }}">
    @if(empty($menu_items))
        <div class="block-menu empty text-gray-400 p-4">No menu items configured</div>
    @else
        <ul class="menu-list @if($style === 'horizontal') flex items-center gap-4 @else flex flex-col gap-2 @endif">
            @foreach($menu_items as $item)
                <li class="menu-item">
                    <a 
                        href="{{ $item['url'] ?? '#' }}" 
                        target="{{ $item['target'] ?? '_self' }}"
                        class="menu-link flex items-center gap-2 px-4 py-2 text-sm text-gray-300 hover:text-white hover:bg-white/5 rounded-lg transition-all"
                    >
                        @if($show_icons && !empty($item['icon']))
                            <i class="{{ $item['icon'] }}"></i>
                        @endif
                        <span>{{ $item['label'] ?? 'Menu Item' }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</nav>

