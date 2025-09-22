<?php

namespace App\Http\Middleware;

use Core\Http\Redirector;
use Core\Support\Facades\Auth;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

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
            $this->session->set('url.intended', (string) $request->getUri());
            return $this->redirector->route('auth.login.view');
        }

        return $handler->handle($request);
    }
}
