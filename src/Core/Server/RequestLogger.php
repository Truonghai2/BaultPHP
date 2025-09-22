<?php

namespace Core\Server;
use Core\Facades\Log;

use Core\Contracts\Config\Repository as Config;
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
        private Application $app
    ) {
    }
    private function getLogger(): LoggerInterface
    {
        return $this->app->make(LoggerInterface::class);
    }

    /**
     * Log an incoming request and its response.
     */
    public function log(ServerRequestInterface $request, ResponseInterface $response, float $startTime): void
    {
        $logger = $this->getLogger();
        $duration = round((microtime(true) - $startTime) * 1000);
        $request_id = $this->app->make('request_id');
        $remote_addr = $request->getServerParams()['REMOTE_ADDR'] ?? '?.?.?.?';
        $method = $request->getMethod();
        $uri = $request->getUri()->getPath();
        $query = $request->getUri()->getQuery();
        if ($query) {
            $uri .= '?' . $query;
        }
        $protocol = 'HTTP/' . $request->getProtocolVersion();
        $status = $response->getStatusCode();
        $response_size = $response->getBody()->getSize() ?? 0;
        $referer = $request->getHeaderLine('Referer') ?: '-';
        $user_agent = $request->getHeaderLine('User-Agent') ?: '-';

        // Định dạng log theo chuẩn Apache Combined Log Format, có bổ sung request_id và duration
        // remote_addr - - [datetime] "METHOD URI PROTOCOL" STATUS_CODE RESPONSE_SIZE "REFERER" "USER_AGENT"
        $message = sprintf(
            '%s - - [%s] "%s %s %s" %d %d "%s" "%s"',
            $remote_addr,
            date('d/M/Y:H:i:s O'),
            $method,
            $uri,
            $protocol,
            $status,
            $response_size,
            $referer,
            $user_agent
        );

        $logger->info($message, ['duration_ms' => $duration, 'request_id' => $request_id]);
    }
}
