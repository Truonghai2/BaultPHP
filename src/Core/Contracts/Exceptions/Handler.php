<?php

namespace Core\Contracts\Exceptions;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Handler is responsible for handling exceptions in the application.
 * It provides methods to report, render, and log exceptions.
 */
interface Handler
{
    /**
     * Report or log an exception.
     *
     * @param  \Throwable  $e
     * @return void
     */
    public function report(Throwable $e): void;

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Psr\Http\Message\ServerRequestInterface  $request
     * @param  \Throwable  $e
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function render(ServerRequestInterface $request, Throwable $e): ResponseInterface;

    /**
     * Render an exception to the console.
     */
    public function renderForConsole($output, Throwable $e): void;
}
