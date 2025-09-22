<?php

namespace App\Http\Middleware;

use Core\Contracts\View\Factory as ViewFactory;
use Core\Validation\ErrorBag;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\HttpFoundation\Session\Session;

class ShareErrorsFromSession implements MiddlewareInterface
{
    protected ViewFactory $view;
    protected Session $session;

    public function __construct(ViewFactory $view, Session $session)
    {
        $this->view = $view;
        $this->session = $session;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Share the $errors variable with all views. If no errors are present in the
        // session, an empty ErrorBag is shared. This prevents "Undefined variable"
        // errors in the views on initial page loads.
        $this->view->share(
            'errors',
            new ErrorBag($this->session->get('errors', [])),
        );

        return $handler->handle($request);
    }
}
