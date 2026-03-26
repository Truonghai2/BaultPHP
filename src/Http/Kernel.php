<?php

namespace App\Http;

use App\Exceptions\Handler as ExceptionHandler;
use Core\{Application, Contracts\StatefulService};
use Core\Contracts\Http\Kernel as KernelContract;
use Core\Exceptions\HttpResponseException;
use Core\Http\FormRequest;
use Core\Routing\Route;
use Core\Routing\Router;
use Laminas\Stratigility\MiddlewarePipe;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionMethod;
use ReflectionParameter;
use Throwable;

class Kernel implements KernelContract, StatefulService
{
    protected Application $app;
    protected Router $router;

    /**
     * Cache for resolved singleton middleware instances.
     * @var array<string, MiddlewareInterface>
     */
    protected array $middlewareInstances = [];

    /**
     * The middlewares that have been resolved for the current request.
     * @var MiddlewareInterface[]
     */
    protected array $resolvedMiddleware = [];

    /**
     * Cache for pre-resolved middleware stacks per route (performance optimization).
     * Key: route cache key (method + uri + middleware)
     * @var array<string, MiddlewareInterface[]>
     */
    protected array $routeMiddlewareCache = [];

    /**
     * Cache for reflection metadata (performance optimization).
     * @var array<string, array{method: ReflectionMethod, parameters: ReflectionParameter[]}>
     */
    protected array $reflectionCache = [];

    /** L1 home page cache (per-worker), dùng trong fast path GET / */
    private static ?string $homeL1Html = null;
    private static ?int $homeL1Expiry = null;
    private static ?string $homeSessionCookieName = null;

    /**
     * The application's global HTTP middleware stack.
     *
     * @var array
     */
    protected array $middleware = [
        \App\Http\Middleware\CorrelationIdMiddleware::class,
        \App\Http\Middleware\ResolveTenantMiddleware::class,
        \App\Http\Middleware\ClockworkMiddleware::class,
        \App\Http\Middleware\PerformanceMonitoringMiddleware::class,
        \App\Http\Middleware\ParseBodyMiddleware::class,
        \App\Http\Middleware\EnsureAdminUserExists::class,
        \App\Http\Middleware\HttpMetricsMiddleware::class,
        \App\Http\Middleware\RequestDeduplicationMiddleware::class,
        \App\Http\Middleware\TrimStrings::class,
        \App\Http\Middleware\ConvertEmptyStringsToNull::class,
        \App\Http\Middleware\SetLocaleMiddleware::class,
    ];

    /**
     * The application's middleware priority.
     *
     * This forces non-global middleware to always be in a given order.
     *
     * @var array<int, class-string>
     */
    protected array $middlewarePriority = [
        \App\Http\Middleware\EncryptCookies::class,
        \App\Http\Middleware\StartSession::class,
        \App\Http\Middleware\VerifyCsrfToken::class,
        \App\Http\Middleware\SubstituteBindings::class,
    ];

    /**
     * The application's route middleware aliases.
     * These are used to map a short name to a middleware class.
     *
     * @var array<string, class-string>
     */
    protected array $routeMiddleware = [
        'auth' => \App\Http\Middleware\Authenticate::class,
        'can' => \App\Http\Middleware\CheckPermissionMiddleware::class,
        'throttle' => \App\Http\Middleware\ThrottleRequests::class,
        'circuit' => \App\Http\Middleware\CircuitBreakerMiddleware::class,
    ];

