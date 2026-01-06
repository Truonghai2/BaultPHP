<?php

declare(strict_types=1);

namespace Modules\Cms\Domain\Blocks;

/**
 * User Menu Block
 *
 * Display login/logout and user profile links
 */
class UserMenuBlock extends AbstractBlock
{
    public function getName(): string
    {
        return 'user-menu';
    }

    public function getTitle(): string
    {
        return 'User Menu';
    }

    public function getDescription(): string
    {
        return 'Display user login/profile menu';
    }

    public function getCategory(): string
    {
        return 'Navigation';
    }

    public function getIcon(): string
    {
        return '👤';
    }

    public function render(array $config = [], ?array $context = null): string
    {
        $user = auth()->user();

        return $this->renderView('cms::blocks.user-menu', [
            'user' => $user,
            'initials' => $user ? $this->getInitials($user->name ?? 'User') : '',
        ]);
    }

    private function getInitials(string $name): string
    {
        $parts = explode(' ', $name);
        $initials = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= strtoupper(substr($part, 0, 1));
        }
        return $initials;
    }

    public function isCacheable(): bool
    {
        return false;
    }
}
