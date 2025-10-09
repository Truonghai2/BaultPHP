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

    protected array $except = [];

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
        $headers = $response->getHeaders();

        foreach ($headers['Set-Cookie'] ?? [] as $key => $cookieHeader) {
            $cookie = Cookie::fromString($cookieHeader);

            if (in_array($cookie->getName(), $this->except, true)) {
                continue;
            }

            $encryptedValue = $this->encrypter->encrypt($cookie->getValue(), true);

            $encryptedCookie = $cookie->withValue($encryptedValue);

            $headers['Set-Cookie'][$key] = (string) $encryptedCookie;
        }

        return $response->withHeader('Set-Cookie', $headers['Set-Cookie']);
    }
}
