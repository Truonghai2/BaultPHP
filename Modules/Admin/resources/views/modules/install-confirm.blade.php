@extends('layouts.app')

@section('title', 'Xác Nhận Cài Đặt Module')

@section('content')
    <div class="max-w-3xl mx-auto bg-white p-8 rounded-lg shadow-md">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">Phát hiện Module mới</h1>
        <p class="text-gray-600 mb-8">Hệ thống đã tìm thấy các module sau chưa được cài đặt. Vui lòng xem lại thông tin và chọn những module bạn muốn thêm vào hệ thống.</p>

        <form action="{{ route('admin.modules.install.process') }}" method="POST" autocomplete="off">
            @csrf

            <div class="space-y-6">
                @foreach($modules as $module)
                    <div class="border border-gray-200 p-6 rounded-lg">
                        <h3 class="text-xl font-semibold text-gray-800">
                            <label class="flex items-center space-x-3">
                                <input type="checkbox" name="modules[]" value="{{ $module['name'] }}" checked class="h-5 w-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span>
                                    {{ $module['name'] }}
                                    <small class="text-sm font-normal text-gray-500">(v{{ $module['version'] }})</small>
                                </span>
                            </label>
                        </h3>
                        <p class="mt-2 text-gray-600">{{ $module['description'] }}</p>

                        @if (!empty($module['requirements']))
                            <div class="mt-4 text-sm bg-gray-50 p-4 rounded-md">
                                <strong class="text-gray-700">Yêu cầu:</strong>
                                <ul class="list-disc list-inside mt-2 space-y-1">
                                    @foreach ($module['requirements'] as $key => $value)
                                        <li><strong class="font-medium">{{ $key }}</strong>: {{ $value }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                @endforeach

                @if (empty($modules))
                    <p class="text-center text-gray-500 py-8">Không có module mới nào được tìm thấy.</p>
                @endif
            </div>

            <div class="mt-8 text-right">
                @if(count($modules) > 0)
                    <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition">
                        Cài đặt các Module đã chọn
                    </button>
                @endif
            </div>
        </form>

    </div>
@endsection