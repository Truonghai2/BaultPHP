<?php

namespace App\Providers;

use Core\Support\Facade;
use Core\Support\ServiceProvider;

class FacadeServiceProvider extends ServiceProvider
{
    /**
     * Đăng ký các service liên quan đến Facade.
     */
    public function register(): void
    {
        // Đây là bước quan trọng nhất: cung cấp cho lớp Facade cơ sở
        // quyền truy cập vào application instance (DI container).
        // Điều này cho phép tất cả các facade con có thể resolve service.
        Facade::setFacadeApplication($this->app);
    }
}
