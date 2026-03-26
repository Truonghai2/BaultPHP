<?php

namespace App\Http\Controllers;

use Core\Http\Controller;
use Core\Observability\PrometheusMetrics;
use Core\Routing\Attributes\Route;
use Psr\Http\Message\ResponseInterface;

/**
 * Metrics Endpoint Controller.
 * Exposes Prometheus metrics at /metrics.
 * Group 'light': không session/CSRF – ổn định toàn hệ thống.
 */
#[Route(group: 'light')]
class MetricsController extends Controller
{
    public function __construct(
        private PrometheusMetrics $metrics
    ) {
    }

    /**
     * Expose metrics in Prometheus format.
     * 
     * GET /metrics
     */
    #[Route('/metrics', method: 'GET')]
    public function index(): ResponseInterface
    {
        $metricsData = $this->metrics->render();

        return response($metricsData, 200)
            ->withHeader('Content-Type', 'text/plain; version=0.0.4');
    }
}
