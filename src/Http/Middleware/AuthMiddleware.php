<?php 

namespace Http\Middleware;

use Core\Support\Facades\Auth;
use Http\Request;
use Http\Response;

class AuthMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        if (!Auth::check()) {
            return (new Response())
                ->setStatus(401)
                ->setContent('Unauthorized');
        }

        return $next($request);
    }
}
