<?php

namespace Core\Console\Commands;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Core\Performance\JITOptimizer;
use Core\Database\ConnectionMetrics;
use Core\Database\Swoole\SwoolePdoPool;

/**
 * Performance Report Command
 * 
 * Generate comprehensive performance report for the application.
 */
class PerformanceReportCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'performance:report
                {--json : Output in JSON format}
                {--detailed : Show detailed metrics}';
    }

    public function description(): string
    {
        return 'Generate comprehensive performance report';
    }

    public function handle(): int
    {
        $json = $this->option('json');
        $detailed = $this->option('detailed');

        if ($json) {
            return $this->outputJson($detailed);
        }

        $this->comment('📊 Generating Performance Report...');
        $this->line('');

        // Collect all metrics
        $report = $this->collectMetrics($detailed);

        // Display report
        $this->displayReport($report, $detailed);

        // Overall health score
        $this->line('');
        $this->displayOverallHealth($report);

        return self::SUCCESS;
    }

    /**
     * Collect all performance metrics.
     */
    protected function collectMetrics(bool $detailed): array
    {
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'opcache' => $this->collectOPcacheMetrics(),
            'database' => $this->collectDatabaseMetrics(),
            'cache' => $this->collectCacheMetrics(),
            'acl' => $this->collectACLMetrics(),
            'system' => $this->collectSystemMetrics(),
        ];

        if ($detailed) {
            $report['detailed'] = [
                'php_info' => $this->collectPHPInfo(),
                'extensions' => $this->collectExtensions(),
            ];
        }

        return $report;
    }

    /**
     * Display the report.
     */
    protected function displayReport(array $report, bool $detailed): void
    {
        // OPcache metrics
        $this->displayOPcacheSection($report['opcache']);

        // Database metrics
        $this->displayDatabaseSection($report['database']);

        // Cache metrics
        $this->displayCacheSection($report['cache']);

        // ACL metrics
        $this->displayACLSection($report['acl']);

        // System metrics
        $this->displaySystemSection($report['system']);

        if ($detailed && isset($report['detailed'])) {
            $this->displayDetailedSection($report['detailed']);
        }
    }

    /**
     * Collect OPcache metrics.
     */
    protected function collectOPcacheMetrics(): array
    {
        try {
            $optimizer = $this->app->make(JITOptimizer::class);
            $stats = $optimizer->getStats();

            if (!$stats['available']) {
                return ['available' => false, 'message' => 'OPcache not available'];
            }

            return [
                'available' => true,
                'hit_rate' => $stats['hit_rate'],
                'cached_scripts' => $stats['cached_scripts'],
                'memory_usage_percent' => $stats['memory_usage_percent'],
                'hot_paths' => $stats['hot_paths'],
                'health' => $this->calculateOPcacheHealth($stats),
            ];
        } catch (\Throwable $e) {
            return ['available' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Collect database metrics.
     */
    protected function collectDatabaseMetrics(): array
    {
        try {
            // Check if Swoole pool is available
            if (class_exists(SwoolePdoPool::class) && SwoolePdoPool::isInitialized('mysql')) {
                $poolStats = SwoolePdoPool::stats('mysql');
                $utilization = $poolStats['pool_size'] > 0 
                    ? ($poolStats['connections_in_use'] / $poolStats['pool_size']) * 100 
                    : 0;

                $metrics = [
                    'available' => true,
                    'pool' => [
                        'size' => $poolStats['pool_size'],
                        'in_use' => $poolStats['connections_in_use'],
                        'idle' => $poolStats['connections_idle'],
                        'utilization' => round($utilization, 2),
                    ],
                ];

                // Get query metrics if available
                try {
                    $connectionMetrics = $this->app->make(ConnectionMetrics::class);
                    $queryStats = $connectionMetrics->getQueryStats();
                    
                    $metrics['queries'] = [
                        'total' => $queryStats['total_queries'],
                        'success_rate' => $queryStats['success_rate'],
                        'avg_duration_ms' => $queryStats['avg_duration_ms'],
                        'qps' => $queryStats['queries_per_second'],
                        'slow_queries' => $queryStats['slow_queries_count'],
                    ];
                } catch (\Throwable $e) {
                    $metrics['queries'] = ['error' => 'Query metrics not available'];
                }

                $metrics['health'] = $this->calculateDatabaseHealth($metrics);
                return $metrics;
            }

            return ['available' => false, 'message' => 'Not running in Swoole mode'];
        } catch (\Throwable $e) {
            return ['available' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Collect cache metrics.
     */
    protected function collectCacheMetrics(): array
    {
        try {
            $cache = $this->app->make('cache');
            
            return [
                'available' => true,
                'driver' => config('cache.default'),
                'stores' => config('cache.stores'),
            ];
        } catch (\Throwable $e) {
            return ['available' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Collect ACL metrics.
     */
    protected function collectACLMetrics(): array
    {
        try {
            $optimizer = $this->app->make(\Modules\User\Domain\Services\ACLOptimizer::class);
            $metrics = $optimizer->getMetrics();

            $totalChecks = $metrics['total_checks'];
            $hitRate = $totalChecks > 0 
                ? (($metrics['l1_hits'] + $metrics['l2_hits']) / $totalChecks) * 100 
                : 0;

            return [
                'available' => true,
                'hit_rate' => round($hitRate, 2),
                'l1_hits' => $metrics['l1_hits'],
                'l2_hits' => $metrics['l2_hits'],
                'cache_misses' => $metrics['cache_misses'],
                'total_checks' => $totalChecks,
                'health' => $hitRate >= 90 ? 'excellent' : ($hitRate >= 75 ? 'good' : 'needs_improvement'),
            ];
        } catch (\Throwable $e) {
            return ['available' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Collect system metrics.
     */
    protected function collectSystemMetrics(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'swoole_enabled' => extension_loaded('swoole'),
            'swoole_version' => extension_loaded('swoole') ? swoole_version() : null,
            'memory_limit' => ini_get('memory_limit'),
            'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
            'memory_peak' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB',
        ];
    }

    /**
     * Collect PHP info.
     */
    protected function collectPHPInfo(): array
    {
        return [
            'version' => PHP_VERSION,
            'sapi' => PHP_SAPI,
            'zend_version' => zend_version(),
            'opcache_enabled' => function_exists('opcache_get_status'),
            'jit_enabled' => (function_exists('opcache_get_status') && opcache_get_status()['jit']['enabled'] ?? false),
        ];
    }

    /**
     * Collect loaded extensions.
     */
    protected function collectExtensions(): array
    {
        $important = ['swoole', 'redis', 'apcu', 'opcache', 'pdo', 'pdo_mysql', 'mbstring', 'curl', 'json'];
        $loaded = [];

        foreach ($important as $ext) {
            $loaded[$ext] = extension_loaded($ext);
        }

        return $loaded;
    }

    /**
     * Display OPcache section.
     */
    protected function displayOPcacheSection(array $data): void
    {
        $this->info('═══ OPcache Performance ═══');
        
        if (!$data['available']) {
            $this->warn('  OPcache is not available');
            $this->line('');
            return;
        }

        $hitRateColor = $data['hit_rate'] >= 95 ? 'green' : ($data['hit_rate'] >= 85 ? 'yellow' : 'red');
        $healthColor = $data['health'] === 'excellent' ? 'green' : ($data['health'] === 'good' ? 'yellow' : 'red');

        $this->io->table(
            ['Metric', 'Value'],
            [
                ['Hit Rate', "<fg={$hitRateColor}>" . sprintf('%.2f%%', $data['hit_rate']) . '</>'],
                ['Cached Scripts', number_format($data['cached_scripts'])],
                ['Memory Usage', sprintf('%.2f%%', $data['memory_usage_percent'])],
                ['Hot Paths', number_format($data['hot_paths'])],
                ['Health', "<fg={$healthColor}>" . ucfirst($data['health']) . '</>'],
            ]
        );
    }

    /**
     * Display database section.
     */
    protected function displayDatabaseSection(array $data): void
    {
        $this->info('═══ Database Performance ═══');
        
        if (!$data['available']) {
            $this->comment('  Database metrics not available (not in Swoole mode)');
            $this->line('');
            return;
        }

        $pool = $data['pool'];
        $utilizationColor = $pool['utilization'] >= 90 ? 'red' : ($pool['utilization'] >= 75 ? 'yellow' : 'green');

        $rows = [
            ['Pool Size', $pool['size']],
            ['In Use', $pool['in_use']],
            ['Idle', $pool['idle']],
            ['Utilization', "<fg={$utilizationColor}>" . sprintf('%.2f%%', $pool['utilization']) . '</>'],
        ];

        if (isset($data['queries']) && !isset($data['queries']['error'])) {
            $queries = $data['queries'];
            $rows[] = ['---', '---'];
            $rows[] = ['Total Queries', number_format($queries['total'])];
            $rows[] = ['Success Rate', sprintf('%.2f%%', $queries['success_rate'])];
            $rows[] = ['Avg Duration', sprintf('%.2fms', $queries['avg_duration_ms'])];
            $rows[] = ['QPS', sprintf('%.2f', $queries['qps'])];
            $rows[] = ['Slow Queries', number_format($queries['slow_queries'])];
        }

        if (isset($data['health'])) {
            $healthColor = $data['health'] === 'excellent' ? 'green' : ($data['health'] === 'good' ? 'yellow' : 'red');
            $rows[] = ['---', '---'];
            $rows[] = ['Health', "<fg={$healthColor}>" . ucfirst($data['health']) . '</>'];
        }

        $this->io->table(['Metric', 'Value'], $rows);
    }

    /**
     * Display cache section.
     */
    protected function displayCacheSection(array $data): void
    {
        $this->info('═══ Cache Configuration ═══');
        
        if (!$data['available']) {
            $this->warn('  Cache not available');
            $this->line('');
            return;
        }

        $this->io->table(
            ['Metric', 'Value'],
            [
                ['Default Driver', $data['driver']],
                ['Available Stores', implode(', ', array_keys($data['stores']))],
            ]
        );
    }

    /**
     * Display ACL section.
     */
    protected function displayACLSection(array $data): void
    {
        $this->info('═══ ACL Performance ═══');
        
        if (!$data['available']) {
            $this->comment('  ACL metrics not available');
            $this->line('');
            return;
        }

        $hitRateColor = $data['hit_rate'] >= 90 ? 'green' : ($data['hit_rate'] >= 75 ? 'yellow' : 'red');
        $healthColor = $data['health'] === 'excellent' ? 'green' : ($data['health'] === 'good' ? 'yellow' : 'red');

        $rows = [
            ['Hit Rate', "<fg={$hitRateColor}>" . sprintf('%.2f%%', $data['hit_rate']) . '</>'],
            ['L1 Hits (APCu)', number_format($data['l1_hits'])],
            ['L2 Hits (Redis)', number_format($data['l2_hits'])],
            ['Cache Misses', number_format($data['cache_misses'])],
            ['Total Checks', number_format($data['total_checks'])],
            ['Health', "<fg={$healthColor}>" . ucfirst(str_replace('_', ' ', $data['health'])) . '</>'],
        ];
        $this->io->table(['Metric', 'Value'], $rows);
        if (($data['total_checks'] ?? 0) === 0) {
            $this->comment('  (No permission checks in this process — e.g. CLI. Hit rate applies to Swoole workers under load.)');
        }
        $this->line('');
    }

    /**
     * Display system section.
     */
    protected function displaySystemSection(array $data): void
    {
        $this->info('═══ System Information ═══');
        
        $swooleStatus = $data['swoole_enabled'] ? 'Enabled' : 'Disabled';
        $swooleColor = $data['swoole_enabled'] ? 'green' : 'yellow';

        $rows = [
            ['PHP Version', $data['php_version']],
            ['Swoole', "<fg={$swooleColor}>{$swooleStatus}</>"],
        ];

        if ($data['swoole_enabled']) {
            $rows[] = ['Swoole Version', $data['swoole_version']];
        }

        $rows[] = ['Memory Limit', $data['memory_limit']];
        $rows[] = ['Memory Usage', $data['memory_usage']];
        $rows[] = ['Memory Peak', $data['memory_peak']];

        $this->io->table(['Metric', 'Value'], $rows);
    }

    /**
     * Display detailed section.
     */
    protected function displayDetailedSection(array $data): void
    {
        $this->info('═══ Detailed Information ═══');
        
        // PHP Info
        $this->comment('PHP Information:');
        foreach ($data['php_info'] as $key => $value) {
            $this->line("  • {$key}: " . var_export($value, true));
        }
        $this->line('');

        // Extensions
        $this->comment('Important Extensions:');
        foreach ($data['extensions'] as $ext => $loaded) {
            $status = $loaded ? '<fg=green>✔</>' : '<fg=red>✗</>';
            $this->line("  {$status} {$ext}");
        }
        $this->line('');
    }

    /**
     * Display overall health.
     */
    protected function displayOverallHealth(array $report): void
    {
        $scores = [];

        if ($report['opcache']['available'] ?? false) {
            $scores[] = $this->getHealthScore($report['opcache']['health']);
        }

        if ($report['database']['available'] ?? false) {
            $scores[] = $this->getHealthScore($report['database']['health'] ?? 'good');
        }

        // Only include ACL in score when there is traffic (CLI has no checks, so would skew score)
        $aclAvailable = $report['acl']['available'] ?? false;
        $aclTotalChecks = (int) ($report['acl']['total_checks'] ?? 0);
        if ($aclAvailable && $aclTotalChecks > 0) {
            $scores[] = $this->getHealthScore($report['acl']['health']);
        }

        $this->info('═══ Overall Health ═══');

        if (empty($scores)) {
            $this->comment('  Not enough data for a score (OPcache/DB metrics need Swoole or web context).');
            $this->line('');
            $this->outputRecommendations($report, $aclTotalChecks, null);
            return;
        }

        $avgScore = array_sum($scores) / count($scores);
        $health = $this->scoreToHealth($avgScore);
        $color = $avgScore >= 90 ? 'green' : ($avgScore >= 75 ? 'yellow' : 'red');
        $this->line("<fg={$color}>Health Score: " . round($avgScore, 2) . "/100 ({$health})</>");
        $this->line('');
        $this->outputRecommendations($report, $aclTotalChecks, $avgScore);
    }

    /**
     * Output recommendation lines (CLI/Swoole context).
     */
    protected function outputRecommendations(array $report, int $aclTotalChecks, ?float $avgScore): void
    {
        $show = $avgScore === null || $avgScore < 90;
        if (!$show) {
            return;
        }
        $this->comment('💡 Recommendations:');
        if (!($report['opcache']['available'] ?? false)) {
            $this->line('  • Enable OPcache for better performance (e.g. in php.ini)');
        } elseif (($report['opcache']['hit_rate'] ?? 100) < 95) {
            $this->line('  • Run "php bault optimize:jit" to improve OPcache performance');
        }
        if (($report['database']['pool']['utilization'] ?? 0) > 80) {
            $this->line('  • Consider increasing database pool size');
        }
        if ($aclTotalChecks === 0) {
            $this->line('  • Run "php cli acl:optimize warm" to prefill ACL cache for Swoole workers.');
        } elseif (($report['acl']['hit_rate'] ?? 100) < 90) {
            $this->line('  • Run "php cli command:clear" then "php cli acl:optimize warm" to improve ACL cache');
        }
    }

    /**
     * Output report in JSON format.
     */
    protected function outputJson(bool $detailed): int
    {
        $report = $this->collectMetrics($detailed);
        
        // Calculate overall health
        $report['overall_health'] = $this->calculateOverallHealthScore($report);

        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        echo PHP_EOL;

        return self::SUCCESS;
    }

    /**
     * Calculate overall health score.
     */
    protected function calculateOverallHealthScore(array $report): array
    {
        $scores = [];

        if ($report['opcache']['available'] ?? false) {
            $scores['opcache'] = $this->getHealthScore($report['opcache']['health']);
        }

        if ($report['database']['available'] ?? false) {
            $scores['database'] = $this->getHealthScore($report['database']['health'] ?? 'good');
        }

        $aclAvail = $report['acl']['available'] ?? false;
        $aclChecks = (int) ($report['acl']['total_checks'] ?? 0);
        if ($aclAvail && $aclChecks > 0) {
            $scores['acl'] = $this->getHealthScore($report['acl']['health']);
        }

        $avgScore = !empty($scores) ? array_sum($scores) / count($scores) : 0;

        return [
            'score' => round($avgScore, 2),
            'status' => $this->scoreToHealth($avgScore),
            'components' => $scores,
        ];
    }

    /**
     * Calculate OPcache health.
     */
    protected function calculateOPcacheHealth(array $stats): string
    {
        $hitRate = $stats['hit_rate'];
        $memoryUsage = $stats['memory_usage_percent'];

        if ($hitRate >= 95 && $memoryUsage < 75) {
            return 'excellent';
        } elseif ($hitRate >= 85 && $memoryUsage < 85) {
            return 'good';
        } elseif ($hitRate >= 70) {
            return 'fair';
        } else {
            return 'poor';
        }
    }

    /**
     * Calculate database health.
     */
    protected function calculateDatabaseHealth(array $metrics): string
    {
        $utilization = $metrics['pool']['utilization'] ?? 0;
        $successRate = $metrics['queries']['success_rate'] ?? 100;

        if ($utilization < 75 && $successRate >= 99) {
            return 'excellent';
        } elseif ($utilization < 85 && $successRate >= 95) {
            return 'good';
        } elseif ($utilization < 90 && $successRate >= 90) {
            return 'fair';
        } else {
            return 'poor';
        }
    }

    /**
     * Get numeric health score.
     */
    protected function getHealthScore(string $health): float
    {
        return match($health) {
            'excellent' => 95,
            'good' => 80,
            'fair' => 65,
            'poor' => 40,
            default => 50,
        };
    }

    /**
     * Convert score to health status.
     */
    protected function scoreToHealth(float $score): string
    {
        if ($score >= 90) return 'Excellent';
        if ($score >= 75) return 'Good';
        if ($score >= 60) return 'Fair';
        return 'Poor';
    }
}
