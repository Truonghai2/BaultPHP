<form action="{{ $action }}" method="{{ $method }}" class="search-block flex items-center gap-2">
    <input 
        type="search" 
        name="q" 
        placeholder="{{ $placeholder }}" 
        class="search-input flex-1 px-4 py-2 bg-gray-800/50 border border-white/10 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
        required
    >
    
    @if($show_button)
        <button 
            type="submit" 
            class="search-button px-4 py-2 bg-gradient-to-r from-indigo-500 to-purple-600 hover:from-indigo-600 hover:to-purple-700 text-white rounded-lg transition-all hover:scale-105 shadow-lg shadow-indigo-500/30"
        >
            🔍
        </button>
    @endif
</form>

