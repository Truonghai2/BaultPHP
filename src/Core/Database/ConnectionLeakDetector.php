<?php

declare(strict_types=1);

namespace Core\Database;

use Psr\Log\LoggerInterface;
use Swoole\Coroutine;

/**
 * Detects and reports connection leaks in coroutine environments.
 */
class ConnectionLeakDetector
{
    private array $activeConnections = [];
    private int $leakThreshold; // seconds
    private int $warningThreshold; // seconds
    
    public function __construct(
        private readonly LoggerInterface $logger,
        int $leakThreshold = 60,
        int $warningThreshold = 30
    ) {
        $this->leakThreshold = $leakThreshold;
        $this->warningThreshold = $warningThreshold;
    }

    /**
     * Track a connection acquisition.
     */
    public function trackAcquisition(mixed $connection, string $poolName): void
    {
        if (!function_exists('Swoole\Coroutine\getCid')) {
            return; // Not in Swoole environment
        }

        $cid = Coroutine::getCid();
        $connectionId = spl_object_id($connection);
        
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        
        $this->activeConnections[$connectionId] = [
            'connection_id' => $connectionId,
            'pool_name' => $poolName,
            'coroutine_id' => $cid,
            'acquired_at' => microtime(true),
            'backtrace' => $this->formatBacktrace($backtrace),
        ];
    }

    /**
     * Track a connection release.
     */
    public function trackRelease(mixed $connection): void
    {
        $connectionId = spl_object_id($connection);
        unset($this->activeConnections[$connectionId]);
    }

    /**
     * Check for connection leaks.
     */
    public function checkForLeaks(): array
    {
        $now = microtime(true);
        $leaks = [];
        $warnings = [];

        foreach ($this->activeConnections as $connectionId => $info) {
            $holdTime = $now - $info['acquired_at'];
            
            if ($holdTime > $this->leakThreshold) {
                $leaks[] = [
                    'connection_id' => $connectionId,
                    'pool_name' => $info['pool_name'],
                    'coroutine_id' => $info['coroutine_id'],
                    'hold_time_seconds' => round($holdTime, 2),
                    'acquired_at' => date('Y-m-d H:i:s', (int)$info['acquired_at']),
                    'backtrace' => $info['backtrace'],
                ];
                
                $this->logger->error('Connection leak detected', [
                    'connection_id' => $connectionId,
                    'pool' => $info['pool_name'],
                    'hold_time' => round($holdTime, 2) . 's',
                    'coroutine' => $info['coroutine_id'],
                    'backtrace' => $info['backtrace'],
                ]);
            } elseif ($holdTime > $this->warningThreshold) {
                $warnings[] = [
                    'connection_id' => $connectionId,
                    'pool_name' => $info['pool_name'],
                    'hold_time_seconds' => round($holdTime, 2),
                ];
                
                $this->logger->warning('Connection held for extended period', [
                    'connection_id' => $connectionId,
                    'pool' => $info['pool_name'],
                    'hold_time' => round($holdTime, 2) . 's',
                ]);
            }
        }

        return [
            'leaks' => $leaks,
            'warnings' => $warnings,
            'total_active' => count($this->activeConnections),
        ];
    }

    /**
     * Start periodic leak detection.
     */
    public function startMonitoring(int $intervalSeconds = 60): void
    {
        if (!class_exists('Swoole\Timer')) {
            $this->logger->warning('Connection leak detector requires Swoole. Monitoring disabled.');
            return;
        }

        \Swoole\Timer::tick($intervalSeconds * 1000, function () {
            $report = $this->checkForLeaks();
            
            if (!empty($report['leaks'])) {
                $this->logger->critical('Connection leaks detected', [
                    'leak_count' => count($report['leaks']),
                    'warning_count' => count($report['warnings']),
                    'total_active' => $report['total_active'],
                ]);
            }
        });

        $this->logger->info('Connection leak detector started', [
            'interval' => $intervalSeconds,
            'leak_threshold' => $this->leakThreshold,
            'warning_threshold' => $this->warningThreshold,
        ]);
    }

    /**
     * Get current active connections.
     */
    public function getActiveConnections(): array
    {
        $now = microtime(true);
        
        return array_map(function ($info) use ($now) {
            return [
                'connection_id' => $info['connection_id'],
                'pool_name' => $info['pool_name'],
                'coroutine_id' => $info['coroutine_id'],
                'hold_time_seconds' => round($now - $info['acquired_at'], 2),
                'acquired_at' => date('Y-m-d H:i:s', (int)$info['acquired_at']),
            ];
        }, $this->activeConnections);
    }

    /**
     * Get leak detection statistics.
     */
    public function getStats(): array
    {
        $holdTimes = array_map(
            fn($info) => microtime(true) - $info['acquired_at'],
            $this->activeConnections
        );

        sort($holdTimes);

        return [
            'active_connections' => count($this->activeConnections),
            'avg_hold_time' => !empty($holdTimes) ? round(array_sum($holdTimes) / count($holdTimes), 2) : 0,
            'max_hold_time' => !empty($holdTimes) ? round(max($holdTimes), 2) : 0,
            'leak_threshold' => $this->leakThreshold,
            'warning_threshold' => $this->warningThreshold,
        ];
    }

    /**
     * Force release all tracked connections.
     * 
     * WARNING: Use only in emergency situations.
     */
    public function forceReleaseAll(): int
    {
        $count = count($this->activeConnections);
        
        $this->logger->warning('Force releasing all tracked connections', [
            'count' => $count,
        ]);
        
        $this->activeConnections = [];
        
        return $count;
    }

    /**
     * Format backtrace for logging.
     */
    private function formatBacktrace(array $backtrace): string
    {
        $formatted = [];
        
        foreach ($backtrace as $frame) {
            $file = $frame['file'] ?? 'unknown';
            $line = $frame['line'] ?? 0;
            $function = $frame['function'] ?? 'unknown';
            $class = $frame['class'] ?? '';
            $type = $frame['type'] ?? '';
            
            $formatted[] = "{$class}{$type}{$function}() at {$file}:{$line}";
        }
        
        return implode("\n  ", $formatted);
    }
}
