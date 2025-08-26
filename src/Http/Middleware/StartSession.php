<?php

namespace Http\Middleware;

use Core\Application;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Middleware này chịu trách nhiệm khởi động session từ request đến.
 * Nó đọc session ID từ cookie và gọi session->start().
 * Việc lưu session sẽ được xử lý bởi một middleware khác (TerminateSession).
 */
class StartSession implements MiddlewareInterface
{
    public function __construct(protected Application $app)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var SessionInterface $session */
        $session = $this->app->make('session');

        $cookies = $request->getCookieParams();
        if (isset($cookies[$session->getName()])) {
            $session->setId($cookies[$session->getName()]);
        }

        $session->start();

        return $handler->handle($request);
    }
}
