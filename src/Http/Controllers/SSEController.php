<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\ResponseFactory;
use Core\Realtime\SSEStream;
use Core\Routing\Attributes\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Server-Sent Events Controller
 *
 * Handles SSE connections for real-time streaming.
 */
class SSEController
{
    public function __construct(
        private readonly SSEStream $sseStream,
        private readonly ResponseFactory $responseFactory,
    ) {
    }

    /**
     * Stream SSE events
     */
    #[Route('/sse/{channel}', method: 'GET')]
    public function stream(ServerRequestInterface $request, string $channel): ResponseInterface
    {
        $response = $this->responseFactory->make('', 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);

        // Optional: Add filter based on query params
        $filter = null;
        if ($request->getQueryParams()['filter'] ?? null) {
            $filterType = $request->getQueryParams()['filter'];
            $filter = function ($event) use ($filterType) {
                return ($event['type'] ?? '') === $filterType;
            };
        }

        return $this->sseStream->stream($response, $channel, $filter);
    }

    /**
     * Publish event to SSE channel
     */
    #[Route('/sse/{channel}/publish', method: 'POST')]
    public function publish(ServerRequestInterface $request, string $channel): ResponseInterface
    {
        $body = json_decode((string) $request->getBody(), true);
        
        $type = $body['type'] ?? 'message';
        $data = $body['data'] ?? [];

        $this->sseStream->publish($channel, $type, $data);

        return $this->responseFactory->json([
            'status' => 'published',
            'channel' => $channel,
            'type' => $type,
        ]);
    }

    /**
     * Get SSE statistics
     */
    #[Route('/sse/stats', method: 'GET')]
    public function stats(): ResponseInterface
    {
        $stats = $this->sseStream->getStats();

        return $this->responseFactory->json($stats);
    }
}
