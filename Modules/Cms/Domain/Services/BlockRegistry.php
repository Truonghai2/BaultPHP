<?php

namespace Modules\Cms\Domain\Services;

use Core\Cache\CacheManager;
use Modules\Cms\Domain\Blocks\AbstractBlock;
use Modules\Cms\Infrastructure\Models\BlockType;
use Psr\Log\LoggerInterface;

/**
 * Block Registry Service
 *
 * Quản lý việc đăng ký và discovery các block types trong hệ thống
 * Giống như plugin registry trong Moodle
 */
class BlockRegistry
{
    private const CACHE_KEY = 'cms:block_registry';
    private const CACHE_TTL = 3600;

    /**
     * Registered block instances
     * @var array<string, AbstractBlock>
     */
    private array $blocks = [];

    /**
     * Block classes to register
     * @var array<string>
     */
    private array $blockClasses = [
        // Basic Content Blocks
        \Modules\Cms\Domain\Blocks\TextBlock::class,
        \Modules\Cms\Domain\Blocks\HtmlBlock::class,

        // Navigation Blocks
        \Modules\Cms\Domain\Blocks\MenuBlock::class,
        \Modules\Cms\Domain\Blocks\NavigationBlock::class,
        \Modules\Cms\Domain\Blocks\UserMenuBlock::class,
        \Modules\Cms\Domain\Blocks\SearchBlock::class,

        // Content Blocks
        \Modules\Cms\Domain\Blocks\RecentPagesBlock::class,
        \Modules\Cms\Domain\Blocks\WelcomeBannerBlock::class,
        \Modules\Cms\Domain\Blocks\StatsBlock::class,
        \Modules\Cms\Domain\Blocks\TeamBlock::class,

        // Homepage Blocks (Match 100% original design)
        \Modules\Cms\Domain\Blocks\HomepageHeroBlock::class,
        \Modules\Cms\Domain\Blocks\HomepageFeaturesBlock::class,
        \Modules\Cms\Domain\Blocks\HomepageStatsBlock::class,

        // Layout Blocks
        \Modules\Cms\Domain\Blocks\FooterBlock::class,
    ];

    public function __construct(
        private readonly CacheManager $cache,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Register a block class
     */
    public function register(string $blockClass): void
    {
        if (!class_exists($blockClass)) {
            throw new \InvalidArgumentException("Block class {$blockClass} does not exist");
        }

        if (!is_subclass_of($blockClass, AbstractBlock::class)) {
            throw new \InvalidArgumentException("Block class {$blockClass} must extend AbstractBlock");
        }

        if (!in_array($blockClass, $this->blockClasses)) {
            $this->blockClasses[] = $blockClass;
        }

        $this->clearCache();
    }

    /**
     * Register multiple block classes
     */
    public function registerMultiple(array $blockClasses): void
    {
        foreach ($blockClasses as $blockClass) {
            $this->register($blockClass);
        }
    }

    /**
     * Get all registered block classes
     */
    public function getBlockClasses(): array
    {
        return $this->blockClasses;
    }

    /**
     * Get all block instances
     *
     * @return array<string, AbstractBlock>
     */
    public function getBlocks(): array
    {
        if (empty($this->blocks)) {
            $this->loadBlocks();
        }

        return $this->blocks;
    }

    /**
     * Get block by name
     */
    public function getBlock(string $name): ?AbstractBlock
    {
        $blocks = $this->getBlocks();
        return $blocks[$name] ?? null;
    }

    /**
     * Get blocks by category
     */
    public function getBlocksByCategory(string $category): array
    {
        return array_filter($this->getBlocks(), function ($block) use ($category) {
            return $block->getCategory() === $category;
        });
    }

    /**
     * Check if block exists
     */
    public function hasBlock(string $name): bool
    {
        return isset($this->getBlocks()[$name]);
    }

    /**
     * Load all blocks
     */
    private function loadBlocks(): void
    {
        foreach ($this->blockClasses as $blockClass) {
            try {
                $block = new $blockClass();
                $this->blocks[$block->getName()] = $block;
            } catch (\Throwable $e) {
                $this->logger->error("Failed to load block {$blockClass}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }
    }

    /**
     * Sync blocks to database
     *
     * Đồng bộ registered blocks vào block_types table
     */
    public function syncToDatabase(): array
    {
        $stats = [
            'created' => 0,
            'updated' => 0,
            'errors' => 0,
        ];

        foreach ($this->getBlocks() as $block) {
            try {
                $blockType = BlockType::where('name', $block->getName())->first();

                $data = [
                    'name' => $block->getName(),
                    'title' => $block->getTitle(),
                    'description' => $block->getDescription(),
                    'class' => get_class($block),
                    'category' => $block->getCategory(),
                    'icon' => $block->getIcon(),
                    'default_config' => $block->getDefaultConfig(),
                    'configurable' => $block->isConfigurable(),
                    'is_active' => true,
                    'version' => $block->getVersion(),
                ];

                if ($blockType) {
                    // Update existing
                    $blockType->update($data);
                    $stats['updated']++;
                } else {
                    // Create new
                    BlockType::create($data);
                    $stats['created']++;
                }
            } catch (\Throwable $e) {
                $stats['errors']++;
                $this->logger->error("Failed to sync block {$block->getName()}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->logger->info('Block sync completed', $stats);

        return $stats;
    }

    /**
     * Get all available block types from database
     */
    public function getAvailableBlockTypes(): \Core\Support\Collection
    {
        return BlockType::active()->get();
    }

    /**
     * Clear cache
     */
    public function clearCache(): void
    {
        $this->cache->forget(self::CACHE_KEY);
    }

    /**
     * Get block categories
     */
    public function getCategories(): array
    {
        $categories = [];

        foreach ($this->getBlocks() as $block) {
            $category = $block->getCategory();
            if (!isset($categories[$category])) {
                $categories[$category] = [];
            }
            $categories[$category][] = $block;
        }

        return $categories;
    }

    /**
     * Discover blocks from directory (for auto-discovery)
     */
    public function discoverFromDirectory(string $directory): array
    {
        $discovered = [];

        if (!is_dir($directory)) {
            return $discovered;
        }

        $files = glob($directory . '/*Block.php');

        foreach ($files as $file) {
            $className = $this->getClassNameFromFile($file);

            if ($className && class_exists($className) && is_subclass_of($className, AbstractBlock::class)) {
                $discovered[] = $className;
            }
        }

        return $discovered;
    }

    /**
     * Get class name from file (simple implementation)
     */
    private function getClassNameFromFile(string $file): ?string
    {
        $content = file_get_contents($file);

        // Extract namespace
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            $namespace = $matches[1];
        } else {
            return null;
        }

        // Extract class name
        if (preg_match('/class\s+(\w+)/', $content, $matches)) {
            $className = $matches[1];
            return $namespace . '\\' . $className;
        }

        return null;
    }
}
