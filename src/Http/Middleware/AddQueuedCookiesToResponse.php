<?php

namespace App\Http\Middleware;

use Core\Cookie\CookieManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AddQueuedCookiesToResponse implements MiddlewareInterface
{
    protected CookieManager $cookieManager;

    public function __construct(CookieManager $cookieManager)
    {
        $this->cookieManager = $cookieManager;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        return $this->cookieManager->addQueuedCookiesToResponse($response);
    }
}
