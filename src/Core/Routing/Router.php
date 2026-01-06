<?php

namespace Core\Routing;

use Core\Application;
use Core\Contracts\Http\Kernel;
use Core\Contracts\StatefulService;
use Core\Exceptions\RouteNotFoundException;
use Core\Http\Request;
use Psr\Http\Message\ServerRequestInterface;

class Router implements StatefulService
{
    protected Application $app;
    protected array $routes = [];
    protected array $namedRoutes = [];
    protected ?Route $currentRoute = null;
    protected ?ServerRequestInterface $currentRequest = null;
    protected array $middleware = [];
    protected array $middlewareGroups = [];
    protected array $routeMiddleware = [];

    /**
     * Pre-compiled regex patterns for route matching (performance optimization).
     * @var array<string, array<string, string>>
     */
    protected array $compiledPatterns = [];

    /**
     * Static routes (no parameters) for O(1) lookup (performance optimization).
     * @var array<string, array<string, Route>>
     */
    protected array $staticRoutes = [];

    /**
     * Dynamic routes (with parameters) for regex matching.
     * @var array<string, array<string, Route>>
     */
    protected array $dynamicRoutes = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function get(string $uri, $handler): Route
    {
        return $this->addRoute('GET', $uri, $handler);
    }

    public function post(string $uri, $handler): Route
    {
        return $this->addRoute('POST', $uri, $handler);
    }

    public function put(string $uri, $handler): Route
    {
        return $this->addRoute('PUT', $uri, $handler);
    }

    public function patch(string $uri, $handler): Route
    {
        return $this->addRoute('PATCH', $uri, $handler);
    }

    public function delete(string $uri, $handler): Route
    {
        return $this->addRoute('DELETE', $uri, $handler);
    }

    public function addRoute(string $method, string $uri, $handler): Route
    {
        $route = new Route($method, $uri, $handler);
        $this->routes[$method][$uri] = $route;

        // Performance optimization: Pre-compile patterns and separate static/dynamic routes
        $this->optimizeRoute($method, $uri, $route);

        return $route;
    }

