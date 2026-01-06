<?php

namespace App\Http\Middleware;

use Core\Contracts\Session\SessionInterface;
use Core\Http\Redirector;
use Core\Support\Facades\Auth;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class Authenticate implements MiddlewareInterface
{
    public function __construct(
        protected Redirector $redirector,
        protected SessionInterface $session,
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

            // Only save intended URL for non-admin routes
            // Admin routes should redirect to their default admin dashboard instead
            $path = $request->getUri()->getPath();
            if (!str_starts_with($path, '/admin')) {
                $this->session->set('url.intended', (string) $request->getUri());
            }

            return $this->redirector->route('auth.login.view');
        }

        return $handler->handle($request);
    }
}
