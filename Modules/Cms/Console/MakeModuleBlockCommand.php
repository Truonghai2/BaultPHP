<?php

namespace Modules\Cms\Console;

use Core\Application;
use Core\Console\Contracts\BaseCommand;

/**
 * Make Module Block Command
 * 
 * Creates a new block for a module with all necessary files:
 * - Block class (Domain/Blocks/BlockName.php)
 * - View template (Resources/views/blocks/block-name.blade.php)
 * 
 * Usage:
 *   php cli make:module-block User UserProfile
 *   php cli make:module-block Shop ProductList --category=E-commerce --icon=🛍️
 */
class MakeModuleBlockCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'make:module-block 
                {module : The module name (e.g., User, Shop, Blog)}
                {name : The block name in PascalCase (e.g., UserProfile, ProductList)}
                {--category=General : Block category for organization}
                {--icon=🧩 : Block icon (emoji) for visual identification}
                {--cacheable=true : Whether the block output can be cached}
                {--force : Overwrite existing files without confirmation}';
    }

    public function description(): string
    {
        return 'Create a new block for a module with class and view files';
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $moduleName = $this->argument('module');
        $blockName = $this->argument('name');
        $category = $this->option('category');
        $icon = $this->option('icon');
        $cacheable = $this->option('cacheable') === 'true';
        $force = $this->option('force');

        $this->io->title('Module Block Generator');
        
        // Validate module exists
        $modulePath = base_path("Modules/{$moduleName}");
        if (!is_dir($modulePath)) {
            $this->io->error("Module '{$moduleName}' not found at: {$modulePath}");
            return self::FAILURE;
        }

        // Check if module is enabled
        $moduleJsonPath = $modulePath . '/module.json';
        if (file_exists($moduleJsonPath)) {
            $moduleData = json_decode(file_get_contents($moduleJsonPath), true);
            if (empty($moduleData['enabled'])) {
                $this->io->warning("Module '{$moduleName}' is disabled. Enable it in module.json");
            }
        } else {
            $this->io->warning("module.json not found. This module may not be auto-discovered.");
        }

        // Generate files
        $this->io->section('Generating Block Files');

        try {
            // 1. Create block class
            $blockClassPath = $this->createBlockClass(
                $modulePath, 
                $moduleName, 
                $blockName, 
                $category, 
                $icon, 
                $cacheable,
                $force
            );

            // 2. Create view template
            $viewPath = $this->createViewTemplate(
                $modulePath, 
                $blockName,
                $force
            );

            // Success summary
            $this->io->success('Block created successfully!');
            
            $this->io->section('Generated Files');
            $this->io->listing([
                "Block Class: {$blockClassPath}",
                "View Template: {$viewPath}",
            ]);

            $this->io->section('Next Steps');
            $this->io->listing([
                "1. Customize the block's render() method",
                "2. Design the blade view template",
                "3. Restart server: docker restart bault_app",
                "4. Block will be auto-discovered!",
            ]);

            $this->io->note([
                "Block Class: Modules\\{$moduleName}\\Domain\\Blocks\\{$blockName}Block",
                "View Name: " . strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $blockName)),
                "View Call: \$this->renderView('{$this->getModuleAlias($moduleName)}::blocks." . $this->getViewName($blockName) . "', \$data)",
            ]);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->io->error('Failed to create block: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Create block class file
     */
    protected function createBlockClass(
        string $modulePath,
        string $moduleName,
        string $blockName,
        string $category,
        string $icon,
        bool $cacheable,
        bool $force
    ): string {
        $blocksDir = $modulePath . '/Domain/Blocks';
        
        // Create directory if not exists
        if (!is_dir($blocksDir)) {
            mkdir($blocksDir, 0755, true);
            $this->io->writeln("  <fg=green>✓</> Created directory: Domain/Blocks/");
        }

        $blockFileName = "{$blockName}Block.php";
        $blockFilePath = $blocksDir . '/' . $blockFileName;

        // Check if file exists
        if (file_exists($blockFilePath) && !$force) {
            throw new \RuntimeException("Block file already exists: {$blockFilePath}\nUse --force to overwrite.");
        }

        // Generate block class content
        $blockContent = $this->getBlockClassTemplate(
            $moduleName,
            $blockName,
            $category,
            $icon,
            $cacheable
        );

        file_put_contents($blockFilePath, $blockContent);
        $this->io->writeln("  <fg=green>✓</> Created block class: {$blockFileName}");

        return $blockFilePath;
    }

    /**
     * Create view template file
     */
    protected function createViewTemplate(
        string $modulePath,
        string $blockName,
        bool $force
    ): string {
        $viewsDir = $modulePath . '/Resources/views/blocks';
        
        // Create directory if not exists
        if (!is_dir($viewsDir)) {
            mkdir($viewsDir, 0755, true);
            $this->io->writeln("  <fg=green>✓</> Created directory: Resources/views/blocks/");
        }

        $viewFileName = $this->getViewName($blockName) . '.blade.php';
        $viewFilePath = $viewsDir . '/' . $viewFileName;

        // Check if file exists
        if (file_exists($viewFilePath) && !$force) {
            throw new \RuntimeException("View file already exists: {$viewFilePath}\nUse --force to overwrite.");
        }

        // Generate view content
        $viewContent = $this->getViewTemplate($blockName);

        file_put_contents($viewFilePath, $viewContent);
        $this->io->writeln("  <fg=green>✓</> Created view template: {$viewFileName}");

        return $viewFilePath;
    }

    /**
     * Get block class template
     */
    protected function getBlockClassTemplate(
        string $moduleName,
        string $blockName,
        string $category,
        string $icon,
        bool $cacheable
    ): string {
        $viewName = $this->getViewName($blockName);
        $moduleAlias = $this->getModuleAlias($moduleName);
        $kebabName = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $blockName));
        
        $cacheableReturn = $cacheable ? 'true' : 'false';
        $cacheLifetime = $cacheable ? '3600' : '0';

        return <<<PHP
