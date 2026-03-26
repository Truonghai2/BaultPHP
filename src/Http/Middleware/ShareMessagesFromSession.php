<?php

namespace App\Http\Middleware;

use Core\Application;
use Core\Contracts\Session\SessionInterface;
use Core\Contracts\View\Factory as ViewFactory;
use Core\Validation\ErrorBag;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Shares flashed session data with all views.
 * This middleware makes variables like $errors, $success, and old input
 * available in all views rendered during the request.
 */
class ShareMessagesFromSession implements MiddlewareInterface
{
    protected ViewFactory $view;
    protected ?SessionInterface $session = null;

    public function __construct(
        ViewFactory $view,
        protected Application $app,
    ) {
        $this->view = $view;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Lazy load session to avoid circular dependency
        // session -> ShareMessagesFromSession -> session
        if ($this->session === null && $this->app->bound(SessionInterface::class)) {
            try {
                $this->session = $this->app->make(SessionInterface::class);
            } catch (\Throwable $e) {
                // If session can't be resolved, skip sharing messages
                return $handler->handle($request);
            }
        }

        // Skip if session is not available
        if ($this->session === null) {
            return $handler->handle($request);
        }

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
