<?php

namespace Http\Middleware;

use Core\Support\Facades\Log;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AttachRequestIdToLogs implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Tạo một ID duy nhất cho request này
        $requestId = (string) Str::uuid();

        // Sử dụng phương thức `withContext` của LogManager để thêm ID này
        // vào TẤT CẢ các log được ghi trong suốt vòng đời của request.
        Log::withContext([
            'request_id' => $requestId,
        ]);

        // Xử lý request
        $response = $handler->handle($request);

        // Thêm Request ID vào header của response để client hoặc các hệ thống khác
        // có thể sử dụng để đối chiếu.
        return $response->withHeader('X-Request-ID', $requestId);
    }
}
