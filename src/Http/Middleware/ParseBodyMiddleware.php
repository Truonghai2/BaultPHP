<?php

namespace App\Http\Middleware;

use Core\Application;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware to parse the request body for POST, PUT, PATCH requests.
 * This ensures that the PSR-7 request object has its parsed body
 * available for the rest of the application.
 */
class ParseBodyMiddleware implements MiddlewareInterface
{
    protected Application $app;

    /**
     * Inject the application container to access Swoole-specific services.
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $method = $request->getMethod();

        if (!in_array($method, ['POST', 'PUT', 'PATCH']) || $request->getParsedBody()) {
            return $handler->handle($request);
        }

        $contentType = $request->getHeaderLine('Content-Type');
        $parsedBody = null;

        if ($this->app->has(\Swoole\Http\Request::class)) {
            /** @var \Swoole\Http\Request $swooleRequest */
            $swooleRequest = $this->app->get(\Swoole\Http\Request::class);

            if (str_contains($contentType, 'application/json')) {
                $rawContent = $swooleRequest->rawContent();
                if ($rawContent) {
                    $jsonBody = json_decode($rawContent, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $parsedBody = $jsonBody;
                    }
                }
            } elseif (str_contains($contentType, 'application/x-www-form-urlencoded') || str_contains($contentType, 'multipart/form-data')) {
                $parsedBody = $swooleRequest->post ?? null;
                if (empty($parsedBody) && ($rawContent = $swooleRequest->rawContent())) {
                    parse_str($rawContent, $parsedBody);
                }
            }
        } else {
            $body = $request->getBody();
            if ($body->isReadable()) {
                $bodyContents = $body->getContents();
                if ($bodyContents) {
                    $parsedData = [];
                    if (str_contains($contentType, 'application/json')) {
                        $jsonBody = json_decode($bodyContents, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $parsedData = $jsonBody;
                        }
                    } else {
                        parse_str($bodyContents, $parsedData);
                    }
                    $parsedBody = $parsedData;
                }

                if ($body->isSeekable()) {
                    $body->rewind();
                }
            }
        }

        if ($parsedBody !== null) {
            $request = $request->withParsedBody((array) $parsedBody);
        }

        return $handler->handle($request);
    }
}
