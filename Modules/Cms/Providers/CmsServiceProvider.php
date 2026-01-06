<?php

namespace Modules\Cms\Providers;

use Core\BaseServiceProvider;
use Modules\Cms\Application\Policies\PageBlockPolicy;
use Modules\Cms\Application\Policies\PagePolicy;
use Modules\Cms\Application\Queries\CachingPageFinder;
use Modules\Cms\Application\Queries\PageFinder;
use Modules\Cms\Application\Queries\PageFinderInterface;
use Modules\Cms\Domain\Services\BlockRegistry;
use Modules\Cms\Console\SyncBlocksCommand;
use Modules\Cms\Http\Controllers\PageController;
use Modules\Cms\Infrastructure\Models\Page;
use Modules\Cms\Infrastructure\Models\PageBlock;
use Modules\User\Domain\Services\AccessControlService;

class CmsServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        // Page Repository
        $this->app->bind(
            \Modules\Cms\Domain\Repositories\PageRepositoryInterface::class,
            \Modules\Cms\Infrastructure\Repositories\EloquentPageRepository::class
        );

        // PageBlock Repository (Page Editor System)
        $this->app->bind(
            \Modules\Cms\Domain\Repositories\PageBlockRepositoryInterface::class,
            \Modules\Cms\Infrastructure\Repositories\EloquentPageBlockRepository::class
        );

        // BlockInstance Repository (Moodle-like Block System)
        $this->app->bind(
            \Modules\Cms\Domain\Repositories\BlockInstanceRepositoryInterface::class,
            \Modules\Cms\Infrastructure\Repositories\EloquentBlockInstanceRepository::class
        );

        // BlockRegion Repository
        $this->app->bind(
            \Modules\Cms\Domain\Repositories\BlockRegionRepositoryInterface::class,
            \Modules\Cms\Infrastructure\Repositories\EloquentBlockRegionRepository::class
        );

        // Domain Services
        $this->app->singleton(\Modules\Cms\Domain\Services\PageService::class);
        $this->app->singleton(\Modules\Cms\Domain\Services\PageBlockService::class);
        $this->app->singleton(BlockRegistry::class);
        $this->app->singleton(\Modules\Cms\Domain\Services\BlockManager::class);
        $this->app->singleton(\Modules\Cms\Domain\Services\BlockRenderer::class);
        
        // Block Rendering Optimization
        $this->app->singleton(\Modules\Cms\Domain\Services\BlockClassRegistry::class, function ($app) {
            return new \Modules\Cms\Domain\Services\BlockClassRegistry(
                $app->make(\Psr\Log\LoggerInterface::class)
            );
        });
        
        $this->app->singleton(\Modules\Cms\Domain\Services\PageBlockRenderer::class, function ($app) {
            return new \Modules\Cms\Domain\Services\PageBlockRenderer(
                $app->make(\Psr\Log\LoggerInterface::class),
                $app->make(\Modules\Cms\Domain\Services\BlockClassRegistry::class),
                $app->make(\Modules\Cms\Domain\Services\BlockCacheManager::class)
            );
        });

        // Query Services
        $this->app->bind(PageFinderInterface::class, PageFinder::class);

        $this->app->when(PageController::class)
            ->needs(PageFinderInterface::class)
            ->give(CachingPageFinder::class);
    }

    public function boot(): void
    {
        /** @var AccessControlService $accessControlService */
        $accessControlService = $this->app->make(AccessControlService::class);

        $this->registerModuleResources();
        $this->registerPolicies($accessControlService);
        $this->registerCommands();
        $this->loadBlockHelpers();
        $this->bootBladeDirectives();
        $this->registerAutoSyncMiddleware();
        $this->discoverModuleBlocks();
    }

    /**
     * Register console commands
     */
    private function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $commands = [
                SyncBlocksCommand::class,
                \Modules\Cms\Console\SetupDefaultBlocksCommand::class,
                \Modules\Cms\Console\BlockPerformanceTestCommand::class,
            ];

            foreach ($commands as $command) {
                $this->app->singleton($command);
                $this->app->tag($command, 'console.command');
            }
        }
    }

    /**
     * Load block rendering helpers
     * 
     * Note: Block helpers are now in src/Core/helpers.php
     * This method is kept for backward compatibility
     */
    private function loadBlockHelpers(): void
    {
        // Block helpers are now part of main helpers.php
        // No separate file needed
    }

    /**
     * Boot Blade directives
     */
    private function bootBladeDirectives(): void
    {
        $bladeProvider = new BladeServiceProvider($this->app);
        $bladeProvider->boot();
    }

    /**
     * Register the module's resources, such as views.
     */
    private function registerModuleResources(): void
    {
        $modulePath = $this->getModulePath();

        $viewPath = $modulePath . '/Resources/views';
        $this->loadViewsFrom($viewPath, 'cms');

        $this->loadMigrationsFrom($modulePath . '/Infrastructure/Migrations');
    }

    /**
     * Register the authorization policies for the module.
     */
    private function registerPolicies(AccessControlService $accessControlService): void
    {
        $accessControlService->policy(Page::class, PagePolicy::class);
        $accessControlService->policy(PageBlock::class, PageBlockPolicy::class);
    }

    /**
     * Register auto-sync middleware for blocks
     * 
     * Automatically syncs blocks in development mode on every request
     * Cache is used to prevent too frequent syncs
     */
    private function registerAutoSyncMiddleware(): void
    {
        // Only register in local/development environment
        if (config('app.env') === 'local' && config('cms.auto_sync_blocks', true)) {
            /** @var \Core\Contracts\Http\Kernel $kernel */
            $kernel = $this->app->make(\Core\Contracts\Http\Kernel::class);
            $kernel->pushMiddlewareToGroup('web', \Modules\Cms\Http\Middleware\AutoSyncBlocksMiddleware::class);
        }

        // Register performance monitor in debug mode
        if (config('app.debug', false)) {
            /** @var \Core\Contracts\Http\Kernel $kernel */
            $kernel = $this->app->make(\Core\Contracts\Http\Kernel::class);
            $kernel->pushMiddlewareToGroup('web', \Modules\Cms\Http\Middleware\BlockRenderMonitor::class);
        }
    }

    /**
     * Discover and register blocks from all enabled modules
     * 
     * This method automatically:
     * 1. Scans Domain/Blocks/ in all enabled modules
     * 2. Registers view namespaces for module blocks
     * 3. Makes blocks available to BlockRegistry
     */
    private function discoverModuleBlocks(): void
    {
        $discovery = new \Core\Module\ModuleBlockDiscovery($this->app);
        
        $cachePath = $this->app->basePath('bootstrap/cache/module-blocks.php');
        
        if (config('app.env') === 'production' && file_exists($cachePath)) {
            $data = $discovery->loadFromCache($cachePath);
        } else {
            $data = $discovery->discover();
            
            if (config('app.env') === 'production') {
                $discovery->cacheDiscovery($cachePath);
            }
        }

        /** @var \Core\View\ViewFactory $viewFactory */
        $viewFactory = $this->app->make('view');
        
        foreach ($data['views'] ?? [] as $alias => $path) {
            if (!$viewFactory->exists($alias . '::test')) {
                $this->loadViewsFrom($path, $alias);
            }
        }

        if (config('app.debug') && !file_exists($cachePath)) {
            $blocksCount = count($data['blocks'] ?? []);
            $modulesCount = count(array_unique(array_values($data['blocks'] ?? [])));
            
            \Core\Support\Facades\Log::info("Module blocks discovered (cache rebuilt)", [
                'blocks_count' => $blocksCount,
                'modules_count' => $modulesCount,
                'blocks' => array_keys($data['blocks'] ?? []),
            ]);
        }
    }
}
