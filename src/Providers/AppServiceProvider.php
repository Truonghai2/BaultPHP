<?php

namespace App\Providers;

use Core\Validation\Factory as ValidationFactory;
use Core\WebSocket\CentrifugoAPIService;
use Core\Support\ServiceProvider;
use Spiral\Goridge\RPC\RPC;
use Spiral\RoadRunner\Environment;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Đăng ký RPC client để có thể inject vào các service khác
        $this->app->singleton(RPC::class, function () {
            // Chỉ tạo RPC client nếu đang chạy trong môi trường RoadRunner
            if (class_exists(Environment::class) && ($rpcAddress = Environment::fromGlobals()->getRPCAddress())) {
                return RPC::create($rpcAddress);
            }
            // Trả về null nếu không phải môi trường RoadRunner để tránh lỗi
            return null;
        });

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
        // Lấy ra Validation Factory từ container
        $validator = $this->app->make(ValidationFactory::class);

        // Đăng ký một rule mới tên là 'slug'
        $validator->extend('slug', function ($attribute, $value, $parameters, $validator) {
            // Rule này kiểm tra xem chuỗi có phải là một slug hợp lệ không
            // (chỉ chứa chữ thường, số, và dấu gạch ngang)
            if (!is_string($value)) {
                return false;
            }
            return preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value);
        }, 'Trường :attribute không phải là một slug hợp lệ.');
    }
}