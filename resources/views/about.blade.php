@extends('layouts.app')

@section('title', 'About ' . $company['name'])

@section('content')
    @php
        // Get About page from CMS
        $aboutPage = \Modules\Cms\Infrastructure\Models\Page::where('slug', 'about-us')->first();
        $userRoles = auth()->check() ? (auth()->user()->getRoles() ?? []) : null;
    @endphp

    @if($aboutPage)
        {{-- About Hero Section - Page Blocks --}}
        @php
            $heroContent = render_page_blocks($aboutPage, 'hero', ['company' => $company], $userRoles);
        @endphp
        @if($heroContent)
            {!! $heroContent !!}
        @else
            {{-- Fallback to global blocks --}}
            {!! render_block_region('about-hero') !!}
        @endif

        {{-- About Content Section - Page Blocks --}}
        {!! render_page_blocks($aboutPage, 'content', ['company' => $company], $userRoles) !!}

        {{-- About Team Section - Page Blocks --}}
        {!! render_page_blocks($aboutPage, 'sidebar', ['company' => $company], $userRoles) !!}
    @else
        {{-- Fallback: Use static block regions if no 'about-us' page configured --}}
        {!! render_block_region('about-hero') !!}
        {!! render_block_region('about-team') !!}
    @endif
@endsection
