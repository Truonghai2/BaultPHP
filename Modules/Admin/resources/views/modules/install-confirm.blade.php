@extends('layouts.app')

@section('title', 'Xác Nhận Cài Đặt Module')

@section('content')
    <div class="max-w-4xl mx-auto">
        <!-- Header với cảnh báo -->
        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-6 mb-6 rounded-lg">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <svg class="h-8 w-8 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div class="ml-4 flex-1">
                    <h2 class="text-2xl font-bold text-yellow-800 mb-2">
                        🔍 Phát hiện {{ count($modules) }} Module mới chưa được cài đặt
                    </h2>
                    <p class="text-yellow-700 leading-relaxed">
                        Hệ thống đã phát hiện các module sau trong thư mục <code class="bg-yellow-100 px-2 py-1 rounded">/Modules</code> 
                        nhưng chưa được đăng ký trong cơ sở dữ liệu. Đây có thể là module mới được thêm vào hoặc update code.
                        <br><br>
                        Vui lòng xem xét thông tin các module và quyết định cài đặt ngay bây giờ hoặc để sau.
                    </p>
                </div>
            </div>
        </div>

        <!-- Form cài đặt -->
        <div class="bg-white rounded-lg shadow-md">
            <form action="{{ route('admin.modules.install.process') }}" method="POST" autocomplete="off" id="install-form">
                @csrf

                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-800">Danh sách Module</h3>
                        <div class="flex items-center space-x-2">
                            <button type="button" onclick="selectAll()" class="text-sm text-blue-600 hover:text-blue-800">
                                Chọn tất cả
                            </button>
                            <span class="text-gray-300">|</span>
                            <button type="button" onclick="deselectAll()" class="text-sm text-blue-600 hover:text-blue-800">
                                Bỏ chọn tất cả
                            </button>
                        </div>
                    </div>
                </div>

                <div class="divide-y divide-gray-200">
                    @foreach($modules as $module)
                        <div class="p-6 hover:bg-gray-50 transition-colors">
                            <div class="flex items-start">
                                <div class="flex items-center h-6">
                                    <input 
                                        type="checkbox" 
                                        name="modules[]" 
                                        value="{{ $module['name'] }}" 
                                        checked 
                                        class="h-5 w-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 module-checkbox"
                                        id="module-{{ $module['name'] }}"
                                    >
                                </div>
                                <div class="ml-4 flex-1">
                                    <label for="module-{{ $module['name'] }}" class="cursor-pointer">
                                        <div class="flex items-center justify-between">
                                            <h4 class="text-lg font-semibold text-gray-900">
                                                {{ $module['display_name'] ?? $module['name'] }}
                                                <span class="ml-2 text-sm font-normal text-gray-500">v{{ $module['version'] }}</span>
                                            </h4>
                                            @if (!empty($module['author']))
                                                <span class="text-sm text-gray-500">by {{ $module['author'] }}</span>
                                            @endif
                                        </div>
                                        <p class="mt-1 text-sm text-gray-600">{{ $module['description'] }}</p>
                                    </label>

                                    @if (!empty($module['requirements']))
                                        <div class="mt-3 p-3 bg-blue-50 border border-blue-200 rounded">
                                            <p class="text-xs font-semibold text-blue-800 mb-2">📦 Dependencies:</p>
                                            <ul class="text-xs text-blue-700 space-y-1">
                                                @foreach ($module['requirements'] as $key => $value)
                                                    <li class="flex items-center">
                                                        <span class="inline-block w-2 h-2 bg-blue-400 rounded-full mr-2"></span>
                                                        <code class="bg-blue-100 px-2 py-0.5 rounded">{{ $key }}</code>
                                                        <span class="mx-1">:</span>
                                                        <span>{{ $value }}</span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Enable Modules Option -->
                <div class="p-4 bg-blue-50 border-t border-blue-200">
                    <div class="flex items-center">
                        <input 
                            type="checkbox" 
                            name="enable_modules" 
                            id="enable-modules-checkbox"
                            class="h-5 w-5 rounded border-gray-300 text-green-600 focus:ring-green-500"
                        >
                        <label for="enable-modules-checkbox" class="ml-3 cursor-pointer">
                            <span class="font-semibold text-gray-900">🚀 Kích hoạt module ngay sau khi cài đặt</span>
                            <p class="text-sm text-gray-600 mt-1">
                                Nếu bật, các module đã chọn sẽ tự động được enable và có thể sử dụng ngay. 
                                Nếu không, module sẽ được đăng ký nhưng để ở trạng thái disabled, bạn có thể enable sau trong quản lý module.
                            </p>
                        </label>
                    </div>
                </div>

                <!-- Actions -->
                <div class="p-6 bg-gray-50 flex items-center justify-between">
                    <div class="flex space-x-3">
                        <form action="{{ route('admin.modules.skip') }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="px-5 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors font-medium">
                                ⏭️ Bỏ qua tạm thời (30 phút)
                            </button>
                        </form>
                        <a href="/admin/modules" class="px-5 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors font-medium inline-block">
                            Quay lại quản lý module
                        </a>
                    </div>
                    <button 
                        type="submit" 
                        class="px-8 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-semibold shadow-md hover:shadow-lg"
                        onclick="return confirmInstall()"
                    >
                        ✅ Cài đặt các module đã chọn
                    </button>
                </div>
            </form>
        </div>

        <!-- Info Box -->
        <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-5">
            <h4 class="font-semibold text-blue-900 mb-3">ℹ️ Quy trình cài đặt module:</h4>
            <div class="space-y-3">
                <div class="flex items-start space-x-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 bg-blue-600 text-white text-xs font-bold rounded-full flex-shrink-0">1</span>
                    <div class="text-sm text-blue-800">
                        <strong>Đồng bộ (module:sync):</strong> Quét và đăng ký các module từ filesystem vào database với trạng thái disabled.
                    </div>
                </div>
                <div class="flex items-start space-x-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 bg-blue-600 text-white text-xs font-bold rounded-full flex-shrink-0">2</span>
                    <div class="text-sm text-blue-800">
                        <strong>Cài đặt Dependencies:</strong> Tự động cài các thư viện PHP cần thiết qua Composer (chạy trong background job).
                    </div>
                </div>
                <div class="flex items-start space-x-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 bg-blue-600 text-white text-xs font-bold rounded-full flex-shrink-0">3</span>
                    <div class="text-sm text-blue-800">
                        <strong>Kích hoạt (module:manage):</strong> Nếu bạn chọn option "Kích hoạt ngay", module sẽ được enable và sẵn sàng sử dụng.
                    </div>
                </div>
                <div class="flex items-start space-x-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 bg-blue-600 text-white text-xs font-bold rounded-full flex-shrink-0">4</span>
                    <div class="text-sm text-blue-800">
                        <strong>Migrations & Setup:</strong> Chạy database migrations và các tác vụ thiết lập tự động (nếu có).
                    </div>
                </div>
            </div>
            <div class="mt-4 pt-4 border-t border-blue-300">
                <p class="text-sm text-blue-800">
                    ⏱️ <strong>Thời gian:</strong> Quá trình này có thể mất 2-5 phút tùy thuộc vào số lượng dependencies. 
                    Tải lại trang sau ít phút để xem kết quả.
                </p>
            </div>
        </div>
    </div>

    <script>
        function selectAll() {
            document.querySelectorAll('.module-checkbox').forEach(cb => cb.checked = true);
        }

        function deselectAll() {
            document.querySelectorAll('.module-checkbox').forEach(cb => cb.checked = false);
        }

        function confirmInstall() {
            const checked = document.querySelectorAll('.module-checkbox:checked');
            
            if (checked.length === 0) {
                alert('⚠️ Vui lòng chọn ít nhất một module để cài đặt!');
                return false;
            }

            const moduleNames = Array.from(checked).map(cb => cb.value).join(', ');
            const enableModules = document.getElementById('enable-modules-checkbox').checked;
            
            let message = `🔄 Bạn có chắc muốn cài đặt ${checked.length} module sau?\n\n${moduleNames}\n\n`;
            
            if (enableModules) {
                message += '✅ Module sẽ được KÍCH HOẠT ngay sau khi cài đặt.\n';
            } else {
                message += '⚠️ Module sẽ được đăng ký nhưng để ở trạng thái DISABLED.\n';
            }
            
            message += '\nQuá trình này sẽ mất vài phút.';
            
            return confirm(message);
        }
    </script>
@endsection
