<?php

namespace App\Http\Middleware;

use App\Exceptions\TooManyRequestsHttpException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\SimpleCache\CacheInterface;

class ThrottleRequests implements MiddlewareInterface
{
    public function __construct(protected CacheInterface $cache)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Lấy cấu hình từ route, ví dụ: ->middleware('throttle:60,1')
        // Ở đây chúng ta sẽ hard-code để minh họa
        $maxAttempts = 60;
        $decayMinutes = 1;

        $key = $this->resolveRequestSignature($request);

        if ($this->cache->get($key, 0) >= $maxAttempts) {
            throw new TooManyRequestsHttpException('Too Many Attempts.');
        }

        $this->cache->set(
            $key,
            $this->cache->get($key, 0) + 1,
            $decayMinutes * 60,
        );

        $response = $handler->handle($request);

        // Thêm các header về rate limit vào response
        return $this->addHeaders(
            $response,
            $maxAttempts,
            $this->calculateRemainingAttempts($key, $maxAttempts),
        );
    }

    protected function resolveRequestSignature(ServerRequestInterface $request): string
    {
        // Tạo một "dấu vân tay" duy nhất cho request, ví dụ dựa trên IP
        return sha1($request->getServerParams()['REMOTE_ADDR'] ?? '127.0.0.1');
    }

    protected function calculateRemainingAttempts(string $key, int $maxAttempts): int
    {
        return $maxAttempts - $this->cache->get($key, 0);
    }

    protected function addHeaders(ResponseInterface $response, int $maxAttempts, int $remainingAttempts): ResponseInterface
    {
        return $response->withHeader('X-RateLimit-Limit', (string) $maxAttempts)
                        ->withHeader('X-RateLimit-Remaining', (string) $remainingAttempts);
    }
}
