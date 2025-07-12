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

    public function __construct(Application $app, Router $router)
    {
        $this->app = $app;
        $this->router = $router;
    }

    public function handle(Request $request): Response
    {
        try {
            $route = $this->router->dispatch($request);

            $middlewares = array_merge($this->middleware, $route->middleware);

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
                function ($next, $middlewareClass) {
                    // Resolve middleware từ container để cho phép Dependency Injection
                    return fn(Request $request) => $this->app->make($middlewareClass)->handle($request, $next);
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
