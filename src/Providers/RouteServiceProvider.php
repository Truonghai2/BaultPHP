<?php 

namespace App\Providers;

use Core\Support\ServiceProvider;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use ReflectionClass;
use Core\Routing\Attributes\Route as RouteAttribute;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Register any routes for your application.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(\Core\Routing\Router::class, function ($app) {
            return new \Core\Routing\Router($app);
        });
    }

    public function boot(): void
    {
        $router = $this->app->make(\Core\Routing\Router::class);

        // In production, load the cached routes file for maximum performance.
        // This file is generated by the `route:cache` command.
        $cachedRoutesFile = $this->app->getCachedRoutesPath();
        if (file_exists($cachedRoutesFile) && !env('APP_DEBUG', false)) {
            $cachedRoutes = require $cachedRoutesFile;
            $router->loadFromCache($cachedRoutes);
            return;
        }

        $this->bootFileBasedRouting($router);
        $this->bootAttributeRouting($router);
    }

    /**
     * Load all routes for the purpose of caching.
     * This method is called by the `route:cache` command.
     */
    public function loadRoutesForCaching(\Core\Routing\Router $router): void
    {
        $this->bootFileBasedRouting($router);
        $this->bootAttributeRouting($router);
    }

    /**
     * Load routes from the main traditional route file.
     *
     * @param \Core\Routing\Router $router
     */
    protected function bootFileBasedRouting(\Core\Routing\Router $router): void
    {
        $routeFile = base_path('routes/web.php');

        if (file_exists($routeFile)) {
            $callback = require $routeFile;
            if (is_callable($callback)) {
                $callback($router);
            }
        }
    }

    /**
     * Scan for and register routes defined by attributes in all enabled modules.
     *
     * @param \Core\Routing\Router $router
     */
    protected function bootAttributeRouting(\Core\Routing\Router $router): void
    {
        // Mở rộng để quét cả controller của app chính và của các module
        $controllerPaths = array_merge(
            glob(base_path('src/Http/Controllers'), GLOB_ONLYDIR),
            glob(base_path('Modules/*/Http/Controllers'), GLOB_ONLYDIR)
        );

        foreach ($controllerPaths as $path) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
            $phpFiles = new \RegexIterator($iterator, '/\.php$/');

            foreach ($phpFiles as $phpFile) {
                $class = $this->fqcnFromFile($phpFile->getRealPath());
                if (!$class) {
                    continue;
                }

                try {
                    $reflector = new ReflectionClass($class);
                } catch (\ReflectionException $e) {
                    continue; // Skip if class cannot be reflected
                }

                if ($reflector->isAbstract()) {
                    continue;
                }

                foreach ($reflector->getMethods() as $method) {
                    $attributes = $method->getAttributes(RouteAttribute::class, \ReflectionAttribute::IS_INSTANCEOF);

                    foreach ($attributes as $attribute) {
                        /** @var RouteAttribute $routeAttribute */
                        $routeAttribute = $attribute->newInstance();
                        $router->addRoute(
                            strtoupper($routeAttribute->method),
                            $routeAttribute->uri,
                            [$class, $method->getName()]
                        )->middleware($routeAttribute->middleware);
                    }
                }
            }
        }
    }

    /**
     * Lấy ra Tên Class Đầy Đủ (FQCN) từ đường dẫn file.
     * Phương thức này giả định cấu trúc thư mục tuân thủ PSR-4 so với thư mục gốc.
     * Nó nhanh và đáng tin cậy hơn rất nhiều so với việc phân tích nội dung file.
     */
    private function fqcnFromFile(string $filePath): ?string
    {
        // Lấy đường dẫn tương đối so với thư mục gốc của dự án
        // Ví dụ: "Modules/User/Http/Controllers/UserController.php"
        $relativePath = str_replace(rtrim(base_path(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR, '', $filePath);

        // Bỏ phần mở rộng .php
        $classPath = substr($relativePath, 0, -4);

        // Chuyển đổi dấu phân cách thư mục thành dấu phân cách namespace để có FQCN
        // Ví dụ: "Modules\User\Http\Controllers\UserController"
        return str_replace(DIRECTORY_SEPARATOR, '\\', $classPath);
    }
}