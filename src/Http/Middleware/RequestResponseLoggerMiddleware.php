<?php

namespace App\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

class RequestResponseLoggerMiddleware implements MiddlewareInterface
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Log request trước khi xử lý
        $this->logRequest($request);

        // Chuyển request cho handler tiếp theo và nhận về response
        $response = $handler->handle($request);

        // Log response sau khi đã có
        $this->logResponse($response);

        return $response;
    }

    private function logRequest(ServerRequestInterface $request): void
    {
        $body = $request->getBody();
        $body->rewind(); // Đảm bảo có thể đọc stream từ đầu
        $content = $body->getContents();
        $body->rewind(); // Tua lại stream để các phần khác của ứng dụng có thể đọc lại

        $this->logger->info('Incoming Request', [
            'method' => $request->getMethod(),
            'uri' => (string) $request->getUri(),
            'headers' => $request->getHeaders(),
            'body' => $this->truncate($content),
        ]);
    }

    private function logResponse(ResponseInterface $response): void
    {
        $body = $response->getBody();
        $body->rewind();
        $content = $body->getContents();
        $body->rewind();

        $this->logger->info('Outgoing Response', [
            'status_code' => $response->getStatusCode(),
            'reason' => $response->getReasonPhrase(),
            'headers' => $response->getHeaders(),
            'body' => $this->truncate($content),
        ]);
    }

    /**
     * Cắt bớt nội dung body quá dài để tránh làm đầy file log.
     */
    private function truncate(string $string, int $limit = 1000): string
    {
        if (mb_strlen($string) > $limit) {
            return mb_substr($string, 0, $limit) . '... [truncated]';
        }
        return $string;
    }
}
