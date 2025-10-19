<?php

namespace App\Http\Middleware;

use Core\Contracts\Session\SessionInterface;
use Core\Cookie\CookieManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

class AddQueuedCookiesToResponse implements MiddlewareInterface
{
    protected CookieManager $cookieManager;

    public function __construct(
        CookieManager $cookieManager,
        protected LoggerInterface $logger,
        protected SessionInterface $session,
    ) {
        $this->cookieManager = $cookieManager;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        if ($this->session->isStarted()) {
            $this->addSessionCookieToQueue();
        }

        return $this->cookieManager->addQueuedCookiesToResponse($response);
    }

    /**
     * Add the session cookie to the cookie queue.
     */
    protected function addSessionCookieToQueue(): void
    {
        $config = config('session');
        $lifetime = $config['expire_on_close'] ? 0 : ($config['lifetime'] * 60);

        $this->cookieManager->queue(
            $this->session->getName(),
            $this->session->getId(),
            $lifetime,
            $config['path'] ?? '/',
            $config['domain'] ?? null,
            $config['secure'] ?? false,
            $config['http_only'] ?? true,
            false,
            $config['same_site'] ?? 'lax',
        );
    }
}
