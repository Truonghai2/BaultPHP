{{--
    View for the Navigation Block.
    
    Available variables:
    - $menu_items: array, Each item contains 'label', 'url', 'icon', 'target'.
    - $style: string, 'horizontal' or 'vertical'.
    - $show_icons: bool.
--}}

@if(!empty($menu_items))
    <nav class="flex items-center {{ $style === 'vertical' ? 'flex-col space-y-2' : 'flex-row lg:gap-x-2' }}">
        @foreach ($menu_items as $item)
            @include('cms::blocks._menu_item', ['item' => $item, 'level' => 0])
        @endforeach
    </nav>
@endif