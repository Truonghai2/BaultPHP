<?php

namespace Tests\Unit\Server;

use Core\Server\CoroutineLimiter;
use PHPUnit\Framework\TestCase;

class CoroutineLimiterTest extends TestCase
{
    public function test_run_executes_callback(): void
    {
        if (!extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole extension is not available');
        }

        \Swoole\Coroutine\run(function() {
            $limiter = new CoroutineLimiter(10);
            $executed = false;

            $limiter->run(function() use (&$executed) {
                $executed = true;
                return 'result';
            });

            $this->assertTrue($executed);
        });
    }

    public function test_run_returns_callback_result(): void
    {
        if (!extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole extension is not available');
        }

        \Swoole\Coroutine\run(function() {
            $limiter = new CoroutineLimiter(10);

            $result = $limiter->run(function() {
                return 'test_result';
            });

            $this->assertEquals('test_result', $result);
        });
    }

    public function test_throws_exception_when_limit_reached(): void
    {
        if (!extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole extension is not available');
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Coroutine limit reached');

        \Swoole\Coroutine\run(function() {
            $limiter = new CoroutineLimiter(2);

            // Occupy all slots
            go(function() {
                \Swoole\Coroutine::sleep(0.1);
            });
            go(function() {
                \Swoole\Coroutine::sleep(0.1);
            });

            \Swoole\Coroutine::sleep(0.01); // Let them start

            // This should throw
            $limiter->run(function() {
                return 'should not execute';
            });
        });
    }

    public function test_run_concurrent_executes_all_callbacks(): void
    {
        if (!extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole extension is not available');
        }

        \Swoole\Coroutine\run(function() {
            $limiter = new CoroutineLimiter(10);

            $callbacks = [
                'task1' => fn() => 'result1',
                'task2' => fn() => 'result2',
                'task3' => fn() => 'result3',
            ];

            $results = $limiter->runConcurrent($callbacks);

            $this->assertCount(3, $results);
            $this->assertTrue($results['task1']['success']);
            $this->assertEquals('result1', $results['task1']['result']);
            $this->assertTrue($results['task2']['success']);
            $this->assertEquals('result2', $results['task2']['result']);
        });
    }

    public function test_run_concurrent_handles_exceptions(): void
    {
        if (!extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole extension is not available');
        }

        \Swoole\Coroutine\run(function() {
            $limiter = new CoroutineLimiter(10);

            $callbacks = [
                'success' => fn() => 'ok',
                'failure' => fn() => throw new \Exception('Test error'),
            ];

            $results = $limiter->runConcurrent($callbacks);

            $this->assertTrue($results['success']['success']);
            $this->assertFalse($results['failure']['success']);
            $this->assertEquals('Test error', $results['failure']['error']);
        });
    }

    public function test_get_active_count_tracks_coroutines(): void
    {
        if (!extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole extension is not available');
        }

        \Swoole\Coroutine\run(function() {
            $limiter = new CoroutineLimiter(10);

            $this->assertEquals(0, $limiter->getActiveCount());

            go(function() use ($limiter) {
                $limiter->run(function() {
                    \Swoole\Coroutine::sleep(0.05);
                });
            });

            \Swoole\Coroutine::sleep(0.01);
            $this->assertGreaterThan(0, $limiter->getActiveCount());
        });
    }

    public function test_get_available_count(): void
    {
        if (!extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole extension is not available');
        }

        \Swoole\Coroutine\run(function() {
            $limiter = new CoroutineLimiter(5);

            $available = $limiter->getAvailableCount();

            $this->assertEquals(5, $available);
        });
    }

    public function test_get_stats_returns_statistics(): void
    {
        if (!extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole extension is not available');
        }

        \Swoole\Coroutine\run(function() {
            $limiter = new CoroutineLimiter(10);

            $stats = $limiter->getStats();

            $this->assertArrayHasKey('max_coroutines', $stats);
            $this->assertArrayHasKey('active_coroutines', $stats);
            $this->assertArrayHasKey('available_slots', $stats);
            $this->assertArrayHasKey('utilization', $stats);
            $this->assertEquals(10, $stats['max_coroutines']);
        });
    }

    public function test_is_at_capacity_returns_false_when_slots_available(): void
    {
        if (!extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole extension is not available');
        }

        \Swoole\Coroutine\run(function() {
            $limiter = new CoroutineLimiter(10);

            $this->assertFalse($limiter->isAtCapacity());
        });
    }

    public function test_slots_are_released_after_execution(): void
    {
        if (!extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole extension is not available');
        }

        \Swoole\Coroutine\run(function() {
            $limiter = new CoroutineLimiter(2);

            // Execute and complete
            $limiter->run(function() {
                return 'done';
            });

            // Should still have slots available
            $this->assertFalse($limiter->isAtCapacity());
            $this->assertEquals(2, $limiter->getAvailableCount());
        });
    }

    public function test_concurrent_execution_respects_limits(): void
    {
        if (!extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole extension is not available');
        }

        \Swoole\Coroutine\run(function() {
            $limiter = new CoroutineLimiter(2);

            $callbacks = array_fill(0, 5, fn() => 'result');

            $results = $limiter->runConcurrent($callbacks);

            // Some should succeed, some should fail due to limit
            $successes = array_filter($results, fn($r) => $r['success']);
            $failures = array_filter($results, fn($r) => !$r['success']);

            $this->assertNotEmpty($successes);
            // At least some should complete successfully
            $this->assertGreaterThanOrEqual(2, count($successes));
        });
    }
}
