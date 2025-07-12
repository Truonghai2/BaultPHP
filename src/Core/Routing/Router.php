<?php 

namespace Core\Routing;

use Core\Application;
use Http\Request;
use App\Exceptions\NotFoundException;
use App\Exceptions\MethodNotAllowedException;

class Router
{
    protected array $routes = [];
    protected array $routePatterns = [];

    public function __construct(protected Application $app)
    {
    }

    public function get(string $uri, callable|array $action): Route
    {
        return $this->addRoute('GET', $uri, $action);
    }

    public function post(string $uri, callable|array $action): Route
    {
        return $this->addRoute('POST', $uri, $action);
    }

    public function put(string $uri, callable|array $action): Route
    {
        return $this->addRoute('PUT', $uri, $action);
    }

    public function patch(string $uri, callable|array $action): Route
    {
        return $this->addRoute('PATCH', $uri, $action);
    }

    public function delete(string $uri, callable|array $action): Route
    {
        return $this->addRoute('DELETE', $uri, $action);
    }

    public function addRoute(string $method, string $uri, callable|array $action): Route
    {
        $route = new Route($method, $uri, $action);
        $this->routes[$method][$uri] = $route;
        return $route;
    }

    /**
     * Finds a route that matches the given request.
     *
     * @throws NotFoundException
     * @throws MethodNotAllowedException
     */
    public function dispatch(Request $request): Route
    {
        $requestMethod = $request->method();
        $requestPath = $request->path();

        foreach ($this->routes[$requestMethod] ?? [] as $uri => $route) {
            $pattern = $this->compileRoutePattern($uri);

            if (preg_match($pattern, $requestPath, $matches)) {
                $parameters = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                return $route->setParameters($parameters);
            }
        }

        // Check for 405 Method Not Allowed
        foreach ($this->routes as $method => $routes) {
            if ($method === $requestMethod) continue;
            foreach ($routes as $uri => $route) {
                if (preg_match($this->compileRoutePattern($uri), $requestPath)) {
                    throw new MethodNotAllowedException('Method Not Allowed', 405);
                }
            }
        }

        throw new NotFoundException('Not Found', 404);
    }

    protected function compileRoutePattern(string $uri): string
    {
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $uri);
        return '#^' . $pattern . '$#';
    }

    /**
     * Lấy một representation của routes có thể cache được.
     */
    public function getRoutesForCaching(): array
    {
        $export = [];
        foreach ($this->routes as $method => $routes) {
            foreach ($routes as $uri => $route) {
                $export[$method][$uri] = [
                    'handler' => $route->handler,
                    'middleware' => $route->middleware,
                ];
            }
        }
        return $export;
    }

    /**
     * Populate the router with routes from a cached array.
     */
    public function loadFromCache(array $cachedRoutes): void
    {
        $this->routes = [];
        foreach ($cachedRoutes as $method => $routes) {
            foreach ($routes as $uri => $data) {
                $route = new Route($method, $uri, $data['handler']);
                $route->middleware($data['middleware']);
                $this->routes[$method][$uri] = $route;
            }
        }
    }


    public function resource(string $uri, string $controller, array $options = []): void
    {
        $base = trim($uri, '/');
        $id = '{id}';

        $map = [
            'index'   => ['GET', "/$base"],
            'create'  => ['GET', "/$base/create"],
            'store'   => ['POST', "/$base"],
            'show'    => ['GET', "/$base/$id"],
            'edit'    => ['GET', "/$base/$id/edit"],
            'update'  => ['PUT', "/$base/$id"],
            'destroy' => ['DELETE', "/$base/$id"],
        ];

        // Apply only/except
        if (!empty($options['only'])) {
            $map = array_filter($map, fn($k) => in_array($k, $options['only']), ARRAY_FILTER_USE_KEY);
        }
        if (!empty($options['except'])) {
            $map = array_filter($map, fn($k) => !in_array($k, $options['except']), ARRAY_FILTER_USE_KEY);
        }

        foreach ($map as $action => [$method, $uri]) {
            $this->addRoute($method, $uri, [$controller, $action]);
        }
    }
    
    public function listRoutes(): array
    {
        return array_map(function ($route) {
            return [
                'method' => $route->method,
                'uri'    => $route->uri,
                'action' => is_array($route->handler)
                    ? implode('@', $route->handler)
                    : 'Closure',
                'middleware' => $route->middleware ?? [],
            ];
        }, $this->routes);
    }
}
