<?php

declare(strict_types=1);

namespace Modules\Cms\Domain\Blocks;

/**
 * Team Block - Displays team members
 * Self-contained: Fetches its own data
 */
class TeamBlock extends AbstractBlock
{
    public function getName(): string
    {
        return 'team';
    }

    public function getTitle(): string
    {
        return 'Team Members';
    }

    public function getDescription(): string
    {
        return 'Display team members with avatars and roles';
    }

    public function getDefaultConfig(): array
    {
        return [
            'layout' => 'grid', // grid, list, carousel
            'columns' => 3,
            'show_avatar' => true,
            'show_role' => true,
            'show_bio' => false,
            'team' => [], // Team members data from config, not hardcoded
        ];
    }

    public function render(array $config = [], ?array $context = null): string
    {
        $config = array_merge($this->getDefaultConfig(), $config);

        $team = $context['team'] ?? $config['team'] ?? [];

        // If no team data, try fetch from database
        if (empty($team) && function_exists('model')) {
            // $team = model('TeamMember')::active()->get()->toArray();
        }

        return $this->renderView('cms::blocks.team', array_merge($config, [
            'team' => $team,
        ]));
    }
}
