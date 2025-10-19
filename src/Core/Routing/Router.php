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
        return $route;
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
        if (isset($this->routes[$method])) {
            foreach ($this->routes[$method] as $routeUri => $route) {
                $pattern = preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', static function ($matches) {
                    return '(?P<' . $matches[1] . '>[^/]+)';
                }, $routeUri);
                if (preg_match('#^' . $pattern . '$#', $uri, $matches)) {
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
        $kernel = $this->app->make(Kernel::class);
        $this->middlewareGroups = $kernel->getMiddlewareGroups();
        $this->routeMiddleware = $kernel->getRouteMiddleware();

        $middleware = [];

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
     */
    public function resetState(): void
    {
        $this->currentRoute = null;
        $this->currentRequest = null;
        $this->app->forgetInstance(Route::class);
    }
}
