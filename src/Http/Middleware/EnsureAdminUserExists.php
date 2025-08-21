<?php

namespace Http\Middleware;

use Modules\User\Infrastructure\Models\User;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware to ensure that at least one admin user exists in the system.
 * If no users exist, it redirects to the initial admin creation page.
 * This is a crucial step for the first-time setup of the application.
 */
class EnsureAdminUserExists implements MiddlewareInterface
{
    /**
     * The URIs that should be accessible even if no admin user has been created.
     * This list should include the admin creation form and any related assets or APIs.
     *
     * @var array<int, string>
     */
    protected array $except = [
        '/setup/create-admin',
        // Example: '/setup/assets/*' to allow CSS/JS for the setup page.
    ];

    /**
     * Process an incoming server request and return a response.
     *
     * @param ServerRequestInterface  $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Efficiently check if any user exists in the database.
        // Using `exists()` is much faster than `count() > 0` as it stops on the first find.
        $adminExists = User::query()->exists();

        // If no admin exists and the current route is not in the exception list,
        // redirect the user to the admin creation page.
        if (!$adminExists && !$this->isExceptedRoute($request)) {
            // The `response()` helper creates a PSR-7 compatible redirect response.
            return response()->redirect('/setup/create-admin');
        }

        // If an admin exists or the route is an exception, proceed with the request.
        return $handler->handle($request);
    }

    /**
     * Determine if the request has a URI that should be excluded from the check.
     * This method supports exact matches and wildcards (e.g., 'api/*').
     *
     * @param ServerRequestInterface $request
     * @return bool
     */
    protected function isExceptedRoute(ServerRequestInterface $request): bool
    {
        $path = trim($request->getUri()->getPath(), '/');

        foreach ($this->except as $except) {
            $except = trim($except, '/');

            // Check for exact match
            if ($path === $except) {
                return true;
            }

            // Check for wildcard match
            if (str_ends_with($except, '/*') && str_starts_with($path, rtrim($except, '/*'))) {
                return true;
            }
        }

        return false;
    }
}
