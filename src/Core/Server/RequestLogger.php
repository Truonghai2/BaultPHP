<?php

namespace Core\Server;

use Core\Application;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Handles logging of HTTP requests and responses.
 * This class is intended to be used in a development environment to provide
 * detailed information about the request lifecycle.
 */
class RequestLogger
{
    public function __construct(
        private Application $app,
        private LoggerInterface $logger,
        private bool $isDebug,
    ) {
    }

    /**
     * Log an incoming request and its response.
     */
    public function log(ServerRequestInterface $request, ResponseInterface $response, float $startTime): void
    {
        if (!$this->isDebug) {
            return;
        }

        $duration = round((microtime(true) - $startTime) * 1000);
        $requestId = $this->app->make('request_id');

        $context = [
            'request_id' => $requestId,
            'method' => $request->getMethod(),
            'uri' => (string) $request->getUri(),
            'path' => $request->getUri()->getPath(),
            'query_params' => $request->getQueryParams(),
            'status_code' => $response->getStatusCode(),
            'reason_phrase' => $response->getReasonPhrase(),
            'duration_ms' => $duration,
            'remote_addr' => $request->getServerParams()['REMOTE_ADDR'] ?? '?.?.?.?',
            'request_headers' => $request->getHeaders(),
            'response_headers' => $response->getHeaders(),
        ];

        $requestBody = $request->getParsedBody();
        if ($requestBody) {
            $context['request_body'] = $requestBody;
        } elseif ($request->getBody()->getSize() > 0 && $request->getBody()->getSize() < 1024 * 10) {
            try {
                $request->getBody()->rewind();
                $context['raw_request_body'] = $request->getBody()->getContents();
            } catch (Throwable $e) {
                $context['raw_request_body_error'] = $e->getMessage();
            }
        }

        if ($response->getBody()->getSize() > 0 && $response->getBody()->getSize() < 1024 * 10) {
            try {
                $response->getBody()->rewind();
                $context['response_body'] = $response->getBody()->getContents();
            } catch (Throwable $e) {
                $context['response_body_error'] = $e->getMessage();
            }
        }

        $this->logger->info(
            sprintf(
                'Request [ID: %s] "%s %s" %d %s (%dms)',
                $requestId,
                $request->getMethod(),
                $request->getUri()->getPath(),
                $response->getStatusCode(),
                $response->getReasonPhrase(),
                $duration,
            ),
            $context,
        );
    }
}
