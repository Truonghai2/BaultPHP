<?php

namespace Core;

use Core\Contracts\Http\Kernel as KernelContract;
use Core\Support\Facades\Facade;
use Core\Support\ServiceProvider;
use Http\Request;
use Http\Response;

class AppKernel
{
    protected Application $app;

    public function __construct()
    {
        $this->app = new Application(base_path());

        // Bind the kernel instance itself into the container for commands to use.
        $this->app->instance(AppKernel::class, $this);

        $this->app->singleton(KernelContract::class, function ($app) {
            return new \Http\Kernel($app, $app->make(\Core\Routing\Router::class));
        });

        Facade::setFacadeApplication($this->app);

        // Trong môi trường production, tải các provider đã được cache để tăng tốc.
        // File này sẽ được tạo bởi một lệnh console, ví dụ: `php bault config:cache`
        $cachedProvidersPath = $this->app->getCachedProvidersPath();
        if (file_exists($cachedProvidersPath) && !env('APP_DEBUG', false)) {
            $this->app->loadCachedProviders(require $cachedProvidersPath);
        } else {
            $this->registerCoreProviders();
            $this->registerModuleProviders();
        }

        $this->app->boot();
    }

    public function getApplication(): Application
    {
        return $this->app;
    }

    public function getProvidersForCaching(): array
    {
        return array_merge($this->getCoreProvidersList(), $this->discoverModuleProvidersList());
    }

    protected function registerCoreProviders(): void
    {
        $providers = $this->getCoreProvidersList();

        foreach ($providers as $provider) {
            if (class_exists($provider)) {
                $this->app->register($provider);
            }
        }
    }

    protected function getCoreProvidersList(): array
    {
        return [
            \App\Providers\AuthServiceProvider::class,
            \App\Providers\ConfigServiceProvider::class,
            \App\Providers\ConsoleServiceProvider::class,
            \App\Providers\EventServiceProvider::class,
            \App\Providers\ExceptionServiceProvider::class,
            \App\Providers\LoggingServiceProvider::class,
            \App\Providers\RouteServiceProvider::class,
            \App\Providers\ValidationServiceProvider::class,
        ];
    }

    protected function registerModuleProviders(): void
    {
        $providers = $this->discoverModuleProvidersList();
        foreach ($providers as $provider) {
            $this->app->register($provider);
        }
    }

    protected function discoverModuleProvidersList(): array
    {
        $discoveredProviders = [];
        $moduleDirs = glob(base_path('Modules/*'), GLOB_ONLYDIR);

        foreach ($moduleDirs as $dir) {
            $metaFile = $dir . '/module.json';
            if (!file_exists($metaFile)) continue;

            $meta = json_decode(file_get_contents($metaFile), true);
            if (!($meta['enabled'] ?? false)) continue;

            foreach ($meta['providers'] ?? [] as $provider) {
                if (class_exists($provider)) {
                    $discoveredProviders[] = $provider;
                }
            }
        }
        return $discoveredProviders;
    }

    public function handle(Request $request): Response
    {
        // Đăng ký đối tượng Request hiện tại vào container để có thể inject vào controller
        $this->app->instance(Request::class, $request);

        /** @var KernelContract $kernel */
        $kernel = $this->app->make(KernelContract::class);

        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        return $response;
    }
}
