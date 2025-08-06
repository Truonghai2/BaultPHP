<?php

namespace Modules\Cms\Providers;

use Core\BaseServiceProvider;
use Modules\Cms\Application\Policies\PageBlockPolicy;
use Modules\Cms\Application\Policies\PagePolicy;
use Modules\Cms\Application\Queries\CachingPageFinder;
use Modules\Cms\Application\Queries\PageFinder;
use Modules\Cms\Application\Queries\PageFinderInterface;
use Modules\Cms\Domain\Services\BlockRegistry;
use Modules\Cms\Http\Controllers\PageController;
use Modules\Cms\Infrastructure\Models\Page;
use Modules\Cms\Infrastructure\Models\PageBlock;
use Modules\User\Domain\Services\AccessControlService;

class CmsServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BlockRegistry::class);

        // Bind the interface to the concrete implementation.
        $this->app->bind(PageFinderInterface::class, PageFinder::class);

        // Use the Caching Decorator when the PageController needs a PageFinder.
        $this->app->when(PageController::class)
            ->needs(PageFinderInterface::class)
            ->give(CachingPageFinder::class);
    }

    public function boot(): void
    {
        // Resolve dependencies from the container manually to comply with
        // the parent ServiceProvider's boot() method signature.
        /** @var AccessControlService $accessControlService */
        $accessControlService = $this->app->make(AccessControlService::class);

        $this->registerModuleResources();
        $this->registerPolicies($accessControlService);
    }

    /**
     * Register the module's resources, such as views.
     */
    private function registerModuleResources(): void
    {
        $modulePath = $this->getModulePath();

        // This makes views available under the 'cms::' namespace.
        // It's a standard practice for modular applications and is necessary for
        // calls like `view('cms::components.page-editor')` to work.
        $viewPath = $modulePath . '/resources/views';
        $this->loadViewsFrom($viewPath, 'cms');

        // Register the module's database migrations.
        // This allows the `ddd:migrate` command to discover and run them.
        $this->loadMigrationsFrom($modulePath . '/Infrastructure/Migrations');
    }

    /**
     * Register the authorization policies for the module.
     */
    private function registerPolicies(AccessControlService $accessControlService): void
    {
        // Register policies for the Cms module.
        // This tells the AccessControlService to use PagePolicy when checking permissions for a Page model.
        $accessControlService->policy(Page::class, PagePolicy::class);
        $accessControlService->policy(PageBlock::class, PageBlockPolicy::class);
    }
}
