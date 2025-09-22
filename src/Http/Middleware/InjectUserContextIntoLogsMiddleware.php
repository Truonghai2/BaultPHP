<?php

namespace App\Http\Middleware;

use Core\Support\Facades\Auth;
use Core\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class InjectUserContextIntoLogsMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Chỉ thêm context nếu người dùng đã được xác thực
        if (Auth::check()) {
            Log::withContext([
                'user_id' => Auth::id(),
            ]);
        }

        return $handler->handle($request);
    }
}
