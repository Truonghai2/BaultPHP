<?php

namespace Modules\Admin\Http\Controllers;

use Core\Http\Controller;
use Core\Routing\Attributes\Route;
use Core\Services\HealthCheckService;

class HealthController extends Controller
{
    public function __construct(private HealthCheckService $healthCheckService)
    {
    }

    #[Route('/admin/server/health', method: 'GET', group: 'web')]
    public function index()
    {
        $healthData = $this->healthCheckService->runChecks();

        return response(view('admin::server.health', ['healthData' => $healthData]));
    }
}
