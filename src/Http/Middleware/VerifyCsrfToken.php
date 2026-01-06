<?php

namespace App\Http\Middleware;

use Core\Security\CsrfManager;
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
        'oauth/token',
    ];

    public function __construct(protected CsrfManager $csrfManager)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->isReading($request) || $this->inExceptArray($request)) {
            return $handler->handle($request);
        }

        $tokenValue = $this->getTokenFromRequest($request);

        if (config('app.debug', false)) {
            $sessionToken = null;
            try {
                $sessionToken = $this->csrfManager->getTokenValue('_token');
            } catch (\Throwable $e) {
                \Core\Support\Facades\Log::error('CSRF: Cannot get session token', [
                    'error' => $e->getMessage()
                ]);
            }
            
            \Core\Support\Facades\Log::debug('CSRF Token Check', [
                'path' => $request->getUri()->getPath(),
                'method' => $request->getMethod(),
                'token_from_request' => $tokenValue ? substr($tokenValue, 0, 10) . '...' : 'null',
                'token_from_session' => $sessionToken ? substr($sessionToken, 0, 10) . '...' : 'null',
                'session_id' => session()->getId(),
                'session_started' => session()->isStarted(),
            ]);
        }

        if (!$this->csrfManager->isTokenValid('_token', $tokenValue)) {
            \Core\Support\Facades\Log::warning('CSRF token mismatch', [
                'path' => $request->getUri()->getPath(),
                'has_token_in_request' => !empty($tokenValue),
                'session_id' => session()->getId(),
            ]);
            
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
