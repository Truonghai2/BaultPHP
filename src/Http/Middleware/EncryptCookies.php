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
        'my_unencrypted_cookie',
        'test',
    ];

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
            if (in_array($key, $this->except, true)) {
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

        foreach ($response->getHeader('Set-Cookie') as $cookieHeader) {
            $cookie = Cookie::fromString($cookieHeader);

            if (in_array($cookie->getName(), $this->except, true)) {
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

        // Xóa tất cả các header 'Set-Cookie' cũ và thêm lại các header đã được xử lý
        $response = $response->withoutHeader('Set-Cookie');
        foreach ($cookiesToSet as $cookieString) {
            $response = $response->withAddedHeader('Set-Cookie', $cookieString);
        }

        return $response;
    }
}
