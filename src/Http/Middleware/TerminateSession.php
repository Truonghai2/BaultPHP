<?php

namespace App\Http\Middleware;

use Core\Application;
use Core\Contracts\Session\SessionInterface;
use Core\Cookie\CookieManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class TerminateSession implements MiddlewareInterface
{
    public function __construct(protected Application $app, protected CookieManager $cookieManager)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        /** @var SessionInterface $session */
        $session = $this->app->make('session');

        if ($session->isStarted()) {
            $session->save();
        }

        return $response;
    }
}
