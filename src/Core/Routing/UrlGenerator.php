<?php

namespace Core\Routing;

use Core\Exceptions\RouteNotFoundException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Generates URLs for the application's routes.
 */
class UrlGenerator
{
    protected Router $router;
    protected ServerRequestInterface $request;

    public function __construct(Router $router, ServerRequestInterface $request)
    {
        $this->router = $router;
        $this->request = $request;
    }

    /**
     * Generate the URL for a given named route.
     *
     * @param string $name
     * @param array $parameters
     * @return string
     * @throws RouteNotFoundException
     * @throws \InvalidArgumentException
     */
    public function route(string $name, array $parameters = []): string
    {
        $route = $this->router->getByName($name);

        if (!$route) {
            throw new RouteNotFoundException("Route [{$name}] not defined.");
        }

        $uri = $route->uri;
        $queryParameters = [];

        foreach ($parameters as $key => $value) {
            $placeholder = '{' . $key . '}';
            if (str_contains($uri, $placeholder)) {
                $uri = str_replace($placeholder, urlencode((string) $value), $uri);
            } else {
                $queryParameters[$key] = $value;
            }
        }

        // Check for any remaining placeholders
        if (preg_match('/\{([a-zA-Z0-9_]+)\}/', $uri, $matches)) {
            throw new \InvalidArgumentException("Missing required parameter for route [{$name}]: {$matches[1]}");
        }

        $url = self::to($uri);

        if (!empty($queryParameters)) {
            $url .= '?' . http_build_query($queryParameters);
        }

        return $url;
    }

    /**
     * Generate a fully qualified URL to a given path.
     *
     * @param string $path
     * @return string
     */
    public static function to(string $path): string
    {
        $baseUrl = rtrim(config('app.url', '/'), '/');
        $path = ltrim($path, '/');

        return "{$baseUrl}/{$path}";
    }
}
