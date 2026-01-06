<?php

namespace App\Http\Middleware;

use Closure;
use Core\Application;
use Core\Cookie\CookieManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AddQueuedCookiesToResponse implements MiddlewareInterface
{
    public function __construct(
        protected Application $app
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        
        /** @var CookieManager $cookieManager */
        $cookieManager = app(CookieManager::class);
        
        $response = $cookieManager->addQueuedCookiesToResponse($response);
        
        return $response;
    }
}
