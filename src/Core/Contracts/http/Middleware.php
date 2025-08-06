<?php

namespace Core\Contracts\Http;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

interface Middleware
{
    public function handle(ServerRequestInterface $request, \Closure $next, ...$guards): ResponseInterface;
}
