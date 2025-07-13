<?php 

namespace Http;

use App\Exceptions\MethodNotAllowedException;
use App\Exceptions\NotFoundException;
use App\Exceptions\Handler as ExceptionHandler;
use Core\Application;
use Core\Contracts\Http\Kernel as KernelContract;
use Core\Routing\Router;
use Throwable;

class Kernel implements KernelContract
{
    protected Application $app;
    protected Router $router;

    /**
     * The application's global HTTP middleware stack.
     *
     * @var array
     */
    protected array $middleware = [
        \Http\Middleware\TrimStrings::class,
        \Http\Middleware\ConvertEmptyStringsToNull::class,
        \Http\Middleware\JwtVerifyTokenMiddleware::class
    ];

    /**
     * The application's route middleware aliases.
     * These are used to map a short name to a middleware class.
     *
     * @var array<string, class-string>
     */
    protected array $routeMiddleware = [
        'can' => \Http\Middleware\CheckPermissionMiddleware::class,
    ];

    /**
     * The application's middleware groups.
     *
     * @var array<string, array<int, class-string|string>>
     */
    protected array $middlewareGroups = [
        'web' => [
            // \App\Http\Middleware\EncryptCookies::class, // Bỏ comment nếu bạn cần mã hóa cookie
            \Http\Middleware\StartSession::class,
            \Http\Middleware\VerifyCsrfToken::class,
        ],
        'api' => [
            'throttle:api', // Ví dụ về middleware có tham số
        ],
    ];

    public function __construct(Application $app, Router $router)
    {
        $this->app = $app;
        $this->router = $router;
    }

    public function handle(Request $request): Response
    {
        try {
            $route = $this->router->dispatch($request);

            // Gán đối tượng Route vào Request để các middleware có thể truy cập
            $request->route = $route;

            // Bắt đầu với middleware toàn cục
            $middlewares = $this->middleware;

            // Hợp nhất middleware từ group của route (nếu có)
            if (!empty($route->group) && isset($this->middlewareGroups[$route->group])) {
                $middlewares = array_merge($middlewares, $this->middlewareGroups[$route->group]);
            }

            // Hợp nhất middleware được định nghĩa trực tiếp trên route
            $middlewares = array_merge($middlewares, $route->middleware);

            $destination = function (Request $request) use ($route) {
                $responseContent = $this->app->call($route->handler, $route->parameters);

                if ($responseContent instanceof Response) {
                    return $responseContent;
                }

                if (is_array($responseContent) || is_object($responseContent)) {
                    return (new Response())->json($responseContent);
                }

                return (new Response())->setContent((string) $responseContent);
            };

            $pipeline = array_reduce(
                array_reverse($middlewares),
                function ($next, $middleware) {
                    // Phân tích chuỗi middleware để lấy class và các tham số
                    [$class, $parameters] = $this->parseMiddleware($middleware);
                    // Resolve middleware từ container để cho phép Dependency Injection
                    return fn(Request $request) => $this->app->make($class)->handle($request, $next, ...$parameters);
                },
                $destination
            );

            return $pipeline($request);
        } catch (NotFoundException | MethodNotAllowedException $e) {
            return $this->renderException($request, $e);
        } catch (Throwable $e) {
            return $this->renderException($request, $e);
        }
    }

    /**
     * Parse a middleware string to get the class and parameters.
     *
     * @param  string  $middleware
     * @return array
     */
    protected function parseMiddleware(string $middleware): array
    {
        // Tách chuỗi bằng dấu ':'
        $parts = explode(':', $middleware, 2);
        $name = $parts[0];
        $parameters = isset($parts[1]) ? explode(',', $parts[1]) : [];

        // Nếu "name" là một alias trong map, sử dụng class tương ứng.
        // Ngược lại, giả định "name" là một tên class đầy đủ.
        $class = $this->routeMiddleware[$name] ?? $name;

        return [$class, $parameters];
    }

    protected function renderException(Request $request, Throwable $e): Response
    {
        $handler = $this->app->make(ExceptionHandler::class);
        $handler->report($e);
        return $handler->renderForHttp($request, $e);
    }

    public function terminate(Request $request, Response $response): void
    {
        // Các tác vụ sau khi response đã được gửi đi, ví dụ: ghi log, lưu session...
    }
}
