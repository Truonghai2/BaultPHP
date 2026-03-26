<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Deduplication;

use Core\Application;
use Core\Http\Deduplication\RequestLockManager;
use Core\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Mockery;

class RequestLockManagerTest extends TestCase
{
    protected CacheInterface $cache;
    protected RequestLockManager $lockManager;
    protected Application $app;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app = new Application(dirname(__DIR__, 4));
        Application::setInstance($this->app);
        Facade::setFacadeApplication($this->app);

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('debug')->andReturnNull();
        $this->app->instance('log', $logger);

        $this->cache = Mockery::mock(CacheInterface::class);
        $this->lockManager = new RequestLockManager($this->cache, 5, 100);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_acquire_lock_success(): void
    {
        $signature = 'test-signature-123';
        $lockKey = "lock:{$signature}";

        $this->cache->shouldReceive('has')->with($lockKey)->once()->andReturn(false);
        $this->cache->shouldReceive('set')
            ->with($lockKey, Mockery::type('string'), 5)
            ->once()
            ->andReturn(true);

        $result = $this->lockManager->acquireLock($signature);
        $this->assertTrue($result);
    }

    public function test_acquire_lock_failure_when_already_locked(): void
    {
        $signature = 'test-signature-123';
        $lockKey = "lock:{$signature}";

        $this->cache->shouldReceive('has')->with($lockKey)->once()->andReturn(true);

        $result = $this->lockManager->acquireLock($signature);
        $this->assertFalse($result);
    }

    public function test_release_lock_deletes_when_token_matches(): void
    {
        $signature = 'test-signature-123';
        $lockKey = "lock:{$signature}";
        $token = 'abc123';

        $this->cache->shouldReceive('has')->with($lockKey)->once()->andReturn(false);
        $this->cache->shouldReceive('set')->with($lockKey, Mockery::type('string'), 5)->once()->andReturn(true);
        $this->lockManager->acquireLock($signature);

        $this->cache->shouldReceive('get')->with($lockKey)->once()->andReturn($token);
        $this->cache->shouldReceive('delete')->with($lockKey)->once()->andReturn(true);

        $ref = new \ReflectionClass($this->lockManager);
        $prop = $ref->getProperty('ownedTokens');
        $prop->setAccessible(true);
        $prop->setValue($this->lockManager, [$signature => $token]);

        $this->lockManager->releaseLock($signature);
        $this->assertTrue(true);
    }

    public function test_has_lock_returns_true_when_lock_exists(): void
    {
        $signature = 'test-signature-123';
        $lockKey = "lock:{$signature}";

        $this->cache->shouldReceive('has')->with($lockKey)->once()->andReturn(true);
        $result = $this->lockManager->hasLock($signature);
        $this->assertTrue($result);
    }

    public function test_has_lock_returns_false_when_lock_not_exists(): void
    {
        $signature = 'test-signature-123';
        $lockKey = "lock:{$signature}";

        $this->cache->shouldReceive('has')->with($lockKey)->once()->andReturn(false);
        $result = $this->lockManager->hasLock($signature);
        $this->assertFalse($result);
    }

    public function test_wait_for_lock_returns_true_when_lock_released(): void
    {
        $signature = 'test-signature-123';
        $lockKey = "lock:{$signature}";

        $callCount = 0;
        $this->cache->shouldReceive('has')->with($lockKey)->andReturnUsing(function () use (&$callCount) {
            $callCount++;
            return $callCount === 1;
        });

        $result = $this->lockManager->waitForLock($signature, 1);
        $this->assertTrue($result);
    }

    public function test_wait_for_lock_returns_false_on_timeout(): void
    {
        $signature = 'test-signature-123';
        $lockKey = "lock:{$signature}";

        $this->cache->shouldReceive('has')->with($lockKey)->andReturn(true);
        $result = $this->lockManager->waitForLock($signature, 0);
        $this->assertFalse($result);
    }

    public function test_custom_lock_timeout(): void
    {
        $customTimeout = 10;
        $lockManager = new RequestLockManager($this->cache, $customTimeout, 100);
        $signature = 'test-signature';
        $lockKey = "lock:{$signature}";

        $this->cache->shouldReceive('has')->with($lockKey)->once()->andReturn(false);
        $this->cache->shouldReceive('set')
            ->with($lockKey, Mockery::type('string'), $customTimeout)
            ->once()
            ->andReturn(true);

        $result = $lockManager->acquireLock($signature);
        $this->assertTrue($result);
    }
}
