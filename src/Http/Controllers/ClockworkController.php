<?php

namespace App\Http\Controllers;

use Core\Routing\Attributes\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Clockwork Controller
 * 
 * Serves Clockwork profiling data via HTTP API
 * Routes:
 * - GET /__clockwork/{id} - Get specific request data
 * - GET /__clockwork/latest - Get latest request
 */
class ClockworkController
{
    public function __construct(
        protected $clockwork = null
    ) {
        // Ensure Clockwork is available
        if (!class_exists(\Clockwork\Clockwork::class)) {
            throw new \RuntimeException('Clockwork is not installed. Install it with: composer require --dev itsgoingd/clockwork');
        }
        if ($this->clockwork === null) {
            $this->clockwork = app(\Clockwork\Clockwork::class);
        }
    }

    /**
     * Get data for a specific request ID
     */
    #[Route('/__clockwork/{id}', method: 'GET')]
    public function getData(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $storage = $this->clockwork->getStorage();
        $data = $storage->retrieve($id);

        if (!$data) {
            return response()->json(['error' => 'Request not found'], 404);
        }

        return response()->json($data->toArray());
    }

    /**
     * Get the latest request data
     */
    #[Route('/__clockwork/latest', method: 'GET')]
    public function getLatest(ServerRequestInterface $request): ResponseInterface
    {
        $storage = $this->clockwork->getStorage();
        $latest = $storage->latest();

        if (!$latest) {
            return response()->json(['error' => 'No requests found'], 404);
        }

        return response()->json($latest->toArray());
    }

    /**
     * Get metadata for all requests (list view)
     */
    #[Route('/__clockwork', method: 'GET')]
    public function getIndex(ServerRequestInterface $request): ResponseInterface
    {
        $storage = $this->clockwork->getStorage();
        
        // Get query parameters for filtering
        $queryParams = $request->getQueryParams();
        $limit = (int) ($queryParams['limit'] ?? 10);
        $offset = (int) ($queryParams['offset'] ?? 0);

        $requests = $storage->all();
        
        // Limit and offset
        $requests = array_slice($requests, $offset, $limit);

        // Return metadata only (not full data)
        $metadata = array_map(function ($req) {
            return [
                'id' => $req->id,
                'method' => $req->method,
                'uri' => $req->uri,
                'time' => $req->time,
                'responseStatus' => $req->responseStatus,
                'responseTime' => $req->responseDuration,
            ];
        }, $requests);

        return response()->json($metadata);
    }
}
