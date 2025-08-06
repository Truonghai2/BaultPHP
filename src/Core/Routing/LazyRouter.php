<?php

namespace Core\Routing;

class LazyRouter
{
    protected string $basePath;
    protected Router $router;

    public function __construct(Router $router, string $basePath = null)
    {
        $this->router = $router;
        $this->basePath = $basePath ?? base_path('Modules');
    }

    public function load(): void
    {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $segments = explode('/', trim($uri, '/'));
        $module = ucfirst($segments[0] ?? '');

        $routeFile = "{$this->basePath}/{$module}/Http/routes.php";

        if (is_file($routeFile)) {
            $register = require $routeFile;

            if (is_callable($register)) {
                $register($this->router);
            }
        }
    }
}
