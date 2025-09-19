<?php

namespace Http;

use App\Exceptions\Handler as ExceptionHandler;
use App\Exceptions\MethodNotAllowedException;
use App\Exceptions\NotFoundException;
use App\Http\Middleware\CheckForPendingModulesMiddleware;
use Core\Application;
use Core\Contracts\Http\Kernel as KernelContract;
use Core\Exceptions\HttpResponseException;
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
        \Http\Middleware\ParseBodyMiddleware::class,
        \Http\Middleware\EnsureAdminUserExists::class,
        \Http\Middleware\AttachRequestIdToLogs::class,
        \Http\Middleware\HttpMetricsMiddleware::class,
        \Http\Middleware\LogRequestMiddleware::class,
        \Http\Middleware\TrimStrings::class,
        \Http\Middleware\ConvertEmptyStringsToNull::class,
        \Http\Middleware\SetLocaleMiddleware::class,
        CheckForPendingModulesMiddleware::class,
    ];

    /**
     * The application's middleware priority.
     *
     * This forces non-global middleware to always be in a given order.
     *
     * @var array<int, class-string>
     */
    protected array $middlewarePriority = [
        \Http\Middleware\EncryptCookies::class,
        \Http\Middleware\StartSession::class,
        \Http\Middleware\ShareMessagesFromSession::class,
        \Http\Middleware\VerifyCsrfToken::class,
        \Http\Middleware\SubstituteBindings::class,
        \Http\Middleware\AddQueuedCookiesToResponse::class,
    ];

    /**
     * The application's route middleware aliases.
     * These are used to map a short name to a middleware class.
     *
     * @var array<string, class-string>
     */
    protected array $routeMiddleware = [
        'auth' => \Http\Middleware\Authenticate::class,
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
            \Http\Middleware\StartSession::class,
            \Http\Middleware\ShareMessagesFromSession::class,
            \Http\Middleware\VerifyCsrfToken::class,
            \Http\Middleware\SubstituteBindings::class,
            \Http\Middleware\TerminateSession::class,
            \Http\Middleware\AddQueuedCookiesToResponse::class,
        ],
        'api' => [
            \Http\Middleware\SubstituteBindings::class,
            \Http\Middleware\CorsMiddleware::class,
            'throttle:60,1',
        ],
    ];

    public function __construct(Application $app, Router $router)
    {
        $this->app = $app;
        $this->router = $router;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->app->instance(ServerRequestInterface::class, $request);
        $this->app->alias(ServerRequestInterface::class, 'request');

        try {
            $routeOrResponse = $this->router->dispatch($request);

            $route = $this->ensureRoute($routeOrResponse);
            $request = $request->withAttribute('route', $route);

            $response = $this->sendRequestThroughRouter($request, $route);

            return $response;
        } catch (HttpResponseException $e) {
            return $e->getResponse();
        } catch (NotFoundException | MethodNotAllowedException $e) {
            return $this->renderException($request, $e);
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

        $pipe = new MiddlewarePipe();

        $middlewareStack = $this->gatherAndSortMiddleware($route);

        foreach ($middlewareStack as $middleware) {
            [$name, $parameters] = $this->parseMiddleware($middleware);
            $class = $this->routeMiddleware[$name] ?? $name;

            // Create a closure that will instantiate and call the middleware,
            // passing the extra parameters. This allows for parameter support.
            $callableMiddleware = function (ServerRequestInterface $req, RequestHandlerInterface $handler) use ($class, $parameters) {
                $instance = $this->app->make($class);
                // Assume middleware has a `handle` method that accepts parameters.
                // This is a convention we are establishing.
                return $instance->handle($req, $handler, ...$parameters);
            };

            $pipe->pipe($callableMiddleware);
        }

        // The final handler that executes the controller action.
        $finalHandler = new class ($this->app, $route, $this) implements RequestHandlerInterface {
            public function __construct(
                private Application $app,
                private Route $route,
                private Kernel $kernel,
            ) {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $responseContent = $this->kernel->resolveAndCallController($this->app, $this->route, $request);

                if ($responseContent instanceof ResponseInterface) {
                    return $responseContent;
                }

                if (is_array($responseContent) || is_object($responseContent) || $responseContent instanceof \JsonSerializable) {
                    return response()->json($responseContent);
                }

                return response((string) $responseContent);
            }
        };

        return $pipe->process($request, $finalHandler);
    }

    protected function ensureRoute($routeOrResponse): Route
    {
        if ($routeOrResponse instanceof ResponseInterface) {
            throw new HttpResponseException($routeOrResponse);
        }
        return $routeOrResponse;
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
            /** @var \Http\FormRequest $formRequest */
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
        $handler->report($e);
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

    /**
     * Gather all middleware for a given route and sort them by priority.
     *
     * @return array<int, class-string|string>
     */
    protected function gatherAndSortMiddleware(Route $route): array
    {
        $group = $route->group ?? '';
        $groupMiddleware = isset($this->middlewareGroups[$group]) ? $this->middlewareGroups[$group] : [];

        $middleware = array_merge(
            $this->middleware,
            $groupMiddleware,
            $route->middleware,
        );

        usort($middleware, function ($a, $b) {
            $aName = $this->parseMiddleware($a)[0];
            $bName = $this->parseMiddleware($b)[0];

            $aClass = $this->routeMiddleware[$aName] ?? $aName;
            $bClass = $this->routeMiddleware[$bName] ?? $bName;

            $aPos = array_search($aClass, $this->middlewarePriority);
            $bPos = array_search($bClass, $this->middlewarePriority);

            if ($aPos === false && $bPos === false) {
                return 0;
            }
            if ($aPos === false) {
                return 1;
            }
            if ($bPos === false) {
                return -1;
            }

            return $aPos <=> $bPos;
        });

        return array_unique($middleware);
    }

    protected function parseMiddleware(string $middleware): array
    {
        return str_contains($middleware, ':') ? explode(':', $middleware, 2) : [$middleware, ''];
    }
}
