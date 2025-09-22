<?php

namespace Core\Contracts\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface Kernel
{
    /**
     * Handle an incoming HTTP request.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface;

    /**
     * Perform any final actions for the request lifecycle.
     */
    public function terminate(ServerRequestInterface $request, ResponseInterface $response): void;

    /**
     * Get the application's middleware groups.
     *
     * @return array<string, array<int, class-string|string>>
     */
    public function getMiddlewareGroups(): array;

    /**
     * Get the application's route middleware aliases.
     *
     * @return array<string, class-string>
     */
    public function getRouteMiddleware(): array;
}
