<?php

namespace Modules\Centrifugo\Providers;

use Core\Support\ServiceProvider;
use InvalidArgumentException;
use Modules\Centrifugo\Domain\Contracts\TokenGeneratorInterface;
use Modules\Centrifugo\Infrastructure\Services\CentrifugoAPIService;
use Modules\Centrifugo\Infrastructure\Services\JwtTokenGenerator;

class CentrifugoServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        // 1. Đăng ký service tạo connection token.
        // Chúng ta bind interface với implementation cụ thể.
        // Điều này cho phép chúng ta dễ dàng thay đổi cách tạo token trong tương lai
        // (ví dụ: dùng một thư viện JWT khác) mà không cần sửa code ở UseCase.
        $this->app->bind(TokenGeneratorInterface::class, JwtTokenGenerator::class);

        // 2. Đăng ký CentrifugoAPIService như một singleton.
        // Service này sẽ được dùng để giao tiếp với API của Centrifugo server
        // (ví dụ: để publish message từ backend).
        // Dùng singleton để đảm bảo chỉ có một instance của service này được tạo ra,
        // giúp tiết kiệm tài nguyên (ví dụ: tái sử dụng HTTP client).
        $this->app->singleton(CentrifugoAPIService::class, function () {
            $apiUrl = config('centrifugo.api_url');
            $apiKey = config('centrifugo.api_key');

            if (empty($apiKey) || empty($apiUrl)) {
                throw new InvalidArgumentException('Centrifugo API URL or API Key is not configured in config/centrifugo.php or .env file.');
            }

            return new CentrifugoAPIService($apiUrl, $apiKey);
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        // Logic để merge config của module vào config chung của ứng dụng.
        // Điều này là cần thiết để hàm `config('centrifugo.key')` có thể hoạt động.
        $this->mergeConfigFrom(__DIR__ . '/../../../config/centrifugo.php', 'centrifugo');
    }
}
