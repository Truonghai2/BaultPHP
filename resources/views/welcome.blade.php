@extends('layouts.app')

@section('title', 'Chào mừng đến với BaultFrame')

@section('content')
    <div class="text-center max-w-3xl mx-auto">
        <h1 class="text-5xl md:text-7xl font-extrabold tracking-tight gradient-text">
            BaultFrame
        </h1>

        <p class="mt-6 text-lg md:text-xl text-gray-400">
            Một framework PHP hiện đại, mạnh mẽ và được xây dựng với sự đơn giản.
            <br class="hidden md:block">
            Tập trung vào hiệu suất và trải nghiệm của nhà phát triển.
        </p>

        <div class="mt-10 flex items-center justify-center gap-x-6">
            <a href="#" class="rounded-md bg-indigo-500 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-400 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-400 transition-colors duration-200">
                Bắt đầu
            </a>
            <a href="#" target="_blank" class="text-sm font-semibold leading-6 text-gray-300 hover:text-white transition-colors duration-200">
                Tài liệu <span aria-hidden="true">→</span>
            </a>
        </div>

        <div class="mt-16 text-sm text-gray-500">
            Phiên bản {{ esc(app()->version()) }}
        </div>
    </div>
@endsection