    /**
     * Optimize route by pre-compiling patterns and categorizing as static/dynamic.
     */
    protected function optimizeRoute(string $method, string $uri, Route $route): void
    {
        // Check if route has parameters
        if (str_contains($uri, '{')) {
            // Dynamic route - pre-compile pattern
            $pattern = preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', static function ($matches) {
                return '(?P<' . $matches[1] . '>[^/]+)';
            }, $uri);
            $this->compiledPatterns[$method][$uri] = $pattern;
            $this->dynamicRoutes[$method][$uri] = $route;
        } else {
            // Static route - O(1) lookup
            $this->staticRoutes[$method][$uri] = $route;
        }
    }

    /**
     * Dispatch the request to the matched route's handler through the middleware pipeline.
     *
     * @throws RouteNotFoundException
     * @return Route
     */
    public function dispatch(ServerRequestInterface $request): Route
    {
        $this->currentRequest = $request;
        $method = $request->getMethod();
        $uri = $request->getUri()->getPath();

        $route = $this->findRoute($method, $uri);

        if (!$route) {
            throw new RouteNotFoundException("Route not found for {$method} {$uri}");
        }

        $this->currentRoute = $route;
        $this->app->instance(Route::class, $route);
        return $route;
    }

    protected function findRoute(string $method, string $uri): ?Route
    {
        // Fast path: O(1) lookup for static routes
        if (isset($this->staticRoutes[$method][$uri])) {
            return $this->staticRoutes[$method][$uri];
        }

        // Slow path: Regex matching for dynamic routes (with pre-compiled patterns)
        if (isset($this->dynamicRoutes[$method])) {
            foreach ($this->dynamicRoutes[$method] as $routeUri => $route) {
                // Use pre-compiled pattern instead of compiling each time
                $pattern = $this->compiledPatterns[$method][$routeUri] ?? null;

                if ($pattern && preg_match('#^' . $pattern . '$#', $uri, $matches)) {
                    $parameters = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                    $route->setParameters($parameters);
                    return $route;
                }
            }
        }

        return null;
    }

    public function gatherRouteMiddleware(Route $route): array
    {
        // Performance optimization: Cache kernel instance
        static $kernel = null;
        if ($kernel === null) {
            $kernel = $this->app->make(Kernel::class);
        }

        $this->middlewareGroups = $kernel->getMiddlewareGroups();
        $this->routeMiddleware = $kernel->getRouteMiddleware();

        $middleware = [];

        if ($route->group && isset($this->middlewareGroups[$route->group])) {
            $middleware = array_merge($middleware, $this->middlewareGroups[$route->group]);
        }

        foreach ($route->middleware as $name) {
            if (isset($this->middlewareGroups[$name])) {
                $middleware = array_merge($middleware, $this->middlewareGroups[$name]);
            } elseif (isset($this->routeMiddleware[$name])) {
                $middleware[] = $this->routeMiddleware[$name];
            } else {
                $middleware[] = $name;
            }
        }

        return array_unique($middleware);
    }

    public function loadFromCache(array $cachedRoutes): void
    {
        foreach ($cachedRoutes as $method => $routes) {
            foreach ($routes as $uri => $routeData) {
                $route = $this->addRoute($method, $uri, $routeData['handler']);
                if (!empty($routeData['middleware'])) {
                    $route->middleware($routeData['middleware']);
                }
                if (!empty($routeData['name'])) {
                    $route->name($routeData['name']);
                    $this->namedRoutes[$routeData['name']] = $route;
                }
            }
        }

        // After loading from cache, ensure all routes are optimized
        $this->rebuildRouteIndexes();
    }

    /**
     * Rebuild route indexes after loading from cache or bulk operations.
     */
    protected function rebuildRouteIndexes(): void
    {
        $this->staticRoutes = [];
        $this->dynamicRoutes = [];
        $this->compiledPatterns = [];

        foreach ($this->routes as $method => $routes) {
            foreach ($routes as $uri => $route) {
                $this->optimizeRoute($method, $uri, $route);
            }
        }
    }

    public function getRoutesForCaching(): array
    {
        $export = [];
        foreach ($this->routes as $method => $routes) {
            foreach ($routes as $uri => $route) {
                $export[$method][$uri] = [
                    'handler' => $route->handler,
                    'middleware' => $route->middleware,
                    'name' => $route->name,
                ];
            }
        }
        return $export;
    }

    public function current(): ?Route
    {
        return $this->currentRoute;
    }

    /**
     * Add a route to the collection of named routes.
     *
     * @param  string  $name
     * @param  \Core\Routing\Route  $route
     * @return void
     */
    public function addNamedRoute(string $name, Route $route): void
    {
        $this->namedRoutes[$name] = $route;
    }

    /**
     * Get a route instance by its name.
     *
     * @param string $name
     * @return \Core\Routing\Route|null
     */
    public function getByName(string $name): ?Route
    {
        return $this->namedRoutes[$name] ?? null;
    }

    /**
     * Generate a URL for the given named route.
     *
     * @param  string  $name
     * @param  array   $parameters
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    public function url(string $name, array $parameters = []): string
    {
        $route = $this->getByName($name);

        if (!$route) {
            // If the name is a valid URL, return it directly.
            if (filter_var($name, FILTER_VALIDATE_URL)) {
                return $name;
            }
            // If the name is a valid URI path, return it.
            if (preg_match('/^\/[\w\/\-\.]*$/', $name)) {
                return $name;
            }
            throw new \InvalidArgumentException("Route [{$name}] not defined.");
        }

        $uri = $route->uri;

        // Replace required parameters
        foreach ($parameters as $key => $value) {
            $uri = str_replace('{' . $key . '}', $value, $uri);
        }

        // Remove any remaining optional parameters (e.g., {id?})
        $uri = preg_replace('/\/\{[a-zA-Z0-9_]+\?\}/', '', $uri);

        // Check if there are any required parameters left
        if (str_contains($uri, '{')) {
            throw new \InvalidArgumentException('Missing required parameters for route: ' . $name);
        }

        return '/' . ltrim($uri, '/');
    }

    /**
     * Get all of the registered routes for the `route:list` command.
     *
     * @return \Core\Routing\Route[]
     */
    public function listRoutes(): array // @phpstan-ignore-line
    {
        $allRoutes = [];
        foreach ($this->routes as $methodRoutes) {
            foreach ($methodRoutes as $route) {
                $allRoutes[] = [
                    'method' => $route->method,
                    'uri' => $route->uri,
                    'name' => $route->name,
                    'handler' => $route->handler,
                    'middleware' => $route->middleware,
                ];
            }
        }
        return $allRoutes;
    }

    /**
     * Resets the router's state after a request.
     * Note: We don't reset compiled patterns and route indexes as they are static.
     */
    public function resetState(): void
    {
        $this->currentRoute = null;
        $this->currentRequest = null;
        $this->app->forgetInstance(Route::class);
    }
}
