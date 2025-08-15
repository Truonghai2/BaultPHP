<?php

namespace Core\Server;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RazonYang\Psr7\Swoole\ServerRequestFactory;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;

/**
 * A bridge to convert between Swoole and PSR-7 HTTP messages.
 * This class provides a safe implementation for emitting responses
 * to avoid warnings with empty response bodies.
 */
class SwoolePsr7Bridge
{
    private Psr17Factory $psr17Factory;
    private ServerRequestFactory $serverRequestFactory;

    public function __construct()
    {
        $this->psr17Factory = new Psr17Factory();
        // Khởi tạo factory một lần và tái sử dụng để tối ưu hiệu năng.
        $this->serverRequestFactory = new ServerRequestFactory(
            $this->psr17Factory,
            $this->psr17Factory,
            $this->psr17Factory,
            $this->psr17Factory,
        );
    }

    public function toPsr7Request(SwooleRequest $swooleRequest): ServerRequestInterface
    {
        // The factory correctly sets the protocol version from the Swoole request,
        // so an explicit call to `withProtocolVersion()` is not needed.
        // Tái sử dụng factory đã được khởi tạo trong constructor.
        return $this->serverRequestFactory->create($swooleRequest);
    }

    public function toSwooleResponse(ResponseInterface $response, SwooleResponse $swooleResponse): void
    {
        // Set status code and reason phrase
        $swooleResponse->status($response->getStatusCode(), $response->getReasonPhrase());

        // Set headers
        foreach ($response->getHeaders() as $name => $values) {
            // The 'Connection' header is managed by Swoole, so we skip it.
            if (strtolower($name) === 'connection') {
                continue;
            }
            foreach ($values as $value) {
                $swooleResponse->header($name, $value);
            }
        }

        $body = $response->getBody();

        // TỐI ƯU HÓA: Kiểm tra xem body có phải là một stream của file không.
        // Nếu có, sử dụng hàm `sendfile` cực kỳ hiệu quả của Swoole để stream nó
        // trực tiếp từ ổ đĩa, tránh việc tải toàn bộ file vào bộ nhớ của PHP.
        // Đây là cách lý tưởng để phục vụ các file download lớn.
        $file = $body->getMetadata('uri');
        if (is_string($file) && is_file($file)) {
            $swooleResponse->sendfile($file);
            return;
        }

        // Phương án dự phòng cho các response body thông thường, không phải file.
        // Lệnh này sẽ đọc toàn bộ body vào bộ nhớ trước khi gửi đi.
        // Nó an toàn cho các body rỗng và phù hợp cho các response HTML/JSON điển hình.
        $swooleResponse->end((string) $body);
    }
}
