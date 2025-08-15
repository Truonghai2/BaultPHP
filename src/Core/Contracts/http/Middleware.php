<?php

namespace Core\Contracts\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Middleware is responsible for handling an incoming request and passing it to the next middleware or controller.
 * It can also perform actions before and after the request is processed.
 */
interface Middleware
{
    /**
     * Handle an incoming request and pass it to the next middleware or controller.
     *
     * @param ServerRequestInterface $request
     * @param \Closure $next
     * @param mixed ...$guards
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request, \Closure $next, ...$guards): ResponseInterface;
}
