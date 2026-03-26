<?php

namespace Tests\Unit\Server;

use Core\Server\WorkerHealthMonitor;
use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class WorkerHealthMonitorTest extends TestCase
{
    private WorkerHealthMonitor $monitor;
    private LoggerInterface $logger;
    private $server;

    protected function setUp(): void
    {
        parent::setUp();
        
        if (!extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole extension is not available');
        }

        $this->logger = Mockery::mock(LoggerInterface::class);
        $this->logger->shouldReceive('debug')->andReturnNull();
        $this->logger->shouldReceive('info')->andReturnNull();
        $this->logger->shouldReceive('warning')->andReturnNull();
        $this->logger->shouldReceive('error')->andReturnNull();
        
        // Mock Swoole server
        $this->server = Mockery::mock(\Swoole\Http\Server::class);
        $this->server->shouldReceive('reload')->andReturnNull();
        $this->server->shouldReceive('stats')->andReturn([
            'worker_num' => 4,
            'task_worker_num' => 4,
        ]);
        
        $this->monitor = new WorkerHealthMonitor($this->server, $this->logger);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_record_worker_start(): void
    {
        $this->monitor->recordWorkerStart(1);

        $metrics = $this->monitor->getWorkerMetrics(1);

        $this->assertNotNull($metrics);
        $this->assertEquals('healthy', $metrics['status']);
        $this->assertEquals(0, $metrics['requests_handled']);
    }

    public function test_record_request_updates_metrics(): void
    {
        $this->monitor->recordWorkerStart(1);
        
        $this->monitor->recordRequest(1, [
            'duration' => 100,
            'memory' => 10 * 1024 * 1024, // 10MB
            'peak_memory' => 12 * 1024 * 1024, // 12MB
        ]);

        $metrics = $this->monitor->getWorkerMetrics(1);

        $this->assertEquals(1, $metrics['requests_handled']);
        $this->assertEquals(100, $metrics['avg_response_time']);
        $this->assertEquals(10, $metrics['avg_memory_mb']);
    }

    public function test_calculate_average_response_time(): void
    {
        $this->monitor->recordWorkerStart(1);
        
        $this->monitor->recordRequest(1, ['duration' => 100]);
        $this->monitor->recordRequest(1, ['duration' => 200]);
        $this->monitor->recordRequest(1, ['duration' => 300]);

        $metrics = $this->monitor->getWorkerMetrics(1);

        $this->assertEquals(200, $metrics['avg_response_time']);
    }

    public function test_calculate_average_memory_usage(): void
    {
        $this->monitor->recordWorkerStart(1);
        
        $this->monitor->recordRequest(1, ['memory' => 10 * 1024 * 1024]); // 10MB
        $this->monitor->recordRequest(1, ['memory' => 20 * 1024 * 1024]); // 20MB
        $this->monitor->recordRequest(1, ['memory' => 30 * 1024 * 1024]); // 30MB

        $metrics = $this->monitor->getWorkerMetrics(1);

        $this->assertEquals(20, $metrics['avg_memory_mb']);
    }

    public function test_track_peak_memory(): void
    {
        $this->monitor->recordWorkerStart(1);
        
        $this->monitor->recordRequest(1, ['peak_memory' => 10 * 1024 * 1024]); // 10MB
        $this->monitor->recordRequest(1, ['peak_memory' => 25 * 1024 * 1024]); // 25MB (peak)
        $this->monitor->recordRequest(1, ['peak_memory' => 15 * 1024 * 1024]); // 15MB

        $metrics = $this->monitor->getWorkerMetrics(1);

        $this->assertEquals(25, $metrics['peak_memory_mb']);
    }

    public function test_record_error_increments_count(): void
    {
        $this->monitor->recordWorkerStart(1);
        
        $this->monitor->recordError(1, 'Test error');
        $this->monitor->recordError(1, 'Another error');

        $metrics = $this->monitor->getWorkerMetrics(1);

        $this->assertEquals(2, $metrics['errors']);
    }

    public function test_worker_marked_unhealthy_after_error_threshold(): void
    {
        $this->monitor->recordWorkerStart(1);
        
        // Record 6 errors (threshold is typically 5)
        for ($i = 0; $i < 6; $i++) {
            $this->monitor->recordError(1, 'Test error ' . $i);
        }

        $metrics = $this->monitor->getWorkerMetrics(1);

        $this->assertEquals('unhealthy', $metrics['status']);
    }

    public function test_worker_marked_degraded_with_slow_responses(): void
    {
        $this->monitor->recordWorkerStart(1);
        
        // Record 10 slow requests (> 1000ms)
        for ($i = 0; $i < 10; $i++) {
            $this->monitor->recordRequest(1, ['duration' => 1500]);
        }

        $metrics = $this->monitor->getWorkerMetrics(1);

        $this->assertContains($metrics['status'], ['degraded', 'unhealthy']);
    }

    public function test_worker_marked_degraded_with_high_memory(): void
    {
        $this->monitor->recordWorkerStart(1);
        
        // Record requests with high memory usage (> 100MB)
        for ($i = 0; $i < 10; $i++) {
            $this->monitor->recordRequest(1, [
                'memory' => 150 * 1024 * 1024, // 150MB
            ]);
        }

        $metrics = $this->monitor->getWorkerMetrics(1);

        $this->assertContains($metrics['status'], ['degraded', 'unhealthy']);
    }

    public function test_record_worker_stop(): void
    {
        $this->monitor->recordWorkerStart(1);
        $this->monitor->recordRequest(1, ['duration' => 100]);
        
        $this->monitor->recordWorkerStop(1);

        $metrics = $this->monitor->getWorkerMetrics(1);

        $this->assertNull($metrics);
    }

    public function test_get_all_workers_metrics(): void
    {
        $this->monitor->recordWorkerStart(1);
        $this->monitor->recordWorkerStart(2);
        $this->monitor->recordWorkerStart(3);
        
        $this->monitor->recordRequest(1, ['duration' => 100]);
        $this->monitor->recordRequest(2, ['duration' => 200]);

        $allMetrics = $this->monitor->getAllWorkerMetrics();

        $this->assertCount(3, $allMetrics);
        $this->assertArrayHasKey(1, $allMetrics);
        $this->assertArrayHasKey(2, $allMetrics);
        $this->assertArrayHasKey(3, $allMetrics);
    }

    public function test_get_health_summary(): void
    {
        $this->monitor->recordWorkerStart(1);
        $this->monitor->recordWorkerStart(2);
        $this->monitor->recordWorkerStart(3);
        
        $this->monitor->recordRequest(1, ['duration' => 100]);
        
        // Make worker 2 unhealthy
        for ($i = 0; $i < 6; $i++) {
            $this->monitor->recordError(2, 'Error');
        }

        $summary = $this->monitor->getHealthSummary();

        $this->assertArrayHasKey('total_workers', $summary);
        $this->assertArrayHasKey('healthy_workers', $summary);
        $this->assertArrayHasKey('degraded_workers', $summary);
        $this->assertArrayHasKey('unhealthy_workers', $summary);
        $this->assertEquals(3, $summary['total_workers']);
        $this->assertGreaterThanOrEqual(1, $summary['unhealthy_workers']);
    }

    public function test_get_unhealthy_workers(): void
    {
        $this->monitor->recordWorkerStart(1);
        $this->monitor->recordWorkerStart(2);
        
        // Make worker 1 unhealthy
        for ($i = 0; $i < 6; $i++) {
            $this->monitor->recordError(1, 'Error');
        }

        $unhealthy = $this->monitor->getUnhealthyWorkers();

        $this->assertNotEmpty($unhealthy);
        $this->assertArrayHasKey(1, $unhealthy);
    }

    public function test_should_restart_worker_when_unhealthy(): void
    {
        $this->monitor->recordWorkerStart(1);
        
        // Make worker unhealthy
        for ($i = 0; $i < 6; $i++) {
            $this->monitor->recordError(1, 'Error');
        }

        $shouldRestart = $this->monitor->shouldRestartWorker(1);

        $this->assertTrue($shouldRestart);
    }

    public function test_should_not_restart_healthy_worker(): void
    {
        $this->monitor->recordWorkerStart(1);
        $this->monitor->recordRequest(1, ['duration' => 100]);

        $shouldRestart = $this->monitor->shouldRestartWorker(1);

        $this->assertFalse($shouldRestart);
    }

    public function test_reset_worker_metrics(): void
    {
        $this->monitor->recordWorkerStart(1);
        $this->monitor->recordRequest(1, ['duration' => 100]);
        $this->monitor->recordError(1, 'Error');
        
        $this->monitor->resetWorkerMetrics(1);

        $metrics = $this->monitor->getWorkerMetrics(1);

        $this->assertEquals(0, $metrics['requests_handled']);
        $this->assertEquals(0, $metrics['errors']);
        $this->assertEquals('healthy', $metrics['status']);
    }

    public function test_nonexistent_worker_returns_null_metrics(): void
    {
        $metrics = $this->monitor->getWorkerMetrics(999);

        $this->assertNull($metrics);
    }

    public function test_get_worker_uptime(): void
    {
        $this->monitor->recordWorkerStart(1);
        
        usleep(10000); // Sleep 10ms

        $metrics = $this->monitor->getWorkerMetrics(1);

        $this->assertGreaterThan(0, $metrics['uptime_seconds']);
    }

    public function test_calculate_requests_per_second(): void
    {
        $this->monitor->recordWorkerStart(1);
        
        // Record some requests
        for ($i = 0; $i < 10; $i++) {
            $this->monitor->recordRequest(1, ['duration' => 100]);
        }
        
        usleep(100000); // Sleep 100ms

        $metrics = $this->monitor->getWorkerMetrics(1);

        $this->assertGreaterThan(0, $metrics['requests_per_second']);
    }

    public function test_metrics_window_is_limited(): void
    {
        $this->monitor->recordWorkerStart(1);
        
        // Record many requests
        for ($i = 0; $i < 500; $i++) {
            $this->monitor->recordRequest(1, ['duration' => 100]);
        }

        $metrics = $this->monitor->getWorkerMetrics(1);

        // Metrics should be calculated from recent window only
        $this->assertEquals(500, $metrics['requests_handled']);
    }
}
