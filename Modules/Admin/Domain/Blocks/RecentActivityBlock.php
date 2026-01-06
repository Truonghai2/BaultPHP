<?php

declare(strict_types=1);

namespace Modules\Admin\Domain\Blocks;

use Modules\Cms\Domain\Blocks\AbstractBlock;

/**
 * Recent Activity Block
 * 
 * Displays recent activity log for admins
 */
class RecentActivityBlock extends AbstractBlock
{
    public function getName(): string
    {
        return 'recent-activity';
    }

    public function getTitle(): string
    {
        return 'Recent Activity';
    }

    public function getDescription(): string
    {
        return 'Display recent admin/user activity log';
    }

    public function getCategory(): string
    {
        return 'Admin';
    }

    public function getIcon(): string
    {
        return '📝';
    }

    public function getDefaultConfig(): array
    {
        return [
            'limit' => 10,
            'show_user' => true,
            'show_timestamp' => true,
            'show_action_type' => true,
        ];
    }

    public function render(array $config = [], ?array $context = null): string
    {
        $config = array_merge($this->getDefaultConfig(), $config);
        
        // Check admin permission
        if (!auth()->check() || !auth()->user()->can('admin.activity.view')) {
            return '';
        }

        // Get recent activities
        $activities = $this->getRecentActivities($config['limit']);

        return $this->renderView('admin::blocks.recent-activity', array_merge($config, [
            'activities' => $activities,
        ]));
    }

    /**
     * Get recent activities
     */
    protected function getRecentActivities(int $limit): array
    {
        // TODO: Implement activity log system
        // For now, return placeholder data
        return [
            [
                'user' => 'Admin User',
                'action' => 'Created page',
                'target' => 'About Us',
                'timestamp' => now()->subMinutes(5),
                'type' => 'create',
            ],
            [
                'user' => 'Editor',
                'action' => 'Updated block',
                'target' => 'Homepage Hero',
                'timestamp' => now()->subMinutes(15),
                'type' => 'update',
            ],
        ];
    }

    public function isCacheable(): bool
    {
        return false; // Real-time activity
    }
}

