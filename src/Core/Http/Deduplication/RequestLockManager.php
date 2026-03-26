<?php

declare(strict_types=1);

namespace Core\Http\Deduplication;

use Psr\SimpleCache\CacheInterface;

/**
 * Quản lý lock cho request deduplication.
 * Dùng token để chỉ release lock do chính process này tạo.
 */
class RequestLockManager
{
    /** @var array<string, string> signature => token (lock do process này giữ) */
    private array $ownedTokens = [];

    public function __construct(
        protected CacheInterface $cache,
        protected int $lockTimeout = 30,
        protected int $waitInterval = 50, // milliseconds
    ) {
    }

    /**
     * Cố gắng acquire lock cho signature. Ghi nhận token để release đúng chủ.
     *
     * @param string $signature Request signature
     * @return bool True nếu lấy được lock
     */
    public function acquireLock(string $signature): bool
    {
        $lockKey = $this->lockKey($signature);
        $token = $this->generateToken();

        try {
            if ($this->cache->has($lockKey)) {
                return false;
            }
            $this->cache->set($lockKey, $token, $this->lockTimeout);
            $this->ownedTokens[$signature] = $token;
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Release lock chỉ khi value trong cache đúng bằng token của process này.
     */
    public function releaseLock(string $signature): void
    {
        $lockKey = $this->lockKey($signature);
        $token = $this->ownedTokens[$signature] ?? null;
        unset($this->ownedTokens[$signature]);

        try {
            if ($token === null) {
                return;
            }
            $current = $this->cache->get($lockKey);
            if ($current === $token) {
                $this->cache->delete($lockKey);
            }
        } catch (\Throwable) {
            // Ignore
        }
    }

    /**
     * Chờ lock được release (dùng khi mode = coalesce).
     *
     * @param string $signature Request signature
     * @param int $maxWait Giây tối đa chờ
     * @return bool True nếu lock đã được release trong thời gian chờ
     */
    public function waitForLock(string $signature, int $maxWait = 15): bool
    {
        $lockKey = $this->lockKey($signature);
        $deadline = microtime(true) + $maxWait;
        $intervalUs = $this->waitInterval * 1000;

        while (microtime(true) < $deadline) {
            try {
                if (!$this->cache->has($lockKey)) {
                    return true;
                }
            } catch (\Throwable) {
                return false;
            }
            usleep($intervalUs);
        }

        return false;
    }

    public function hasLock(string $signature): bool
    {
        try {
            return $this->cache->has($this->lockKey($signature));
        } catch (\Throwable) {
            return false;
        }
    }

    protected function lockKey(string $signature): string
    {
        return 'lock:' . $signature;
    }

    protected function generateToken(): string
    {
        return bin2hex(random_bytes(16));
    }
}
