<?php

namespace Http\Middleware;

use Core\Application;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class StartSession implements MiddlewareInterface
{
    public function __construct(protected Application $app)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var SessionInterface $session */
        $session = $this->app->get('session');
        $session->start();

        // This is the crucial part: we need to attach the session to the request
        // so that other parts of the application can access it.
        if ($request instanceof Request) {
            $request->setSession($session);
        }

        $response = $handler->handle($request);

        // The session is saved automatically when the session object is destructed.
        // Explicitly calling save() can be problematic in some cases, especially with async requests.
        // We'll rely on the default behavior for now.
        // $session->save();

        return $response;
    }
}
