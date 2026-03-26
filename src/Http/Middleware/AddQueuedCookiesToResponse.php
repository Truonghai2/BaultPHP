<?php

namespace App\Http\Middleware;

use Core\Application;
use Core\Cookie\CookieManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AddQueuedCookiesToResponse implements MiddlewareInterface
{
    public function __construct(
        protected Application $app,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        // Lazy load CookieManager to avoid circular dependency
        // CookieManager -> Encrypter -> config -> (some service) -> session -> CookieManager
        if (!$this->app->bound(CookieManager::class)) {
            return $response;
        }

        try {
            /** @var CookieManager $cookieManager */
            $cookieManager = $this->app->make(CookieManager::class);
            $response = $cookieManager->addQueuedCookiesToResponse($response);
        } catch (\Throwable $e) {
            // If CookieManager can't be resolved, skip adding cookies
            // Log error but don't break the request
            if ($this->app->bound('log')) {
                $this->app->make('log')->warning('AddQueuedCookiesToResponse: Failed to get CookieManager', [
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        return $response;
    }
}
