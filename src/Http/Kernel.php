<?php

namespace Http;

use App\Exceptions\Handler as ExceptionHandler;
use App\Exceptions\MethodNotAllowedException;
use App\Exceptions\NotFoundException;
use Core\Application;
use Core\Contracts\Http\Kernel as KernelContract;
use Core\Http\FormRequest;
use Core\Routing\Route;
use Core\Routing\Router;
use Laminas\Stratigility\MiddlewarePipe;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionMethod;
use ReflectionParameter;
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
        \Http\Middleware\AttachRequestIdToLogs::class,
        \Http\Middleware\HttpMetricsMiddleware::class,
        \Http\Middleware\TrimStrings::class,
        \Http\Middleware\ConvertEmptyStringsToNull::class,
        \Http\Middleware\SetLocaleMiddleware::class,
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
            \Http\Middleware\EncryptCookies::class,
            \Http\Middleware\SubstituteBindings::class,
            \Http\Middleware\StartSession::class,
            \Http\Middleware\ShareErrorsFromSession::class,
            // \Http\Middleware\VerifyCsrfToken::class,
            \Http\Middleware\AddQueuedCookiesToResponse::class,
        ],
        'api' => [
            \Http\Middleware\JwtVerifyTokenMiddleware::class,
            \Http\Middleware\SubstituteBindings::class,
            \Http\Middleware\CorsMiddleware::class,
            'throttle:60,1', // Giới hạn 60 request mỗi phút cho các route trong group này
        ],
    ];

    public function __construct(Application $app, Router $router)
    {
        $this->app = $app;
        $this->router = $router;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        try {
            // The router can return a Route object or a Response object directly
            // (e.g., for CORS pre-flight OPTIONS requests).
            $routeOrResponse = $this->router->dispatch($request);

            // If the router returns a response, we return it immediately,
            // bypassing the rest of the middleware stack.
            if ($routeOrResponse instanceof ResponseInterface) {
                return $routeOrResponse;
            }

            $route = $routeOrResponse;
            // Attach the route to the request so that middleware can access it
            $request = $request->withAttribute('route', $route);

            $pipe = new MiddlewarePipe();

            // 1. Add global middleware to the pipeline
            foreach ($this->middleware as $middleware) {
                $pipe->pipe($this->resolveMiddleware($middleware));
            }

            // 2. Add group middleware to the pipeline
            $group = $route->group ?? '';
            if (!empty($group) && isset($this->middlewareGroups[$group])) {
                foreach ($this->middlewareGroups[$group] as $middleware) {
                    $pipe->pipe($this->resolveMiddleware($middleware));
                }
            }

            // 3. Add route-specific middleware to the pipeline
            foreach ($route->middleware as $middleware) {
                $pipe->pipe($this->resolveMiddleware($middleware));
            }

            // 4. Create the final handler that executes the controller
            $finalHandler = new class ($this->app, $route, $this) implements RequestHandlerInterface {
                public function __construct(
                    private Application $app,
                    private \Core\Routing\Route $route,
                    private Kernel $kernel, // Inject the Kernel instance
                ) {
                }

                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    // Resolve dependencies and call the controller action
                    $responseContent = $this->kernel->resolveAndCallController($this->app, $this->route, $request);

                    // If the controller already returned a Response object, just return it
                    if ($responseContent instanceof ResponseInterface) {
                        return $responseContent;
                    }

                    // If the controller returned an array or object, convert it to a JSON response
                    if (is_array($responseContent) || is_object($responseContent) || $responseContent instanceof \JsonSerializable) {
                        return response()->json($responseContent);
                    }

                    // For all other cases (null, string, etc.), create a standard response.
                    // The empty body check will be handled centrally in the main handle method.
                    return response((string) $responseContent);
                }
            };

            // 5. Process the request through the middleware pipeline
            $response = $pipe->process($request, $finalHandler);

            return $response;
        } catch (NotFoundException | MethodNotAllowedException $e) {
            return $this->renderException($request, $e);
        } catch (Throwable $e) {
            return $this->renderException($request, $e);
        }
    }

    /**
     * Resolve controller dependencies and call the handler.
     * This method handles automatic FormRequest validation and injection.
     */
    public function resolveAndCallController(Application $app, Route $route, ServerRequestInterface $request): mixed
    {
        [$controllerClass, $method] = $route->handler;

        $reflectionMethod = new ReflectionMethod($controllerClass, $method);
        $parameters = $reflectionMethod->getParameters();

        $dependencies = [];
        foreach ($parameters as $parameter) {
            $dependencies[] = $this->resolveParameter($app, $route, $request, $parameter);
        }

        // Resolve the controller instance from the container to allow dependency injection in its constructor
        $controllerInstance = $app->make($controllerClass);

        return $reflectionMethod->invokeArgs($controllerInstance, $dependencies);
    }

    /**
     * Resolve a single parameter for the controller method.
     */
    protected function resolveParameter(Application $app, Route $route, ServerRequestInterface $request, ReflectionParameter $parameter): mixed
    {
        $type = $parameter->getType();
        $typeName = ($type && !$type->isBuiltin()) ? $type->getName() : null;

        // Debugging: Dump the parameter and request to see what's going on

        // 1. Resolve FormRequest: create, set request, and validate
        if ($typeName && is_subclass_of($typeName, FormRequest::class)) {
            /** @var FormRequest $formRequest */
            $formRequest = $app->make($typeName);
            $formRequest->setRequest($request);
            $formRequest->validateResolved();
            return $formRequest;
        }

        // 2. Resolve ServerRequestInterface itself
        if ($typeName === ServerRequestInterface::class) {
            return $request;
        }

        // 3. Resolve route parameters by name (handles route model binding)
        if ($route && array_key_exists($parameter->getName(), $route->parameters)) {
            return $route->parameters[$parameter->getName()];
        }

        // 4. Resolve other classes from the container
        if ($typeName && $app->has($typeName)) {
            return $app->make($typeName);
        }

        // 5. Handle default values or throw an error for unresolvable parameters
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        throw new \LogicException("Unable to resolve controller parameter: [\${$parameter->getName()}] in method {$route->handler[0]}::{$route->handler[1]}");
    }

    protected function resolveMiddleware(string $middleware): \Psr\Http\Server\MiddlewareInterface
    {
        // TODO: Xử lý các middleware có tham số như 'throttle:60,1' sẽ cần một factory phức tạp hơn.
        // Hiện tại, chúng ta chỉ resolve các class middleware từ container.
        $class = $this->routeMiddleware[$middleware] ?? $middleware;
        return $this->app->make($class);
    }

    protected function renderException(ServerRequestInterface $request, Throwable $e): ResponseInterface
    {
        $handler = $this->app->make(ExceptionHandler::class);
        $handler->report($e);
        return $handler->render($request, $e);
    }

    public function terminate(ServerRequestInterface $request, ResponseInterface $response): void
    {
        // Các tác vụ sau khi response đã được gửi đi, ví dụ: ghi log, lưu session...
    }

    /**
     * Register a new route middleware alias.
     *
     * @param  string  $name
     * @param  class-string  $class
     */
    public function aliasMiddleware(string $name, string $class): void
    {
        $this->routeMiddleware[$name] = $class;
    }

    /**
     * Register a new middleware group.
     *
     * @param  string  $group
     * @param  array<int, class-string|string>  $middleware
     */
    public function middlewareGroup(string $group, array $middleware): void
    {
        $this->middlewareGroups[$group] = $middleware;
    }
}
