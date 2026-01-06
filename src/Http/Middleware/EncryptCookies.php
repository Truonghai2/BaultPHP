<?php

namespace App\Http\Middleware;

use Core\Encryption\Encrypter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\HttpFoundation\Cookie;

class EncryptCookies implements MiddlewareInterface
{
    protected Encrypter $encrypter;

    /**
     * Tên của các cookie không nên được mã hóa.
     *
     * @var array<int, string>
     */
    protected array $except = [
        'bault_session',
        'my_unencrypted_cookie',
        'test',
    ];

    /**
     * Check if cookie name should be excluded from encryption.
     */
    protected function isExcepted(string $name): bool
    {
        // Exact matches
        if (in_array($name, $this->except, true)) {
            return true;
        }

        // Pattern matches - Remember Me cookies
        if (str_starts_with($name, 'remember_')) {
            return true;
        }

        return false;
    }

    public function __construct(Encrypter $encrypter)
    {
        $this->encrypter = $encrypter;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $decryptedRequest = $this->decrypt($request);

        $response = $handler->handle($decryptedRequest);

        return $this->encrypt($response);
    }

    /**
     * Giải mã các cookie trong request đến.
     *
     * @param ServerRequestInterface $request
     * @return ServerRequestInterface
     */
    protected function decrypt(ServerRequestInterface $request): ServerRequestInterface
    {
        $cookies = $request->getCookieParams();

        foreach ($cookies as $key => $cookie) {
            if ($this->isExcepted($key)) {
                continue;
            }
            try {
                $cookies[$key] = $this->encrypter->decrypt($cookie, false);
            } catch (\Core\Encryption\Exceptions\DecryptException) {
                $cookies[$key] = null;
            }
        }

        return $request->withCookieParams($cookies);
    }

    /**
     * Mã hóa các cookie trong response trả về.
     *
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    protected function encrypt(ResponseInterface $response): ResponseInterface
    {
        $cookiesToSet = [];
        $setCookieHeaders = $response->getHeader('Set-Cookie');

        foreach ($setCookieHeaders as $cookieHeader) {
            // Skip if cookieHeader is not a string (defensive programming)
            if (!is_string($cookieHeader)) {
                continue;
            }

            $cookie = Cookie::fromString($cookieHeader);

            if ($this->isExcepted($cookie->getName())) {
                $cookiesToSet[] = (string) $cookie;
                continue;
            }

            $value = $cookie->getValue();
            if ($value === null || $value === '') {
                $encryptedValue = $value;
            } else {
                $encryptedValue = $this->encrypter->encrypt($value, true);
            }

            $encryptedCookie = $cookie->withValue($encryptedValue);
            $cookiesToSet[] = (string) $encryptedCookie;
        }

        $response = $response->withoutHeader('Set-Cookie');
        foreach ($cookiesToSet as $cookieString) {
            $response = $response->withAddedHeader('Set-Cookie', $cookieString);
        }

        return $response;
    }
}
