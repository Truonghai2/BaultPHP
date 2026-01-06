<?php

declare(strict_types=1);

namespace Modules\Cms\Domain\Blocks;

/**
 * Stats Block
 * 
 * Display statistics with icons and numbers
 */
class StatsBlock extends AbstractBlock
{
    public function getName(): string
    {
        return 'stats';
    }

    public function getTitle(): string
    {
        return 'Statistics';
    }

    public function getDescription(): string
    {
        return 'Display statistics cards with numbers';
    }

    public function getCategory(): string
    {
        return 'Content';
    }

    public function getIcon(): string
    {
        return '📊';
    }

    public function getDefaultConfig(): array
    {
        return [
            'stats' => [
                ['label' => 'Total Users', 'value' => '1,234', 'icon' => '👥', 'color' => 'blue'],
                ['label' => 'Active Projects', 'value' => '56', 'icon' => '🚀', 'color' => 'green'],
                ['label' => 'Completed', 'value' => '789', 'icon' => '✅', 'color' => 'purple'],
                ['label' => 'Revenue', 'value' => '$12.5K', 'icon' => '💰', 'color' => 'orange'],
            ],
            'columns' => 4,
            'show_icons' => true,
        ];
    }

    public function render(array $config = [], ?array $context = null): string
    {
        $config = array_merge($this->getDefaultConfig(), $config);
        
        return $this->renderView('cms::blocks.stats', $config);
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

