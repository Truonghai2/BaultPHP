<?php

declare(strict_types=1);

namespace Modules\Cms\Domain\Services;

use Core\Cache\CacheManager;
use Modules\Cms\Infrastructure\Models\BlockInstance;
use Modules\Cms\Infrastructure\Models\BlockRegion;
use Modules\Cms\Infrastructure\Models\BlockType;
use Psr\Log\LoggerInterface;

/**
 * Block Renderer Domain Service
 *
 * Render blocks for display
 */
class BlockRenderer
{
    public function __construct(
        private readonly BlockRegistry $registry,
        private readonly CacheManager $cache,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Render a single block instance
     *
     * @param BlockInstance $instance Block instance to render
     * @param array|null $userRoles User roles for visibility check
     * @param array|null $context Additional context data from controller/view
     * @return string Rendered HTML
     */
    public function renderBlock(BlockInstance $instance, ?array $userRoles = null, ?array $context = null): string
    {
        try {
            if (!$instance->isVisibleTo($userRoles)) {
                return '';
            }

            $blockType = BlockType::find($instance->block_type_id);
            if (!$blockType) {
                $this->logger->warning('Block type not found', [
                    'block_type_id' => $instance->block_type_id,
                    'instance_id' => $instance->id,
                ]);
                return '';
            }

            $block = $this->registry->getBlock($blockType->name);

            if (!$block) {
                $this->logger->warning('Block type not found in registry', [
                    'block_type' => $blockType->name,
                    'instance_id' => $instance->id,
                ]);
                return '';
            }

            // Skip cache for now (cache API issue)
            // $cacheKey = $this->getCacheKey($instance);
            // if ($block->isCacheable() && empty($context)) {
            //     $cached = $this->cache->get($cacheKey);
            //     if ($cached !== null) {
            //         return $cached;
            //     }
            // }

            $blockContext = array_merge($context ?? [], [
                'title' => $instance->title,
                'content' => $instance->content,
            ]);

            // Ensure config is array (handle cast issues)
            $config = $instance->config;
            if (is_string($config)) {
                $config = json_decode($config, true) ?? [];
            } elseif (!is_array($config)) {
                $config = [];
            }

            $content = $block->render($config, $blockContext);

            $html = $this->wrapBlock($instance, $content, $blockType);

            // Skip cache for now
            // if ($block->isCacheable() && !empty($html) && empty($context)) {
            //     $this->cache->put($cacheKey, $html, $block->getCacheLifetime());
            // }

            return $html;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to render block', [
                'instance_id' => $instance->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            if (config('app.debug')) {
                return sprintf(
                    '<div class="block-error">Block render error: %s</div>',
                    htmlspecialchars($e->getMessage()),
                );
            }

            return '';
        }
    }

    /**
     * Wrap block content in container
     */
    protected function wrapBlock(BlockInstance $instance, string $content, BlockType $blockType): string
    {
        if (empty($content)) {
            return '';
        }

        $cssClasses = 'block block-' . $blockType->name;
        if (!$instance->visible) {
            $cssClasses .= ' block-hidden';
        }

        $html = sprintf(
            '<div class="%s" data-block-id="%d">',
            htmlspecialchars($cssClasses),
            $instance->id,
        );

        $config = $instance->config ?? [];
        $showTitle = $config['show_title'] ?? true;
        if ($instance->title && $showTitle) {
            $html .= sprintf(
                '<div class="block-header"><h3 class="block-title">%s</h3></div>',
                htmlspecialchars($instance->title),
            );
        }

        $html .= sprintf('<div class="block-body">%s</div>', $content);
        $html .= '</div>';

        return $html;
    }

    /**
     * Build HTML attributes string from array
     */
    protected function buildAttributesString(array $attributes): string
    {
        $parts = [];

        foreach ($attributes as $key => $value) {
            $parts[] = sprintf('%s="%s"', $key, htmlspecialchars($value));
        }

        return implode(' ', $parts);
    }

    /**
     * Render all blocks in a region
     *
     * @param string $regionName Region name
     * @param string|null $contextType Context type (global, page, user)
     * @param int|null $contextId Context ID
     * @param array|null $userRoles User roles for visibility
     * @param array|null $context Additional context data from controller/view
     * @return string Rendered HTML
     */
    public function renderRegion(
        string $regionName,
        ?string $contextType = 'global',
        ?int $contextId = null,
        ?array $userRoles = null,
        ?array $context = null,
    ): string {
        try {
            $blocks = $this->getVisibleBlocks($regionName, $contextType, $contextId, $userRoles);

            if (empty($blocks)) {
                return '';
            }

            $html = sprintf(
                '<div class="region region-%s" data-region="%s">',
                htmlspecialchars($regionName),
                htmlspecialchars($regionName),
            );

            foreach ($blocks as $block) {
                $html .= $this->renderBlock($block, $userRoles, $context);
            }

            $html .= '</div>';

            return $html;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to render region', [
                'region' => $regionName,
                'error' => $e->getMessage(),
            ]);

            return '';
        }
    }

    /**
     * Get visible blocks for rendering
     */
    protected function getVisibleBlocks(string $regionName, ?string $contextType, ?int $contextId, ?array $userRoles): array
    {
        $region = BlockRegion::where('name', $regionName)->first();

        if (!$region) {
            return [];
        }

        $query = BlockInstance::where('region_id', $region->id)
            ->where('visible', true)
            ->where('context_type', $contextType ?? 'global');

        if ($contextId !== null) {
            $query->where('context_id', $contextId);
        }

        $blocks = $query->orderBy('weight')->get()->all();

        if ($userRoles !== null) {
            return array_filter(
                $blocks,
                fn (BlockInstance $block) => $block->isVisibleTo($userRoles),
            );
        }

        return $blocks;
    }

    /**
     * Render all regions for a context
     */
    public function renderAllRegions(?string $contextType = 'global', ?int $contextId = null, ?array $userRoles = null): array
    {
        $regions = $this->blockRegionRepository->findAllActive();
        $rendered = [];

        foreach ($regions as $region) {
            $html = $this->renderRegion($region->getName()->getValue(), $contextType, $contextId, $userRoles);
            if (!empty($html)) {
                $rendered[$region->getName()->getValue()] = $html;
            }
        }

        return $rendered;
    }

    /**
     * Clear all block caches
     */
    public function clearCache(): void
    {
        // TODO: Implement bulk cache clear
        // For now, this is a placeholder
    }

    /**
     * Clear cache for specific block instance
     */
    public function clearBlockCache(BlockInstance $instance): void
    {
        $cacheKey = $this->getCacheKey($instance);
        $this->cache->forget($cacheKey);
    }

    /**
     * Get cache key for block instance
     */
    protected function getCacheKey(BlockInstance $instance): string
    {
        return sprintf(
            'block.%d.%s.%s',
            $instance->id,
            $instance->context_type,
            $instance->context_id ?? 'null',
        );
    }
}
