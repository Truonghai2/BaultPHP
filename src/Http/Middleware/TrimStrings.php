<?php

namespace App\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class TrimStrings implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $body = $request->getParsedBody();

        if (is_array($body)) {
            $request = $request->withParsedBody($this->trim($body));
        }

        return $handler->handle($request);
    }

    protected function trim(array $data): array
    {
        return array_map(function ($value) {
            if (is_array($value)) {
                return $this->trim($value);
            }

            return is_string($value) ? trim($value) : $value;
        }, $data);
    }
}
