<?php

namespace App\Http\Middleware;

use Core\Http\RedirectResponse;
use Modules\User\Infrastructure\Models\User;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Middleware to ensure that at least one admin user exists in the system.
 * If no users exist, it redirects to the initial admin creation page.
 * This is a crucial step for the first-time setup of the application.
 */
class EnsureAdminUserExists implements MiddlewareInterface
{
    protected SessionInterface $session;

    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
    }

    /**
     * The URIs that should be accessible even if no admin user has been created.
     * This list should include the admin creation form and any related assets or APIs.
     *
     * @var array<int, string>
     */
    protected array $except = [
        'setup/create-admin',
        'ping',
        'api/health',
        'metrics',
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
        $adminExists = User::query()->exists();

        if (! $adminExists && ! $this->isExceptedRoute($request)) {
            $redirect = new RedirectResponse('/setup/create-admin');
            $redirect->setSession($this->session);

            return $redirect;
        }

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
            if ($except !== '' && fnmatch($except, $path, FNM_PATHNAME)) {
                return true;
            }
        }

        return false;
    }
}
