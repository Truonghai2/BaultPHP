<?php

namespace Core;

use Core\Module\Module;
use Core\Support\Facades\Facade;
use Core\Support\ServiceProvider;

class AppKernel
{
    protected Application $app;

    public function __construct()
    {
        error_log(" -> [AppKernel] Initializing...");
        $this->app = new Application(base_path());

        // Bind the kernel instance itself into the container for commands to use.

        Facade::setFacadeApplication($this->app);

        // Bind Http\Kernel into the container so it can be resolved
        $this->app->singleton(\Core\Contracts\Http\Kernel::class, \Http\Kernel::class);


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
            \App\Providers\CacheServiceProvider::class,
            \App\Providers\ConfigServiceProvider::class,
            \App\Providers\DatabaseServiceProvider::class,
            \App\Providers\ConsoleServiceProvider::class,
            \App\Providers\EventServiceProvider::class,
            \App\Providers\ExceptionServiceProvider::class,
            \App\Providers\LoggingServiceProvider::class,
            \App\Providers\RouteServiceProvider::class,
            \App\Providers\QueueServiceProvider::class,
            \App\Providers\ValidationServiceProvider::class,
            \App\Providers\AppServiceProvider::class,
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
        $cachedModulesPath = $this->app->basePath('bootstrap/cache/modules.php');

        if (file_exists($cachedModulesPath) && !env('APP_DEBUG', false)) {
            error_log("    -> Loading enabled modules from cache.");
            $enabledModuleNames = require $cachedModulesPath;
        } else {
            // Nếu không có cache (hoặc ở chế độ debug), truy vấn CSDL
            try {
                error_log("    -> Loading enabled modules from database.");
                $enabledModuleNames = Module::where('enabled', true)->pluck('name')->all();
            } catch (\Exception $e) {
                // Nếu có lỗi CSDL (ví dụ: chưa migrate), trả về mảng rỗng để tránh crash
                error_log("Could not fetch enabled modules from database. Is it migrated? Error: " . $e->getMessage());
                return [];
            }
        }

 
        $discoveredProviders = [];
        foreach ($enabledModuleNames as $moduleName) {
            $dir = base_path('Modules/' . $moduleName);
            $metaFile = $dir . '/module.json';
            if (!file_exists($metaFile)) continue;

            error_log("    -> Loading enabled module: " . $moduleName);
            $meta = json_decode(file_get_contents($metaFile), true);
            foreach ($meta['providers'] ?? [] as $provider) {
                if (class_exists($provider)) {
                    $discoveredProviders[] = $provider;
                }
            }
        }
        return $discoveredProviders;
    }
}
