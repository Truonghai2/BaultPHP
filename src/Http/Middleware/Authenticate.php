<?php

namespace App\Http\Middleware;

use Core\Application;
use Core\Contracts\Session\SessionInterface;
use Core\Http\Redirector;
use Core\Support\Facades\Auth;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class Authenticate implements MiddlewareInterface
{
    protected ?SessionInterface $session = null;

    public function __construct(
        protected Redirector $redirector,
        protected Application $app,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!Auth::check()) {
            $acceptHeader = $request->getHeaderLine('Accept');
            $contentType = $request->getHeaderLine('Content-Type');
            $isApiRequest = str_contains($acceptHeader, 'application/json')
                         || str_contains($contentType, 'application/json')
                         || str_starts_with($request->getUri()->getPath(), '/api/');

            if ($isApiRequest) {
                return response()->json([
                    'error' => 'Unauthenticated',
                    'message' => 'You must be logged in to access this resource.',
                ], 401);
            }

            // Lazy load session to avoid circular dependency
            if ($this->session === null && $this->app->bound(SessionInterface::class)) {
                try {
                    $this->session = $this->app->make(SessionInterface::class);
                } catch (\Throwable $e) {
                    // If session can't be resolved, continue without saving intended URL
                }
            }

            $path = $request->getUri()->getPath();
            if (!str_starts_with($path, '/admin') && $this->session !== null) {
                $this->session->set('url.intended', (string) $request->getUri());
            }

            return $this->redirector->route('auth.login.view');
        }

        return $handler->handle($request);
    }
}
