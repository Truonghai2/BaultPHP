<?php

namespace Tests\Performance;

use Tests\TestCase;

class RequestPerformanceTest extends TestCase
{
    public function test_request_latency_is_acceptable()
    {
        // Define a simple route to benchmark
        $this->app->make('router')->get('/benchmark-test', function () {
            return 'Benchmark Success';
        });

        $start = microtime(true);
        $response = $this->get('/benchmark-test');
        $end = microtime(true);

        $duration = $end - $start;

        $response->assertStatus(200);
        
        echo PHP_EOL . "Request Duration: " . round($duration * 1000, 2) . "ms" . PHP_EOL;

        // Assert response time is under 200ms
        $this->assertLessThan(0.2, $duration, "Request took too long: " . round($duration * 1000, 2) . "ms");
    }

    public function test_json_api_latency()
    {
        $this->app->make('router')->get('/benchmark-api', function () {
             return ['data' => range(1, 100)];
        });

        $start = microtime(true);
        $response = $this->get('/benchmark-api');
        $end = microtime(true);

        $duration = $end - $start;

        $response->assertStatus(200);
        $this->assertLessThan(0.2, $duration, "API Request took too long: " . round($duration * 1000, 2) . "ms");
    }
}
