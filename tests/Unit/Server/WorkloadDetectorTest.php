<?php

namespace Tests\Unit\Server;

use Core\Server\WorkloadDetector;
use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class WorkloadDetectorTest extends TestCase
{
    private WorkloadDetector $detector;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = Mockery::mock(LoggerInterface::class);
        $this->logger->shouldReceive('debug')->andReturnNull();
        $this->logger->shouldReceive('info')->andReturnNull();
        
        $this->detector = new WorkloadDetector($this->logger);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_detect_io_bound_workload(): void
    {
        // Record 150 samples with high I/O ratio
        for ($i = 0; $i < 150; $i++) {
            $this->detector->recordRequest([
                'total_time' => 1.0,
                'io_time' => 0.8, // 80% I/O
                'cpu_time' => 0.2,
            ]);
        }

        $type = $this->detector->detectWorkloadType();

        $this->assertEquals('io_bound', $type);
    }

    public function test_detect_cpu_bound_workload(): void
    {
        // Record 150 samples with high CPU ratio
        for ($i = 0; $i < 150; $i++) {
            $this->detector->recordRequest([
                'total_time' => 1.0,
                'io_time' => 0.2,
                'cpu_time' => 0.8, // 80% CPU
            ]);
        }

        $type = $this->detector->detectWorkloadType();

        $this->assertEquals('cpu_bound', $type);
    }

    public function test_detect_mixed_workload(): void
    {
        // Record 150 samples with balanced ratio
        for ($i = 0; $i < 150; $i++) {
            $this->detector->recordRequest([
                'total_time' => 1.0,
                'io_time' => 0.5, // 50% I/O
                'cpu_time' => 0.5, // 50% CPU
            ]);
        }

        $type = $this->detector->detectWorkloadType();

        $this->assertEquals('mixed', $type);
    }

    public function test_returns_mixed_with_insufficient_samples(): void
    {
        // Record only 50 samples (less than 100 required)
        for ($i = 0; $i < 50; $i++) {
            $this->detector->recordRequest([
                'total_time' => 1.0,
                'io_time' => 0.9,
                'cpu_time' => 0.1,
            ]);
        }

        $type = $this->detector->detectWorkloadType();

        $this->assertEquals('mixed', $type);
    }

    public function test_calculate_optimal_worker_num_for_io_bound(): void
    {
        if (!function_exists('swoole_cpu_num')) {
            $this->markTestSkipped('Swoole extension is not available');
        }

        for ($i = 0; $i < 150; $i++) {
            $this->detector->recordRequest([
                'total_time' => 1.0,
                'io_time' => 0.8,
                'cpu_time' => 0.2,
            ]);
        }

        $workerNum = $this->detector->calculateOptimalWorkerNum();
        $expectedMin = swoole_cpu_num() * 4;

        $this->assertGreaterThanOrEqual($expectedMin, $workerNum);
    }

    public function test_calculate_optimal_worker_num_for_cpu_bound(): void
    {
        if (!function_exists('swoole_cpu_num')) {
            $this->markTestSkipped('Swoole extension is not available');
        }

        for ($i = 0; $i < 150; $i++) {
            $this->detector->recordRequest([
                'total_time' => 1.0,
                'io_time' => 0.2,
                'cpu_time' => 0.8,
            ]);
        }

        $workerNum = $this->detector->calculateOptimalWorkerNum();
        $expected = swoole_cpu_num();

        $this->assertEquals($expected, $workerNum);
    }

    public function test_calculate_optimal_worker_num_for_mixed(): void
    {
        if (!function_exists('swoole_cpu_num')) {
            $this->markTestSkipped('Swoole extension is not available');
        }

        for ($i = 0; $i < 150; $i++) {
            $this->detector->recordRequest([
                'total_time' => 1.0,
                'io_time' => 0.5,
                'cpu_time' => 0.5,
            ]);
        }

        $workerNum = $this->detector->calculateOptimalWorkerNum();
        $expectedMin = swoole_cpu_num() * 2;

        $this->assertGreaterThanOrEqual($expectedMin, $workerNum);
    }

    public function test_get_metrics_returns_summary(): void
    {
        if (!function_exists('swoole_cpu_num')) {
            $this->markTestSkipped('Swoole extension is not available');
        }

        for ($i = 0; $i < 100; $i++) {
            $this->detector->recordRequest([
                'total_time' => 1.0,
                'io_time' => 0.6,
                'cpu_time' => 0.4,
                'db_queries' => 5,
                'redis_ops' => 10,
                'http_calls' => 2,
            ]);
        }

        $metrics = $this->detector->getMetrics();

        $this->assertArrayHasKey('workload_type', $metrics);
        $this->assertArrayHasKey('io_ratio', $metrics);
        $this->assertArrayHasKey('cpu_ratio', $metrics);
        $this->assertArrayHasKey('sample_count', $metrics);
        $this->assertArrayHasKey('optimal_workers', $metrics);
        $this->assertArrayHasKey('avg_response_time', $metrics);
        $this->assertArrayHasKey('avg_db_queries', $metrics);
        $this->assertEquals(100, $metrics['sample_count']);
    }

    public function test_reset_clears_all_metrics(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $this->detector->recordRequest([
                'total_time' => 1.0,
                'io_time' => 0.6,
                'cpu_time' => 0.4,
            ]);
        }

        $this->detector->reset();
        $metrics = $this->detector->getMetrics();

        $this->assertEquals('unknown', $metrics['workload_type']);
        $this->assertEquals(0, $metrics['sample_count']);
    }

    public function test_metrics_window_is_limited_to_max_samples(): void
    {
        if (!function_exists('swoole_cpu_num')) {
            $this->markTestSkipped('Swoole extension is not available');
        }

        // Record more than MAX_SAMPLES (1000)
        for ($i = 0; $i < 1500; $i++) {
            $this->detector->recordRequest([
                'total_time' => 1.0,
                'io_time' => 0.6,
                'cpu_time' => 0.4,
            ]);
        }

        $metrics = $this->detector->getMetrics();

        // Sample count should be capped
        $this->assertLessThanOrEqual(1500, $metrics['sample_count']);
    }

    public function test_estimate_io_ratio_when_no_explicit_metrics(): void
    {
        // Record with db_queries but no explicit io_time
        for ($i = 0; $i < 100; $i++) {
            $this->detector->recordRequest([
                'db_queries' => 5,
                'redis_ops' => 10,
            ]);
        }

        $type = $this->detector->detectWorkloadType();

        // Should estimate as I/O-bound
        $this->assertContains($type, ['io_bound', 'mixed']);
    }
}
