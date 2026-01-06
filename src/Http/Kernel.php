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
     * The application's global HTTP middleware stack.
     *
     * @var array
     */
    protected array $middleware = [
        \App\Http\Middleware\CollectDebugDataMiddleware::class,
        \App\Http\Middleware\PerformanceMonitoringMiddleware::class,
        \App\Http\Middleware\ParseBodyMiddleware::class,
        \App\Http\Middleware\EnsureAdminUserExists::class,
        \App\Http\Middleware\HttpMetricsMiddleware::class,
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

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->app->instance(ServerRequestInterface::class, $request);
        $this->app->alias(ServerRequestInterface::class, 'request');

        try {
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
     * Send the given request through the middleware pipeline.
     */
    protected function sendRequestThroughRouter(ServerRequestInterface $request, Route $route): ResponseInterface
    {
        $this->app->instance(ServerRequestInterface::class, $request);
        $this->resolvedMiddleware = [];

        $pipeline = new MiddlewarePipe();

        $middlewareStack = array_merge($this->middleware, $this->router->gatherRouteMiddleware($route));

        foreach ($middlewareStack as $middleware) {
            $instance = $this->resolveMiddleware($middleware);
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

        if (!class_exists($className)) {
            throw new \RuntimeException("Middleware class [{$className}] does not exist.");
        }

        $instance = $this->app->make($className);

        if (!empty($parameters) && method_exists($instance, 'setParameters')) {
            $instance->setParameters($parameters);
        }

        // Only cache middleware that don't have parameters, as they are globally reusable.
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

        $reflectionMethod = new ReflectionMethod($controllerClass, $method);
        $parameters = $reflectionMethod->getParameters();

        // Route parameters are extracted from the URL pattern (e.g., {id}, {name})
        // and stored directly in $route->parameters by the Router's findRoute() method.
        // We use them as-is without filtering.
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
     */
    public function resetState(): void
    {
        $this->resolvedMiddleware = [];
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
