{{-- Welcome Banner Block --}}
<div class="welcome-banner welcome-banner-{{ $style }}">
    <div class="banner-content">
        <h1 class="banner-title">{{ $title }}</h1>
        <p class="banner-subtitle">{{ $subtitle }}</p>
        <p class="banner-description">{{ $description }}</p>
        
        @if($show_button)
            <a href="{{ $button_url }}" class="banner-button">{{ $button_text }}</a>
        @endif
    </div>
</div>

