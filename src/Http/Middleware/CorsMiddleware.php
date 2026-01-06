<?php

namespace App\Http\Middleware;

use App\Http\Cors\CorsOriginsManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CorsMiddleware implements MiddlewareInterface
{
    public function __construct(
        private CorsOriginsManager $originsManager
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $config = config('cors');
        $requestOrigin = $request->getHeaderLine('Origin');

        // Xử lý preflight request trước
        if ($request->getMethod() === 'OPTIONS') {
            return $this->handlePreflightRequest($request, $requestOrigin, $config);
        }

        $response = $handler->handle($request);

        return $this->addCorsHeaders($response, $requestOrigin, $config);
    }

    /**
     * Xử lý preflight OPTIONS request.
     */
    private function handlePreflightRequest(
        ServerRequestInterface $request,
        string $requestOrigin,
        array $config
    ): ResponseInterface {
        $response = response('', 204); // No Content

        $response = $this->addCorsHeaders($response, $requestOrigin, $config);

        // Thêm headers đặc biệt cho preflight
        $maxAge = $config['max_age'] ?? 86400;
        $response = $response->withHeader('Access-Control-Max-Age', (string) $maxAge);

        // Cho phép các headers mà client request
        $requestHeaders = $request->getHeaderLine('Access-Control-Request-Headers');
        if (!empty($requestHeaders)) {
            $response = $response->withHeader('Access-Control-Allow-Headers', $requestHeaders);
        }

        return $response;
    }

    /**
     * Thêm CORS headers vào response.
     */
    private function addCorsHeaders(
        ResponseInterface $response,
        string $requestOrigin,
        array $config
    ): ResponseInterface {
        // Kiểm tra origin có được phép không
        $allowedOrigin = $this->originsManager->getAllowedOriginHeader($requestOrigin);

        if ($allowedOrigin !== null) {
            $response = $response->withHeader('Access-Control-Allow-Origin', $allowedOrigin);
        } elseif (in_array('*', $this->originsManager->getAllOrigins(), true) && empty($requestOrigin)) {
            // Chỉ dùng * khi không có origin header (same-origin request)
            $response = $response->withHeader('Access-Control-Allow-Origin', '*');
        }

        // Methods
        $response = $response->withHeader(
            'Access-Control-Allow-Methods',
            implode(', ', $config['allowed_methods'] ?? ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'])
        );

        // Headers
        $response = $response->withHeader(
            'Access-Control-Allow-Headers',
            implode(', ', $config['allowed_headers'] ?? ['Content-Type', 'Authorization', 'X-Requested-With', 'X-CSRF-TOKEN'])
        );

        // Exposed headers
        if (!empty($config['exposed_headers'])) {
            $response = $response->withHeader(
                'Access-Control-Expose-Headers',
                implode(', ', $config['exposed_headers'])
            );
        }

        // Credentials
        if ($config['supports_credentials'] ?? false) {
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        // Vary header để caching đúng
        $response = $response->withHeader('Vary', 'Origin');

        return $response;
    }
}
