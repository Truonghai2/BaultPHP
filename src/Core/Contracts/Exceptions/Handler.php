<?php

namespace Core\Contracts\Exceptions;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

interface Handler
{
    /**
     * Bootstrap the exception handler.
     *
     * @return void
     */
    public function bootstrap(): void;

    /**
     * Report or log an exception.
     *
     * @param \Throwable $e
     * @return void
     */
    public function report(Throwable $e): void;

    /**
     * Render an exception into an HTTP response.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Throwable $e
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function render(Request $request, Throwable $e): ResponseInterface;

    /**
     * Render an exception to the console.
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param \Throwable $e
     * @return void
     */
    public function renderForConsole(OutputInterface $output, Throwable $e): void;
}
