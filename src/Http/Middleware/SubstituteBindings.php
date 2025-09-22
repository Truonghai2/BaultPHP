<?php

namespace App\Http\Middleware;

use Core\Application;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class SubstituteBindings implements MiddlewareInterface
{
    public function __construct(protected Application $app)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $route = $request->getAttribute('route');

        if ($route) {
            foreach ($route->parameters as $key => $value) {
                if (isset($route->action['bindings'][$key])) {
                    $model = $route->action['bindings'][$key];

                    try {
                        $instance = $this->app->make($model)->findOrFail($value);
                        $route->parameters[$key] = $instance;
                    } catch (ModelNotFoundException $e) {
                        // Do nothing
                    }
                }
            }
        }

        return $handler->handle($request);
    }
}
