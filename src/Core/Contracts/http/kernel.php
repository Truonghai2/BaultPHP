<?php

namespace Core\Contracts\Http;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

interface Kernel
{
    public function handle(ServerRequestInterface $request): ResponseInterface;
    public function terminate(ServerRequestInterface $request, ResponseInterface $response): void;
}
