<?php

namespace App\Http\Middleware;

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
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler, string ...$permissions): ResponseInterface
    {
        if (!Auth::check()) {
            throw new AccessDeniedException('Authentication required.');
        }

        $user = Auth::user();

        foreach ($permissions as $permission) {
            $orPermissions = explode('|', $permission);

            $hasAtLeastOnePermission = false;
            foreach ($orPermissions as $orPermission) {
                if ($user->can($orPermission)) {
                    $hasAtLeastOnePermission = true;
                    break;
                }
            }

            if (!$hasAtLeastOnePermission) {
                $required = count($orPermissions) > 1 ? 'one of [' . implode(', ', $orPermissions) . ']' : "'" . $permission . "'";
                throw new AccessDeniedException("User does not have the required permission: {$required}.");
            }
        }

        return $handler->handle($request);
    }
}
