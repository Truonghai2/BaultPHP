<?php

namespace App\Providers;

use Core\Metrics\MetricsService;
use Core\Support\ServiceProvider;
use Core\Validation\Factory as ValidationFactory;
use Core\WebSocket\CentrifugoAPIService;
use Modules\User\Domain\Services\AccessControlService;
use Modules\User\Infrastructure\Models\User;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Đăng ký CentrifugoAPIService như một singleton.
        // Service này sẽ được khởi tạo một lần và tái sử dụng trong suốt vòng đời của ứng dụng.
        $this->app->singleton(CentrifugoAPIService::class, function () {
            $apiUrl = $_ENV['CENTRIFUGO_API_URL'] ?? 'http://127.0.0.1:8000';
            $apiKey = $_ENV['CENTRIFUGO_API_KEY'] ?? null;

            if (is_null($apiKey)) {
                // Trong ứng dụng thực tế, bạn nên log lỗi hoặc throw exception ở đây nếu API key là bắt buộc.
                throw new \InvalidArgumentException('CENTRIFUGO_API_KEY is not configured in your .env file.');
            }

            return new CentrifugoAPIService($apiUrl, $apiKey);
        });
    }

    public function boot(): void
    {
        // Đăng ký một global "before" callback cho tất cả các lần kiểm tra quyền.
        // Đây là nơi lý tưởng để cấp quyền cho super-admin.
        AccessControlService::before(function (User $user, string $ability) {
            // Sử dụng quyền đã được định nghĩa trong config/auth.php
            $superAdminPermission = config('auth.super_admin_permission', 'system.manage-all');

            if ($user->can($superAdminPermission)) {
                return true;
            }
            return null; // Trả về null để tiếp tục kiểm tra với các Policy hoặc Role khác.
        });

        // Lấy ra Validation Factory từ container
        $validator = $this->app->make(\Core\Validation\Factory::class);

        // Đăng ký một rule mới tên là 'slug'
        // Thông báo lỗi cho rule này được định nghĩa trong file lang/vi/validation.php
        $validator->extend('slug', function ($attribute, $value, $parameters, $validator) {
            // Rule này kiểm tra xem chuỗi có phải là một slug hợp lệ không
            // (chỉ chứa chữ thường, số, và dấu gạch ngang)
            if (!is_string($value)) {
                return false;
            }
            return preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value);
        });

        // Đăng ký một rule mới tên là 'no_profanity' để kiểm tra từ ngữ không phù hợp.
        // Thông báo lỗi cũng được định nghĩa trong file lang.
        $validator->extend('no_profanity', function ($attribute, $value, $parameters, $validator) {
            // Đây là một ví dụ đơn giản. Trong thực tế, bạn có thể lấy danh sách
            // từ file config hoặc database để dễ quản lý hơn.
            $forbiddenWords = ['badword', 'curse', 'profane'];

            // str_ireplace để không phân biệt hoa thường
            str_ireplace($forbiddenWords, '***', $value, $count);
            return $count === 0; // Nếu không có sự thay thế nào, rule pass.
        });
    }
}
