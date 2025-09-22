<?php

namespace App\Http\Controllers;

use App\Http\ResponseFactory;
use Core\Routing\Attributes\Route;
use Core\Services\HealthCheckService;
use Psr\Http\Message\ResponseInterface;

class HealthCheckController
{
    private HealthCheckService $healthCheckService;
    private ResponseFactory $responseFactory;

    public function __construct(HealthCheckService $healthCheckService, ResponseFactory $responseFactory)
    {
        $this->healthCheckService = $healthCheckService;
        $this->responseFactory = $responseFactory;
    }

    #[Route('/api/health', method: 'GET')]
    public function __invoke(): ResponseInterface
    {
        $results = $this->healthCheckService->runChecks();

        $httpStatus = $results['status'] === 'UP' ? 200 : 503;

        return $this->responseFactory->json($results, $httpStatus);
    }

    #[Route('/ping', method: 'GET')]
    public function ping(): ResponseInterface
    {
        return $this->responseFactory->json(['status' => 'pong']);
    }
}
