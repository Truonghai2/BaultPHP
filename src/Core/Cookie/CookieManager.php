<?php

namespace Core\Cookie;

use Core\Contracts\StatefulService;
use Core\Encryption\Encrypter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\HttpFoundation\Cookie;

class CookieManager implements StatefulService
{
    protected Encrypter $encrypter;
    protected array $queued = [];
    protected array $config;

    public function __construct(Encrypter $encrypter)
    {
        $this->encrypter = $encrypter;
        $this->config = config('session');
    }

    /**
     * Get a cookie value from the request.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        /** @var ServerRequestInterface $request */
        $request = app(ServerRequestInterface::class);
        $cookies = $request->getCookieParams();

        return $cookies[$key] ?? $default;
    }

    /**
     * Check if a cookie exists on the request.
     */
    public function has(string $key): bool
    {
        /** @var ServerRequestInterface $request */
        $request = app(ServerRequestInterface::class);
        return array_key_exists($key, $request->getCookieParams());
    }

    /**
     * Queue a cookie to be sent with the response.
     */
    public function queue(
        string $name,
        string $value,
        int $minutes = 0,
        ?string $path = null,
        ?string $domain = null,
        ?bool $secure = null,
        bool $httpOnly = true,
        bool $raw = false,
        ?string $sameSite = null,
    ): void {
        $value = $raw ? $value : $this->encrypter->encrypt($value);

        $this->queued[$name] = new Cookie(
            $name,
            $value,
            $minutes === 0 ? 0 : time() + ($minutes * 60),
            $path ?? $this->config['path'] ?? '/',
            $domain ?? $this->config['domain'] ?? null,
            $secure ?? $this->config['secure'] ?? false,
            $httpOnly,
            $raw,
            $sameSite ?? $this->config['same_site'] ?? 'lax',
        );
    }

    /**
     * Queue a cookie to be forgotten (expired).
     */
    public function forget(string $name, ?string $path = null, ?string $domain = null): void
    {
        $this->queue($name, '', -2628000, $path, $domain);
    }

    /**
     * Add all queued cookies to the given response.
     */
    public function addQueuedCookiesToResponse(ResponseInterface $response): ResponseInterface
    {
        foreach ($this->queued as $cookie) {
            $response = $response->withAddedHeader('Set-Cookie', (string) $cookie);
        }

        return $response;
    }

    /**
     * Reset the queued cookies for the next request.
     */
    public function resetState(): void
    {
        $this->queued = [];
    }
}
