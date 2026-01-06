<?php

namespace Modules\User\Console;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Modules\User\Domain\Services\ACLOptimizer;
use Modules\User\Infrastructure\Models\User;

/**
 * ACLOptimizeCommand
 *
 * CLI command for ACL optimization tasks.
 */
class ACLOptimizeCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'acl:optimize 
                {action : Action to perform (warm|metrics|report|reset)}
                {--users= : Comma-separated user IDs to warm cache for}';
    }

    public function description(): string
    {
        return 'Optimize ACL performance (warm|metrics|report|reset)';
    }

    public function handle(): int
    {
        /** @var ACLOptimizer $optimizer */
        $optimizer = $this->app->make(ACLOptimizer::class);

        $action = $this->argument('action');

        switch ($action) {
            case 'warm':
                return $this->warmCache($optimizer);
            case 'metrics':
                return $this->showMetrics($optimizer);
            case 'report':
                return $this->showReport($optimizer);
            case 'reset':
                return $this->resetMetrics($optimizer);
            default:
                $this->io->error("Unknown action: {$action}");
                $this->io->writeln('Available actions: warm, metrics, report, reset');
                return self::FAILURE;
        }
    }

    private function warmCache(ACLOptimizer $optimizer): int
    {
        $this->io->writeln('<info>Warming ACL cache...</info>');

        // Get users to warm
        $userIds = [];

        if ($userOption = $this->option('users')) {
            // Specific users
            $userIds = array_map('intval', explode(',', $userOption));
        } else {
            // All active users (last 30 days)
            $this->io->writeln('<info>No users specified. Warming cache for all active users (last 30 days)...</info>');

            $activeUsers = User::where('updated_at', '>=', date('Y-m-d H:i:s', strtotime('-30 days')))
                ->where('status', '=', 'active')
                ->select('id')
                ->limit(1000) // Safety limit
                ->get();

            $userIds = $activeUsers->pluck('id')->toArray();
        }

        if (empty($userIds)) {
            $this->io->warning('No users found to warm cache for');
            return self::SUCCESS;
        }

        $this->io->writeln('<info>Warming cache for ' . count($userIds) . ' users...</info>');

        $stats = $optimizer->warmCache($userIds);

        $this->io->success('Cache warming complete!');
        $this->io->table(
            ['Metric', 'Value'],
            [
                ['Total Users', $stats['total']],
                ['Warmed', $stats['warmed']],
                ['Failed', $stats['failed']],
                ['Duration', $stats['duration']],
                ['Avg Time', $stats['avg_time']],
            ],
        );

        return self::SUCCESS;
    }

    private function showMetrics(ACLOptimizer $optimizer): int
    {
        $metrics = $optimizer->getMetrics();

        $this->io->writeln('<info>ACL Performance Metrics:</info>');
        $this->io->newLine();

        $this->io->table(
            ['Metric', 'Value'],
            [
                ['L1 Hits (APCu)', $metrics['l1_hits']],
                ['L2 Hits (Redis)', $metrics['l2_hits']],
                ['Cache Misses', $metrics['cache_misses']],
                ['Total Checks', $metrics['total_checks']],
                ['Batch Checks', $metrics['batch_checks']],
                ['Cache Invalidations', $metrics['cache_invalidations']],
                ['Hit Rate', $optimizer->getCacheHitRate() . '%'],
            ],
        );

        return self::SUCCESS;
    }

    private function showReport(ACLOptimizer $optimizer): int
    {
        $report = $optimizer->getPerformanceReport();

        $this->io->writeln('<info>=== ACL Performance Report ===</info>');
        $this->io->newLine();

        // Cache Performance
        $this->io->writeln('<info>Cache Performance:</info>');
        $this->io->table(
            ['Metric', 'Value'],
            [
                ['L1 Hits', $report['cache_performance']['l1_hits']],
                ['L2 Hits', $report['cache_performance']['l2_hits']],
                ['Cache Misses', $report['cache_performance']['cache_misses']],
                ['Hit Rate', $report['cache_performance']['hit_rate']],
            ],
        );

        // Operations
        $this->io->newLine();
        $this->io->writeln('<info>Operations:</info>');
        $this->io->table(
            ['Metric', 'Value'],
            [
                ['Total Checks', $report['operations']['total_checks']],
                ['Batch Checks', $report['operations']['batch_checks']],
                ['Cache Invalidations', $report['operations']['cache_invalidations']],
            ],
        );

        // Health
        $this->io->newLine();
        $status = $report['health']['status'];
        $color = $status === 'excellent' ? 'green' : ($status === 'good' ? 'yellow' : 'red');

        $this->io->writeln("Status: <fg={$color}>" . strtoupper($status) . '</>');
        $this->io->writeln('Recommendation: ' . $report['health']['recommendation']);

        return self::SUCCESS;
    }

    private function resetMetrics(ACLOptimizer $optimizer): int
    {
        $optimizer->resetMetrics();
        $this->io->success('ACL metrics reset successfully');
        return self::SUCCESS;
    }
}
