@extends('layouts.app')

@section('title', 'Documentation')

@section('content')
{{-- Page block: hero region (nếu có CMS page slug "docs" với blocks vùng hero) --}}
@if(isset($docsPage) && $docsPage)
    @php $heroContent = render_page_blocks($docsPage, 'hero', null, $userRoles ?? null); @endphp
    @if($heroContent)
        <div class="docs-page-hero mb-6">{!! $heroContent !!}</div>
    @endif
@endif

<div class="docs-layout">
    <aside class="docs-sidebar">
        {{-- Page block: sidebar region (nếu có CMS page "docs" với blocks vùng sidebar) --}}
        @if(isset($docsPage) && $docsPage)
            @php $sidebarBlocks = render_page_blocks($docsPage, 'sidebar', null, $userRoles ?? null); @endphp
            @if($sidebarBlocks)
                <div class="docs-page-sidebar mb-4">{!! $sidebarBlocks !!}</div>
            @endif
        @endif
        <h2>Documentation</h2>
        <ul class="docs-nav">
            @foreach($files as $f)
                <li>
                    <a href="/docs/{{ urlencode($f['slug']) }}" class="{{ ($current ?? '') === $f['slug'] ? 'active' : '' }}">{{ e($f['title']) }}</a>
                </li>
            @endforeach
        </ul>
        @if(empty($files))
            <p class="text-sm text-gray-500">No docs found.</p>
        @endif
    </aside>
    <main class="docs-main">
        {{-- Page block: content region trên cùng (nếu có CMS page "docs" với blocks vùng content) --}}
        @if(isset($docsPage) && $docsPage)
            @php $topContent = render_page_blocks($docsPage, 'content', null, $userRoles ?? null); @endphp
            @if($topContent)
                <div class="docs-page-content-top mb-6">{!! $topContent !!}</div>
            @endif
        @endif
        <div class="docs-content" id="docs-body">
            @if($content !== '')
                <div id="doc-raw" data-content="{{ base64_encode($content) }}" style="display: none;"></div>
                <div id="doc-rendered"></div>
            @else
                <div class="docs-empty">
                    <p>Chọn một tài liệu từ danh sách bên trái.</p>
                </div>
            @endif
        </div>
    </main>
</div>
@endsection

@push('styles')
<style>
    .docs-layout { display: flex; gap: 1.5rem; max-width: 1400px; margin: 0 auto; padding: 1.5rem 1rem; }
    .docs-sidebar {
        width: 280px; flex-shrink: 0;
        background: rgba(255,255,255,0.03); border-radius: 12px; padding: 1rem;
        max-height: calc(100vh - 120px); overflow-y: auto;
    }
    .docs-sidebar h2 { font-size: 0.9rem; font-weight: 700; color: rgba(255,255,255,0.9); margin: 0 0 0.75rem 0; padding-bottom: 0.5rem; border-bottom: 1px solid rgba(255,255,255,0.1); }
    .docs-nav { list-style: none; padding: 0; margin: 0; }
    .docs-nav a {
        display: block; padding: 0.5rem 0.75rem; border-radius: 8px;
        color: rgba(255,255,255,0.75); text-decoration: none; font-size: 0.9rem;
        transition: background 0.15s, color 0.15s;
    }
    .docs-nav a:hover { background: rgba(255,255,255,0.08); color: #fff; }
    .docs-nav a.active { background: rgba(99, 102, 241, 0.25); color: #a5b4fc; }
    .docs-main { flex: 1; min-width: 0; }
    .docs-content {
        background: rgba(255,255,255,0.03); border-radius: 12px; padding: 2rem;
        color: #e5e7eb; line-height: 1.7;
    }
    .docs-content h1 { font-size: 1.75rem; margin: 0 0 1rem 0; color: #fff; }
    .docs-content h2 { font-size: 1.35rem; margin: 1.5rem 0 0.75rem 0; color: #f3f4f6; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 0.25rem; }
    .docs-content h3 { font-size: 1.15rem; margin: 1.25rem 0 0.5rem 0; color: #e5e7eb; }
    .docs-content p { margin: 0 0 1rem 0; }
    .docs-content ul, .docs-content ol { margin: 0 0 1rem 0; padding-left: 1.5rem; }
    .docs-content li { margin: 0.25rem 0; }
    .docs-content code { background: rgba(0,0,0,0.3); padding: 0.2em 0.4em; border-radius: 4px; font-size: 0.9em; font-family: 'JetBrains Mono', monospace; }
    .docs-content pre { background: rgba(0,0,0,0.35); padding: 1rem; border-radius: 8px; overflow-x: auto; margin: 1rem 0; }
    .docs-content pre code { background: none; padding: 0; }
    .docs-content blockquote { border-left: 4px solid rgba(99, 102, 241, 0.6); margin: 1rem 0; padding-left: 1rem; color: rgba(255,255,255,0.8); }
    .docs-content table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
    .docs-content th, .docs-content td { border: 1px solid rgba(255,255,255,0.15); padding: 0.5rem 0.75rem; text-align: left; }
    .docs-content th { background: rgba(255,255,255,0.06); font-weight: 600; }
    .docs-empty { text-align: center; padding: 3rem 2rem; color: rgba(255,255,255,0.5); }
    @media (max-width: 768px) {
        .docs-layout { flex-direction: column; }
        .docs-sidebar { width: 100%; max-height: 240px; }
    }
</style>
@endpush

@if($content !== '')
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<script>
(function() {
    var raw = document.getElementById('doc-raw');
    var out = document.getElementById('doc-rendered');
    if (!raw || !out) return;
    try {
        var txt = atob(raw.getAttribute('data-content') || '');
        if (typeof marked !== 'undefined') {
            marked.setOptions({ gfm: true, breaks: true });
            out.innerHTML = marked.parse(txt);
        } else {
            out.textContent = txt;
        }
    } catch (e) { out.textContent = 'Failed to load content.'; }
})();
</script>
@endpush
@endif