<?php

declare(strict_types=1);

namespace Modules\\{$moduleName}\\Domain\\Blocks;

use Modules\Cms\Domain\Blocks\AbstractBlock;

/**
 * {$blockName} Block
 * 
 * TODO: Add block description
 */
class {$blockName}Block extends AbstractBlock
{
    public function getName(): string
    {
        return '{$kebabName}';
    }

    public function getTitle(): string
    {
        return '{$this->splitCamelCase($blockName)}';
    }

    public function getDescription(): string
    {
        return 'TODO: Add description for {$this->splitCamelCase($blockName)} block';
    }

    public function getCategory(): string
    {
        return '{$category}';
    }

    public function getIcon(): string
    {
        return '{$icon}';
    }

    public function getDefaultConfig(): array
    {
        return [
            // TODO: Add default configuration options
            'title' => '{$this->splitCamelCase($blockName)}',
            'show_example' => true,
        ];
    }

    public function render(array \$config = [], ?array \$context = null): string
    {
        \$config = array_merge(\$this->getDefaultConfig(), \$config);
        
        // TODO: Add your business logic here
        // - Query database models
        // - Process data
        // - Call services
        
        return \$this->renderView('{$moduleAlias}::blocks.{$viewName}', [
            'config' => \$config,
            'context' => \$context,
        ]);
    }

    public function isCacheable(): bool
    {
        return {$cacheableReturn};
    }

    public function getCacheLifetime(): int
    {
        return {$cacheLifetime}; // seconds
    }
}

PHP;
    }

    /**
     * Get view template
     */
    protected function getViewTemplate(string $blockName): string
    {
        $title = $this->splitCamelCase($blockName);
        
        return <<<'BLADE'
{{-- 
    TODO: Design your block template
    
    Available variables:
    - $config: Block configuration array
    - $context: Context data passed from page/controller
--}}

<div class="block-{{ $config['title'] ?? 'untitled' }} bg-gray-800/30 rounded-xl p-6">
    <h3 class="text-xl font-bold text-white mb-4">
        {{ $config['title'] ?? 'Block Title' }}
    </h3>
    
    @if($config['show_example'] ?? false)
        <div class="content text-gray-300">
            <p>This is a generated block template. Customize it to fit your needs!</p>
        </div>
    @endif
    
    {{-- TODO: Add your HTML/Blade markup here --}}
</div>
BLADE;
    }

    /**
     * Convert BlockName to block-name
     */
    protected function getViewName(string $blockName): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $blockName));
    }

    /**
     * Get module alias (lowercase module name)
     */
    protected function getModuleAlias(string $moduleName): string
    {
        return strtolower($moduleName);
    }

    /**
     * Split camel case to words
     */
    protected function splitCamelCase(string $text): string
    {
        return ucwords(strtolower(preg_replace('/(?<!^)[A-Z]/', ' $0', $text)));
    }
}
