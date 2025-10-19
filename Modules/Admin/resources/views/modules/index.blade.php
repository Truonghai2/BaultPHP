@extends('layouts.app')

@section('title', 'Quản lý Modules')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800">
            Quản lý Modules
        </h1>
        {{-- Nút để mở modal upload module mới --}}
        <button class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
            Cài đặt Module mới
        </button>
    </div>

    {{-- Vùng chứa này sẽ được quản lý bởi JavaScript (ví dụ: Vue, React) để hiển thị danh sách module --}}
    <div id="module-manager"></div>
</div>
@endsection