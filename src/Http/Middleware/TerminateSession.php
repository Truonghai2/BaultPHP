<?php

namespace App\Http\Middleware;

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

            $config = $this->app->make('config')->get('session');

            $lifetime = $config['expire_on_close'] ? 0 : $config['lifetime'];

            $this->cookieManager->queue(
                $session->getName(),
                $session->getId(),
                $lifetime,
                $config['path'],
                $config['domain'],
                $config['secure'],
                $config['http_only'],
                false,
                $config['same_site'],
            );
        }

        return $response;
    }
}
