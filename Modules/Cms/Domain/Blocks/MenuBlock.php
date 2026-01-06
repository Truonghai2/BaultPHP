<?php

namespace Modules\Cms\Domain\Blocks;

/**
 * Menu Block
 *
 * Block để hiển thị navigation menu
 */
class MenuBlock extends AbstractBlock
{
    public function getName(): string
    {
        return 'menu';
    }

    public function getTitle(): string
    {
        return 'Navigation Menu';
    }

    public function getDescription(): string
    {
        return 'Display a navigation menu with custom links';
    }

    public function getCategory(): string
    {
        return 'navigation';
    }

    public function getIcon(): ?string
    {
        return 'fa-bars';
    }

    public function getDefaultConfig(): array
    {
        return [
            'menu_items' => [],
            'style' => 'vertical',
            'show_icons' => false,
        ];
    }

    public function getConfigSchema(): array
    {
        return [
            'style' => [
                'type' => 'select',
                'label' => 'Menu Style',
                'options' => [
                    'vertical' => 'Vertical',
                    'horizontal' => 'Horizontal',
                    'dropdown' => 'Dropdown',
                ],
                'default' => 'vertical',
            ],
            'show_icons' => [
                'type' => 'checkbox',
                'label' => 'Show Icons',
                'default' => false,
            ],
            'menu_items' => [
                'type' => 'repeater',
                'label' => 'Menu Items',
                'fields' => [
                    'label' => ['type' => 'text', 'label' => 'Label'],
                    'url' => ['type' => 'text', 'label' => 'URL'],
                    'icon' => ['type' => 'text', 'label' => 'Icon Class'],
                    'target' => [
                        'type' => 'select',
                        'label' => 'Target',
                        'options' => ['_self' => 'Same Window', '_blank' => 'New Window'],
                    ],
                ],
            ],
        ];
    }

    public function render(array $config = [], ?array $context = null): string
    {
        $config = array_merge($this->getDefaultConfig(), $config);

        return $this->renderView('cms::blocks.menu', $config);
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
