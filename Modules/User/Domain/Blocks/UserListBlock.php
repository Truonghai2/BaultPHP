<?php

declare(strict_types=1);

namespace Modules\User\Domain\Blocks;

use Modules\Cms\Domain\Blocks\AbstractBlock;
use Modules\User\Infrastructure\Models\User;

/**
 * User List Block
 * 
 * Displays a list of users (latest, popular, etc.)
 */
class UserListBlock extends AbstractBlock
{
    public function getName(): string
    {
        return 'user-list';
    }

    public function getTitle(): string
    {
        return 'User List';
    }

    public function getDescription(): string
    {
        return 'Display a list of users with filters (latest, active, etc.)';
    }

    public function getCategory(): string
    {
        return 'User';
    }

    public function getIcon(): string
    {
        return '👥';
    }

    public function getDefaultConfig(): array
    {
        return [
            'limit' => 10,
            'order_by' => 'latest', // latest, active, popular
            'show_avatar' => true,
            'show_role' => true,
            'show_joined_date' => true,
            'layout' => 'grid', // grid, list
        ];
    }

    public function render(array $config = [], ?array $context = null): string
    {
        $config = array_merge($this->getDefaultConfig(), $config);
        
        // Get users from database
        $users = $this->getUsers($config);

        return $this->renderView('user::blocks.user-list', array_merge($config, [
            'users' => $users,
        ]));
    }

    /**
     * Get users from database with filters
     */
    protected function getUsers(array $config)
    {
        $query = User::query();

        // Apply ordering
        switch ($config['order_by']) {
            case 'active':
                $query->orderBy('last_login_at', 'desc');
                break;
            case 'popular':
                // TODO: Implement popularity metric
                $query->orderBy('id', 'desc');
                break;
            case 'latest':
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        return $query->limit($config['limit'])->get();
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