    /**
     * The application's middleware groups.
     *
     * @var array<string, array<int, class-string|string>>
     */
    protected array $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \App\Http\Middleware\AddQueuedCookiesToResponse::class,
            \App\Http\Middleware\TerminateSession::class,
            \App\Http\Middleware\StartSession::class,
            \App\Http\Middleware\ShareMessagesFromSession::class,
            \App\Http\Middleware\CheckForPendingModulesMiddleware::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \App\Http\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\SpaCorsMiddleware::class,
        ],
        'api' => [
            \App\Http\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\CorsMiddleware::class,
            'throttle:api',
            \App\Http\Middleware\CircuitBreakerMiddleware::class,
        ],
        /** Nhóm nhẹ: không session/CSRF/EnsureAdmin, dùng cho ping, health, metrics – ổn định toàn hệ thống */
        'light' => [
            \App\Http\Middleware\SubstituteBindings::class,
        ],
    ];

    public function __construct(Application $app, Router $router)
    {
        $this->app = $app;
        $this->router = $router;
    }

    /**
     * Get the application's middleware groups.
     *
     * @return array<string, array<int, class-string|string>>
     */
    public function getMiddlewareGroups(): array
    {
        return $this->middlewareGroups;
    }

    /**
     * Get the application's route middleware aliases.
     *
     * @return array<string, class-string>
     */
    public function getRouteMiddleware(): array
    {
        return $this->routeMiddleware;
    }

    /**
     * Global middleware cho route group 'light' (ping, health, metrics).
     * Không session, CSRF, EnsureAdmin – ổn định và nhanh trên toàn hệ thống.
     *
     * @return array<int, class-string>
     */
    protected function getLightGlobalMiddleware(): array
    {
        return [
            \App\Http\Middleware\CorrelationIdMiddleware::class,
            \App\Http\Middleware\ParseBodyMiddleware::class,
        ];
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->app->instance(ServerRequestInterface::class, $request);
        $this->app->alias(ServerRequestInterface::class, 'request');

        // Fast path: GET / guest (không có ?nocache) → L1 rồi Redis
        if ($request->getMethod() === 'GET') {
            $path = $request->getUri()->getPath();
            if (trim($path, '/') === '') {
                $query = $request->getUri()->getQuery();
                $skipCache = $query !== '' && str_contains($query, 'nocache=');
                if (self::isGuestForHome($request) && !$skipCache) {
                    $now = time();
                    if (self::$homeL1Expiry !== null && $now < self::$homeL1Expiry && self::$homeL1Html !== null && self::$homeL1Html !== '') {
                        return response(self::$homeL1Html, 200, [
                            'Content-Type' => 'text/html; charset=UTF-8',
                            'X-Cache' => 'HIT-L1',
                        ]);
                    }
                    try {
                        $cached = cache(null)->get('page.home.guest');
                        if ($cached !== null && $cached !== '') {
                            self::$homeL1Html = $cached;
                            self::$homeL1Expiry = $now + 60;
                            return response($cached, 200, [
                                'Content-Type' => 'text/html; charset=UTF-8',
                                'X-Cache' => 'HIT',
                            ]);
                        }
                    } catch (\Throwable) {
                        // Fall through to normal dispatch
                    }
                }
            }
        }

        try {
            // Load any modules that are activated on_request and match this path
            if ($this->app->bound(\Core\Module\LazyModuleLoader::class)) {
                $this->app->make(\Core\Module\LazyModuleLoader::class)->ensureModulesLoadedForRequest($request);
            }
            $route = $this->router->dispatch($request);
            $request = $request->withAttribute('route', $route);

            $response = $this->sendRequestThroughRouter($request, $route);

            $this->terminate($request, $response);

            return $response;
        } catch (Throwable $e) {
            return $this->renderException($request, $e);
        }
    }

    /**
     * Guest cho GET /: không có session cookie (dùng cho fast path, không gọi config mỗi request sau lần đầu).
     */
    private static function isGuestForHome(ServerRequestInterface $request): bool
    {
        if (self::$homeSessionCookieName === null) {
            self::$homeSessionCookieName = config('session.cookie', 'bault_session');
        }
        $cookie = $request->getHeaderLine('Cookie');
        return $cookie === '' || !str_contains($cookie, self::$homeSessionCookieName . '=');
    }

    /**
     * Send the given request through the middleware pipeline.
     */
    protected function sendRequestThroughRouter(ServerRequestInterface $request, Route $route): ResponseInterface
    {
        $this->app->instance(ServerRequestInterface::class, $request);
        $this->resolvedMiddleware = [];

        $pipeline = new MiddlewarePipe();

        // Performance optimization: Cache middleware stack per route
        $routeKey = $this->getRouteCacheKey($route);

        if (!isset($this->routeMiddlewareCache[$routeKey])) {
            $globalMiddleware = $route->group === 'light' ? $this->getLightGlobalMiddleware() : $this->middleware;
            $middlewareStack = array_merge($globalMiddleware, $this->router->gatherRouteMiddleware($route));

            // Pre-resolve and cache middleware instances
            $resolved = [];
            foreach ($middlewareStack as $middleware) {
                $resolved[] = $this->resolveMiddleware($middleware);
            }

            $this->routeMiddlewareCache[$routeKey] = $resolved;
        }

        // Use cached middleware stack
        foreach ($this->routeMiddlewareCache[$routeKey] as $instance) {
            $this->resolvedMiddleware[] = $instance;
            $pipeline->pipe($instance);
        }

        $finalHandler = new class ($this, $this->app, $route) implements RequestHandlerInterface {
            public function __construct(
                private Kernel $kernel,
                private Application $app,
                private Route $route,
            ) {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                try {
                    $responseContent = $this->kernel->resolveAndCallController($this->app, $this->route, $request);

                    if ($responseContent instanceof ResponseInterface) {
                        return $responseContent;
                    }

                    if (is_array($responseContent) || is_object($responseContent) || $responseContent instanceof \JsonSerializable) {
                        return response()->json($responseContent);
                    }

                    return response((string) $responseContent);
                } catch (HttpResponseException $e) {
                    return $e->getResponse();
                }
            }
        };

        return $pipeline->process($request, $finalHandler);
    }

    /**
     * Resolve a middleware instance from the container.
     * This method now caches resolved instances to improve performance.
     *
     * @param string|callable|MiddlewareInterface $middleware
     * @return MiddlewareInterface
     */
    protected function resolveMiddleware($middleware): MiddlewareInterface
    {
        if ($middleware instanceof MiddlewareInterface) {
            return $middleware;
        }

        if ($middleware instanceof \Closure) {
            return new \Laminas\Stratigility\Middleware\CallableMiddlewareDecorator($middleware);
        }

        if (!is_string($middleware)) {
            throw new \InvalidArgumentException('Invalid middleware type provided.');
        }

        // If the middleware is a string, we'll resolve it from the container.
        $cacheKey = $middleware;
        if (isset($this->middlewareInstances[$cacheKey])) {
            return $this->middlewareInstances[$cacheKey];
        }

        [$name, $params] = array_pad(explode(':', $middleware, 2), 2, null);
        $parameters = $params ? explode(',', $params) : [];

        $className = $this->routeMiddleware[$name] ?? $name;

        // Force autoload to ensure class is loaded
        if (!class_exists($className, true)) {
            throw new \RuntimeException("Middleware class [{$className}] does not exist.");
        }

        // Try to resolve middleware from container, but handle circular dependency
        try {
            $instance = $this->app->make($className);
        } catch (\Core\Exceptions\ContainerException $e) {
            // If circular dependency detected for this middleware, create instance directly
            // This breaks the circular dependency chain
            if (strpos($e->getMessage(), 'Circular dependency') !== false && 
                strpos($e->getMessage(), $className) !== false) {
                // Circular dependency detected, create instance with minimal dependencies
                // Most middleware only need Application, so try that
                $reflection = new \ReflectionClass($className);
                $constructor = $reflection->getConstructor();
                
                if ($constructor === null) {
                    $instance = new $className();
                } else {
                    $params = $constructor->getParameters();
                    $args = [];
                    foreach ($params as $param) {
                        $type = $param->getType();
                        if ($type && !$type->isBuiltin() && $type->getName() === Application::class) {
                            $args[] = $this->app;
                        } elseif ($param->isDefaultValueAvailable()) {
                            $args[] = $param->getDefaultValue();
                        } else {
                            try {
                                $typeName = $type && !$type->isBuiltin() ? $type->getName() : null;
                                if ($typeName) {
                                    $args[] = $this->app->make($typeName);
                                } else {
                                    throw new \RuntimeException("Cannot resolve middleware [{$className}] parameter [{$param->getName()}] due to circular dependency.");
                                }
                            } catch (\Throwable $innerE) {
                                throw new \RuntimeException("Cannot resolve middleware [{$className}] due to circular dependency and unresolvable parameter [{$param->getName()}].", 0, $e);
                            }
                        }
                    }
                    $instance = $reflection->newInstanceArgs($args);
                }
            } else {
                throw $e;
            }
        }

        if (!empty($parameters) && method_exists($instance, 'setParameters')) {
            $instance->setParameters($parameters);
        }

        if (empty($parameters)) {
            $this->middlewareInstances[$cacheKey] = $instance;
        }

        return $instance;
    }

    /**
     * Resolve controller dependencies and call the handler.
     * This method handles automatic FormRequest validation and injection.
     */
    public function resolveAndCallController(Application $app, Route $route, ServerRequestInterface $request): mixed
    {
        // Handle Closure handlers
        if ($route->handler instanceof \Closure) {
            $reflectionFunction = new \ReflectionFunction($route->handler);
            $parameters = $reflectionFunction->getParameters();

            $internalKeys = ['uri', 'methods', 'handler', 'name', 'middleware', 'parameters'];
            $routeParameters = array_diff_key($route->parameters, array_flip($internalKeys));
            $dependencies = [];

            foreach ($parameters as $parameter) {
                $paramName = $parameter->getName();

                if (array_key_exists($paramName, $routeParameters)) {
                    $dependencies[] = $routeParameters[$paramName];
                    unset($routeParameters[$paramName]);
                } else {
                    $dependencies[] = $this->resolveParameter($app, $request, $parameter, $route);
                }
            }

            return $reflectionFunction->invokeArgs($dependencies);
        }

        // Handle array handlers [class, method]
        if (!is_array($route->handler)) {
            throw new \RuntimeException('Route handler must be an array [class, method] or a Closure');
        }

        [$controllerClass, $method] = $route->handler;

        // Performance optimization: Cache reflection metadata
        $cacheKey = $controllerClass . '::' . $method;

        if (!isset($this->reflectionCache[$cacheKey])) {
            $reflectionMethod = new ReflectionMethod($controllerClass, $method);
            $this->reflectionCache[$cacheKey] = [
                'method' => $reflectionMethod,
                'parameters' => $reflectionMethod->getParameters(),
            ];
        }

        $cached = $this->reflectionCache[$cacheKey];
        $reflectionMethod = $cached['method'];
        $parameters = $cached['parameters'];

        $routeParameters = $route->parameters;
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $paramName = $parameter->getName();

            if (array_key_exists($paramName, $routeParameters)) {
                $dependencies[] = $routeParameters[$paramName];
                unset($routeParameters[$paramName]);
            } else {
                $dependencies[] = $this->resolveParameter($app, $request, $parameter, $route);
            }
        }

        $controllerInstance = $app->make($controllerClass);

        return $reflectionMethod->invokeArgs($controllerInstance, $dependencies);
    }

    /**
     * Resolve a single parameter for the controller method.
     */
    protected function resolveParameter(Application $app, ServerRequestInterface $request, ReflectionParameter $parameter, Route $route): mixed
    {
        $type = $parameter->getType();
        $typeName = ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) ? $type->getName() : null;

        if ($typeName && is_subclass_of($typeName, FormRequest::class)) {
            /** @var FormRequest $formRequest */
            $formRequest = $app->make($typeName);
            // Set the request instance to avoid circular dependency
            // The request is already set in the container by Kernel::handle()
            $formRequest->setRequest($request);
            $formRequest->validateResolved();
            return $formRequest;
        }

        if ($typeName === ServerRequestInterface::class || $typeName === get_class($request)) {
            return $request;
        }

        if ($typeName && $app->has($typeName)) {
            return $app->make($typeName);
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }
        throw new \LogicException("Unable to resolve controller parameter: [\${$parameter->getName()}] in method {$route->handler[0]}::{$route->handler[1]}");
    }

    protected function renderException(ServerRequestInterface $request, Throwable $e): ResponseInterface
    {
        $handler = $this->app->make(ExceptionHandler::class);
        $handler->report($request, $e);
        return $handler->render($request, $e);
    }

    public function terminate(ServerRequestInterface $request, ResponseInterface $response): void
    {
        if (!$request->getAttribute('route')) {
            return;
        }

        foreach (array_reverse($this->resolvedMiddleware) as $middleware) {
            if (method_exists($middleware, 'terminate')) {
                $middleware->terminate($request, $response);
            }
        }
    }

    /**
     * Reset the state of the kernel after a request.
     * Note: We don't reset caches (routeMiddlewareCache, reflectionCache) as they are static.
     */
    public function resetState(): void
    {
        $this->resolvedMiddleware = [];
        $this->middlewareInstances = [];
    }

    /**
     * Generate a cache key for route middleware stack.
     */
    protected function getRouteCacheKey(Route $route): string
    {
        $middlewareKey = implode(',', $route->middleware);
        return md5($route->method . '|' . $route->uri . '|' . ($route->group ?? '') . '|' . $middlewareKey);
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

    public function pushMiddlewareToGroup(string $group, string $middleware): void
    {
        if (!isset($this->middlewareGroups[$group])) {
            $this->middlewareGroups[$group] = [];
        }
        array_unshift($this->middlewareGroups[$group], $middleware);
    }
}
