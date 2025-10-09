<?php

namespace App\Http\Middleware;

use Core\Cookie\CookieManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

class AddQueuedCookiesToResponse implements MiddlewareInterface
{
    protected CookieManager $cookieManager;

    public function __construct(CookieManager $cookieManager, protected LoggerInterface $logger)
    {
        $this->cookieManager = $cookieManager;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->logger->info('Adding queued cookies to response via middleware.');
        $response = $handler->handle($request);
        return $this->cookieManager->addQueuedCookiesToResponse($response);
    }
}
