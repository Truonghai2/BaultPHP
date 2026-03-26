<?php

declare(strict_types=1);

namespace Modules\Cms\Domain\Blocks;

/**
 * Footer Block
 *
 * Display footer with links and copyright
 */
class FooterBlock extends AbstractBlock
{
    public function getName(): string
    {
        return 'footer';
    }

    public function getTitle(): string
    {
        return 'Footer';
    }

    public function getDescription(): string
    {
        return 'Display footer with links and copyright';
    }

    public function getCategory(): string
    {
        return 'Layout';
    }

    public function getIcon(): string
    {
        return '🔽';
    }

    public function getDefaultConfig(): array
    {
        return [
            'columns' => [
                [
                    'title' => 'Company',
                    'links' => [
                        ['label' => 'About Us', 'url' => '/about'],
                        ['label' => 'Careers', 'url' => '/careers'],
                        ['label' => 'Contact', 'url' => '/contact'],
                    ],
                ],
                [
                    'title' => 'Resources',
                    'links' => [
                        ['label' => 'Documentation', 'url' => '/docs'],
                        ['label' => 'API', 'url' => '/api'],
                        ['label' => 'Support', 'url' => '/support'],
                    ],
                ],
                [
                    'title' => 'Legal',
                    'links' => [
                        ['label' => 'Privacy Policy', 'url' => '/privacy'],
                        ['label' => 'Terms of Service', 'url' => '/terms'],
                        ['label' => 'Cookie Policy', 'url' => '/cookies'],
                    ],
                ],
            ],
            'copyright' => '© 2025 BaultPHP Framework. All rights reserved.',
            'social_links' => [
                ['platform' => 'GitHub', 'url' => 'https://github.com', 'icon' => '📦'],
                ['platform' => 'Twitter', 'url' => 'https://twitter.com', 'icon' => '🐦'],
            ],
        ];
    }

    public function getConfigSchema(): array
    {
        return [
            'columns' => [
                'type' => 'repeater',
                'label' => 'Footer Columns',
                'fields' => [
                    'title' => ['type' => 'text', 'label' => 'Column Title'],
                    'links' => [
                        'type' => 'repeater',
                        'label' => 'Links',
                        'fields' => [
                            'label' => ['type' => 'text', 'label' => 'Label'],
                            'url' => ['type' => 'text', 'label' => 'URL'],
                        ],
                    ],
                ],
            ],
            'copyright' => ['type' => 'text', 'label' => 'Copyright Text'],
            'social_links' => [
                'type' => 'repeater',
                'label' => 'Social Links',
                'fields' => [
                    'platform' => ['type' => 'text', 'label' => 'Platform'],
                    'url' => ['type' => 'text', 'label' => 'URL'],
                    'icon' => ['type' => 'text', 'label' => 'Icon (Emoji or Class)'],
                ],
            ],
        ];
    }

    public function render(array $config = [], ?array $context = null): string
    {
        $config = array_merge($this->getDefaultConfig(), $config);

        return $this->renderView('cms::blocks.footer', $config);
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
