<?php

namespace Modules\Admin\Providers;

use Core\BaseServiceProvider;

class ModuleServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        parent::register();
    }

    public function boot(): void
    {
        $this->loadModuleViews('admin');
    }
}
