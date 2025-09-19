<?php

namespace Modules\Admin\Http\Controllers;

use Core\Http\Controller;
use Core\Routing\Attributes\Route;
use Core\Services\HealthCheckService;
use Http\JsonResponse;

class HealthController extends Controller
{
    public function __construct(private HealthCheckService $healthCheckService)
    {
    }

    #[Route('/admin/server/health', method: 'GET')]
    public function index(): JsonResponse
    {
        $healthData = $this->healthCheckService->runChecks();

        return response(view('admin::server.health', ['healthData' => $healthData]));
    }
}
