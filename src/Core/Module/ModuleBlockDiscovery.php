<?php

namespace Core\Module;

use Core\Application;

/**
 * Module Block Discovery Service
 *
 * Automatically discovers and registers blocks from all enabled modules.
 *
 * Convention:
 * - Blocks must be in: Modules/{ModuleName}/Domain/Blocks/
 * - Block views in: Modules/{ModuleName}/Resources/views/blocks/
 * - Must extend AbstractBlock
 */
class ModuleBlockDiscovery
{
    protected Application $app;
    protected array $discoveredBlocks = [];
    protected array $moduleViewNamespaces = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Discover all blocks from enabled modules
     *
     * @return array [
     *   'blocks' => ['BlockClass' => 'ModuleName', ...],
     *   'views' => ['module-alias' => 'path/to/views', ...]
     * ]
     */
    public function discover(): array
    {
        $this->discoveredBlocks = [];
        $this->moduleViewNamespaces = [];

        $moduleJsonPaths = glob($this->app->basePath('Modules/*/module.json'));

        if ($moduleJsonPaths === false) {
            return [
                'blocks' => [],
                'views' => [],
            ];
        }

        foreach ($moduleJsonPaths as $path) {
            $this->discoverModuleBlocks($path);
        }

        return [
            'blocks' => $this->discoveredBlocks,
            'views' => $this->moduleViewNamespaces,
        ];
    }

    /**
     * Discover blocks from a single module
     */
    protected function discoverModuleBlocks(string $moduleJsonPath): void
    {
        $moduleData = json_decode(file_get_contents($moduleJsonPath), true);

        // Only discover from enabled modules
        if (empty($moduleData['enabled']) || $moduleData['enabled'] !== true) {
            return;
        }

        $moduleName = $moduleData['name'] ?? basename(dirname($moduleJsonPath));
        $moduleAlias = $moduleData['alias'] ?? strtolower($moduleName);
        $modulePath = dirname($moduleJsonPath);

        // Discover blocks
        $blocksPath = $modulePath . '/Domain/Blocks';
        if (is_dir($blocksPath)) {
            $this->scanBlocksDirectory($blocksPath, $moduleName);
        }

        // Register view namespace
        $viewsPath = $modulePath . '/Resources/views';
        if (is_dir($viewsPath)) {
            $this->moduleViewNamespaces[$moduleAlias] = $viewsPath;
        }
    }

    /**
     * Scan blocks directory and register block classes
     */
    protected function scanBlocksDirectory(string $path, string $moduleName): void
    {
        $blockFiles = glob($path . '/*Block.php');

        if ($blockFiles === false) {
            return;
        }

        foreach ($blockFiles as $file) {
            $className = $this->getClassNameFromFile($file, $moduleName);

            if ($className && $this->isValidBlockClass($className)) {
                $this->discoveredBlocks[$className] = $moduleName;
            }
        }
    }

    /**
     * Get fully qualified class name from file path
     */
    protected function getClassNameFromFile(string $filePath, string $moduleName): ?string
    {
        $fileName = basename($filePath, '.php');
        $className = "Modules\\{$moduleName}\\Domain\\Blocks\\{$fileName}";

        return class_exists($className) ? $className : null;
    }

    /**
     * Check if class is a valid block (extends AbstractBlock)
     */
    protected function isValidBlockClass(string $className): bool
    {
        if (!class_exists($className)) {
            return false;
        }

        try {
            $reflection = new \ReflectionClass($className);

            // Must be concrete (not abstract/interface)
            if ($reflection->isAbstract() || $reflection->isInterface()) {
                return false;
            }

            // Must extend AbstractBlock
            return $reflection->isSubclassOf(\Modules\Cms\Domain\Blocks\AbstractBlock::class);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Get discovered blocks
     */
    public function getDiscoveredBlocks(): array
    {
        return $this->discoveredBlocks;
    }

    /**
     * Get module view namespaces
     */
    public function getModuleViewNamespaces(): array
    {
        return $this->moduleViewNamespaces;
    }

    /**
     * Get blocks for a specific module
     */
    public function getModuleBlocks(string $moduleName): array
    {
        return array_filter(
            $this->discoveredBlocks,
            fn ($module) => $module === $moduleName,
        );
    }

    /**
     * Cache discovered blocks
     */
    public function cacheDiscovery(string $cachePath): bool
    {
        $discovery = $this->discover();

        $cacheContent = "<?php\n\nreturn " . var_export($discovery, true) . ";\n";

        return file_put_contents($cachePath, $cacheContent) !== false;
    }

    /**
     * Load from cache
     */
    public function loadFromCache(string $cachePath): array
    {
        if (!file_exists($cachePath)) {
            return $this->discover();
        }

        $discovery = require $cachePath;

        $this->discoveredBlocks = $discovery['blocks'] ?? [];
        $this->moduleViewNamespaces = $discovery['views'] ?? [];

        return $discovery;
    }
}
