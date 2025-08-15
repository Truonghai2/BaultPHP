<?php

namespace Core\Routing;

use App\Exceptions\MethodNotAllowedException;
use App\Exceptions\NotFoundException;
use Core\Application;
use Core\Http\Response;
use Psr\Http\Message\ResponseInterface;

class Router
{
    protected array $routes = [];
    protected array $namedRoutes = [];
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

    public function addRoute(string $method, string $uri, callable|array $action, ?string $name = null): Route
    {
        $route = new Route($method, $uri, $action);
        $this->routes[$method][$uri] = $route;
        if ($name) {
            $this->namedRoutes[$name] = $uri;
        }
        return $route;
    }

    /**
     * Thêm một route vào bộ sưu tập các route có tên.
     * Phương thức này được gọi bởi đối tượng Route khi một tên được gán.
     *
     * @param string $name
     * @param string $uri
     */
    public function addNamedRoute(string $name, string $uri): void
    {
        $this->namedRoutes[$name] = $uri;
    }
    /**
     * Finds a route that matches the given request.
     *
     * @throws \App\Exceptions\NotFoundException
     * @throws \App\Exceptions\MethodNotAllowedException
     */
    public function dispatch(\Psr\Http\Message\ServerRequestInterface $request): ResponseInterface
    {
        $route = $this->findRoute($request);

        $response = $this->process($route);

        return $this->prepareResponse($response);
    }

    protected function findRoute(\Psr\Http\Message\ServerRequestInterface $request): Route
    {
        $requestMethod = $request->getMethod();
        $requestPath = rtrim($request->getUri()->getPath(), '/');
        if ($requestPath === '') {
            $requestPath = '/';
        }

        foreach ($this->routes[$requestMethod] ?? [] as $uri => $route) {
            $pattern = $this->compileRoutePattern($uri);

            if (preg_match($pattern, $requestPath, $matches)) {
                $parameters = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                return $route->setParameters($parameters);
            }
        }

        // Check for 405 Method Not Allowed
        foreach ($this->routes as $method => $routes) {
            if ($method === $requestMethod) {
                continue;
            }
            foreach ($routes as $uri => $route) {
                if (preg_match($this->compileRoutePattern($uri), $requestPath)) {
                    throw new MethodNotAllowedException('Method Not Allowed', 405);
                }
            }
        }

        throw new NotFoundException('Not Found', 404);
    }

    protected function process(Route $route)
    {
        $handler = $route->handler;

        if (is_callable($handler)) {
            return call_user_func_array($handler, $route->parameters);
        }

        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            if (class_exists($class) && method_exists($class, $method)) {
                $controller = $this->app->make($class);
                // CẢI TIẾN: Sử dụng app()->call() thay vì call_user_func_array().
                // Việc này cho phép DI Container tự động inject các dependency vào phương thức
                // của controller (ví dụ: Request, Service,...) cùng với các tham số từ route.
                return $this->app->call([$controller, $method], $route->parameters);
            }
        }

        throw new \RuntimeException('Invalid route handler');
    }

    protected function prepareResponse($response): ResponseInterface
    {
        if ($response instanceof ResponseInterface) {
            return $response;
        }

        if (is_array($response) || $response instanceof \JsonSerializable) {
            return Response::json($response);
        }

        return new Response((string) $response);
    }

    protected function compileRoutePattern(string $uri): string
    {
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $uri);
        return '#^' . $pattern . '$#';
    }

    /**
     * Tạo URL từ tên route và các tham số.
     *
     * @param string $name Tên của route.
     * @param array $parameters Mảng các tham số cho route.
     * @return string URL đã được tạo.
     * @throws \Exception Nếu không tìm thấy route.
     */
    public function url(string $name, array $parameters = []): string
    {
        if (!isset($this->namedRoutes[$name])) {
            throw new \Exception("Route [{$name}] not defined.");
        }

        $uri = $this->namedRoutes[$name];

        foreach ($parameters as $key => $value) {
            $uri = str_replace('{' . $key . '}', (string) $value, $uri);
        }

        return preg_replace('/\/\{(\w+)\?\}/', '', $uri);
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
                    'handler'    => $route->handler,
                    'middleware' => $route->middleware,
                    'bindings'   => $route->bindings,
                    'name'       => $route->name,
                    'group'      => $route->group ?? null,
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
                $route           = new Route($method, $uri, $data['handler']);
                $route->middleware = $data['middleware'] ?? [];
                $route->bindings   = $data['bindings'] ?? [];
                $route->group      = $data['group'] ?? null;
                if (!empty($data['name'])) {
                    $route->name($data['name']);
                }
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
            $map = array_filter($map, fn ($k) => in_array($k, $options['only']), ARRAY_FILTER_USE_KEY);
        }
        if (!empty($options['except'])) {
            $map = array_filter($map, fn ($k) => !in_array($k, $options['except']), ARRAY_FILTER_USE_KEY);
        }

        foreach ($map as $action => [$method, $uri]) {
            $this->addRoute($method, $uri, [$controller, $action]);
        }
    }

    public function listRoutes(): array
    {
        $routes = [];
        foreach ($this->routes as $methodGroup) {
            foreach ($methodGroup as $route) {
                $routes[] = [
                    'method' => $route->method,
                    'uri'    => $route->uri,
                    'name'   => $route->name,
                    'action' => is_array($route->handler)
                        ? implode('@', $route->handler)
                        : 'Closure',
                    'middleware' => $route->middleware ?? [],
                ];
            }
        }
        return $routes;
    }
}
