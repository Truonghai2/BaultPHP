<?php

namespace App\Http\Middleware;

use Closure;
use Core\Cookie\CookieManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AddQueuedCookiesToResponse implements MiddlewareInterface
{
    public function __construct(protected CookieManager $cookieManager)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        sdd("test");
        return $this->cookieManager->addQueuedCookiesToResponse($response);
    }
}
