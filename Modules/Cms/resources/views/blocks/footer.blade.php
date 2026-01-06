<div class="footer-block bg-gray-900/50 border-t border-white/10 py-12">
    <div class="max-w-7xl mx-auto px-6">
        <!-- Footer Columns -->
        <div class="footer-columns grid grid-cols-1 md:grid-cols-{{ count($columns) }} gap-8 mb-8">
            @foreach($columns as $column)
                <div class="footer-column">
                    <h3 class="footer-title text-white font-semibold text-lg mb-4">
                        {{ $column['title'] }}
                    </h3>
                    <div class="footer-links flex flex-col gap-2">
                        @foreach($column['links'] as $link)
                            <a 
                                href="{{ $link['url'] }}" 
                                class="footer-link text-gray-400 hover:text-white transition-colors text-sm"
                            >
                                {{ $link['label'] }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
        
        <!-- Footer Bottom -->
        <div class="footer-bottom border-t border-white/5 pt-8 flex flex-col md:flex-row justify-between items-center gap-4">
            <div class="footer-copyright text-gray-400 text-sm">
                {{ $copyright }}
            </div>
            
            <div class="footer-social flex items-center gap-4">
                @foreach($social_links as $social)
                    <a 
                        href="{{ $social['url'] }}" 
                        class="social-link text-2xl hover:scale-110 transition-transform" 
                        title="{{ $social['platform'] }}"
                        target="_blank"
                        rel="noopener"
                    >
                        {{ $social['icon'] }}
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</div>

