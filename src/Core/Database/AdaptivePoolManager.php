<?php

declare(strict_types=1);

namespace Core\Database;

use Core\Database\Swoole\SwoolePdoPool;
use Psr\Log\LoggerInterface;

/**
 * Automatically adjusts database connection pool size based on utilization.
 */
class AdaptivePoolManager
{
    private int $minPoolSize;
    private int $maxPoolSize;
    private int $currentPoolSize;
    private float $targetUtilization;
    private float $scaleUpThreshold;
    private float $scaleDownThreshold;
    private int $checkInterval; // seconds
    private int $scaleUpAmount;
    private int $scaleDownAmount;
    private array $poolHistory = [];

    public function __construct(
        private readonly LoggerInterface $logger,
        array $config = []
    ) {
        $this->minPoolSize = $config['min_pool_size'] ?? 5;
        $this->maxPoolSize = $config['max_pool_size'] ?? 50;
        $this->currentPoolSize = $config['initial_pool_size'] ?? 10;
        $this->targetUtilization = $config['target_utilization'] ?? 0.75;
        $this->scaleUpThreshold = $config['scale_up_threshold'] ?? 0.85;
        $this->scaleDownThreshold = $config['scale_down_threshold'] ?? 0.30;
        $this->checkInterval = $config['check_interval'] ?? 30;
        $this->scaleUpAmount = $config['scale_up_amount'] ?? 5;
        $this->scaleDownAmount = $config['scale_down_amount'] ?? 3;
    }

    /**
     * Start monitoring and adjusting pool size.
     */
    public function startMonitoring(string $poolName = 'mysql'): void
    {
        if (!class_exists('Swoole\Timer')) {
            $this->logger->warning('Adaptive pool manager requires Swoole. Monitoring disabled.');
            return;
        }

        \Swoole\Timer::tick($this->checkInterval * 1000, function () use ($poolName) {
            $this->adjustPoolSize($poolName);
        });

        $this->logger->info("Adaptive pool manager started for '{$poolName}'", [
            'min_size' => $this->minPoolSize,
            'max_size' => $this->maxPoolSize,
            'target_utilization' => $this->targetUtilization,
        ]);
    }

    /**
     * Check pool utilization and adjust size if needed.
     */
    public function adjustPoolSize(string $poolName): void
    {
        $stats = SwoolePdoPool::stats($poolName);
        
        if (!$stats) {
            $this->logger->warning("Unable to get stats for pool '{$poolName}'");
            return;
        }

        $poolSize = $stats['pool_size'];
        $inUse = $stats['connections_in_use'];
        $idle = $stats['connections_idle'];

        $utilization = $poolSize > 0 ? $inUse / $poolSize : 0;

        // Record history
        $this->poolHistory[] = [
            'timestamp' => time(),
            'pool_size' => $poolSize,
            'in_use' => $inUse,
            'idle' => $idle,
            'utilization' => $utilization,
        ];

        // Keep only last 100 records
        if (count($this->poolHistory) > 100) {
            array_shift($this->poolHistory);
        }

        $this->logger->debug("Pool utilization check", [
            'pool' => $poolName,
            'size' => $poolSize,
            'in_use' => $inUse,
            'idle' => $idle,
            'utilization' => round($utilization * 100, 2) . '%',
        ]);

        // Decision logic
        if ($utilization >= $this->scaleUpThreshold && $poolSize < $this->maxPoolSize) {
            $this->scaleUp($poolName, $poolSize);
        } elseif ($utilization <= $this->scaleDownThreshold && $poolSize > $this->minPoolSize) {
            // Only scale down if consistently low utilization
            if ($this->isConsistentlyLowUtilization()) {
                $this->scaleDown($poolName, $poolSize);
            }
        }
    }

    /**
     * Increase pool size.
     */
    protected function scaleUp(string $poolName, int $currentSize): void
    {
        $newSize = min($this->maxPoolSize, $currentSize + $this->scaleUpAmount);
        
        $this->logger->info("Scaling up pool '{$poolName}'", [
            'from' => $currentSize,
            'to' => $newSize,
        ]);

        // Note: Actually scaling the pool requires recreating it
        // This is a placeholder - actual implementation would need
        // to coordinate with SwoolePdoPool
        
        $this->currentPoolSize = $newSize;
        
        // Emit metric
        $this->emitScalingEvent($poolName, 'up', $currentSize, $newSize);
    }

