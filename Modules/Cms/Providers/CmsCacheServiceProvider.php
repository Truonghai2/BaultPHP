<?php

declare(strict_types=1);

namespace Modules\Cms\Providers;

use Core\Support\ServiceProvider;
use Modules\Cms\Domain\Events\BlockTypeUpdated;
use Modules\Cms\Domain\Events\BlockUpdated;
use Modules\Cms\Domain\Events\PageUpdated;
use Modules\Cms\Domain\Listeners\InvalidateBlockCache;
use Modules\Cms\Infrastructure\Models\BlockInstance;
use Modules\Cms\Infrastructure\Models\Page;
use Modules\Cms\Infrastructure\Models\PageBlock;
use Modules\Cms\Infrastructure\Observers\BlockInstanceObserver;
use Modules\Cms\Infrastructure\Observers\PageBlockObserver;
use Modules\Cms\Infrastructure\Observers\PageObserver;

/**
 * CMS Cache Service Provider
 *
 * Registers cache invalidation events and observers
 */
class CmsCacheServiceProvider extends ServiceProvider
{
    /**
     * Event listeners mapping
     *
     * @var array<string, array<string>>
     */
    protected array $listen = [
        BlockUpdated::class => [
            [InvalidateBlockCache::class, 'handleBlockUpdated'],
        ],
        PageUpdated::class => [
            [InvalidateBlockCache::class, 'handlePageUpdated'],
        ],
        BlockTypeUpdated::class => [
            [InvalidateBlockCache::class, 'handleBlockTypeUpdated'],
        ],
    ];

    public function register(): void
    {
        // Register BlockCacheManager as singleton
        $this->app->singleton(\Modules\Cms\Domain\Services\BlockCacheManager::class);
    }

    public function boot(): void
    {
        // Register model observers for automatic cache invalidation
        Page::observe(PageObserver::class);
        PageBlock::observe(PageBlockObserver::class);
        BlockInstance::observe(BlockInstanceObserver::class);

        // Register event listeners
        foreach ($this->listen as $event => $listeners) {
            foreach ($listeners as $listener) {
                [$class, $method] = $listener;
                $this->app->bind($event, function ($app) use ($class, $method, $event) {
                    $instance = $app->make($class);
                    return function ($eventInstance) use ($instance, $method) {
                        $instance->{$method}($eventInstance);
                    };
                });
            }
        }
    }
}
