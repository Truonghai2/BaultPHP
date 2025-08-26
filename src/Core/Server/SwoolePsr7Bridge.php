<?php

namespace Core\Server;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RazonYang\Psr7\Swoole\ServerRequestFactory;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;

/**
 * A bridge to convert between Swoole and PSR-7 HTTP messages.
 * This class provides a safe implementation for emitting responses
 * to avoid warnings with empty response bodies.
 */
class SwoolePsr7Bridge
{
    private Psr17Factory $psr17Factory;
    private ServerRequestFactory $serverRequestFactory;
    private HttpFoundationFactory $httpFoundationFactory;

    public function __construct()
    {
        $this->psr17Factory = new Psr17Factory();
        $this->serverRequestFactory = new ServerRequestFactory(
            $this->psr17Factory,
            $this->psr17Factory,
            $this->psr17Factory,
            $this->psr17Factory,
        );
        $this->httpFoundationFactory = new HttpFoundationFactory();
    }

    public function toPsr7Request(SwooleRequest $swooleRequest): ServerRequestInterface
    {
        $psr7Request = $this->serverRequestFactory->create($swooleRequest);

        // Manually parse the request body if it's not already parsed.
        if (null === $psr7Request->getParsedBody()) {
            $contentType = strtolower($swooleRequest->header['content-type'] ?? '');
            $rawContent = $swooleRequest->getContent();

            if (str_contains($contentType, 'application/json') && !empty($rawContent)) {
                $body = json_decode($rawContent, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $psr7Request = $psr7Request->withParsedBody($body);
                }
            } elseif (str_contains($contentType, 'application/x-www-form-urlencoded') && !empty($rawContent)) {
                $body = [];
                parse_str($rawContent, $body);
                $psr7Request = $psr7Request->withParsedBody($body);
            }
        }

        return $psr7Request;
    }

    public function toSwooleResponse(ResponseInterface $response, SwooleResponse $swooleResponse): void
    {
        // Prevent "headers already sent" warning
        if (!$swooleResponse->isWritable()) {
            return;
        }

        // Set status code and reason phrase
        $swooleResponse->status($response->getStatusCode(), $response->getReasonPhrase());

        // Set headers
        foreach ($response->getHeaders() as $name => $values) {
            // The 'Connection' header is managed by Swoole, so we skip it.
            if (strtolower($name) === 'connection') {
                continue;
            }

            $values = \is_array($values) ? $values : [$values];
            foreach ($values as $value) {
                $swooleResponse->header((string) $name, (string) $value);
            }
        }

        // TỐI ƯU HÓA: Kiểm tra xem body có phải là một stream của file không.
        // Nếu có, sử dụng hàm `sendfile` cực kỳ hiệu quả của Swoole để stream nó
        // trực tiếp từ ổ đĩa, tránh việc tải toàn bộ file vào bộ nhớ của PHP.
        // Đây là cách lý tưởng để phục vụ các file download lớn.
        $body = $response->getBody();
        $file = $body->getMetadata('uri');
        if (is_string($file) && is_file($file)) {
            $swooleResponse->sendfile($file);

            return;
        }

        // Rewind the stream before reading its content to ensure we send the full body,
        // even if it has been read before.
        if ($body->isSeekable()) {
            $body->rewind();
        }

        // Phương án dự phòng cho các response body thông thường, không phải file.
        // Lệnh này sẽ đọc toàn bộ body vào bộ nhớ trước khi gửi đi.
        // Nó an toàn cho các body rỗng và phù hợp cho các response HTML/JSON điển hình.
        $swooleResponse->end($body->getContents());
    }
}
