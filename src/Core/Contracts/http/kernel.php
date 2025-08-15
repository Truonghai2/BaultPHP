<?php

namespace Core\Contracts\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Defines the contract for the application's HTTP kernel.
 * The kernel is responsible for handling an incoming request and returning a response.
 */
interface Kernel
{
    /**
     * Handle an incoming HTTP request.
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface;

    /**
     * Perform any final actions for the request after the response has been sent.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     */
    public function terminate(ServerRequestInterface $request, ResponseInterface $response): void;
}