    /**
     * Decrease pool size.
     */
    protected function scaleDown(string $poolName, int $currentSize): void
    {
        $newSize = max($this->minPoolSize, $currentSize - $this->scaleDownAmount);
        
        $this->logger->info("Scaling down pool '{$poolName}'", [
            'from' => $currentSize,
            'to' => $newSize,
        ]);

        $this->currentPoolSize = $newSize;
        
        // Emit metric
        $this->emitScalingEvent($poolName, 'down', $currentSize, $newSize);
    }

    /**
     * Check if utilization has been consistently low.
     */
    protected function isConsistentlyLowUtilization(int $sampleSize = 5): bool
    {
        if (count($this->poolHistory) < $sampleSize) {
            return false;
        }

        $recentSamples = array_slice($this->poolHistory, -$sampleSize);
        
        foreach ($recentSamples as $sample) {
            if ($sample['utilization'] > $this->scaleDownThreshold) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get pool adjustment recommendations.
     */
    public function getRecommendations(string $poolName): array
    {
        $stats = SwoolePdoPool::stats($poolName);
        
        if (!$stats) {
            return ['error' => 'Unable to get pool stats'];
        }

        $poolSize = $stats['pool_size'];
        $inUse = $stats['connections_in_use'];
        $utilization = $poolSize > 0 ? $inUse / $poolSize : 0;

        $recommendations = [];

        if ($utilization > 0.9) {
            $recommendations[] = [
                'severity' => 'high',
                'message' => "Pool utilization is very high ({$this->formatPercent($utilization)}). Consider increasing pool size.",
                'suggested_action' => "Increase pool size to " . ($poolSize + $this->scaleUpAmount),
            ];
        } elseif ($utilization > 0.75) {
            $recommendations[] = [
                'severity' => 'medium',
                'message' => "Pool utilization is moderate-high ({$this->formatPercent($utilization)}). Monitor closely.",
                'suggested_action' => "Consider increasing pool size if sustained",
            ];
        } elseif ($utilization < 0.2) {
            $recommendations[] = [
                'severity' => 'low',
                'message' => "Pool utilization is low ({$this->formatPercent($utilization)}). Consider reducing pool size.",
                'suggested_action' => "Decrease pool size to " . max($this->minPoolSize, $poolSize - $this->scaleDownAmount),
            ];
        }

        // Check for connection exhaustion patterns
        if ($this->hasExhaustionPattern()) {
            $recommendations[] = [
                'severity' => 'high',
                'message' => "Detected patterns of pool exhaustion. Immediate action recommended.",
                'suggested_action' => "Increase pool size significantly or investigate slow queries",
            ];
        }

        return [
            'current_stats' => $stats,
            'utilization' => $this->formatPercent($utilization),
            'recommendations' => $recommendations,
        ];
    }

    /**
     * Check if there's a pattern of pool exhaustion.
     */
    protected function hasExhaustionPattern(): bool
    {
        if (count($this->poolHistory) < 10) {
            return false;
        }

        $recentSamples = array_slice($this->poolHistory, -10);
        $highUtilizationCount = 0;

        foreach ($recentSamples as $sample) {
            if ($sample['utilization'] >= 0.95) {
                $highUtilizationCount++;
            }
        }

        // If 50% of recent samples show near-exhaustion
        return $highUtilizationCount >= 5;
    }

    /**
     * Get pool history.
     */
    public function getPoolHistory(): array
    {
        return $this->poolHistory;
    }

    /**
     * Emit scaling event for monitoring.
     */
    protected function emitScalingEvent(
        string $poolName,
        string $direction,
        int $oldSize,
        int $newSize
    ): void {
        // This would typically send to a metrics collector
        // For now, just log it
        $this->logger->notice("Pool scaling event", [
            'pool' => $poolName,
            'direction' => $direction,
            'old_size' => $oldSize,
            'new_size' => $newSize,
            'timestamp' => time(),
        ]);
    }

    /**
     * Format percentage for display.
     */
    private function formatPercent(float $value): string
    {
        return round($value * 100, 2) . '%';
    }
}
