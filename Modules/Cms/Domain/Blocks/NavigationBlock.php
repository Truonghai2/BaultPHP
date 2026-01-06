<?php

declare(strict_types=1);

namespace Modules\Cms\Domain\Blocks;

/**
 * Navigation Block
 * 
 * Display site navigation menu
 */
class NavigationBlock extends AbstractBlock
{
    public function getName(): string
    {
        return 'navigation';
    }

    public function getTitle(): string
    {
        return 'Navigation Menu';
    }

    public function getDescription(): string
    {
        return 'Display site navigation links';
    }

    public function getCategory(): string
    {
        return 'Navigation';
    }

    public function getIcon(): string
    {
        return '🗺️';
    }

    public function getDefaultConfig(): array
    {
        return [
            'menu_items' => [],
            'style' => 'horizontal',
            'show_icons' => false,
        ];
    }

    public function getConfigSchema(): array
    {
        return [
            'menu_items' => [
                'type' => 'repeater',
                'label' => 'Menu Items',
                'fields' => [
                    'label' => ['type' => 'text', 'label' => 'Label', 'placeholder' => 'Home'],
                    'url' => ['type' => 'text', 'label' => 'URL', 'placeholder' => '/'],
                    'icon' => ['type' => 'text', 'label' => 'Icon Class (Optional)'],
                    'target' => [
                        'type' => 'select',
                        'label' => 'Target',
                        'options' => ['_self' => 'Same Window', '_blank' => 'New Window'],
                        'default' => '_self',
                    ],
                    'sub_items' => [
                        'type' => 'repeater',
                        'label' => 'Sub-menu Items',
                        'fields' => [
                            'label' => ['type' => 'text', 'label' => 'Label'],
                            'url' => ['type' => 'text', 'label' => 'URL'],
                            'target' => [
                                'type' => 'select',
                                'label' => 'Target',
                                'options' => ['_self' => 'Same Window', '_blank' => 'New Window'],
                            ],
                            'sub_items' => [
                                'type' => 'repeater',
                                'label' => 'Sub-sub-menu Items (Level 3)',
                                'fields' => [
                                    'label' => ['type' => 'text', 'label' => 'Label'],
                                    'url' => ['type' => 'text', 'label' => 'URL'],
                                    'target' => [
                                        'type' => 'select',
                                        'label' => 'Target',
                                        'options' => ['_self' => 'Same Window', '_blank' => 'New Window'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function render(array $config = [], ?array $context = null): string
    {
        $config = array_merge($this->getDefaultConfig(), $config);
        
        return $this->renderView('cms::blocks.navigation', $config);
    }

    public function isCacheable(): bool
    {
        return true;
    }

    public function getCacheLifetime(): int
    {
        return 3600;
    }
}
