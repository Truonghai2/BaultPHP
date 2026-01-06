{{-- 
    HTML Block View
    
    Displays raw HTML content with optional wrapper
    
    Variables:
    - $html: Raw HTML content
    - $wrapInDiv: Whether to wrap in div (default: true)
    - $customClass: Additional CSS classes
--}}

@props(['html' => '', 'wrapInDiv' => true, 'customClass' => ''])

@if($wrapInDiv)
    <div class="block-html {{ $customClass }}">
        {!! $html !!}
    </div>
@else
    {!! $html !!}
@endif
