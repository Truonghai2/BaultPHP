<?php

declare(strict_types=1);

namespace Modules\Cms\Domain\Blocks;

/**
 * Search Block
 * 
 * Display search form
 */
class SearchBlock extends AbstractBlock
{
    public function getName(): string
    {
        return 'search';
    }

    public function getTitle(): string
    {
        return 'Search';
    }

    public function getDescription(): string
    {
        return 'Display search form';
    }

    public function getCategory(): string
    {
        return 'Navigation';
    }

    public function getIcon(): string
    {
        return '🔍';
    }

    public function getDefaultConfig(): array
    {
        return [
            'placeholder' => 'Search...',
            'action' => '/search',
            'method' => 'GET',
            'show_button' => true,
        ];
    }

    public function render(array $config = [], ?array $context = null): string
    {
        $config = array_merge($this->getDefaultConfig(), $config);
        
        return $this->renderView('cms::blocks.search', $config);
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

