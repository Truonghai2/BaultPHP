<?php

namespace Modules\User\Console;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Modules\User\Domain\Services\ACLOptimizer;
use Modules\User\Infrastructure\Models\User;

/**
 * ACLSchedulerCommand
 *
 * Scheduled tasks for ACL cache management.
 * Run this via cron for automatic cache maintenance.
 */
class ACLSchedulerCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'acl:schedule 
                {task : Task to run (warm-active|warm-all|cleanup|metrics)}
                {--dry-run : Show what would be done without executing}';
    }

    public function description(): string
    {
        return 'Scheduled ACL cache maintenance tasks';
    }

    public function handle(): int
    {
        /** @var ACLOptimizer $optimizer */
        $optimizer = $this->app->make(ACLOptimizer::class);

        $task = $this->argument('task');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->io->warning('DRY RUN MODE - No changes will be made');
            $this->io->newLine();
        }

        switch ($task) {
            case 'warm-active':
                return $this->warmActiveUsers($optimizer, $dryRun);
            case 'warm-all':
                return $this->warmAllUsers($optimizer, $dryRun);
            case 'cleanup':
                return $this->cleanupStaleCache($optimizer, $dryRun);
            case 'metrics':
                return $this->logMetrics($optimizer);
            default:
                $this->io->error("Unknown task: {$task}");
                $this->io->writeln('Available tasks: warm-active, warm-all, cleanup, metrics');
                return self::FAILURE;
        }
    }

    /**
     * Warm cache for active users (logged in last 7 days).
     */
    private function warmActiveUsers(ACLOptimizer $optimizer, bool $dryRun): int
    {
        $this->io->writeln('<info>Warming cache for active users (last 7 days)...</info>');

        $activeUsers = User::where('updated_at', '>=', date('Y-m-d H:i:s', strtotime('-7 days')))
            ->where('status', '=', 'active')
            ->select('id')
            ->get();

        $userIds = $activeUsers->pluck('id')->toArray();

        if (empty($userIds)) {
            $this->io->warning('No active users found');
            return self::SUCCESS;
        }

        $this->io->writeln('Found ' . count($userIds) . ' active users');

        if ($dryRun) {
            $this->io->writeln('<comment>Would warm cache for these users</comment>');
            return self::SUCCESS;
        }

        $stats = $optimizer->warmCache($userIds);

        $this->io->success('Active users cache warmed!');
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

    /**
     * Warm cache for all users.
     */
    private function warmAllUsers(ACLOptimizer $optimizer, bool $dryRun): int
    {
        $this->io->writeln('<info>Warming cache for ALL users...</info>');

        $allUsers = User::where('status', '=', 'active')
            ->select('id')
            ->get();

        $userIds = $allUsers->pluck('id')->toArray();

        if (empty($userIds)) {
            $this->io->warning('No users found');
            return self::SUCCESS;
        }

        $this->io->writeln('Found ' . count($userIds) . ' users');

        if ($dryRun) {
            $this->io->writeln('<comment>Would warm cache for all users</comment>');
            return self::SUCCESS;
        }

        // Confirm for large number of users
        if (count($userIds) > 1000) {
            $confirm = $this->io->ask(
                'This will warm cache for ' . count($userIds) . ' users. Continue? (yes/no)',
                'no',
            );

            if ($confirm !== 'yes') {
                $this->io->writeln('<comment>Cancelled</comment>');
                return self::SUCCESS;
            }
        }

        $stats = $optimizer->warmCache($userIds);

        $this->io->success('All users cache warmed!');
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

    /**
     * Cleanup stale cache entries.
     */
    private function cleanupStaleCache(ACLOptimizer $optimizer, bool $dryRun): int
    {
        $this->io->writeln('<info>Cleaning up stale cache entries...</info>');

        // Get inactive users (not logged in for 90 days)
        $inactiveUsers = User::where('updated_at', '<', date('Y-m-d H:i:s', strtotime('-90 days')))
            ->select('id')
            ->get();

        $userIds = $inactiveUsers->pluck('id')->toArray();

        if (empty($userIds)) {
            $this->io->writeln('<comment>No stale cache entries found</comment>');
            return self::SUCCESS;
        }

        $this->io->writeln('Found ' . count($userIds) . ' inactive users');

        if ($dryRun) {
            $this->io->writeln('<comment>Would invalidate cache for these users</comment>');
            return self::SUCCESS;
        }

        $optimizer->invalidateBatch($userIds);

        $this->io->success("Invalidated cache for {count($userIds)} inactive users");

        return self::SUCCESS;
    }

    /**
     * Log current metrics.
     */
    private function logMetrics(ACLOptimizer $optimizer): int
    {
        $report = $optimizer->getPerformanceReport();

        $this->io->writeln('<info>Logging ACL metrics...</info>');

        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'cache_performance' => $report['cache_performance'],
            'operations' => $report['operations'],
            'health' => $report['health'],
        ];

        // Log to file
        $logFile = storage_path('logs/acl-metrics-' . date('Y-m-d') . '.log');
        file_put_contents(
            $logFile,
            json_encode($logData, JSON_PRETTY_PRINT) . "\n",
            FILE_APPEND,
        );

        $this->io->success("Metrics logged to: {$logFile}");
        $this->io->writeln('Cache Hit Rate: ' . $report['cache_performance']['hit_rate']);
        $this->io->writeln('Status: ' . strtoupper($report['health']['status']));

        return self::SUCCESS;
    }
}
