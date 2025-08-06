<?php

namespace Core\Server;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;

/**
 * Class SwoolePsr7Bridge
 *
 * A bridge to convert between Swoole's proprietary HTTP objects
 * and the PSR-7 standard HTTP message objects.
 */
class SwoolePsr7Bridge
{
    private Psr17Factory $psrFactory;

    public function __construct()
    {
        $this->psrFactory = new Psr17Factory();
    }

    /**
     * Convert a Swoole Request to a PSR-7 ServerRequest.
     *
     * @param SwooleRequest $swooleRequest
     * @return ServerRequestInterface
     */
    public function toPsr7Request(SwooleRequest $swooleRequest): ServerRequestInterface
    {
        $serverParams = array_change_key_case($swooleRequest->server, CASE_UPPER);

        $request = $this->psrFactory->createServerRequest(
            $serverParams['REQUEST_METHOD'] ?? 'GET',
            $serverParams['REQUEST_URI'] ?? '/',
            $serverParams
        );

        $request = $request->withQueryParams($swooleRequest->get ?? [])
            ->withParsedBody($swooleRequest->post ?? null)
            ->withCookieParams($swooleRequest->cookie ?? [])
            ->withUploadedFiles($swooleRequest->files ?? []);

        foreach ($swooleRequest->header as $key => $value) {
            $request = $request->withHeader($key, $value);
        }

        $bodyStream = $this->psrFactory->createStream($swooleRequest->rawContent());
        return $request->withBody($bodyStream);
    }

    public function toSwooleResponse(ResponseInterface $psr7Response, SwooleResponse $swooleResponse): void
    {
        $swooleResponse->status($psr7Response->getStatusCode(), $psr7Response->getReasonPhrase());
        foreach ($psr7Response->getHeaders() as $key => $values) {
            $swooleResponse->header($key, implode(', ', $values));
        }
        $swooleResponse->end($psr7Response->getBody()->__toString());
    }
}
