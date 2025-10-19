@extends('layouts.app')

@section('title', 'Xác Nhận Cài Đặt Module')

@section('content')
    <div class="max-w-3xl mx-auto bg-white p-8 rounded-lg shadow-md">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">Phát hiện Module mới</h1>
        <p class="text-gray-600 mb-8">Hệ thống đã tìm thấy các module sau chưa được cài đặt. Vui lòng xem lại thông tin và chọn những module bạn muốn thêm vào hệ thống.</p>

        <form action="{{ url('/admin/modules/install/confirm') }}" method="POST">
            @csrf

            <div class="space-y-6">
                @forelse($modules as $module)
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
                @empty
                    <p class="text-center text-gray-500 py-8">Không có module mới nào được tìm thấy.</p>
                @endforelse
            </div>

            <div class="mt-8 text-right">
                @if(!empty($modules))
                    <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition">
                        Cài đặt các Module đã chọn
                    </button>
                @endif
            </div>
        </form>

        <div class="mt-10 p-4 bg-yellow-100 border-l-4 border-yellow-400 text-yellow-800 rounded-r-lg">
            <h4 class="font-bold">Lưu ý quan trọng</h4>
            <p class="mt-1">Sau khi cài đặt, bạn có thể cần phải chạy lệnh migration để cập nhật cơ sở dữ liệu. Hãy chạy lệnh sau từ terminal:</p>
            <pre class="mt-2 bg-gray-800 text-white p-3 rounded-md text-sm"><code>php bault ddd:migrate</code></pre>
        </div>
    </div>
@endsection