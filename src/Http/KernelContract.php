<?php

namespace Http;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

interface KernelContract
{
    public function handle(ServerRequestInterface $request): ResponseInterface;
    public function terminate(ServerRequestInterface $request, ResponseInterface $response): void;
    public function aliasMiddleware(string $name, string $class): void;
    public function middlewareGroup(string $group, array $middleware): void;
}
