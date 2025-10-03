<?php

namespace Core\Debug;

use Closure;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class GuzzleMiddleware
 *
 * Một Guzzle middleware để theo dõi và ghi lại các request.
 */
class GuzzleMiddleware
{
    protected GuzzleCollector $collector;

    public function __construct(GuzzleCollector $collector)
    {
        $this->collector = $collector;
    }

    /**
     * Phương thức __invoke để Guzzle có thể gọi middleware này.
     */
    public function __invoke(callable $handler): Closure
    {
        return function (RequestInterface $request, array $options) use ($handler): PromiseInterface {
            $start = microtime(true);

            /** @var PromiseInterface $promise */
            $promise = $handler($request, $options);

            return $promise->then(
                function (ResponseInterface $response) use ($request, $start) {
                    $duration = microtime(true) - $start;
                    $this->collector->addRequest($duration, $request, $response);
                    return $response;
                },
                function (RequestException $exception) use ($request, $start) {
                    $duration = microtime(true) - $start;
                    $this->collector->addRequest($duration, $request, null, $exception);
                    throw $exception;
                },
            );
        };
    }
}
