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
        $requestId = (string) Str::uuid();

        Log::withContext([
            'request_id' => $requestId,
        ]);

        $response = $handler->handle($request);

        return $response->withHeader('X-Request-ID', $requestId);
    }
}
