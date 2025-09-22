<?php

namespace App\Http\Middleware;

use Closure;
use Core\Routing\Attributes\Middleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class StorePreviousUrlMiddleware extends Middleware
{
    public function __construct(private SessionInterface $session)
    {
    }

    public function handle(ServerRequestInterface $request, Closure $next): ResponseInterface
    {
        $response = $next($request);

        if (
            $request->getMethod() === 'GET' &&
            strtolower($request->getHeaderLine('X-Requested-With')) !== 'xmlhttprequest' &&
            (string) $request->getUri() !== $this->session->get('url.previous')
        ) {
            $this->session->set('url.previous', (string) $request->getUri());
        }

        return $response;
    }
}
