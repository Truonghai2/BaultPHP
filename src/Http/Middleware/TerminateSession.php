<?php

namespace Http\Middleware;

use Core\Application;
use Core\Cookie\CookieManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Middleware này đảm bảo session được lưu và cookie được thêm vào response
 * vào cuối vòng đời của request.
 * Nó hoạt động như một "after" middleware, giải quyết vấn đề cần phải gọi save() thủ công.
 */
class TerminateSession implements MiddlewareInterface
{
    public function __construct(protected Application $app, protected CookieManager $cookieManager)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        /** @var SessionInterface $session */
        $session = $this->app->make('session');

        if ($session->isStarted()) {
            $session->save();

            $this->cookieManager->queue(
                $session->getName(),
                $session->getId(),
                $this->getCookieLifetimeInMinutes(),
            );
        }

        return $response;
    }

    protected function getCookieLifetimeInMinutes(): int
    {
        return $this->app->make('config')->get('session.lifetime', 120);
    }
}
