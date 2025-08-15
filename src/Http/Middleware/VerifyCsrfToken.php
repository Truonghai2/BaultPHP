<?php

namespace App\Http\Middleware;

use Core\Contracts\Session\SessionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class VerifyCsrfToken implements MiddlewareInterface
{
    /**
     * Các URI nên được loại trừ khỏi việc xác thực CSRF.
     *
     * @var array<int, string>
     */
    protected array $except = [
        // Ví dụ: 'api/webhooks/*'
    ];

    public function __construct(protected SessionInterface $session)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->isReading($request) || $this->inExceptArray($request)) {
            return $handler->handle($request);
        }

        $token = $this->getTokenFromRequest($request);

        if (!is_string($this->session->token()) || !is_string($token) || !hash_equals($this->session->token(), $token)) {
            // Ném ra một exception, ExceptionHandler sẽ xử lý và trả về response 419
            throw new \App\Exceptions\TokenMismatchException('CSRF token mismatch.');
        }

        return $handler->handle($request);
    }

    protected function isReading(ServerRequestInterface $request): bool
    {
        return in_array($request->getMethod(), ['HEAD', 'GET', 'OPTIONS']);
    }

    protected function getTokenFromRequest(ServerRequestInterface $request): ?string
    {
        $body = $request->getParsedBody();
        $token = $body['_token'] ?? null;

        if (!$token) {
            $token = $request->getHeaderLine('X-CSRF-TOKEN');
        }

        return $token;
    }

    protected function inExceptArray(ServerRequestInterface $request): bool
    {
        $path = trim($request->getUri()->getPath(), '/');

        foreach ($this->except as $except) {
            $except = trim($except, '/');
            if ($except === $path) {
                return true;
            }
            if (str_ends_with($except, '/*') && str_starts_with($path, rtrim($except, '/*'))) {
                return true;
            }
        }
        return false;
    }
}
