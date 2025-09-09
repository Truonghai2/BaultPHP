<?php

namespace Http\Middleware;

use Core\Support\Facades\Auth;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class CheckPermissionMiddleware implements MiddlewareInterface
{
    /**
     * Handle an incoming request.
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @param string ...$permissions The permissions required to access the route.
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request, RequestHandlerInterface $handler, string ...$permissions): ResponseInterface
    {
        $user = Auth::user();

        if (!$user) {
            throw new AccessDeniedException('Authentication required.');
        }

        foreach ($permissions as $permission) {
            if (!$user->can($permission)) {
                throw new AccessDeniedException("You do not have permission to '{$permission}'.");
            }
        }

        return $handler->handle($request);
    }
}
