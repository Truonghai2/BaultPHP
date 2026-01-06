{{--
    Recursive Menu Item Partial
    Renders a single menu item, which can be a link or a dropdown.

    Available variables:
    - $item: The menu item array.
    - $level: The current depth of the menu (0 for top-level, 1 for sub-menu, etc.).
--}}

@php
    $hasSubItems = !empty($item['sub_items']);
@endphp

@if ($hasSubItems)
    {{-- This item is a dropdown --}}
    <div class="relative" x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false">
        {{-- Dropdown Trigger --}}
        <button @click.prevent="open = !open" class="w-full flex items-center justify-between rounded-lg px-3 py-2 text-sm leading-6 transition-colors {{ $level > 0 ? 'text-gray-300 hover:bg-white/5 hover:text-white' : 'font-semibold text-gray-300 hover:text-white hover:bg-white/5' }}">
            <span>{{ $item['label'] ?? 'Menu Item' }}</span>
            <svg class="h-5 w-5 flex-none text-gray-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
            </svg>
        </button>

        {{-- Dropdown Panel --}}
        <div x-show="open" x-cloak x-transition class="absolute z-10 mt-1 w-56 rounded-xl bg-gray-800/95 p-2 shadow-lg ring-1 ring-white/10 backdrop-blur-md {{ $level === 0 ? '-left-8 top-full' : 'left-full -top-2' }}">
            @foreach ($item['sub_items'] as $sub_item)
                {{-- Recursive call for sub-items --}}
                @include('cms::blocks._menu_item', ['item' => $sub_item, 'level' => $level + 1])
            @endforeach
        </div>
    </div>
@else
    {{-- This item is a simple link --}}
    @php
        $isActive = request()->is(ltrim($item['url'] ?? '#', '/'));
    @endphp
    <a href="{{ $item['url'] ?? '#' }}" target="{{ $item['target'] ?? '_self' }}" class="block rounded-lg px-3 py-2 text-sm leading-6 transition-colors {{ $isActive && $level === 0 ? 'text-white bg-white/10' : 'text-gray-300 hover:bg-white/5 hover:text-white' }} {{ $level === 0 ? 'font-semibold' : '' }}">
        {{ $item['label'] ?? 'Menu Item' }}
    </a>
@endif