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
        error_log(" -> [AppKernel] Initializing...");
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
        if (file_exists($cachedProvidersPath) && !env('APP_DEBUG', false)) { // Note: env() might not be loaded yet if not CLI
            error_log(" -> [AppKernel] Loading cached providers from: " . $cachedProvidersPath);
            $this->app->loadCachedProviders(require $cachedProvidersPath);
        } else {
            error_log(" -> [AppKernel] No cache found or in debug mode. Registering providers manually.");
            $this->registerCoreProviders();
            $this->registerModuleProviders();
        }

        error_log(" -> [AppKernel] Booting service providers...");
        $this->app->boot();
        error_log(" -> [AppKernel] Initialization complete.");
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
        error_log(" -> [AppKernel] Registering core providers...");
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
        error_log(" -> [AppKernel] Discovering and registering module providers...");
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
            error_log("    -> Found module meta: " . $metaFile);

            $meta = json_decode(file_get_contents($metaFile), true);
            if (!($meta['enabled'] ?? false)) continue;

            error_log("    -> Module '" . ($meta['name'] ?? 'Unknown') . "' is enabled.");
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
