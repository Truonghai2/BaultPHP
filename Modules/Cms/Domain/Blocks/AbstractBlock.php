<?php

namespace Modules\Cms\Domain\Blocks;

use Modules\Cms\Infrastructure\Models\BlockInstance;
use Modules\User\Infrastructure\Models\User;

/**
 * Abstract Block Base Class
 * 
 * Tất cả blocks phải extend class này
 * Giống như block_base trong Moodle
 */
abstract class AbstractBlock
{
    /**
     * Block name (unique identifier)
     */
    abstract public function getName(): string;

    /**
     * Block title (human readable)
     */
    abstract public function getTitle(): string;

    /**
     * Block description
     */
    abstract public function getDescription(): string;

    /**
     * Block category
     */
    public function getCategory(): string
    {
        return 'general';
    }

    /**
     * Icon class (font-awesome, heroicons...)
     */
    public function getIcon(): ?string
    {
        return 'fa-cube';
    }

    /**
     * Block có thể configure không
     */
    public function isConfigurable(): bool
    {
        return true;
    }

    /**
     * Default configuration
     */
    public function getDefaultConfig(): array
    {
        return [];
    }

    /**
     * Configuration schema (for form generation)
     */
    public function getConfigSchema(): array
    {
        return [];
    }

    /**
     * Preload data for a collection of blocks of the same type
     * 
     * This is a PERFORMANCE OPTIMIZATION hook that allows blocks to batch-load
     * data for multiple instances in a single query instead of N queries.
     * 
     * WHEN TO USE:
     * - Block needs to fetch data from database/API
     * - Multiple instances of same block type on a page
     * - Want to avoid N+1 query problem
     * 
     * EXAMPLE:
     * ```php
     * public function preloadData(\Core\Support\Collection $blocks): array
     * {
     *     $data = [];
     *     foreach ($blocks as $block) {
     *         $config = $block->getConfig();
     *         $data[$block->id] = [
     *             'items' => Item::where('category', $config['category'])->get()
     *         ];
     *     }
     *     return $data;
     * }
     * ```
     * 
     * The preloaded data will be available in render() via:
     * `$context['preloaded']`
     *
     * @param \Core\Support\Collection $blocks Collection of PageBlock instances
     * @return array<int, array> Preloaded data, keyed by block ID
     */
    public function preloadData(\Core\Support\Collection $blocks): array
    {
        return [];
    }

    /**
     * Render block content
     * 
     * @param array $config Block configuration
     * @param array|null $context Additional context data (user, page, etc.)
     * @return string HTML content
     */
    abstract public function render(array $config = [], ?array $context = null): string;

    /**
     * Get content (helper method)
     */
    protected function getContent(BlockInstance $instance): string
    {
        return $instance->content ?? '';
    }

    /**
     * Get config value
     */
    protected function getConfig(BlockInstance $instance, string $key, $default = null)
    {
        return $instance->config[$key] ?? $default;
    }

    /**
     * Render với view file
     * 
     * @param string $view View path (e.g., 'cms::blocks.homepage-stats')
     * @param array $data Data to pass to view
     * @return string Rendered HTML
     */
    protected function renderView(string $view, array $data = []): string
    {
        if (function_exists('view')) {
            try {
                $viewFactory = view();
                
                if (str_starts_with($view, 'cms::')) {
                    $this->ensureCmsNamespaceRegistered($viewFactory);
                }
                
                return $viewFactory->make($view, $data)->render();
            } catch (\Throwable $e) {
                if (function_exists('logger')) {
                    logger()->error("Block view render failed", [
                        'block' => get_class($this),
                        'view' => $view,
                        'error' => $e->getMessage(),
                    ]);
                }
                
                if (config('app.debug')) {
                    return "<!-- Block view error: {$e->getMessage()} -->";
                }
            }
        }

        return '';
    }

