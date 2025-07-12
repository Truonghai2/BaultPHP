<?php

namespace Http\Middleware;

use Core\Session\SessionManager;
use Http\Request;
use Http\Response;

class StartSession
{
    public function __construct(protected SessionManager $session)
    {
    }

    public function handle(Request $request, \Closure $next): Response
    {
        $this->session->start();
        return $next($request);
    }
}