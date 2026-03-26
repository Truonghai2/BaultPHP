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
     * Queue session cookie để gửi về client.
     * Dùng scheme/host từ request hiện tại để tránh cookie Secure trên HTTP hoặc domain sai.
     */
    protected function addSessionCookie(SessionInterface $session): void
    {
        if (!$session->isStarted()) {
            $this->app->make('log')->warning('TerminateSession: Session not started, skipping cookie');
            return;
        }

        $config = config('session');
        $lifetime = (int) ($config['lifetime'] ?? 120); // minutes

        // Secure và domain theo request hiện tại (tránh cookie bị trình duyệt từ chối)
        $secure = (bool) ($config['secure'] ?? false);
        $domain = $config['domain'] ?? null;
        try {
            $request = $this->app->make(\Psr\Http\Message\ServerRequestInterface::class);
            $uri = $request->getUri();
            $secure = strtolower($uri->getScheme()) === 'https';
            $host = $uri->getHost();
            if (in_array($host, ['localhost', '127.0.0.1'], true)) {
                $domain = null;
            }
        } catch (\Throwable) {
            // giữ giá trị từ config
        }

        /** @var CookieManager $cookieManager */
        $cookieManager = app(CookieManager::class);

        $cookieManager->queue(
            name: $session->getName(),
            value: $session->getId(),
            minutes: $lifetime,
            path: $config['path'] ?? '/',
            domain: $domain,
            secure: $secure,
            httpOnly: (bool) ($config['http_only'] ?? true),
            raw: true, // Session ID không cần encrypt
            sameSite: $config['same_site'] ?? 'lax',
        );
    }
}