    /**
     * Ensure CMS namespace is registered (safety fallback)
     */
    private function ensureCmsNamespaceRegistered($viewFactory): void
    {
        static $registered = false;
        
        if (!$registered) {
            try {
                if (!$viewFactory->exists('cms::blocks.homepage-stats')) {
                    $cmsPath = dirname(__DIR__, 2) . '/Resources/views';
                    if (is_dir($cmsPath) && method_exists($viewFactory, 'addNamespace')) {
                        $viewFactory->addNamespace('cms', $cmsPath);
                    }
                }
                $registered = true;
            } catch (\Throwable $e) {
            }
        }
    }

    /**
     * Render block with automatic view resolution
     * 
     * Automatically looks for view at: cms::blocks.{block-name}
     * Falls back to render() method if view not found
     * 
     * @param array $config Block configuration
     * @param array|null $context Additional context
     * @return string Rendered HTML
     */
    protected function renderWithAutoView(array $config = [], ?array $context = null): string
    {
        $viewName = 'cms::blocks.' . $this->getName();
        
        if ($this->viewExists($viewName)) {
            return $this->renderView($viewName, array_merge($config, [
                'context' => $context,
                'block' => $this,
            ]));
        }
        
        return $this->render($config, $context);
    }

    /**
     * Check if view exists
     * 
     * @param string $view View path
     * @return bool
     */
    protected function viewExists(string $view): bool
    {
        if (function_exists('view')) {
            try {
                return view()->exists($view);
            } catch (\Throwable $e) {
                return false;
            }
        }
        
        return false;
    }

    /**
     * Get view path for this block
     * 
     * @return string
     */
    protected function getViewPath(): string
    {
        return 'cms::blocks.' . $this->getName();
    }

    /**
     * Render component (reusable UI piece)
     * 
     * @param string $component Component name (e.g., 'stat-card')
     * @param array $data Component data
     * @return string Rendered HTML
     */
    protected function renderComponent(string $component, array $data = []): string
    {
        $viewPath = 'cms::blocks.components.' . $component;
        return $this->renderView($viewPath, $data);
    }

    /**
     * Before save hook
     */
    public function beforeSave(BlockInstance $instance): void
    {
    }

    /**
     * After save hook
     */
    public function afterSave(BlockInstance $instance): void
    {
    }

    /**
     * Before delete hook
     */
    public function beforeDelete(BlockInstance $instance): void
    {
    }

    /**
     * After delete hook
     */
    public function afterDelete(BlockInstance $instance): void
    {
    }

    /**
     * Validate config data
     */
    public function validateConfig(array $config): array
    {
        return [];
    }

    /**
     * Has custom settings
     */
    public function hasSettings(): bool
    {
        return !empty($this->getConfigSchema());
    }

    /**
     * Applicable contexts - where can this block be added
     */
    public function getApplicableContexts(): array
    {
        return ['global', 'page', 'user'];
    }

    /**
     * Get block version
     */
    public function getVersion(): int
    {
        return 1;
    }

    /**
     * Can user add this block?
     */
    public function canAdd(User $user): bool
    {
        return $user->can('cms.blocks.manage');
    }

    /**
     * Can user edit this block instance?
     */
    public function canEdit(User $user, BlockInstance $instance): bool
    {
        return $user->can('cms.blocks.manage');
    }

    /**
     * Can user delete this block instance?
     */
    public function canDelete(User $user, BlockInstance $instance): bool
    {
        return $user->can('cms.blocks.manage');
    }

    /**
     * Get CSS classes for block wrapper
     */
    public function getCssClasses(): array
    {
        return ['block', 'block-' . $this->getName()];
    }

    /**
     * Get additional attributes for block wrapper
     */
    public function getAttributes(): array
    {
        return [];
    }

    /**
     * Block có cache được không?
     */
    public function isCacheable(): bool
    {
        return false;
    }

    /**
     * Cache lifetime (seconds)
     */
    public function getCacheLifetime(): int
    {
        return 3600;
    }

    /**
     * Cache key for this block instance
     */
    public function getCacheKey(BlockInstance $instance): string
    {
        return sprintf(
            'block:%s:%d:%s',
            $this->getName(),
            $instance->id,
            md5(serialize($instance->config))
        );
    }
}
