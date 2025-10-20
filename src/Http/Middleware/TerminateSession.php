<?php

namespace App\Http\Middleware;

use Core\Application;
use Core\Contracts\Session\SessionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class TerminateSession implements MiddlewareInterface
{
    public function __construct(protected Application $app)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        sdd("$");
        $this->app->make('session')->save();

        return $response;
    }
}