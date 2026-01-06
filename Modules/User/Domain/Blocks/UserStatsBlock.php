<?php

declare(strict_types=1);

namespace Modules\User\Domain\Blocks;

use Modules\Cms\Domain\Blocks\AbstractBlock;
use Modules\User\Infrastructure\Models\User;

/**
 * User Statistics Block
 *
 * Displays platform-wide user statistics
 */
class UserStatsBlock extends AbstractBlock
{
    public function getName(): string
    {
        return 'user-stats';
    }

    public function getTitle(): string
    {
        return 'User Statistics';
    }

    public function getDescription(): string
    {
        return 'Display platform user statistics (total users, active, new, etc.)';
    }

    public function getCategory(): string
    {
        return 'User';
    }

    public function getIcon(): string
    {
        return '📊';
    }

    public function getDefaultConfig(): array
    {
        return [
            'show_total' => true,
            'show_active' => true,
            'show_new' => true,
            'show_online' => false,
            'period' => '24h', // 24h, 7d, 30d, all
        ];
    }

    public function render(array $config = [], ?array $context = null): string
    {
        $config = array_merge($this->getDefaultConfig(), $config);

        // Get real stats from database
        $stats = $this->getStats($config['period']);

        return $this->renderView('user::blocks.user-stats', array_merge($config, [
            'stats' => $stats,
        ]));
    }

    /**
     * Get user statistics from database
     */
    protected function getStats(string $period): array
    {
        $stats = [];

        // Total users
        $stats['total'] = User::count();

        // Active users (based on last login)
        $periodDate = $this->getPeriodDate($period);
        if ($periodDate) {
            $stats['active'] = User::where('last_login_at', '>=', $periodDate)->count();
            $stats['new'] = User::where('created_at', '>=', $periodDate)->count();
        } else {
            $stats['active'] = User::whereNotNull('last_login_at')->count();
            $stats['new'] = 0;
        }

        // Online users (logged in within last 5 minutes)
        $stats['online'] = User::where('last_login_at', '>=', now()->subMinutes(5))->count();

        return $stats;
    }

    /**
     * Convert period string to date
     */
    protected function getPeriodDate(string $period): ?\Carbon\Carbon
    {
        return match($period) {
            '24h' => now()->subDay(),
            '7d' => now()->subWeek(),
            '30d' => now()->subMonth(),
            default => null,
        };
    }

    public function isCacheable(): bool
    {
        return true;
    }

    public function getCacheLifetime(): int
    {
        return 300; // 5 minutes
    }
}
