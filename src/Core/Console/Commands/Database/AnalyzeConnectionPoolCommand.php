<?php

declare(strict_types=1);

namespace Core\Console\Commands\Database;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Core\Database\AdaptivePoolManager;
use Core\Database\ConnectionLeakDetector;
use Core\Database\ConnectionMetrics;
use Core\Database\Swoole\SwoolePdoPool;

/**
 * Analyze database connection pool health and performance.
 */
class AnalyzeConnectionPoolCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    /**
     * The name and signature of the console command.
     *
     * @return string
     */
    public function signature(): string
    {
        return 'db:analyze-pool {pool=mysql : The pool name to analyze} {--metrics : Show query metrics} {--leaks : Show connection leak detection} {--recommendations : Show optimization recommendations}';
    }

    /**
     * The console command description.
     *
     * @return string
     */
    public function description(): string
    {
        return 'Analyze database connection pool health and performance';
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $poolName = $this->argument('pool');
        $showMetrics = $this->option('metrics');
        $showLeaks = $this->option('leaks');
        $showRecommendations = $this->option('recommendations');

        // Show all if no specific option
        if (!$showMetrics && !$showLeaks && !$showRecommendations) {
            $showMetrics = $showLeaks = $showRecommendations = true;
        }

        $this->io->title("📊 Analyzing connection pool: {$poolName}");

        // Pool statistics
        $this->displayPoolStats($poolName);

        // Metrics
        if ($showMetrics) {
            $this->displayMetrics();
        }

        // Leak detection
        if ($showLeaks) {
            $this->displayLeakDetection();
        }

        // Recommendations
        if ($showRecommendations) {
            $this->displayRecommendations($poolName);
        }

        return self::SUCCESS;
    }

    /**
     * Display connection pool statistics.
     *
     * @param string $poolName
     * @return void
     */
    private function displayPoolStats(string $poolName): void
    {
        $this->io->section('🔹 Connection Pool Status');

        if (!class_exists(SwoolePdoPool::class) || !SwoolePdoPool::isInitialized($poolName)) {
            $this->io->warning("Pool '{$poolName}' is not initialized (not running in Swoole mode)");
            return;
        }

        $stats = SwoolePdoPool::stats($poolName);

        if (!$stats) {
            $this->io->error("Unable to get stats for pool '{$poolName}'");
            return;
        }

        $poolSize = $stats['pool_size'];
        $inUse = $stats['connections_in_use'];
        $idle = $stats['connections_idle'];
        $utilization = $poolSize > 0 ? ($inUse / $poolSize) * 100 : 0;

        $this->io->table(
            ['Metric', 'Value'],
            [
                ['Pool Size', $poolSize],
                ['Connections In Use', $inUse],
                ['Connections Idle', $idle],
                ['Utilization', sprintf('%.2f%%', $utilization)],
                ['Status', $this->getHealthStatus($utilization)],
            ]
        );
    }

    /**
     * Display query performance metrics.
     *
     * @return void
     */
    private function displayMetrics(): void
    {
        $this->io->section('📈 Query Performance Metrics');

        $metrics = $this->app->make(ConnectionMetrics::class);
        $stats = $metrics->getQueryStats();

        $this->io->table(
            ['Metric', 'Value'],
            [
                ['Total Queries', $stats['total_queries']],
                ['Failed Queries', $stats['failed_queries']],
                ['Success Rate', sprintf('%.2f%%', $stats['success_rate'])],
                ['Avg Duration', sprintf('%.2fms', $stats['avg_duration_ms'])],
                ['P50 Duration', sprintf('%.2fms', $stats['p50_duration_ms'])],
                ['P95 Duration', sprintf('%.2fms', $stats['p95_duration_ms'])],
                ['P99 Duration', sprintf('%.2fms', $stats['p99_duration_ms'])],
                ['Slow Queries', $stats['slow_queries_count']],
                ['QPS', sprintf('%.2f', $stats['queries_per_second'])],
            ]
        );

        // Show slow queries
        if ($stats['slow_queries_count'] > 0) {
            $this->io->warning("⚠️  Slow Queries Detected ({$stats['slow_queries_count']} total):");
            $slowQueries = $metrics->getSlowQueries(5);
            
            foreach ($slowQueries as $i => $query) {
                $this->io->text(sprintf(
                    "  %d. %s (%.2fms)",
                    $i + 1,
                    $query['sql'],
                    $query['duration_ms']
                ));
            }
        }
    }

    /**
     * Display connection leak detection results.
     *
     * @return void
     */
    private function displayLeakDetection(): void
    {
        $this->io->section('🔍 Connection Leak Detection');

        $detector = $this->app->make(ConnectionLeakDetector::class);
        $stats = $detector->getStats();

        $this->io->table(
            ['Metric', 'Value'],
            [
                ['Active Connections', $stats['active_connections']],
                ['Avg Hold Time', sprintf('%.2fs', $stats['avg_hold_time'])],
                ['Max Hold Time', sprintf('%.2fs', $stats['max_hold_time'])],
                ['Warning Threshold', $stats['warning_threshold'] . 's'],
                ['Leak Threshold', $stats['leak_threshold'] . 's'],
            ]
        );

        // Check for leaks
        $report = $detector->checkForLeaks();

        if (!empty($report['leaks'])) {
            $leakCount = count($report['leaks']);
            $this->io->error("🚨 Connection Leaks Detected ({$leakCount} total):");
            
            foreach ($report['leaks'] as $leak) {
                $this->io->text(sprintf(
                    "  - Connection #%s (Pool: %s, Coroutine: %s, Hold Time: %.2fs)",
                    $leak['connection_id'],
                    $leak['pool_name'],
                    $leak['coroutine_id'],
                    $leak['hold_time_seconds']
                ));
            }
        } elseif (!empty($report['warnings'])) {
            $warningCount = count($report['warnings']);
            $this->io->warning("⚠️  Long-held Connections ({$warningCount} total):");
            
            foreach ($report['warnings'] as $warning) {
                $this->io->text(sprintf(
                    "  - Connection #%s (Pool: %s, Hold Time: %.2fs)",
                    $warning['connection_id'],
                    $warning['pool_name'],
                    $warning['hold_time_seconds']
                ));
            }
        } else {
            $this->io->success("✅ No connection leaks detected");
        }
    }

    /**
     * Display optimization recommendations.
     *
     * @param string $poolName
     * @return void
     */
    private function displayRecommendations(string $poolName): void
    {
        $this->io->section('💡 Recommendations');

        if (!class_exists(SwoolePdoPool::class) || !SwoolePdoPool::isInitialized($poolName)) {
            $this->io->text("No recommendations available (pool not initialized)");
            return;
        }

        $adaptiveManager = $this->app->make(AdaptivePoolManager::class);
        $recommendations = $adaptiveManager->getRecommendations($poolName);

        if (empty($recommendations['recommendations'])) {
            $this->io->success("✅ Pool is operating within optimal parameters");
            return;
        }

        foreach ($recommendations['recommendations'] as $rec) {
            $icon = match ($rec['severity']) {
                'high' => '🚨',
                'medium' => '⚠️',
                'low' => 'ℹ️',
                default => '•',
            };

            $this->io->text("\n{$icon} [{$rec['severity']}] {$rec['message']}");
            $this->io->text("   Suggested Action: {$rec['suggested_action']}");
        }
    }

    /**
     * Get health status based on utilization percentage.
     *
     * @param float $utilization
     * @return string
     */
    private function getHealthStatus(float $utilization): string
    {
        if ($utilization >= 90) {
            return '🔴 Critical';
        } elseif ($utilization >= 75) {
            return '🟡 Warning';
        } elseif ($utilization >= 50) {
            return '🟢 Good';
        } else {
            return '🔵 Excellent';
        }
    }
}
