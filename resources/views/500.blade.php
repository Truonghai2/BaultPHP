@extends('layouts.app')

@section('title', 'Lỗi Máy Chủ')

@section('content')
    <div class="text-center">
        <p class="text-base font-semibold text-red-400">500</p>
        <h1 class="mt-4 text-3xl font-bold tracking-tight text-white sm:text-5xl">Lỗi máy chủ nội bộ</h1>
        <p class="mt-6 text-base leading-7 text-gray-400">Rất tiếc, đã có lỗi xảy ra. Chúng tôi đang tìm cách khắc phục.</p>
        <div class="mt-10 flex items-center justify-center gap-x-6">
            <a href="/" class="rounded-md bg-indigo-500 px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-400 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-400">
                Quay về trang chủ
            </a>
        </div>
    </div>
@endsection

