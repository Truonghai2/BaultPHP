<?php

declare(strict_types=1);

namespace Modules\User\Domain\Blocks;

use Modules\Cms\Domain\Blocks\AbstractBlock;

/**
 * User Profile Block
 * 
 * Displays user profile information
 * Can be used in user dashboards, profile pages, etc.
 */
class UserProfileBlock extends AbstractBlock
{
    public function getName(): string
    {
        return 'user-profile';
    }

    public function getTitle(): string
    {
        return 'User Profile';
    }

    public function getDescription(): string
    {
        return 'Display user profile card with avatar, bio, and stats';
    }

    public function getCategory(): string
    {
        return 'User';
    }

    public function getIcon(): string
    {
        return '👤';
    }

    public function getDefaultConfig(): array
    {
        return [
            'show_avatar' => true,
            'show_bio' => true,
            'show_stats' => true,
            'show_social_links' => false,
            'layout' => 'card', // card, minimal, detailed
        ];
    }

    public function render(array $config = [], ?array $context = null): string
    {
        $config = array_merge($this->getDefaultConfig(), $config);
        
        // Get user from context or current auth user
        $user = $context['user'] ?? auth()->user();
        
        if (!$user) {
            return '<div class="empty-state text-center py-8 text-gray-400">
                <p>Please login to view profile</p>
            </div>';
        }

        // Get user stats (from database or service)
        $stats = $this->getUserStats($user);

        return $this->renderView('user::blocks.user-profile', array_merge($config, [
            'user' => $user,
            'stats' => $stats,
        ]));
    }

    /**
     * Get user statistics
     */
    protected function getUserStats($user): array
    {
        // TODO: Implement real stats from database
        return [
            'posts' => 0,
            'followers' => 0,
            'following' => 0,
        ];
    }

    public function isCacheable(): bool
    {
        return false; // User-specific content
    }
}

