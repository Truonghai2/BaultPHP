<?php

declare(strict_types=1);

namespace Modules\Cms\Domain\Blocks;

/**
 * Homepage Stats Block
 *
 * Renders the statistics section with customizable metrics
 */
class HomepageStatsBlock extends AbstractBlock
{
    public function getName(): string
    {
        return 'homepage-stats';
    }

    public function getTitle(): string
    {
        return 'Homepage Statistics Section';
    }

    public function getDescription(): string
    {
        return 'Statistics section with customizable metrics and values';
    }

    public function getCategory(): string
    {
        return 'Homepage';
    }

    public function getIcon(): string
    {
        return '📊';
    }

    public function getDefaultConfig(): array
    {
        return [
            'title' => 'Trusted by developers worldwide',
            'description' => 'Join the growing community of developers building with BaultPHP',

            'stats' => [
                [
                    'label' => 'Active Installations',
                    'value' => '12k+',
                ],
                [
                    'label' => 'GitHub Stars',
                    'value' => '5k+',
                ],
                [
                    'label' => 'Contributors',
                    'value' => '250+',
                ],
                [
                    'label' => 'Available Plugins',
                    'value' => '100+',
                ],
            ],
        ];
    }

    public function render(array $config = [], ?array $context = null): string
    {
        $config = array_merge($this->getDefaultConfig(), $config);

        // Color schemes for stat cards
        $colors = [
            ['from' => 'indigo', 'to' => 'blue'],
            ['from' => 'purple', 'to' => 'pink'],
            ['from' => 'cyan', 'to' => 'teal'],
            ['from' => 'orange', 'to' => 'red'],
        ];

        // Render using Blade template (much cleaner!)
        return $this->renderView('cms::blocks.homepage-stats', [
            'title' => $config['title'],
            'description' => $config['description'],
            'stats' => $config['stats'],
            'colors' => $colors,
        ]);
    }

    public function isCacheable(): bool
    {
        return true;
    }

    public function getCacheLifetime(): int
    {
        return 1800; // 30 minutes (stats might update more frequently)
    }
}
