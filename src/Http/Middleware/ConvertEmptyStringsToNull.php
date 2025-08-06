<?php

namespace Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ConvertEmptyStringsToNull implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $body = $request->getParsedBody();

        if (is_array($body)) {
            $request = $request->withParsedBody($this->convert($body));
        }

        return $handler->handle($request);
    }

    protected function convert(array $data): array
    {
        return array_map(function ($value) {
            if (is_array($value)) {
                return $this->convert($value);
            }

            return $value === '' ? null : $value;
        }, $data);
    }
}