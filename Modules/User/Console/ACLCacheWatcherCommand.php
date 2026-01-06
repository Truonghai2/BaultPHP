<?php

namespace Modules\User\Console;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Modules\User\Domain\Services\ACLOptimizer;
use Modules\User\Infrastructure\Models\User;

/**
 * ACLCacheWatcherCommand
 *
 * Watches for role/permission changes and auto-invalidates cache.
 * Runs continuously like a file watcher.
 */
class ACLCacheWatcherCommand extends BaseCommand
{
    private bool $shouldStop = false;
    private int $checkInterval = 5; // seconds
    private array $lastChecks = [];

    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'acl:watch 
                {--interval=5 : Check interval in seconds}
                {--warmup : Warm cache on startup}';
    }

    public function description(): string
    {
        return 'Watch for ACL changes and auto-invalidate cache (runs continuously)';
    }

    public function handle(): int
    {
        /** @var ACLOptimizer $optimizer */
        $optimizer = $this->app->make(ACLOptimizer::class);

        $this->checkInterval = (int) ($this->option('interval') ?? 5);

        // Setup signal handlers for graceful shutdown
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, [$this, 'handleSignal']);
            pcntl_signal(SIGINT, [$this, 'handleSignal']);
        }

        $this->io->success('ACL Cache Watcher started!');
        $this->io->writeln("<info>Checking every {$this->checkInterval} seconds...</info>");
        $this->io->writeln('<comment>Press Ctrl+C to stop</comment>');
        $this->io->newLine();

        // Warmup cache on startup if requested
        if ($this->option('warmup')) {
            $this->io->writeln('<info>Initial cache warmup...</info>');
            $this->warmupActiveUsers($optimizer);
        }

        // Initialize last check timestamps
        $this->initializeLastChecks();

        $iteration = 0;
        while (!$this->shouldStop) {
            $iteration++;
            $startTime = microtime(true);

            $this->io->writeln('<fg=cyan>[' . date('Y-m-d H:i:s') . "]</> Checking for changes... (iteration #{$iteration})");

            $changes = $this->checkForChanges($optimizer);

            if ($changes['invalidated'] > 0 || $changes['warmed'] > 0) {
                $this->io->writeln("<fg=green>✓</> Invalidated: {$changes['invalidated']}, Warmed: {$changes['warmed']}");
            } else {
                $this->io->writeln('<fg=gray>○</> No changes detected');
            }

            // Show metrics every 10 iterations
            if ($iteration % 10 === 0) {
                $this->showQuickMetrics($optimizer);
            }

            $elapsed = microtime(true) - $startTime;
            $sleepTime = max(0, $this->checkInterval - $elapsed);

            if ($sleepTime > 0 && !$this->shouldStop) {
                sleep((int) $sleepTime);
            }

            // Process signals if available
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
        }

        $this->io->newLine();
        $this->io->writeln('<info>ACL Cache Watcher stopped gracefully</info>');

        return self::SUCCESS;
    }

    /**
     * Initialize last check timestamps.
     */
    private function initializeLastChecks(): void
    {
        $this->lastChecks = [
            'role_assignments' => $this->getTableLastUpdate('role_assignments'),
            'permission_role' => $this->getTableLastUpdate('permission_role'),
            'roles' => $this->getTableLastUpdate('roles'),
            'permissions' => $this->getTableLastUpdate('permissions'),
        ];
    }

    /**
     * Check for changes and invalidate/warm cache as needed.
     */
    private function checkForChanges(ACLOptimizer $optimizer): array
    {
        $invalidated = 0;
        $warmed = 0;

        // Check role_assignments table
        $currentRoleAssignments = $this->getTableLastUpdate('role_assignments');
        if ($currentRoleAssignments > $this->lastChecks['role_assignments']) {
            $affectedUsers = $this->getRecentlyChangedUsers('role_assignments', $this->lastChecks['role_assignments']);
            $invalidated += count($affectedUsers);
            $optimizer->invalidateBatch($affectedUsers);

            // Optional: warm immediately
            if (count($affectedUsers) <= 10) {
                $optimizer->warmCache($affectedUsers);
                $warmed += count($affectedUsers);
            }

            $this->lastChecks['role_assignments'] = $currentRoleAssignments;
            $this->io->writeln("  <fg=yellow>→</> Role assignments changed ({$invalidated} users affected)");
        }

        // Check permission_role table
        $currentPermissionRole = $this->getTableLastUpdate('permission_role');
        if ($currentPermissionRole > $this->lastChecks['permission_role']) {
            // Invalidate all users with affected roles
            $affectedRoles = $this->getRecentlyChangedRoles($this->lastChecks['permission_role']);
            $affectedUsers = $this->getUsersByRoles($affectedRoles);
            $invalidated += count($affectedUsers);
            $optimizer->invalidateBatch($affectedUsers);

            $this->lastChecks['permission_role'] = $currentPermissionRole;
            $this->io->writeln("  <fg=yellow>→</> Role permissions changed ({$invalidated} users affected)");
        }

        return [
            'invalidated' => $invalidated,
            'warmed' => $warmed,
        ];
    }

    /**
     * Get last update timestamp for a table.
     */
    private function getTableLastUpdate(string $table): int
    {
        try {
            $result = $this->app->make('db')->select(
                "SELECT MAX(UNIX_TIMESTAMP(updated_at)) as max_time FROM {$table}",
            );
            return (int) ($result[0]->max_time ?? time());
        } catch (\Exception $e) {
            return time();
        }
    }

    /**
     * Get users affected by recent role assignment changes.
     */
    private function getRecentlyChangedUsers(string $table, int $since): array
    {
        try {
            $result = $this->app->make('db')->select(
                "SELECT DISTINCT user_id FROM {$table} WHERE UNIX_TIMESTAMP(updated_at) > ?",
                [$since],
            );
            return array_column($result, 'user_id');
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get roles that had permission changes.
     */
    private function getRecentlyChangedRoles(int $since): array
    {
        try {
            $result = $this->app->make('db')->select(
                'SELECT DISTINCT role_id FROM permission_role WHERE UNIX_TIMESTAMP(created_at) > ?',
                [$since],
            );
            return array_column($result, 'role_id');
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get users assigned to specific roles.
     */
    private function getUsersByRoles(array $roleIds): array
    {
        if (empty($roleIds)) {
            return [];
        }

        try {
            $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
            $result = $this->app->make('db')->select(
                "SELECT DISTINCT user_id FROM role_assignments WHERE role_id IN ({$placeholders})",
                $roleIds,
            );
            return array_column($result, 'user_id');
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Warmup cache for active users.
     */
    private function warmupActiveUsers(ACLOptimizer $optimizer): void
    {
        $activeUsers = User::where('updated_at', '>=', date('Y-m-d H:i:s', strtotime('-7 days')))
            ->where('status', '=', 'active')
            ->select('id')
            ->limit(500)
            ->get();

        $userIds = $activeUsers->pluck('id')->toArray();

        if (!empty($userIds)) {
            $stats = $optimizer->warmCache($userIds);
            $this->io->writeln("  <fg=green>✓</> Warmed cache for {$stats['warmed']} active users");
        }
    }

    /**
     * Show quick metrics.
     */
    private function showQuickMetrics(ACLOptimizer $optimizer): void
    {
        $hitRate = $optimizer->getCacheHitRate();
        $color = $hitRate >= 95 ? 'green' : ($hitRate >= 85 ? 'yellow' : 'red');

        $this->io->newLine();
        $this->io->writeln("  <fg={$color}>Cache Hit Rate: {$hitRate}%</>");
        $this->io->newLine();
    }

    /**
     * Handle termination signals.
     *
     * @param int $signal
     * @param int|false $previousExitCode
     * @return int|false
     */
    public function handleSignal(int $signal, int|false $previousExitCode = 0): int|false
    {
        $this->shouldStop = true;
        $this->io->newLine();
        $this->io->writeln('<comment>Received stop signal, shutting down gracefully...</comment>');

        return false; // Continue with default signal handling
    }
}
