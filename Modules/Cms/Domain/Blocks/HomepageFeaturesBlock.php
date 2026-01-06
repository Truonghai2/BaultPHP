<?php

declare(strict_types=1);

namespace Modules\Cms\Domain\Blocks;

/**
 * Homepage Features Block
 *
 * Renders the features grid section with customizable feature cards
 */
class HomepageFeaturesBlock extends AbstractBlock
{
    public function getName(): string
    {
        return 'homepage-features';
    }

    public function getTitle(): string
    {
        return 'Homepage Features Grid';
    }

    public function getDescription(): string
    {
        return 'Features grid with icon, title, and description cards';
    }

    public function getCategory(): string
    {
        return 'Homepage';
    }

    public function getIcon(): string
    {
        return '⭐';
    }

    public function getDefaultConfig(): array
    {
        return [
            'section_title' => 'Production Ready',
            'main_title' => 'Everything you need for modern PHP development',
            'description' => 'BaultPHP combines best practices from DDD, CQRS, and Event Sourcing with modern PHP features to deliver a robust development experience.',

            'features' => [
                [
                    'icon' => '<svg class="h-5 w-5 flex-none text-indigo-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 01-9.201 2.466l-.312-.311h2.433a.75.75 0 000-1.5H3.989a.75.75 0 00-.75.75v4.242a.75.75 0 001.5 0v-2.43l.31.31a7 7 0 0011.712-3.138.75.75 0 00-1.449-.39zm1.23-3.723a.75.75 0 00.219-.53V2.929a.75.75 0 00-1.5 0V5.36l-.31-.31A7 7 0 003.239 8.188a.75.75 0 101.448.389A5.5 5.5 0 0113.89 6.11l.311.31h-2.432a.75.75 0 000 1.5h4.243a.75.75 0 00.53-.219z" clip-rule="evenodd" /></svg>',
                    'title' => 'High Performance',
                    'description' => 'Built on Swoole for lightning-fast response times. Handle thousands of concurrent connections with ease.',
                ],
                [
                    'icon' => '<svg class="h-5 w-5 flex-none text-indigo-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3.5 2A1.5 1.5 0 002 3.5V15a3 3 0 106 0V3.5A1.5 1.5 0 006.5 2h-3zm11.753 6.99L9.5 14.743V6.257l1.51-1.51a1.5 1.5 0 012.122 0l2.121 2.121a1.5 1.5 0 010 2.122zM8.364 18H16.5a1.5 1.5 0 001.5-1.5v-3a1.5 1.5 0 00-1.5-1.5h-2.136l-6 6zM5 16a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" /></svg>',
                    'title' => 'Domain Driven Design',
                    'description' => 'Built with DDD principles. Organize your business logic into clear, maintainable domains.',
                ],
                [
                    'icon' => '<svg class="h-5 w-5 flex-none text-indigo-400" viewBox="0 0 20 20" fill="currentColor"><path d="M2 4.25A2.25 2.25 0 014.25 2h11.5A2.25 2.25 0 0118 4.25v8.5A2.25 2.25 0 0115.75 15h-3.105a3.501 3.501 0 001.1 1.677A.75.75 0 0113.26 18H6.74a.75.75 0 01-.484-1.323A3.501 3.501 0 007.355 15H4.25A2.25 2.25 0 012 12.75v-8.5zm1.5 0a.75.75 0 01.75-.75h11.5a.75.75 0 01.75.75v7.5a.75.75 0 01-.75.75H4.25a.75.75 0 01-.75-.75v-7.5z" /></svg>',
                    'title' => 'Plugin System',
                    'description' => 'Extend functionality with a powerful plugin system. Install and manage modules with ease.',
                ],
            ],
        ];
    }

    public function render(array $config = [], ?array $context = null): string
    {
        $config = array_merge($this->getDefaultConfig(), $config);

        return $this->renderView('cms::blocks.homepage-features', $config);
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
