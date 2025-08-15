<?php

namespace Http\Middleware;

use Core\Application;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class StartSession implements MiddlewareInterface
{
    public function __construct(protected Application $app)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $session = $this->app->get('session');
        $session->start();

        $response = $handler->handle($request);

        $session->save();

        return $response;
    }
}
