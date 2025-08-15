<?php

namespace Http\Middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Http\ResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class JwtVerifyTokenMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $header = $request->getHeaderLine('Authorization');

        if ($header && preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
            $token = $matches[1];

            try {
                $decoded = JWT::decode($token, new Key(config('app.key'), 'HS256'));
                // Gán user ID hoặc toàn bộ payload vào request để các phần sau sử dụng.
                // Sử dụng 'jwt_user' để tránh xung đột với các thuộc tính khác.
                $request = $request->withAttribute('jwt_user', $decoded->data);
            } catch (\Exception $e) {
                // BẢO MẬT: Không bao giờ bỏ qua lỗi xác thực.
                // Nếu token không hợp lệ, trả về lỗi 401 ngay lập tức.
                return (new ResponseFactory())->json(['error' => 'Unauthorized', 'message' => $e->getMessage()], 401);
            }
        }

        return $handler->handle($request);
    }
}
