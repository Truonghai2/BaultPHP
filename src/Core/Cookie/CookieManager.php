<?php

namespace Core\Cookie;

use Core\Contracts\StatefulService;
use Core\Encryption\Encrypter;
use Core\Encryption\Exceptions\DecryptException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Cookie;

class CookieManager implements StatefulService
{
    /**
     * Static queue shared across all instances within a request
     * @var array
     */
    protected static array $sharedQueue = [];
    
    protected array $queued = [];
    protected array $config;

    public function __construct(
        protected LoggerInterface $logger,
        protected Encrypter $encrypter,
    ) {
        $this->config = config('session');
    }

    /**
     * Get a decrypted cookie value from the request.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        /** @var ServerRequestInterface $request */
        $request = app(ServerRequestInterface::class);
        $cookies = $request->getCookieParams();

        if (!isset($cookies[$key])) {
            return $default;
        }

        $value = $cookies[$key];

        try {
            return $this->encrypter->decrypt($value);
        } catch (DecryptException $e) {
            $this->logger->warning("Failed to decrypt cookie '{$key}'. The cookie might be invalid or tampered.", [
                'exception' => $e->getMessage(),
            ]);
            return $default;
        }
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
     * The cookie value will be encrypted by default.
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

        $cookie = new Cookie(
            $name,
            $value,
            $minutes === 0 ? 0 : time() + ($minutes * 60),
            $path ?? $this->config['path'] ?? '/',
            $domain ?? $this->config['domain'] ?? null,
            $secure ?? $this->config['secure'] ?? false,
            $httpOnly,
            false, // The value is already encoded by the encrypter, so we don't need raw handling here.
            $sameSite ?? $this->config['same_site'] ?? 'lax',
        );
        
        // Queue to both instance and static shared queue
        $this->queued[$name] = $cookie;
        self::$sharedQueue[$name] = $cookie;
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
        // Use shared queue instead of instance queue
        $queue = self::$sharedQueue;
        
        foreach ($queue as $cookie) {
            $cookieString = (string) $cookie;
            $response = $response->withAddedHeader('Set-Cookie', $cookieString);
        }

        return $response;
    }

    /**
     * Reset the queued cookies for the next request.
     */
    public function resetState(): void
    {
        $this->queued = [];
        self::$sharedQueue = [];
    }
}
