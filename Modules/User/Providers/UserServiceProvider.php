<?php

namespace Modules\User\Providers;

use Core\Routing\Router;
use Core\Support\ServiceProvider;
use Modules\User\Application\Services\AccessControlService;

class UserServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $router = $this->app->make(Router::class);

    }

    public function register(): void
    {
        // Đăng ký AccessControlService với phương thức `bind` để nó trở thành request-scoped.
        // Mỗi khi `app(AccessControlService::class)` được gọi trong một request mới,
        // một instance hoàn toàn mới sẽ được tạo ra.
        $this->app->bind(AccessControlService::class, AccessControlService::class);
    }
}
