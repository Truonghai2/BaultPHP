<?php

namespace Core\Console\Commands\Cache;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Core\Performance\JITOptimizer;

/**
 * JIT Optimization Command
 * 
 * Optimize PHP OPcache with JIT compilation and hot path detection.
 */
class OptimizeJitCommand extends BaseCommand
{
    public function __construct(
        Application $app,
        protected ?JITOptimizer $optimizer = null
    ) {
        parent::__construct($app);
        $this->optimizer = $optimizer ?? $app->make(JITOptimizer::class);
    }

    public function signature(): string
    {
        return 'optimize:jit 
                {action=optimize : Action to perform (optimize|stats|reset|analyze)}';
    }

    public function description(): string
    {
        return 'Optimize PHP OPcache with JIT compilation and hot path detection';
    }

    public function handle(): int
    {
        $action = $this->argument('action');

        return match($action) {
            'optimize' => $this->runOptimize(),
            'stats' => $this->showStats(),
            'reset' => $this->resetOptimization(),
            'analyze' => $this->analyze(),
            default => $this->handleUnknownAction($action),
        };
    }

    /**
     * Run JIT optimization.
     */
    protected function runOptimize(): int
    {
        if (!function_exists('opcache_get_status')) {
            $this->error('❌ OPcache is not available');
            $this->comment('Please enable OPcache in your php.ini');
            return self::FAILURE;
        }

        $this->info('🚀 Running JIT optimization...');
        $this->line('');

        try {
            // Get initial stats
            $initialStats = $this->optimizer->getStats();
            $initialHitRate = $initialStats['hit_rate'] ?? 0;

            // Run optimization
            $this->optimizer->optimize();

            // Get final stats
            $finalStats = $this->optimizer->getStats();
            $finalHitRate = $finalStats['hit_rate'] ?? 0;

            $this->line('');
            $this->info('✔ JIT optimization completed!');
            $this->line('');

            // Show results
            $this->io->table(
                ['Metric', 'Before', 'After', 'Change'],
                [
                    [
                        'Hit Rate',
                        sprintf('%.2f%%', $initialHitRate),
                        sprintf('%.2f%%', $finalHitRate),
                        sprintf('%+.2f%%', $finalHitRate - $initialHitRate)
                    ],
                    [
                        'Cached Scripts',
                        $initialStats['cached_scripts'] ?? 0,
                        $finalStats['cached_scripts'] ?? 0,
                        sprintf('%+d', ($finalStats['cached_scripts'] ?? 0) - ($initialStats['cached_scripts'] ?? 0))
                    ],
                    [
                        'Memory Usage',
                        sprintf('%.2f%%', $initialStats['memory_usage_percent'] ?? 0),
                        sprintf('%.2f%%', $finalStats['memory_usage_percent'] ?? 0),
                        sprintf('%+.2f%%', ($finalStats['memory_usage_percent'] ?? 0) - ($initialStats['memory_usage_percent'] ?? 0))
                    ],
                    [
                        'Hot Paths',
                        $initialStats['hot_paths'] ?? 0,
                        $finalStats['hot_paths'] ?? 0,
                        sprintf('%+d', ($finalStats['hot_paths'] ?? 0) - ($initialStats['hot_paths'] ?? 0))
                    ],
                ]
            );

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('❌ Optimization failed: ' . $e->getMessage());
            if ($this->io->isVerbose()) {
                $this->line($e->getTraceAsString());
            }
            return self::FAILURE;
        }
    }

    /**
     * Show OPcache statistics.
     */
    protected function showStats(): int
    {
        if (!function_exists('opcache_get_status')) {
            $this->error('❌ OPcache is not available');
            return self::FAILURE;
        }

        $stats = $this->optimizer->getStats();

        if (!$stats['available']) {
            $this->error('❌ ' . ($stats['message'] ?? 'OPcache not available'));
            return self::FAILURE;
        }

        $this->info('📊 OPcache Statistics:');
        $this->line('');

        // Performance stats
        $hitRate = $stats['hit_rate'];
        $hitRateColor = $hitRate >= 95 ? 'green' : ($hitRate >= 85 ? 'yellow' : 'red');
        
        $this->io->table(
            ['Metric', 'Value'],
            [
                ['Hit Rate', "<fg={$hitRateColor}>" . sprintf('%.2f%%', $hitRate) . '</>'],
                ['Hits', number_format($stats['hits'])],
                ['Misses', number_format($stats['misses'])],
                ['Cached Scripts', number_format($stats['cached_scripts'])],
            ]
        );

        // Memory stats
        $memoryUsage = $stats['memory_usage_percent'];
        $memoryColor = $memoryUsage >= 90 ? 'red' : ($memoryUsage >= 75 ? 'yellow' : 'green');

        $this->line('');
        $this->io->table(
            ['Memory Metric', 'Value'],
            [
                ['Used', $this->formatBytes($stats['memory_used'])],
                ['Free', $this->formatBytes($stats['memory_free'])],
                ['Usage', "<fg={$memoryColor}>" . sprintf('%.2f%%', $memoryUsage) . '</>'],
            ]
        );

        // JIT stats
        $this->line('');
        $this->io->table(
            ['JIT Metric', 'Value'],
            [
                ['Hot Paths', number_format($stats['hot_paths'])],
                ['Profiled Files', number_format($stats['profiled_files'])],
            ]
        );

        // Health recommendation
        $this->line('');
        $this->showHealthRecommendation($stats);

        return self::SUCCESS;
    }

