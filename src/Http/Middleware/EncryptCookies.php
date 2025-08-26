<?php

namespace Http\Middleware;

use Core\Encryption\Encrypter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

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
        return $handler->handle($this->decrypt($request));
    }

    protected function decrypt(ServerRequestInterface $request): ServerRequestInterface
    {
        $cookies = $request->getCookieParams();

        foreach ($cookies as $key => $cookie) {
            if (in_array($key, $this->except, true)) {
                continue;
            }
            try {
                $cookies[$key] = $this->encrypter->decrypt($cookie, true);
            } catch (\Exception) {
                $cookies[$key] = null;
            }
        }

        return $request->withCookieParams($cookies);
    }
}
