<div class="block-text prose prose-invert max-w-none">
    @if($show_title && !empty($title))
        <h3 class="block-title text-2xl font-bold text-white mb-4">{{ $title }}</h3>
    @endif
    
    <div class="block-content text-gray-300 leading-relaxed">
        {!! $content !!}
    </div>
</div>

