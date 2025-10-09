<?php

namespace Tests\Unit\ORM;

use Core\Application;
use Core\Config\Repository as ConfigRepository;
use Core\ORM\Connection;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Core\ORM\Connection
 */
class ConnectionMemoryLeakTest extends TestCase
{
    private ?Application $app;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Tạo một Application container giả
        $this->app = new Application(dirname(__DIR__, 3));

        $config = new ConfigRepository([
            'database' => [
                'default' => 'sqlite',
                'connections' => [
                    'sqlite' => [
                        'driver' => 'sqlite',
                        'database' => ':memory:',
                    ],
                ],
            ],
        ]);

        $this->app->instance('config', $config);
        $this->app->singleton(Connection::class, function ($app) {
            return new Connection($app);
        });
    }

    protected function tearDown(): void
    {
        $this->app->flush();
        $this->app = null;
        parent::tearDown();
    }

    public function testConnectionMethodDoesNotLeakMemoryInNonPooledEnvironment(): void
    {
        gc_collect_cycles();
        $initialMemory = memory_get_usage();

        $connectionManager = $this->app->make(Connection::class);
        $iterations = 1000;

        for ($i = 0; $i < $iterations; $i++) {
            $pdo = $connectionManager->connection('sqlite');
            unset($pdo);
        }

        gc_collect_cycles();

        $finalMemory = memory_get_usage();
        $memoryDiff = $finalMemory - $initialMemory;

        printf(
            "\nMemory usage: Initial=%.2f KB, Final=%.2f KB, Diff=%.2f KB after %d iterations\n",
            $initialMemory / 1024,
            $finalMemory / 1024,
            $memoryDiff / 1024,
            $iterations,
        );

        $this->assertLessThan(
            256 * 1024,
            $memoryDiff,
            'Memory usage increased significantly, indicating a potential memory leak.',
        );
    }
}
