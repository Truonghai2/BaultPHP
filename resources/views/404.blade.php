@extends('layouts.app')

@section('title', 'Không Tìm Thấy Trang')

@section('content')
    <div class="text-center">
        <p class="text-base font-semibold text-indigo-400">404</p>
        <h1 class="mt-4 text-3xl font-bold tracking-tight text-white sm:text-5xl">Không tìm thấy trang</h1>
        <p class="mt-6 text-base leading-7 text-gray-400">Xin lỗi, chúng tôi không thể tìm thấy trang bạn đang tìm kiếm.</p>
        <div class="mt-10 flex items-center justify-center gap-x-6">
            <a href="/" class="rounded-md bg-indigo-500 px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-400 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-400">
                Quay về trang chủ
            </a>
            <a href="#" class="text-sm font-semibold text-gray-300 hover:text-white">Liên hệ hỗ trợ <span aria-hidden="true">&rarr;</span></a>
        </div>
    </div>
@endsection

