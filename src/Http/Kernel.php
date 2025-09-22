<?php

namespace App\Http;

use App\Exceptions\Handler as ExceptionHandler;
use Core\Application;
use Core\Contracts\Http\Kernel as KernelContract;
use Core\Exceptions\HttpResponseException;
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
        \App\Http\Middleware\ParseBodyMiddleware::class,
        \App\Http\Middleware\EnsureAdminUserExists::class,
        \App\Http\Middleware\HttpMetricsMiddleware::class,
        \App\Http\Middleware\TrimStrings::class,
        \App\Http\Middleware\ConvertEmptyStringsToNull::class,
        \App\Http\Middleware\SetLocaleMiddleware::class,
        \App\Http\Middleware\CheckForPendingModulesMiddleware::class,
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
        \App\Http\Middleware\ShareMessagesFromSession::class,
        \App\Http\Middleware\VerifyCsrfToken::class,
        \App\Http\Middleware\SubstituteBindings::class,
        \App\Http\Middleware\AddQueuedCookiesToResponse::class,
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
    ];

    /**
     * The application's middleware groups.
     *
     * @var array<string, array<int, class-string|string>>
     */
    protected array $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \App\Http\Middleware\StartSession::class,
            \App\Http\Middleware\ShareMessagesFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \App\Http\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\TerminateSession::class,
            \App\Http\Middleware\AddQueuedCookiesToResponse::class,
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

            return $response;
        } catch (HttpResponseException $e) {
            return $e->getResponse();
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

        $pipeline = new MiddlewarePipe();

        $middlewareStack = array_merge($this->middleware, $this->router->gatherRouteMiddleware($route));

        foreach ($middlewareStack as $middleware) {
            $pipeline->pipe($this->resolveMiddleware($middleware));
        }

        $finalHandler = new class ($this->app, $route) implements RequestHandlerInterface {
            public function __construct(private Application $app, private Route $route)
            {
            }
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                /** @var \Core\Routing\Router $router */
                $router = $this->app->make(\Core\Routing\Router::class);
                $responseContent = $router->runRoute($request, $this->route->handler, $this->route->parameters);

                if ($responseContent instanceof ResponseInterface) {
                    return $responseContent;
                }

                if (is_array($responseContent) || is_object($responseContent) || $responseContent instanceof \JsonSerializable) {
                    return response()->json($responseContent);
                }

                return response((string) $responseContent);
            }
        };

        return $pipeline->process($request, $finalHandler);
    }

    protected function resolveMiddleware($middleware)
    {
        if ($middleware instanceof \Closure) {
            return new \Laminas\Stratigility\Middleware\CallableMiddlewareDecorator($middleware);
        }

        if (is_string($middleware)) {
            [$name, $parameters] = array_pad(explode(':', $middleware, 2), 2, null);
            $parameters = $parameters ? explode(',', $parameters) : [];

            if (isset($this->routeMiddleware[$name])) {
                $middleware = $this->routeMiddleware[$name];
            } else {
                $middleware = $name;
            }

            $instance = $this->app->make($middleware);

            if (method_exists($instance, 'setParameters')) {
                $instance->setParameters($parameters);
            }

            return $instance;
        }

        return $this->app->make($middleware);
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

        if ($typeName && is_subclass_of($typeName, FormRequest::class)) {
            /** @var \Core\Http\FormRequest $formRequest */
            $formRequest = $app->make($typeName);
            $formRequest->validateResolved();
            return $formRequest;
        }

        if ($typeName === ServerRequestInterface::class) {
            return $request;
        }

        if ($route && array_key_exists($parameter->getName(), $route->parameters)) {
            return $route->parameters[$parameter->getName()];
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
