<?php

namespace App\Http\Middleware;

use Core\Config;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

class RequestResponseLoggerMiddleware implements MiddlewareInterface
{
    /** @var string[] */
    protected array $keysToSanitize;

    public function __construct(private LoggerInterface $logger, Config $config)
    {
        // Lấy danh sách các key cần ẩn đi từ config và chuyển thành chữ thường để so sánh
        $this->keysToSanitize = array_map('strtolower', $config->get('sanitizer.keys', []));
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Chỉ ghi log chi tiết nếu đang ở môi trường local để tránh làm quá tải log ở production
        if (config('app.env') !== 'local') {
            return $handler->handle($request);
        }

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
        $parsedBody = $request->getParsedBody();
        $bodyContent = '';

        if (is_array($parsedBody)) {
            $bodyContent = json_encode($this->sanitize($parsedBody));
        } else {
            $stream = $request->getBody();
            if ($stream->isReadable()) {
                $stream->rewind();
                $bodyContent = $stream->getContents();
                // Cố gắng decode json để sanitize, nếu không được thì giữ nguyên
                try {
                    $data = json_decode($bodyContent, true, 512, JSON_THROW_ON_ERROR);
                    $bodyContent = json_encode($this->sanitize($data));
                } catch (JsonException) {
                    // Không phải body dạng JSON, giữ nguyên để truncate
                }
                $stream->rewind();
            }
        }

        $this->logger->info('Incoming Request', [
            'method' => $request->getMethod(),
            'uri' => (string) $request->getUri(),
            'headers' => $request->getHeaders(),
            'body' => $this->truncate($bodyContent),
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
            'body' => $this->truncate($this->sanitizeBodyString($content)),
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

    /**
     * Sanitize an array of data recursively.
     */
    private function sanitize(array $data): array
    {
        if (empty($this->keysToSanitize)) {
            return $data;
        }

        foreach ($data as $key => &$value) {
            if (in_array(strtolower((string) $key), $this->keysToSanitize, true)) {
                $value = '********';
            } elseif (is_array($value)) {
                $value = $this->sanitize($value);
            }
        }

        return $data;
    }

    /**
     * Sanitize a JSON string.
     */
    private function sanitizeBodyString(string $body): string
    {
        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            $sanitizedData = $this->sanitize($data);
            return json_encode($sanitizedData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            // If it's not a valid JSON string, we can't sanitize it based on keys.
            // Return the original (truncated) body.
            return $body;
        }
    }
}
