<?php

namespace Http\Middleware;

use App\Exceptions\ForbiddenException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CheckPermissionMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $route = $request->getAttribute('route');

        if ($route && isset($route->action['permission'])) {
            $permission = $route->action['permission'];

            if (!auth()->user()->hasPermissionTo($permission)) {
                throw new ForbiddenException();
            }
        }

        return $handler->handle($request);
    }
}
