<?php

namespace Tests\Unit\Database\Swoole;

use Core\Database\Swoole\PoolMetrics;
use PHPUnit\Framework\TestCase;

class PoolMetricsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        PoolMetrics::resetAll();
    }

    protected function tearDown(): void
    {
        PoolMetrics::resetAll();
        parent::tearDown();
    }

    public function test_initialize_creates_pool_metrics(): void
    {
        PoolMetrics::initialize('test_pool', 10);

        $metrics = PoolMetrics::getMetrics('test_pool');

        $this->assertNotEmpty($metrics);
        $this->assertEquals(10, $metrics['pool_size']);
        $this->assertEquals(0, $metrics['total_acquires']);
        $this->assertEquals(10, $metrics['idle_connections']);
    }

    public function test_record_connection_acquire_updates_metrics(): void
    {
        PoolMetrics::initialize('test_pool', 10);
        
        PoolMetrics::recordConnectionAcquire('test_pool', 5.5);

        $metrics = PoolMetrics::getMetrics('test_pool');

        $this->assertEquals(1, $metrics['total_acquires']);
        $this->assertEquals(1, $metrics['active_connections']);
        $this->assertEquals(9, $metrics['idle_connections']);
        $this->assertEquals(5.5, $metrics['max_wait_time_ms']);
    }

    public function test_record_connection_release_updates_metrics(): void
    {
        PoolMetrics::initialize('test_pool', 10);
        PoolMetrics::recordConnectionAcquire('test_pool', 0);

        PoolMetrics::recordConnectionRelease('test_pool');

        $metrics = PoolMetrics::getMetrics('test_pool');

        $this->assertEquals(1, $metrics['total_releases']);
        $this->assertEquals(0, $metrics['active_connections']);
        $this->assertEquals(10, $metrics['idle_connections']);
    }

    public function test_record_pool_exhaustion_increments_count(): void
    {
        PoolMetrics::initialize('test_pool', 10);

        PoolMetrics::recordPoolExhaustion('test_pool');
        PoolMetrics::recordPoolExhaustion('test_pool');

        $metrics = PoolMetrics::getMetrics('test_pool');

        $this->assertEquals(2, $metrics['exhaustion_count']);
        $this->assertNotNull($metrics['last_exhaustion_time']);
    }

    public function test_record_error_increments_count(): void
    {
        PoolMetrics::initialize('test_pool', 10);

        PoolMetrics::recordError('test_pool');
        PoolMetrics::recordError('test_pool');
        PoolMetrics::recordError('test_pool');

        $metrics = PoolMetrics::getMetrics('test_pool');

        $this->assertEquals(3, $metrics['error_count']);
    }

    public function test_record_circuit_breaker_trip(): void
    {
        PoolMetrics::initialize('test_pool', 10);

        PoolMetrics::recordCircuitBreakerTrip('test_pool');

        $metrics = PoolMetrics::getMetrics('test_pool');

        $this->assertEquals(1, $metrics['circuit_breaker_trips']);
    }

    public function test_update_wait_queue_length(): void
    {
        PoolMetrics::initialize('test_pool', 10);

        PoolMetrics::updateWaitQueueLength('test_pool', 5);

        $metrics = PoolMetrics::getMetrics('test_pool');

        $this->assertEquals(5, $metrics['wait_queue_length']);
    }

    public function test_calculates_average_wait_time(): void
    {
        PoolMetrics::initialize('test_pool', 10);

        PoolMetrics::recordConnectionAcquire('test_pool', 10.0);
        PoolMetrics::recordConnectionAcquire('test_pool', 20.0);
        PoolMetrics::recordConnectionAcquire('test_pool', 30.0);

        $metrics = PoolMetrics::getMetrics('test_pool');

        $this->assertEquals(20.0, $metrics['avg_wait_time_ms']);
    }

    public function test_calculates_utilization(): void
    {
        PoolMetrics::initialize('test_pool', 10);

        // Acquire 7 connections
        for ($i = 0; $i < 7; $i++) {
            PoolMetrics::recordConnectionAcquire('test_pool', 0);
        }

        $metrics = PoolMetrics::getMetrics('test_pool');

        $this->assertEquals(0.7, $metrics['utilization']);
    }

    public function test_health_status_healthy(): void
    {
        PoolMetrics::initialize('test_pool', 10);

        // Low utilization, no errors
        PoolMetrics::recordConnectionAcquire('test_pool', 5.0);

        $health = PoolMetrics::getHealthStatus('test_pool');

        $this->assertEquals('healthy', $health);
    }

    public function test_health_status_degraded_high_utilization(): void
    {
        PoolMetrics::initialize('test_pool', 10);

        // 9 out of 10 connections (90% utilization)
        for ($i = 0; $i < 9; $i++) {
            PoolMetrics::recordConnectionAcquire('test_pool', 0);
        }

        $health = PoolMetrics::getHealthStatus('test_pool');

        $this->assertEquals('degraded', $health);
    }

    public function test_health_status_unhealthy_saturated(): void
    {
        PoolMetrics::initialize('test_pool', 10);

        // All 10 connections (100% utilization)
        for ($i = 0; $i < 10; $i++) {
            PoolMetrics::recordConnectionAcquire('test_pool', 0);
        }

        $health = PoolMetrics::getHealthStatus('test_pool');

        $this->assertEquals('unhealthy', $health);
    }

    public function test_health_status_unhealthy_circuit_breaker(): void
    {
        PoolMetrics::initialize('test_pool', 10);

        PoolMetrics::recordCircuitBreakerTrip('test_pool');

        $health = PoolMetrics::getHealthStatus('test_pool');

        $this->assertEquals('unhealthy', $health);
    }

    public function test_get_all_metrics_returns_all_pools(): void
    {
        PoolMetrics::initialize('pool1', 10);
        PoolMetrics::initialize('pool2', 20);

        $allMetrics = PoolMetrics::getAllMetrics();

        $this->assertCount(2, $allMetrics);
        $this->assertArrayHasKey('pool1', $allMetrics);
        $this->assertArrayHasKey('pool2', $allMetrics);
    }

    public function test_reset_reinitializes_pool_metrics(): void
    {
        PoolMetrics::initialize('test_pool', 10);
        PoolMetrics::recordConnectionAcquire('test_pool', 10.0);
        PoolMetrics::recordError('test_pool');

        PoolMetrics::reset('test_pool');

        $metrics = PoolMetrics::getMetrics('test_pool');

        $this->assertEquals(0, $metrics['total_acquires']);
        $this->assertEquals(0, $metrics['error_count']);
        $this->assertEquals(10, $metrics['pool_size']);
    }

    public function test_metrics_for_nonexistent_pool_returns_empty(): void
    {
        $metrics = PoolMetrics::getMetrics('nonexistent_pool');

        $this->assertEmpty($metrics);
    }

    public function test_is_saturated_flag(): void
    {
        PoolMetrics::initialize('test_pool', 10);

        // Not saturated
        for ($i = 0; $i < 8; $i++) {
            PoolMetrics::recordConnectionAcquire('test_pool', 0);
        }
        $this->assertFalse(PoolMetrics::getMetrics('test_pool')['is_saturated']);

        // Saturated
        for ($i = 0; $i < 2; $i++) {
            PoolMetrics::recordConnectionAcquire('test_pool', 0);
        }
        $this->assertTrue(PoolMetrics::getMetrics('test_pool')['is_saturated']);
    }

    public function test_is_underutilized_flag(): void
    {
        PoolMetrics::initialize('test_pool', 10);

        // Underutilized (2 out of 10 = 20%)
        for ($i = 0; $i < 2; $i++) {
            PoolMetrics::recordConnectionAcquire('test_pool', 0);
        }

        $metrics = PoolMetrics::getMetrics('test_pool');

        $this->assertTrue($metrics['is_underutilized']);
    }
}
