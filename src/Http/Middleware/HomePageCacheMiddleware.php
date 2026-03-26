<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Fallback for GET / when Kernel fast path missed (e.g. cache was cold).
 * L1 per-worker in-memory (TTL 60s) + Redis; session not started — guest = no session cookie.
 */
class HomePageCacheMiddleware implements MiddlewareInterface
{
    private const CACHE_KEY = 'page.home.guest';
    private const L1_TTL = 60;

    /** @var string|null L1 HTML (per worker) */
    private static ?string $l1Html = null;
    /** @var int|null L1 expiry timestamp */
    private static ?int $l1Expiry = null;
    /** @var string|null Session cookie name (cached) */
    private static ?string $sessionCookieName = null;

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        if ($request->getMethod() !== 'GET') {
            return $handler->handle($request);
        }

        $path = $request->getUri()->getPath();
        if (trim($path, '/') !== '') {
            return $handler->handle($request);
        }

        $query = $request->getUri()->getQuery();
        if ($query !== '' && str_contains($query, 'nocache=')) {
            return $handler->handle($request);
        }

        if (self::$sessionCookieName === null) {
            self::$sessionCookieName = config('session.cookie', 'bault_session');
        }
        $cookieLine = $request->getHeaderLine('Cookie');
        if ($cookieLine !== '' && str_contains($cookieLine, self::$sessionCookieName . '=')) {
            return $handler->handle($request);
        }

        $now = time();
        if (self::$l1Expiry !== null && $now < self::$l1Expiry && self::$l1Html !== null && self::$l1Html !== '') {
            return response(self::$l1Html, 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
                'X-Cache' => 'HIT-L1',
            ]);
        }

        try {
            $cached = cache(null)->get(self::CACHE_KEY);
        } catch (\Throwable) {
            return $handler->handle($request);
        }

        if ($cached === null || $cached === '') {
            return $handler->handle($request);
        }

        self::$l1Html = $cached;
        self::$l1Expiry = $now + self::L1_TTL;

        return response($cached, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Cache' => 'HIT',
        ]);
    }
}
