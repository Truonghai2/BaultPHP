<?php

namespace Core\Http\Client\Middleware;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Retry Middleware.
 * 
 * Automatically retries failed requests.
 */
class RetryMiddleware
{
    public function __construct(
        private int $maxRetries = 3,
        private int $delayMs = 100,
        private array $retryStatuses = [429, 500, 502, 503, 504]
    ) {
    }

    /**
     * Create Guzzle middleware.
     */
    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            $retries = 0;

            return $handler($request, $options)->then(
                $this->onFulfilled($request, $options, $handler, $retries),
                $this->onRejected($request, $options, $handler, $retries)
            );
        };
    }

    /**
     * Handle successful response.
     */
    protected function onFulfilled(RequestInterface $request, array $options, callable $handler, int &$retries): callable
    {
        return function (ResponseInterface $response) use ($request, $options, $handler, &$retries) {
            // Retry on specific status codes
            if (in_array($response->getStatusCode(), $this->retryStatuses) && $retries < $this->maxRetries) {
                $retries++;
                
                // Exponential backoff
                $delay = $this->delayMs * pow(2, $retries - 1);
                usleep($delay * 1000);

                return $handler($request, $options);
            }

            return $response;
        };
    }

    /**
     * Handle failed request.
     */
    protected function onRejected(RequestInterface $request, array $options, callable $handler, int &$retries): callable
    {
        return function ($reason) use ($request, $options, $handler, &$retries) {
            // Retry on connection errors
            if ($reason instanceof ConnectException && $retries < $this->maxRetries) {
                $retries++;
                
                // Exponential backoff
                $delay = $this->delayMs * pow(2, $retries - 1);
                usleep($delay * 1000);

                return $handler($request, $options);
            }

            throw $reason;
        };
    }
}