    /**
     * Reset JIT optimization.
     */
    protected function resetOptimization(): int
    {
        if (!$this->confirm('This will reset all JIT optimization data. Continue?', true)) {
            $this->comment('Operation cancelled.');
            return self::SUCCESS;
        }

        $this->info('🔄 Resetting JIT optimization...');

        try {
            $this->optimizer->reset();
            $this->info('✔ JIT optimization reset successfully!');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('❌ Reset failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Analyze OPcache and provide recommendations.
     */
    protected function analyze(): int
    {
        if (!function_exists('opcache_get_status')) {
            $this->error('❌ OPcache is not available');
            return self::FAILURE;
        }

        $stats = $this->optimizer->getStats();

        if (!$stats['available']) {
            $this->error('❌ OPcache not available');
            return self::FAILURE;
        }

        $this->info('🔍 Analyzing OPcache configuration...');
        $this->line('');

        $recommendations = [];
        $warnings = [];
        $critical = [];

        // Analyze hit rate
        $hitRate = $stats['hit_rate'];
        if ($hitRate < 85) {
            $critical[] = "Hit rate is low ({$hitRate}%) - Consider increasing memory_consumption or running optimize:jit";
        } elseif ($hitRate < 95) {
            $warnings[] = "Hit rate could be improved ({$hitRate}%) - Run optimize:jit to optimize hot paths";
        } else {
            $recommendations[] = "Hit rate is excellent ({$hitRate}%)";
        }

        // Analyze memory usage
        $memoryUsage = $stats['memory_usage_percent'];
        if ($memoryUsage > 90) {
            $critical[] = "Memory usage is critical ({$memoryUsage}%) - Increase opcache.memory_consumption";
        } elseif ($memoryUsage > 75) {
            $warnings[] = "Memory usage is high ({$memoryUsage}%) - Consider increasing opcache.memory_consumption";
        } else {
            $recommendations[] = "Memory usage is healthy ({$memoryUsage}%)";
        }

        // Analyze hot paths
        if ($stats['hot_paths'] === 0) {
            $warnings[] = "No hot paths detected - Run the application to collect profiling data";
        } else {
            $recommendations[] = "Detected {$stats['hot_paths']} hot paths for optimization";
        }

        // Display results
        if (!empty($critical)) {
            $this->error('🚨 Critical Issues:');
            foreach ($critical as $issue) {
                $this->line('  • ' . $issue);
            }
            $this->line('');
        }

        if (!empty($warnings)) {
            $this->warn('⚠️  Warnings:');
            foreach ($warnings as $warning) {
                $this->line('  • ' . $warning);
            }
            $this->line('');
        }

        if (!empty($recommendations)) {
            $this->info('✔ Good:');
            foreach ($recommendations as $rec) {
                $this->line('  • ' . $rec);
            }
            $this->line('');
        }

        // Overall health
        $healthScore = $this->calculateHealthScore($stats);
        $this->displayHealthScore($healthScore);

        return self::SUCCESS;
    }

    /**
     * Handle unknown action.
     */
    protected function handleUnknownAction(string $action): int
    {
        $this->error("Unknown action: {$action}");
        $this->line('');
        $this->comment('Available actions:');
        $this->line('  • optimize - Run JIT optimization');
        $this->line('  • stats    - Show OPcache statistics');
        $this->line('  • reset    - Reset optimization data');
        $this->line('  • analyze  - Analyze and provide recommendations');
        return self::FAILURE;
    }

    /**
     * Show health recommendation based on stats.
     */
    protected function showHealthRecommendation(array $stats): void
    {
        $healthScore = $this->calculateHealthScore($stats);

        if ($healthScore >= 90) {
            $this->info('✔ Health: Excellent');
            $this->comment('OPcache is optimally configured and performing well.');
        } elseif ($healthScore >= 75) {
            $this->warn('⚠️  Health: Good');
            $this->comment('OPcache is working well but could be improved. Run "optimize:jit analyze" for recommendations.');
        } elseif ($healthScore >= 60) {
            $this->warn('⚠️  Health: Fair');
            $this->comment('OPcache needs attention. Run "optimize:jit analyze" and "optimize:jit" to improve.');
        } else {
            $this->error('🚨 Health: Poor');
            $this->comment('OPcache requires immediate attention. Run "optimize:jit analyze" for recommendations.');
        }
    }

    /**
     * Calculate health score (0-100).
     */
    protected function calculateHealthScore(array $stats): float
    {
        $hitRateScore = min(100, $stats['hit_rate']);
        $memoryScore = 100 - $stats['memory_usage_percent'];
        
        // Weighted average
        $score = ($hitRateScore * 0.7) + ($memoryScore * 0.3);

        return round($score, 2);
    }

    /**
     * Display health score with color.
     */
    protected function displayHealthScore(float $score): void
    {
        $color = match(true) {
            $score >= 90 => 'green',
            $score >= 75 => 'yellow',
            $score >= 60 => 'yellow',
            default => 'red',
        };

        $this->line('');
        $this->info("Overall Health Score: <fg={$color}>{$score}/100</>");
    }

    /**
     * Format bytes to human readable format.
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
