<?php

declare(strict_types=1);

namespace Modules\Admin\Domain\Blocks;

use Modules\Cms\Domain\Blocks\AbstractBlock;

/**
 * Admin Dashboard Stats Block
 *
 * Displays key metrics for admin dashboard
 */
class AdminDashboardStatsBlock extends AbstractBlock
{
    public function getName(): string
    {
        return 'admin-dashboard-stats';
    }

    public function getTitle(): string
    {
        return 'Admin Dashboard Statistics';
    }

    public function getDescription(): string
    {
        return 'Display key admin metrics (users, pages, blocks, system)';
    }

    public function getCategory(): string
    {
        return 'Admin';
    }

    public function getIcon(): string
    {
        return '📊';
    }

    public function getDefaultConfig(): array
    {
        return [
            'show_users' => true,
            'show_pages' => true,
            'show_blocks' => true,
            'show_system' => true,
        ];
    }

    public function render(array $config = [], ?array $context = null): string
    {
        $config = array_merge($this->getDefaultConfig(), $config);

        // Check admin permission
        if (!auth()->check() || !auth()->user()->can('admin.dashboard.view')) {
            return '<div class="text-center py-8 text-red-400">Access Denied</div>';
        }

        // Get stats
        $stats = $this->getAdminStats();

        return $this->renderView('admin::blocks.dashboard-stats', array_merge($config, [
            'stats' => $stats,
        ]));
    }

    /**
     * Get admin statistics
     */
    protected function getAdminStats(): array
    {
        return [
            'users' => [
                'total' => \Modules\User\Infrastructure\Models\User::count(),
                'active_today' => \Modules\User\Infrastructure\Models\User::where('last_login_at', '>=', now()->subDay())->count(),
                'new_today' => \Modules\User\Infrastructure\Models\User::where('created_at', '>=', now()->subDay())->count(),
            ],
            'pages' => [
                'total' => \Modules\Cms\Infrastructure\Models\Page::count(),
                'published' => \Modules\Cms\Infrastructure\Models\Page::where('status', 'published')->count(),
                'draft' => \Modules\Cms\Infrastructure\Models\Page::where('status', 'draft')->count(),
            ],
            'blocks' => [
                'total' => \Modules\Cms\Infrastructure\Models\BlockType::count(),
                'page_blocks' => \Modules\Cms\Infrastructure\Models\PageBlock::count(),
            ],
            'system' => [
                'php_version' => phpversion(),
                'memory_usage' => round(memory_get_usage() / 1024 / 1024, 2) . ' MB',
                'uptime' => $this->getSystemUptime(),
            ],
        ];
    }

    /**
     * Get system uptime (placeholder)
     */
    protected function getSystemUptime(): string
    {
        // TODO: Implement real uptime tracking
        return 'N/A';
    }

    public function isCacheable(): bool
    {
        return false; // Real-time admin stats
    }
}
