<?php

namespace Modules\Centrifugo\Providers;

use Core\BaseServiceProvider;

class ModuleServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        parent::register();

        // Đăng ký các service provider khác của module này.
        $this->app->register(CentrifugoServiceProvider::class);
    }
}
