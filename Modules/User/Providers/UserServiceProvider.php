<?php

namespace Modules\User\Providers;

use Core\Routing\Router;
use Illuminate\Support\ServiceProvider;

class UserServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $router = $this->app->make(Router::class);

        $routeFile = base_path('Modules/User/Http/Routes/web.php');

        if (file_exists($routeFile)) {
            $callback = require $routeFile;
            if (is_callable($callback)) {
                $callback($router);
            }

        }
    }

    public function register(): void
    {
        //
    }
}
