<?php

namespace Http\Controllers;

use Http\JsonResponse;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Livewire\Mechanisms\HandleRequests\HandleRequests;

class LivewireController
{
    /**
     * Handle Livewire's internal requests.
     */
    public function __invoke(Request $request): JsonResponse
    {
        // Create a new instance of Livewire's core request handler.
        $handler = new HandleRequests();

        // Convert the framework's request to a PSR-7 request that Livewire understands.
        $psr7Request = $request->toPsr7();

        // Let Livewire handle the request and get a PSR-7 response.
        $psr7Response = $handler->handle($psr7Request);

        // Convert the PSR-7 response back to the framework's response.
        return Response::fromPsr7($psr7Response);
    }
}

