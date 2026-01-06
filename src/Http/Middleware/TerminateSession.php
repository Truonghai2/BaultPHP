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
    public function __construct(
        protected Application $app,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        /** @var SessionInterface $session */
        $session = $this->app->make('session');

        $session->save();

        // Queue session cookie để gửi về trình duyệt
        $this->addSessionCookie($session);

        return $response;
    }

    /**
     * Queue session cookie để gửi về client
     */
    protected function addSessionCookie(SessionInterface $session): void
    {
        if (!$session->isStarted()) {
            $this->app->make('log')->warning('TerminateSession: Session not started, skipping cookie');
            return;
        }

        $config = config('session');
        $lifetime = $config['lifetime'] ?? 120; // minutes

        /** @var CookieManager $cookieManager */
        $cookieManager = app(CookieManager::class);

        $cookieManager->queue(
            name: $session->getName(),
            value: $session->getId(),
            minutes: $lifetime,
            path: $config['path'] ?? '/',
            domain: $config['domain'] ?? null,
            secure: $config['secure'] ?? false,
            httpOnly: $config['http_only'] ?? true,
            raw: true, // Session ID không cần encrypt
            sameSite: $config['same_site'] ?? 'lax',
        );
    }
}
