<?php

declare(strict_types=1);

namespace Modules\Admin\Domain\Blocks;

use Modules\Cms\Domain\Blocks\AbstractBlock;

/**
 * Quick Actions Block
 *
 * Displays quick action buttons for admin
 */
class QuickActionsBlock extends AbstractBlock
{
    public function getName(): string
    {
        return 'quick-actions';
    }

    public function getTitle(): string
    {
        return 'Quick Actions';
    }

    public function getDescription(): string
    {
        return 'Display quick action buttons for common admin tasks';
    }

    public function getCategory(): string
    {
        return 'Admin';
    }

    public function getIcon(): string
    {
        return '⚡';
    }

    public function getDefaultConfig(): array
    {
        return [
            'actions' => [
                [
                    'label' => 'New Page',
                    'icon' => '📄',
                    'url' => '/admin/pages/create',
                    'color' => 'indigo',
                    'permission' => 'cms.pages.create',
                ],
                [
                    'label' => 'New User',
                    'icon' => '👤',
                    'url' => '/admin/users/create',
                    'color' => 'blue',
                    'permission' => 'users.create',
                ],
                [
                    'label' => 'Sync Blocks',
                    'icon' => '🔄',
                    'url' => '/admin/blocks/sync',
                    'color' => 'purple',
                    'permission' => 'cms.blocks.sync',
                ],
                [
                    'label' => 'Settings',
                    'icon' => '⚙️',
                    'url' => '/admin/settings',
                    'color' => 'gray',
                    'permission' => 'admin.settings.view',
                ],
            ],
        ];
    }

    public function render(array $config = [], ?array $context = null): string
    {
        $config = array_merge($this->getDefaultConfig(), $config);

        // Filter actions by permission
        $user = auth()->user();
        $allowedActions = [];

        foreach ($config['actions'] as $action) {
            if (empty($action['permission']) || ($user && $user->can($action['permission']))) {
                $allowedActions[] = $action;
            }
        }

        return $this->renderView('admin::blocks.quick-actions', array_merge($config, [
            'actions' => $allowedActions,
        ]));
    }

    public function isCacheable(): bool
    {
        return false; // User-specific permissions
    }
}
