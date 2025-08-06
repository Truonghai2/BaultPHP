<?php

namespace Http\Middleware;

use Core\Support\Facades\Auth;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Http\ResponseFactory;

class AuthMiddleware
{
    public function handle(ServerRequestInterface $request, callable $next)
    {
        if (!Auth::check()) {
            return (new ResponseFactory())
                ->make('Unauthorized', 401);
        }

        return $next($request);
    }
}
