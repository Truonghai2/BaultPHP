<?php

namespace App\Http\Middleware;

use Core\Contracts\View\Factory as ViewFactory;
use Core\Validation\ErrorBag;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Shares flashed session data with all views.
 * This middleware makes variables like $errors, $success, and old input
 * available in all views rendered during the request.
 */
class ShareMessagesFromSession implements MiddlewareInterface
{
    protected ViewFactory $view;
    protected SessionInterface $session;

    public function __construct(ViewFactory $view, SessionInterface $session)
    {
        $this->view = $view;
        $this->session = $session;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $flashData = $this->session->getFlashBag()->all();

        $this->view->share(
            'errors',
            new ErrorBag($flashData['errors'] ?? []),
        );

        $this->view->share(
            '_old_input',
            $flashData['_old_input'] ?? [],
        );

        foreach ($flashData as $key => $messages) {
            $this->view->share($key, $messages[0] ?? null);
        }

        return $handler->handle($request);
    }
}
