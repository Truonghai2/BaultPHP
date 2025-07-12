<?php

namespace Core\Console;

use Core\Application;
use Core\Routing\Router;
use App\Providers\RouteServiceProvider;

class RouteCacheCommand
{
    protected string $name = 'route:cache';
    protected string $description = 'Create a route cache file for faster route registration.';

    public function __construct(protected Application $app)
    {
    }

    public function handle(): int
    {
        echo "Caching routes...\n";

        $cachePath = $this->app->getCachedRoutesPath();

        // Xóa cache cũ nếu có
        if (file_exists($cachePath)) {
            unlink($cachePath);
        }

        // Đảm bảo thư mục tồn tại
        $cacheDir = dirname($cachePath);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }

        // Khởi tạo Router và RouteServiceProvider
        $router = new Router($this->app);
        $provider = new RouteServiceProvider($this->app);
        $provider->loadRoutesForCaching($router);

        // Lấy danh sách routes đã đăng ký
        $routes = $router->getRoutesForCaching();

        // Kiểm tra route dùng Closure (không thể serialize)
        foreach ($routes as $method => $methodRoutes) {
            foreach ($methodRoutes as $uri => $routeData) {
                if ($routeData['handler'] instanceof \Closure || is_object($routeData['handler'])) {
                    echo "\n\033[0;31mError: Cannot cache route [{$method} {$uri}] because it uses a Closure or object handler.\033[0m\n";

                    // Dọn dẹp cache nếu lỗi
                    if (file_exists($cachePath)) {
                        unlink($cachePath);
                    }

                    return 1;
                }
            }
        }

        // Ghi file cache
        $content = '<?php' . PHP_EOL . PHP_EOL . 'return ' . var_export($routes, true) . ';' . PHP_EOL;
        file_put_contents($cachePath, $content);

        echo "Routes cached successfully!\n";

        return 0;
    }

}