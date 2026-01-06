<?php

declare(strict_types=1);

namespace Modules\Cms\Domain\Blocks;

/**
 * Welcome Banner Block
 *
 * Display welcome message with customizable content
 */
class WelcomeBannerBlock extends AbstractBlock
{
    public function getName(): string
    {
        return 'welcome-banner';
    }

    public function getTitle(): string
    {
        return 'Welcome Banner';
    }

    public function getDescription(): string
    {
        return 'Display welcome banner with title and description';
    }

    public function getCategory(): string
    {
        return 'Content';
    }

    public function getIcon(): string
    {
        return '🎉';
    }

    public function getDefaultConfig(): array
    {
        return [
            'title' => 'Welcome to BaultPHP',
            'subtitle' => 'A Modern PHP Framework',
            'description' => 'Build powerful web applications with ease.',
            'show_button' => true,
            'button_text' => 'Get Started',
            'button_url' => '/docs',
            'style' => 'gradient', // gradient, solid, image
        ];
    }

    public function render(array $config = [], ?array $context = null): string
    {
        $config = array_merge($this->getDefaultConfig(), $config);

        return $this->renderView('cms::blocks.welcome-banner', $config);
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
